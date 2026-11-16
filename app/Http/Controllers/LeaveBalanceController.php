<?php

namespace App\Http\Controllers;

use App\Enums\BalanceKind;
use App\Enums\LedgerReason;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LeaveBalanceController extends Controller
{
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('manageLeave', $employee);

        $data = $request->validate([
            'action' => ['required', 'in:allocation,extra_hours,conversion,correction'],
            'kind' => ['nullable', 'in:vacation,tvt'],
            'hours' => ['required', 'numeric', 'not_in:0', 'between:-2000,2000'],
            'entry_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [], ['hours' => 'uren', 'entry_date' => 'datum']);

        $hours = (float) $data['hours'];

        if ($data['action'] !== 'correction' && $hours < 0) {
            throw ValidationException::withMessages(['hours' => 'Vul een positief aantal uren in.']);
        }

        DB::transaction(function () use ($data, $hours, $employee, $request): void {
            $entry = fn (BalanceKind $kind, LedgerReason $reason, float $amount, string $default) => $employee->ledgerEntries()->create([
                'kind' => $kind, 'reason' => $reason, 'hours' => $amount, 'entry_date' => $data['entry_date'],
                'description' => $data['description'] ?? $default, 'user_id' => $request->user()->id,
            ]);

            match ($data['action']) {
                'allocation' => $entry(BalanceKind::Vacation, LedgerReason::Allocation, $hours, 'Toekenning verlofuren'),
                'extra_hours' => $entry(BalanceKind::TimeForTime, LedgerReason::ExtraHours, $hours, 'Extra gewerkte uren'),
                'conversion' => [
                    $entry(BalanceKind::TimeForTime, LedgerReason::Conversion, -$hours, 'Omzetting naar vakantie-uren'),
                    $entry(BalanceKind::Vacation, LedgerReason::Conversion, $hours, 'Omzetting van tijd-voor-tijd'),
                ],
                'correction' => $entry(BalanceKind::from($data['kind'] ?? 'vacation'), LedgerReason::Correction, $hours, 'Correctie'),
            };
        });

        return redirect()->route('employees.show', [$employee, 'tab' => 'leave'])->with('status', 'Saldo bijgewerkt.');
    }
}
