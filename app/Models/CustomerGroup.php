<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
class CustomerGroup extends Model
{
    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }
}
