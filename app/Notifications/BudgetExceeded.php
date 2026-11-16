<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\ProjectService;
use Illuminate\Notifications\Notification;

class BudgetExceeded extends Notification
{
    public function __construct(public Project $project, public ProjectService $service, public string $kind, public string $message) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'project_id' => $this->project->id,
            'project' => $this->project->name,
            'service' => $this->service->name,
            'kind' => $this->kind,
            'message' => $this->message,
        ];
    }
}
