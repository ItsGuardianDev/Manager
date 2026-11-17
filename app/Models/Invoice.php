<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * An invoice (or credit invoice) to an organization, optionally tied to a project.
 * Draft invoices have no number yet; the number is assigned when the invoice is sent.
 * Totals are calculated from the eager-loaded lines and payments.
 */
#[Guarded(['id'])]
class Invoice extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'invoice_date' => 'date',
            'due_date' => 'date',
            'sent_at' => 'datetime',
            'reminder_paused' => 'boolean',
        ];
    }

    /**
     * Next number of the form YYYYNNNN, counting up per year.
     */
    public static function nextNumber(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $highest = static::query()->where('number', 'like', $year.'%')->pluck('number')->map(fn ($number) => (int) substr($number, 4))->max() ?? 0;

        return $year.str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creditFor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'credit_for_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderBy('paid_on');
    }

    public function events(): HasMany
    {
        return $this->hasMany(InvoiceEvent::class)->latest()->latest('id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    public function isCredit(): bool
    {
        return $this->credit_for_id !== null;
    }

    public function subtotal(): float
    {
        return round($this->lines->sum(fn (InvoiceLine $line) => $line->amount()), 2);
    }

    public function vatTotal(): float
    {
        return round($this->lines->sum(fn (InvoiceLine $line) => $line->vatAmount()), 2);
    }

    public function total(): float
    {
        return round($this->subtotal() + $this->vatTotal(), 2);
    }

    public function paidTotal(): float
    {
        return round($this->payments->sum('amount'), 2);
    }

    public function outstanding(): float
    {
        return $this->isDraft() ? 0.0 : round($this->total() - $this->paidTotal(), 2);
    }

    /**
     * Subtotals per VAT rate, for the invoice footer.
     *
     * @return array<string, float>
     */
    public function vatBreakdown(): array
    {
        return $this->lines->groupBy(fn (InvoiceLine $line) => (string) (float) $line->vat_rate)
            ->map(fn ($lines) => round($lines->sum(fn (InvoiceLine $line) => $line->vatAmount()), 2))
            ->all();
    }

    /** Days past the due date; negative while still within the payment term. */
    public function daysOverdue(): int
    {
        return (int) $this->due_date->startOfDay()->diffInDays(today(), false);
    }

    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Sent && $this->outstanding() > 0 && $this->daysOverdue() > 0;
    }

    /**
     * Next reminder step, one week after the due date and then weekly, as long as reminders are not paused.
     *
     * @return array{label: string, date: Carbon, days: int}|null
     */
    public function nextAction(): ?array
    {
        if ($this->status !== InvoiceStatus::Sent || $this->outstanding() <= 0) {
            return null;
        }

        $date = $this->due_date->copy()->addWeeks($this->reminders_sent + 1);
        $ordinal = ['Eerste', 'Tweede', 'Derde'][$this->reminders_sent] ?? ($this->reminders_sent + 1).'e';

        return ['label' => $this->reminder_paused ? 'Herinneringen gepauzeerd' : $ordinal.' herinnering', 'date' => $date, 'days' => (int) today()->diffInDays($date->startOfDay(), false)];
    }

    public function label(): string
    {
        return $this->number ?? 'Concept #'.$this->id;
    }

    public function record(string $description): void
    {
        $this->events()->create(['description' => $description, 'user_id' => Auth::id()]);
    }
}
