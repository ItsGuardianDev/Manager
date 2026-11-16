<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\Project;
use App\Models\ProjectService;
use App\Models\User;
use App\Notifications\BudgetExceeded;
use Illuminate\Support\Collection;

/**
 * Tells the responsible people once when a service goes over its hour or cost budget,
 * and re-arms the alert when the budget is raised or the overspend is corrected.
 */
class BudgetMonitor
{
    public function check(Project $project): void
    {
        $project->load(Project::BUDGET_RELATIONS);

        foreach ($project->services as $service) {
            $this->evaluate($project, $service, 'hours', $service->isOverHoursBudget(), 'hours_alerted_at',
                "Het urenbudget van dienst \"{$service->name}\" is overschreden ({$service->spentHours()} van {$service->budgetHours()} uur).");
            $this->evaluate($project, $service, 'costs', $service->isOverCostBudget(), 'costs_alerted_at',
                "Het kostenbudget van dienst \"{$service->name}\" is overschreden (€ ".number_format($service->spentCosts(), 2, ',', '.').' van € '.number_format($service->budgetCosts(), 2, ',', '.').').');
        }
    }

    private function evaluate(Project $project, ProjectService $service, string $kind, bool $over, string $flag, string $message): void
    {
        if ($over && $service->{$flag} === null) {
            $service->forceFill([$flag => now()])->saveQuietly();
            $project->logEvent('Waarschuwing: '.$message);

            foreach ($this->recipients($project) as $user) {
                $user->notify(new BudgetExceeded($project, $service, $kind, $message));
            }
        } elseif (! $over && $service->{$flag} !== null) {
            $service->forceFill([$flag => null])->saveQuietly();
        }
    }

    /**
     * The project manager; if none (or without login) everyone who may edit projects.
     *
     * @return Collection<int, User>
     */
    private function recipients(Project $project): Collection
    {
        $manager = $project->manager?->user;

        if ($manager) {
            return collect([$manager]);
        }

        return User::where('is_active', true)->whereHas('group', fn ($query) => $query->whereJsonContains('permissions', Permission::ProjectsEdit->value))->get();
    }
}
