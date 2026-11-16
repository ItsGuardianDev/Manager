<?php

namespace App\Models;

use App\Enums\InvoiceAttention;
use App\Enums\InvoiceDelivery;
use App\Enums\OrganizationType;
use App\Models\Concerns\HasTimeline;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

#[Guarded(['id', 'name_normalized'])]
class Organization extends Model
{
    use HasFactory, HasTimeline;

    /**
     * Countries offered in the address dropdowns.
     *
     * @var list<string>
     */
    public const COUNTRIES = [
        'Nederland', 'België', 'Duitsland', 'Frankrijk', 'Luxemburg', 'Verenigd Koninkrijk', 'Ierland', 'Spanje',
        'Portugal', 'Italië', 'Oostenrijk', 'Zwitserland', 'Denemarken', 'Zweden', 'Noorwegen', 'Finland', 'Polen',
        'Verenigde Staten', 'Canada',
    ];

    /**
     * @var array<int, string>
     */
    public const PAYMENT_TERMS = [0 => 'Direct', 7 => '7 dagen', 14 => '14 dagen', 30 => '30 dagen', 45 => '45 dagen', 60 => '60 dagen', 90 => '90 dagen'];

    /**
     * @var array<string, string>
     */
    public const VAT_CODES = ['vat_21' => 'BTW hoog (21%)', 'vat_9' => 'BTW laag (9%)', 'vat_0' => 'BTW 0%', 'reverse_charge' => 'BTW verlegd'];

    /**
     * @var array<string, string>
     */
    public const REMINDER_PLANS = ['default' => 'Algemene voorkeursinstelling gebruiken', 'none' => 'Geen herinneringen', 'friendly' => 'Vriendelijke herinneringen', 'strict' => 'Strikte herinneringen'];

    /**
     * @var array<string, string>
     */
    public const INVOICE_TEMPLATES = ['standard' => 'Standaard', 'detailed' => 'Gespecificeerd', 'compact' => 'Compact'];

    public function hasDifferentPostalAddress(): bool
    {
        return filled($this->postal_street) || filled($this->postal_zipcode) || filled($this->postal_city);
    }

    protected static function booted(): void
    {
        static::saving(function (Organization $organization): void {
            $organization->name_normalized = self::normalizeName($organization->name);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
            'invoice_delivery' => InvoiceDelivery::class,
            'invoice_attention' => InvoiceAttention::class,
            'is_vat_exempt' => 'boolean',
            'is_active' => 'boolean',
            'payment_term_days' => 'integer',
        ];
    }

    /**
     * Reduce a company name to a comparable form: lowercase, no punctuation and
     * without a trailing legal form, so "Acme B.V." and "ACME bv" collide.
     */
    public static function normalizeName(string $name): string
    {
        $value = Str::of(Str::ascii($name))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim();
        $value = $value->replaceMatches('/\s+(b v|bv|n v|nv|vof|v o f|gmbh|ltd|inc|llc)$/', '');

        return (string) $value->replace(' ', '');
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function companySize(): BelongsTo
    {
        return $this->belongsTo(CompanySize::class);
    }

    public function relationManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'relation_manager_id');
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class)
            ->withPivot(['id', 'job_title', 'department', 'is_primary'])
            ->withTimestamps();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function invoiceContactPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'invoice_contact_person_id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['q'] ?? ''));

        return $query
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('website', 'like', $like)
                        ->orWhere('visit_city', 'like', $like)
                        ->orWhere('coc_number', 'like', $like)
                        ->orWhere('vat_number', 'like', $like);
                });
            })
            ->when(Arr::wrap($filters['type'] ?? []), fn (Builder $query, array $values) => $query->whereIn('type', $values))
            ->when(Arr::wrap($filters['industry_id'] ?? []), fn (Builder $query, array $values) => $query->whereIn('industry_id', $values))
            ->when(Arr::wrap($filters['company_size_id'] ?? []), fn (Builder $query, array $values) => $query->whereIn('company_size_id', $values))
            ->when(Arr::wrap($filters['relation_manager_id'] ?? []), fn (Builder $query, array $values) => $query->whereIn('relation_manager_id', $values))
            ->when(Arr::wrap($filters['team_id'] ?? []), fn (Builder $query, array $values) => $query->whereIn('team_id', $values))
            ->when(Arr::wrap($filters['customer_group_id'] ?? []), fn (Builder $query, array $values) => $query->whereIn('customer_group_id', $values))
            ->when(Arr::wrap($filters['visit_country'] ?? []), fn (Builder $query, array $values) => $query->whereIn('visit_country', $values))
            ->when(Arr::wrap($filters['invoice_delivery'] ?? []), fn (Builder $query, array $values) => $query->whereIn('invoice_delivery', $values))
            ->when(! empty($filters['created_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['created_from']))
            ->when(! empty($filters['created_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['created_to']))
            ->when(($filters['status'] ?? 'active') === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? 'active') === 'inactive', fn (Builder $query) => $query->where('is_active', false));
    }
}
