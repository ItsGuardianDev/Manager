<?php

namespace App\Models;

use App\Models\Concerns\HasTimeline;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

#[Guarded(['id', 'full_name_normalized'])]
class Person extends Model
{
    use HasFactory, HasTimeline;

    protected $table = 'people';

    protected static function booted(): void
    {
        static::saving(function (Person $person): void {
            $person->full_name_normalized = self::normalizeFullName(
                $person->first_name, $person->last_name_prefix, $person->last_name,
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'date_of_birth' => 'date',
        ];
    }

    public static function normalizeFullName(?string $firstName, ?string $prefix, ?string $lastName): string
    {
        $value = Str::ascii(trim("{$firstName} {$prefix} {$lastName}"));

        return (string) Str::of($value)->lower()->replaceMatches('/[^a-z0-9]+/', '');
    }

    /**
     * Full display name, e.g. "Jan van der Berg".
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => collect([$this->first_name, $this->last_name_prefix, $this->last_name])
            ->filter()->implode(' '));
    }

    protected function initialsLabel(): Attribute
    {
        return Attribute::get(fn (): string => Str::upper(
            Str::substr((string) ($this->first_name ?: $this->last_name), 0, 1).Str::substr($this->last_name, 0, 1)
        ));
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot(['id', 'job_title', 'department', 'is_primary'])
            ->withTimestamps();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function relationManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'relation_manager_id');
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
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
                $query->where(function (Builder $query) use ($like, $search): void {
                    $query->where('full_name_normalized', 'like', '%'.self::normalizeFullName($search, null, '').'%')
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('mobile', 'like', $like)
                        ->orWhere('city', 'like', $like)
                        ->orWhereHas('organizations', fn (Builder $query) => $query->where('name', 'like', $like));
                });
            })
            ->when(Arr::wrap($filters['organization_id'] ?? []), fn (Builder $query, array $ids) => $query->whereHas(
                'organizations', fn (Builder $query) => $query->whereIn('organizations.id', $ids)
            ))
            ->when(($filters['kind'] ?? 'all') === 'employee', fn (Builder $query) => $query->whereHas('employee'))
            ->when(($filters['kind'] ?? 'all') === 'contact', fn (Builder $query) => $query->whereHas('organizations'))
            ->when(! empty($filters['created_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['created_from']))
            ->when(! empty($filters['created_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['created_to']))
            ->when(($filters['status'] ?? 'active') === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? 'active') === 'inactive', fn (Builder $query) => $query->where('is_active', false));
    }
}
