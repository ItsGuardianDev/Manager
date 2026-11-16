<?php

namespace App\Models;

use App\Enums\InvoiceMethod;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Guarded(['id'])]
class DefaultService extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['invoice_method' => InvoiceMethod::class];
    }
}
