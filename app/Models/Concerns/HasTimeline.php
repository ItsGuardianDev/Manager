<?php

namespace App\Models\Concerns;

use App\Enums\NoteType;
use App\Models\Document;
use App\Models\Note;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Notes, automatic events and documents that make up the timeline of a CRM or HRM record.
 */
trait HasTimeline
{
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'noteable')->latest('id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')->latest('id');
    }

    /**
     * Record a system event ("Contact aangemaakt door …") on the timeline.
     */
    public function logEvent(string $text, ?int $userId = null): Note
    {
        return $this->notes()->create([
            'body' => $text,
            'type' => NoteType::Event->value,
            'user_id' => $userId ?? auth()->id(),
        ]);
    }
}
