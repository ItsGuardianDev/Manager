<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $names = [
        'Agriculture' => 'Landbouw',
        'Construction' => 'Bouw',
        'Consulting' => 'Consultancy',
        'Education' => 'Onderwijs',
        'Energy & Utilities' => 'Energie en nutsvoorzieningen',
        'Financial Services' => 'Financiële dienstverlening',
        'Government' => 'Overheid',
        'Healthcare' => 'Gezondheidszorg',
        'Hospitality' => 'Horeca en recreatie',
        'IT & Software' => 'IT en software',
        'Legal' => 'Juridisch',
        'Logistics & Transport' => 'Logistiek en transport',
        'Manufacturing' => 'Maakindustrie',
        'Marketing & Advertising' => 'Marketing en reclame',
        'Media & Entertainment' => 'Media en entertainment',
        'Other' => 'Overig',
        'Real Estate' => 'Vastgoed',
        'Retail' => 'Detailhandel',
        'Telecommunications' => 'Telecommunicatie',
    ];

    public function up(): void
    {
        foreach ($this->names as $english => $dutch) {
            if (! DB::table('industries')->where('name', $dutch)->exists()) {
                DB::table('industries')->where('name', $english)->update(['name' => $dutch]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->names as $english => $dutch) {
            DB::table('industries')->where('name', $dutch)->update(['name' => $english]);
        }
    }
};
