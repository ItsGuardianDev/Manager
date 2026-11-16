<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class ProjectCopier
{
    /**
     * Copy a project with its services, budgets and members. Registered hours, purchases and alerts are not copied.
     */
    public function copy(Project $source, string $name, ?string $number = null): Project
    {
        return DB::transaction(function () use ($source, $name, $number): Project {
            $source->load(['services.hourLines', 'services.costLines', 'members', 'teams']);

            $copy = $source->replicate(['number', 'name', 'status', 'closed_at', 'copied_from_id']);
            $copy->fill([
                'number' => $number ?? Project::nextNumber(),
                'name' => $name,
                'status' => ProjectStatus::Active,
                'copied_from_id' => $source->id,
            ])->save();

            $copy->members()->sync($source->members->modelKeys());
            $copy->teams()->sync($source->teams->modelKeys());

            foreach ($source->services as $service) {
                $newService = $service->replicate(['completion_percentage', 'hours_alerted_at', 'costs_alerted_at']);
                $newService->project_id = $copy->id;
                $newService->completion_percentage = 0;
                $newService->save();

                foreach ($service->hourLines as $line) {
                    $newService->hourLines()->create($line->only(['hour_type_id', 'hours', 'rate']));
                }
                foreach ($service->costLines as $line) {
                    $newService->costLines()->create($line->only(['cost_type_id', 'quantity', 'purchase_price', 'margin_percentage']));
                }
            }

            $copy->logEvent("Project gekopieerd van \"{$source->name}\" (#{$source->number}).");
            $source->logEvent("Project gekopieerd naar \"{$copy->name}\" (#{$copy->number}).");

            return $copy;
        });
    }
}
