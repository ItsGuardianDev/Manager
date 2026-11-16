<?php

namespace App\Models;

use App\Enums\InvoiceDelivery;
use App\Enums\OrganizationType;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

#[Guarded(['id', 'name_normalized'])]
class Organization extends Model
{
    use HasFactory;

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

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'noteable')->latest();
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
            ->when(Arr::wrap($filters['invoice_delivery'] ?? []), fn (Builder $query, array $values) => $query->whereIn('invoice_delivery', $values))
            ->when(! empty($filters['created_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['created_from']))
            ->when(! empty($filters['created_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['created_to']))
            ->when(($filters['status'] ?? 'active') === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? 'active') === 'inactive', fn (Builder $query) => $query->where('is_active', false));
    }
}
