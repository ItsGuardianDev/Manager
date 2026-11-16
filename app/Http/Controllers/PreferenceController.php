<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreferenceController extends Controller
{
    public const ORGANIZATION_CHARTS = ['type' => 'Relatiesoort', 'relation_manager' => 'Relatiebeheerder', 'country' => 'Land', 'industry' => 'Branche', 'size' => 'Bedrijfsgrootte'];

    public const DEFAULT_CHARTS = ['type', 'industry', 'size'];

    public function organizationCharts(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'charts' => ['nullable', 'array'],
            'charts.*' => [Rule::in(array_keys(self::ORGANIZATION_CHARTS))],
        ]);

        $selected = array_values(array_intersect(array_keys(self::ORGANIZATION_CHARTS), $data['charts'] ?? []));

        $user = $request->user();
        $user->update(['preferences' => [...($user->preferences ?? []), 'organization_charts' => $selected]]);

        return back()->with('status', 'Weergave opgeslagen.');
    }
}
