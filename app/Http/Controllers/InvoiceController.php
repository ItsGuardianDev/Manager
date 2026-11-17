<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\ProjectStatus;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Organization;
use App\Models\Project;
use App\Services\InvoiceBuilder;
use App\Services\XlsxWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InvoiceController extends Controller
{
    private const TABS = ['ready' => 'Te factureren', 'drafts' => 'Conceptfacturen', 'outstanding' => 'Openstaande facturen', 'all' => 'Alle facturen'];

    public function __construct(private InvoiceBuilder $builder) {}

    public function index(Request $request): View
    {
        Gate::authorize('invoices.view');

        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'ready';

        if ($tab === 'ready') {
            return view('invoices.ready', ['tab' => $tab, 'tabs' => self::TABS, 'rows' => $this->readyRows($request->query('q'))]);
        }

        $invoices = $this->filtered($request, $tab)->with(['organization', 'project', 'lines', 'payments'])->paginate(15)->withQueryString();

        return view('invoices.index', [
            'tab' => $tab, 'tabs' => self::TABS, 'invoices' => $invoices,
            'outstandingTotal' => Invoice::with('lines', 'payments')->where('status', InvoiceStatus::Sent)->get()->sum(fn (Invoice $invoice) => $invoice->outstanding()),
            'overdueFilters' => ['' => 'Alle openstaande facturen', 'within' => 'Binnen betaaltermijn', 'late' => '1 tot 20 dagen verlopen', 'later' => 'Meer dan 21 dagen verlopen'],
        ]);
    }

    /**
     * @return Builder<Invoice>
     */
    private function filtered(Request $request, string $tab)
    {
        $today = today()->toDateString();

        return Invoice::query()
            ->when($tab === 'drafts', fn ($query) => $query->where('status', InvoiceStatus::Draft))
            ->when($tab === 'outstanding', fn ($query) => $query->where('status', InvoiceStatus::Sent)
                ->when($request->query('overdue') === 'within', fn ($query) => $query->whereDate('due_date', '>=', $today))
                ->when($request->query('overdue') === 'late', fn ($query) => $query->whereDate('due_date', '<', $today)->whereDate('due_date', '>=', today()->subDays(20)->toDateString()))
                ->when($request->query('overdue') === 'later', fn ($query) => $query->whereDate('due_date', '<', today()->subDays(20)->toDateString())))
            ->when($tab === 'all' && in_array($request->query('status'), ['draft', 'sent', 'paid'], true), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->query('q'), fn ($query, $q) => $query->where(fn ($query) => $query->where('number', 'like', "%{$q}%")->orWhere('subject', 'like', "%{$q}%")
                ->orWhereHas('organization', fn ($query) => $query->where('name', 'like', "%{$q}%"))
                ->orWhereHas('project', fn ($query) => $query->where('name', 'like', "%{$q}%"))))
            ->when($request->query('from'), fn ($query, $from) => $query->whereDate('invoice_date', '>=', $from))
            ->when($request->query('to'), fn ($query, $to) => $query->whereDate('invoice_date', '<=', $to))
            ->orderByDesc('invoice_date')->orderByDesc('id');
    }

    /**
     * One row per project that has something ready (or almost ready) to invoice.
     *
     * @return array<int, array<string, mixed>>
     */
    private function readyRows(?string $search): array
    {
        $projects = Project::with('organization')->where('status', ProjectStatus::Active)->where('is_invoiceable', true)
            ->when($search, fn ($query, $q) => $query->where(fn ($query) => $query->where('name', 'like', "%{$q}%")->orWhereHas('organization', fn ($query) => $query->where('name', 'like', "%{$q}%"))))
            ->orderBy('name')->get();

        return $projects->map(function (Project $project) {
            $items = collect($this->builder->proposals($project));
            $amount = fn ($items) => round($items->sum(fn (array $item) => $item['quantity'] * $item['unit_price'] * (1 - $item['discount'] / 100)), 2);

            return ['project' => $project, 'ready' => $amount($items->where('ready', true)), 'notReady' => $amount($items->where('ready', false)),
                'oldest' => $items->where('ready', true)->pluck('date')->filter()->min(), 'types' => $items->pluck('type')->unique()->values()->all(), 'count' => $items->count()];
        })->filter(fn (array $row) => $row['count'] > 0)->values()->all();
    }

    public function proposal(Project $project): View
    {
        Gate::authorize('invoices.view');

        $items = collect($this->builder->proposals($project));

        return view('invoices.proposal', ['project' => $project->load('organization'), 'ready' => $items->where('ready', true)->values(), 'notReady' => $items->where('ready', false)->values()]);
    }

    public function storeFromProposal(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('invoices.manage');

        $keys = $request->validate(['keys' => ['required', 'array'], 'keys.*' => ['string']], ['keys.required' => 'Selecteer minimaal één regel.'])['keys'];

        return redirect()->route('invoices.show', $this->builder->createDraft($project, $keys))->with('status', 'Conceptfactuur aangemaakt.');
    }

    public function writeOff(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('invoices.manage');

        $keys = $request->validate(['keys' => ['required', 'array'], 'keys.*' => ['string']], ['keys.required' => 'Selecteer minimaal één regel.'])['keys'];
        $count = $this->builder->writeOff($project, $keys);

        return back()->with('status', $count > 0 ? 'Urenregels afgeboekt: '.$count : 'Alleen uren kunnen worden afgeboekt.');
    }

    /**
     * Monthly revenue (excluding VAT) from sent invoices, grouped per service, project, debtor or revenue group. Credit invoices count negative.
     *
     * @return array{year: int, group: string, groups: array<string, string>, rows: array<int, array{label: string, months: array<int, float>, total: float}>, totals: array<int, float>, grandTotal: float}
     */
    private function revenue(Request $request): array
    {
        $groups = ['service' => 'Diensten', 'project' => 'Projecten', 'debtor' => 'Debiteuren', 'revenue_group' => 'Omzetgroepen'];
        $group = array_key_exists($request->query('group'), $groups) ? $request->query('group') : 'service';
        $year = max(2000, min(2100, (int) ($request->query('year') ?: now()->year)));

        $lines = InvoiceLine::with(['invoice.organization', 'invoice.project', 'service.revenueGroup'])
            ->whereHas('invoice', fn ($query) => $query->where('status', '!=', InvoiceStatus::Draft)->whereYear('invoice_date', $year))
            ->get();

        $label = fn (InvoiceLine $line): string => match ($group) {
            'project' => $line->invoice->project?->name ?? 'Zonder project',
            'debtor' => $line->invoice->organization->name,
            'revenue_group' => $line->service?->revenueGroup?->name ?? 'Zonder omzetgroep',
            default => $line->service?->name ?? 'Overige regels',
        };

        $rows = $lines->groupBy($label)->map(function ($group, string $name) {
            $months = array_fill(1, 12, 0.0);

            foreach ($group as $line) {
                $months[$line->invoice->invoice_date->month] += $line->amount();
            }

            $months = array_map(fn (float $amount) => round($amount, 2), $months);

            return ['label' => $name, 'months' => $months, 'total' => round(array_sum($months), 2)];
        })->sortBy('label')->values()->all();

        $totals = array_map(fn (int $month) => round(array_sum(array_column(array_column($rows, 'months'), $month)), 2), range(1, 12));

        return ['year' => $year, 'group' => $group, 'groups' => $groups, 'rows' => $rows, 'totals' => array_combine(range(1, 12), $totals), 'grandTotal' => round(array_sum($totals), 2)];
    }

    public function report(Request $request): View|BinaryFileResponse
    {
        Gate::authorize('invoices.view');

        $report = $this->revenue($request);

        if ($request->query('export')) {
            return XlsxWriter::make()
                ->sheet('Omzet '.$report['year'], [$report['groups'][$report['group']], ...array_map(fn (int $month) => Carbon::create($report['year'], $month, 1)->translatedFormat('M'), range(1, 12)), 'Totaal'],
                    array_map(fn (array $row) => [$row['label'], ...array_values($row['months']), $row['total']], $report['rows']))
                ->download('omzet-'.$report['year'].'.xlsx');
        }

        return view('invoices.report', $report);
    }

    public function create(Request $request): View
    {
        Gate::authorize('invoices.manage');

        return view('invoices.create', ['selected' => $request->query('organization_id'), 'organizations' => Organization::where('is_active', true)->orderBy('name')->pluck('name', 'id'), 'projects' => Project::where('status', ProjectStatus::Active)->orderBy('name')->get(['id', 'name', 'organization_id'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('invoices.manage');

        $data = $request->validate(['organization_id' => ['required', 'exists:organizations,id'], 'project_id' => ['nullable', 'exists:projects,id'], 'subject' => ['nullable', 'string', 'max:255']],
            [], ['organization_id' => 'debiteur', 'project_id' => 'project', 'subject' => 'onderwerp']);

        if ($data['project_id'] && Project::whereKey($data['project_id'])->value('organization_id') != $data['organization_id']) {
            return back()->withInput()->withErrors(['project_id' => 'Dit project hoort niet bij de gekozen debiteur.']);
        }

        $invoice = $this->builder->newInvoice((int) $data['organization_id'], $data['project_id'] ? (int) $data['project_id'] : null, $data['subject'] ?? null);
        $invoice->record('Conceptfactuur aangemaakt');

        return redirect()->route('invoices.show', $invoice);
    }

    public function show(Request $request, Invoice $invoice): View
    {
        Gate::authorize('invoices.view');

        $invoice->load(['organization.invoiceContactPerson', 'project', 'lines.service', 'payments', 'events.user', 'creditFor']);

        return view('invoices.show', ['invoice' => $invoice, 'tab' => in_array($request->query('tab'), ['invoice', 'lines', 'timeline'], true) ? $request->query('tab') : 'invoice']);
    }

    public function print(Invoice $invoice): View
    {
        Gate::authorize('invoices.view');

        return view('invoices.print', ['invoice' => $invoice->load(['organization', 'project', 'lines', 'payments'])]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('invoices.manage');
        $this->ensureDraft($invoice);

        $data = $request->validate(['subject' => ['nullable', 'string', 'max:255'], 'invoice_date' => ['required', 'date'], 'due_date' => ['required', 'date', 'after_or_equal:invoice_date'], 'notes' => ['nullable', 'string', 'max:2000']],
            [], ['subject' => 'onderwerp', 'invoice_date' => 'factuurdatum', 'due_date' => 'vervaldatum', 'notes' => 'opmerking']);

        $invoice->update($data);
        $invoice->record('Factuurgegevens gewijzigd');

        return back()->with('status', 'Factuur bijgewerkt.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('invoices.manage');
        $this->ensureDraft($invoice);

        $invoice->delete();

        return redirect()->route('invoices.index', ['tab' => 'drafts'])->with('status', 'Conceptfactuur verwijderd; de uren zijn weer beschikbaar voor facturatie.');
    }

    public function storeLine(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('invoices.manage');
        $this->ensureDraft($invoice);

        $invoice->lines()->create([...$this->validateLine($request), 'type' => 'custom', 'sort_order' => ($invoice->lines()->max('sort_order') ?? 0) + 1]);
        $invoice->record('Factuurregel toegevoegd');

        return redirect()->route('invoices.show', [$invoice, 'tab' => 'lines']);
    }

    public function updateLine(Request $request, Invoice $invoice, InvoiceLine $line): RedirectResponse
    {
        Gate::authorize('invoices.manage');
        $this->ensureDraft($invoice);
        abort_unless($line->invoice_id === $invoice->id, 404);

        $line->update($this->validateLine($request));
        $invoice->record('Factuurregel gewijzigd');

        return redirect()->route('invoices.show', [$invoice, 'tab' => 'lines']);
    }

    public function destroyLine(Invoice $invoice, InvoiceLine $line): RedirectResponse
    {
        Gate::authorize('invoices.manage');
        $this->ensureDraft($invoice);
        abort_unless($line->invoice_id === $invoice->id, 404);

        $line->delete();
        $invoice->record('Factuurregel verwijderd');

        return redirect()->route('invoices.show', [$invoice, 'tab' => 'lines']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateLine(Request $request): array
    {
        return $request->validate([
            'description' => ['required', 'string', 'max:255'], 'quantity' => ['required', 'numeric', 'between:-99999,99999'], 'unit_price' => ['required', 'numeric', 'between:-99999999,99999999'],
            'vat_rate' => ['required', 'in:'.implode(',', array_keys(InvoiceLine::VAT_RATES))], 'discount_percentage' => ['nullable', 'numeric', 'between:0,100'],
        ], [], ['description' => 'omschrijving', 'quantity' => 'aantal', 'unit_price' => 'prijs', 'vat_rate' => 'btw', 'discount_percentage' => 'korting']) + ['discount_percentage' => 0];
    }

    public function send(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('invoices.manage');

        $method = $request->validate(['method' => ['required', 'in:print,email']], [], ['method' => 'verzendmethode'])['method'];
        $invoice->load('lines', 'organization.invoiceContactPerson');

        $recipient = $invoice->organization->invoiceContactPerson?->email ?? $invoice->organization->email;

        if ($method === 'email' && ! $recipient) {
            throw ValidationException::withMessages(['method' => 'Dit bedrijf heeft geen e-mailadres om de factuur naar te sturen.']);
        }

        $this->builder->send($invoice, $method);

        if ($method === 'email') {
            $url = route('invoices.print', $invoice);
            Mail::raw("Beste klant,\n\nBijgaand factuur {$invoice->number} ter hoogte van € ".number_format($invoice->total(), 2, ',', '.').", te voldoen vóór {$invoice->due_date->format('d-m-Y')}.\n\nU kunt de factuur bekijken via {$url}.\n\nMet vriendelijke groet", fn ($message) => $message->to($recipient)->subject('Factuur '.$invoice->number));
        }

        return redirect()->route('invoices.show', $invoice)->with('status', 'Factuur '.$invoice->number.' verstuurd.');
    }

    public function sendMany(Request $request): RedirectResponse
    {
        Gate::authorize('invoices.manage');

        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']], ['ids.required' => 'Selecteer minimaal één conceptfactuur.'])['ids'];
        $sent = 0;

        foreach (Invoice::with('lines')->whereIn('id', $ids)->where('status', InvoiceStatus::Draft)->get() as $invoice) {
            if ($invoice->lines->isNotEmpty()) {
                $this->builder->send($invoice, 'print');
                $sent++;
            }
        }

        return back()->with('status', $sent.' factuur/facturen verstuurd.');
    }

    public function storePayment(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('invoices.manage');

        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'paid_on' => ['required', 'date'], 'note' => ['nullable', 'string', 'max:255']],
            [], ['amount' => 'bedrag', 'paid_on' => 'datum betaling', 'note' => 'toelichting']);

        $invoice->load('lines', 'payments');
        $this->builder->registerPayment($invoice, (float) $data['amount'], Carbon::parse($data['paid_on']), $data['note'] ?? null);

        return redirect()->route('invoices.show', $invoice)->with('status', 'Betaling geregistreerd.');
    }

    public function remind(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('invoices.manage');
        abort_unless($invoice->status === InvoiceStatus::Sent, 422);

        $invoice->increment('reminders_sent');
        $invoice->record('Herinnering '.$invoice->reminders_sent.' verstuurd');

        return back()->with('status', 'Herinnering geregistreerd.');
    }

    public function toggleReminders(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('invoices.manage');

        $invoice->update(['reminder_paused' => ! $invoice->reminder_paused]);
        $invoice->record($invoice->reminder_paused ? 'Herinneringen gepauzeerd' : 'Herinneringen hervat');

        return back();
    }

    public function credit(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('invoices.manage');

        $invoice->load('lines');

        return redirect()->route('invoices.show', $this->builder->createCredit($invoice))->with('status', 'Conceptcreditfactuur aangemaakt.');
    }

    public function export(Request $request): BinaryFileResponse
    {
        Gate::authorize('invoices.view');

        $tab = array_key_exists($request->query('tab'), self::TABS) && $request->query('tab') !== 'ready' ? $request->query('tab') : 'all';
        $invoices = $this->filtered($request, $tab)->with(['organization', 'project', 'lines', 'payments'])->get();

        $rows = $invoices->map(fn (Invoice $invoice) => [
            $invoice->number, $invoice->status->label(), $invoice->organization->name, $invoice->project?->name, $invoice->subject, $invoice->invoice_date, $invoice->due_date,
            $invoice->subtotal(), $invoice->vatTotal(), $invoice->total(), $invoice->paidTotal(), $invoice->outstanding(), $invoice->isCredit() ? 'Ja' : 'Nee',
        ]);

        return XlsxWriter::make()
            ->sheet('Facturen', ['Factuurnummer', 'Status', 'Debiteur', 'Project', 'Onderwerp', 'Factuurdatum', 'Vervaldatum', 'Totaal excl. btw', 'Btw', 'Totaal incl. btw', 'Betaald', 'Openstaand', 'Creditfactuur'], $rows)
            ->download('facturen-'.now()->format('Y-m-d').'.xlsx');
    }

    private function ensureDraft(Invoice $invoice): void
    {
        if (! $invoice->isDraft()) {
            throw ValidationException::withMessages(['invoice' => 'Een verstuurde factuur kan niet meer worden gewijzigd. Maak een creditfactuur om te corrigeren.']);
        }
    }
}
