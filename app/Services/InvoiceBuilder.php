<?php

namespace App\Services;

use App\Enums\InvoiceMethod;
use App\Enums\InvoiceStatus;
use App\Enums\TimeEntryStatus;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePayment;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectService;
use App\Models\TimeEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns project services and registered hours into invoice proposals and runs the invoice life cycle
 * (draft, send, payment, credit). Proposals are recalculated on every call, so a selection is
 * always checked against the current state of the project.
 *
 * @phpstan-type Proposal array{key: string, type: string, service_id: int, service: string, description: string, quantity: float, unit_price: float, vat_rate: float, discount: float, date: ?Carbon, ready: bool, entry_ids: array<int, int>}
 */
class InvoiceBuilder
{
    private const CYCLE_MONTHS = ['monthly' => 1, 'quarterly' => 3, 'half_yearly' => 6, 'yearly' => 12];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function proposals(Project $project): array
    {
        $project->loadMissing('organization');
        $items = [];

        foreach ($project->services()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get() as $service) {
            $items = [...$items, ...match ($service->invoice_method) {
                InvoiceMethod::AfterCalculation => $this->hourProposals($project, $service),
                InvoiceMethod::FixedPrice => $this->fixedPriceProposals($project, $service),
                InvoiceMethod::Subscription => $this->subscriptionProposals($project, $service),
            }];
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function hourProposals(Project $project, ProjectService $service): array
    {
        $entries = TimeEntry::with('hourType')->where('project_service_id', $service->id)
            ->whereNull('invoiced_at')->whereNull('invoice_id')->whereNull('written_off_at')
            ->get();

        return $entries->groupBy(fn (TimeEntry $entry) => implode(':', [$entry->hour_type_id ?? 0, (float) $entry->rate, $entry->status === TimeEntryStatus::Approved ? 1 : 0]))
            ->map(function ($group, string $group_key) use ($service, $project) {
                /** @var TimeEntry $first */
                $first = $group->first();
                $ready = $first->status === TimeEntryStatus::Approved;

                return $this->item("hours:{$service->id}:{$group_key}", 'hours', $service, $project, $service->name.($first->hourType ? ' — '.$first->hourType->name : ''),
                    round($group->sum('hours'), 2), (float) $first->rate, $group->max('date'), $ready, $group->pluck('id')->all());
            })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fixedPriceProposals(Project $project, ProjectService $service): array
    {
        if (! $service->price || InvoiceLine::where('project_service_id', $service->id)->where('type', 'fixed_price')->exists()) {
            return [];
        }

        $date = $service->invoice_from ?? $service->start_date;

        return [$this->item("fixed:{$service->id}", 'fixed_price', $service, $project, $service->name, 1, $service->price, $date, $service->invoice_from === null || $service->invoice_from->lte(today()))];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function subscriptionProposals(Project $project, ProjectService $service): array
    {
        $months = self::CYCLE_MONTHS[$service->billing_cycle?->value ?? 'monthly'] ?? 1;
        $last = InvoiceLine::where('project_service_id', $service->id)->where('type', 'subscription')->with('invoice')->get()->map(fn ($line) => $line->invoice->invoice_date)->max();
        $due = $last ? $last->copy()->addMonthsNoOverflow($months) : ($service->invoice_from ?? $service->start_date ?? today());

        if (! $service->price || ($service->end_date && $due->gt($service->end_date))) {
            return [];
        }

        $period = $due->format('d-m-Y').' t/m '.$due->copy()->addMonthsNoOverflow($months)->subDay()->format('d-m-Y');

        return [$this->item("subscription:{$service->id}:{$due->format('Ymd')}", 'subscription', $service, $project, $service->name.' ('.$period.')', 1, $service->price, $due, $due->lte(today()))];
    }

    /**
     * @param  array<int, int>  $entryIds
     * @return array<string, mixed>
     */
    private function item(string $key, string $type, ProjectService $service, Project $project, string $description, float $quantity, float $unitPrice, ?Carbon $date, bool $ready, array $entryIds = []): array
    {
        return [
            'key' => $key, 'type' => $type, 'service_id' => $service->id, 'service' => $service->name, 'description' => $description,
            'quantity' => $quantity, 'unit_price' => $unitPrice, 'vat_rate' => $this->vatRate($service, $project), 'discount' => (float) $service->discount_percentage,
            'date' => $date, 'ready' => $ready, 'entry_ids' => $entryIds,
        ];
    }

    private function vatRate(ProjectService $service, Project $project): float
    {
        $code = $service->vat_code ?: $project->organization?->default_vat_code ?: 'vat_21';

        return match ($code) {
            'vat_9' => 9.0,
            'vat_0', 'reverse_charge' => 0.0,
            default => 21.0,
        };
    }

    /**
     * Create a draft invoice from the selected, ready proposals of one project.
     *
     * @param  array<int, string>  $keys
     */
    public function createDraft(Project $project, array $keys): Invoice
    {
        $selected = collect($this->proposals($project))->filter(fn (array $item) => $item['ready'] && in_array($item['key'], $keys, true))->values();

        if ($selected->isEmpty()) {
            throw ValidationException::withMessages(['keys' => 'Selecteer minimaal één regel die gereed is voor facturatie.']);
        }

        return DB::transaction(function () use ($project, $selected) {
            $invoice = $this->newInvoice($project->organization_id, $project->id, $project->name);

            foreach ($selected as $index => $item) {
                $invoice->lines()->create([
                    'project_service_id' => $item['service_id'], 'type' => $item['type'], 'description' => $item['description'], 'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'], 'vat_rate' => $item['vat_rate'], 'discount_percentage' => $item['discount'], 'sort_order' => $index,
                ]);
                TimeEntry::whereIn('id', $item['entry_ids'])->update(['invoice_id' => $invoice->id]);
            }

            $invoice->record('Conceptfactuur aangemaakt op basis van '.$selected->count().' regel(s)');

            return $invoice;
        });
    }

    public function newInvoice(int $organizationId, ?int $projectId, ?string $subject): Invoice
    {
        $organization = Organization::findOrFail($organizationId);

        return Invoice::create([
            'organization_id' => $organizationId, 'project_id' => $projectId, 'status' => InvoiceStatus::Draft, 'subject' => $subject,
            'invoice_date' => today(), 'due_date' => today()->addDays($organization->payment_term_days ?? 14), 'created_by' => auth()->id(),
        ]);
    }

    /**
     * Written-off hours are no longer proposed and are never invoiced.
     *
     * @param  array<int, string>  $keys
     */
    public function writeOff(Project $project, array $keys): int
    {
        $entryIds = collect($this->proposals($project))->filter(fn (array $item) => $item['type'] === 'hours' && in_array($item['key'], $keys, true))->flatMap(fn (array $item) => $item['entry_ids']);

        return TimeEntry::whereIn('id', $entryIds)->update(['written_off_at' => now()]);
    }

    public function send(Invoice $invoice, string $method): void
    {
        if (! $invoice->isDraft()) {
            throw ValidationException::withMessages(['invoice' => 'Deze factuur is al verstuurd.']);
        }

        if ($invoice->lines->isEmpty()) {
            throw ValidationException::withMessages(['invoice' => 'Een factuur zonder regels kan niet worden verstuurd.']);
        }

        DB::transaction(function () use ($invoice, $method) {
            $invoice->update(['number' => Invoice::nextNumber(), 'status' => InvoiceStatus::Sent, 'sent_at' => now(), 'sent_method' => $method]);
            TimeEntry::where('invoice_id', $invoice->id)->update(['invoiced_at' => now()]);
            $invoice->record('Factuur '.$invoice->number.' verstuurd ('.($method === 'email' ? 'per e-mail' : 'afgedrukt').')');
        });
    }

    public function registerPayment(Invoice $invoice, float $amount, Carbon $paidOn, ?string $note): InvoicePayment
    {
        if ($invoice->isDraft()) {
            throw ValidationException::withMessages(['amount' => 'Op een conceptfactuur kan geen betaling worden geregistreerd.']);
        }

        return DB::transaction(function () use ($invoice, $amount, $paidOn, $note) {
            $payment = $invoice->payments()->create(['amount' => $amount, 'paid_on' => $paidOn, 'note' => $note, 'created_by' => auth()->id()]);
            $invoice->load('payments', 'lines');
            $paid = $invoice->outstanding() <= 0;
            $invoice->update(['status' => $paid ? InvoiceStatus::Paid : InvoiceStatus::Sent]);
            $invoice->record('Betaling van € '.number_format($amount, 2, ',', '.').' geregistreerd'.($paid ? ' — factuur volledig betaald' : ''));

            return $payment;
        });
    }

    /**
     * Draft credit invoice that reverses every line of a sent invoice.
     */
    public function createCredit(Invoice $original): Invoice
    {
        if ($original->isDraft() || $original->isCredit()) {
            throw ValidationException::withMessages(['invoice' => 'Alleen een verstuurde, gewone factuur kan worden gecrediteerd.']);
        }

        return DB::transaction(function () use ($original) {
            $credit = $this->newInvoice($original->organization_id, $original->project_id, 'Credit '.$original->number);
            $credit->update(['credit_for_id' => $original->id]);

            foreach ($original->lines as $line) {
                $credit->lines()->create([
                    'project_service_id' => $line->project_service_id, 'type' => 'credit', 'description' => $line->description, 'quantity' => -1 * $line->quantity,
                    'unit_price' => $line->unit_price, 'vat_rate' => $line->vat_rate, 'discount_percentage' => $line->discount_percentage, 'sort_order' => $line->sort_order,
                ]);
            }

            $credit->record('Creditfactuur aangemaakt voor factuur '.$original->number);
            $original->record('Creditfactuur aangemaakt');

            return $credit;
        });
    }
}
