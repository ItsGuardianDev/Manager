<?php

namespace Database\Seeders;

use App\Models\CompanySize;
use App\Models\Industry;
use Illuminate\Database\Seeder;

class LookupSeeder extends Seeder
{
    public function run(): void
    {
        $industries = [
            'Landbouw', 'Automotive', 'Bouw', 'Consultancy', 'Onderwijs', 'Energie en nutsvoorzieningen',
            'Financiële dienstverlening', 'Overheid', 'Gezondheidszorg', 'Horeca en recreatie', 'IT en software', 'Juridisch',
            'Logistiek en transport', 'Maakindustrie', 'Marketing en reclame', 'Media en entertainment',
            'Non-profit', 'Vastgoed', 'Detailhandel', 'Telecommunicatie', 'Overig',
        ];

        foreach ($industries as $name) {
            Industry::firstOrCreate(['name' => $name]);
        }

        foreach (['1-10 medewerkers', '11-20 medewerkers', '21-50 medewerkers', '51-100 medewerkers', '101-500 medewerkers', 'meer dan 500 medewerkers'] as $order => $name) {
            CompanySize::firstOrCreate(['name' => $name], ['sort_order' => $order]);
        }
    }
}
