<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Guarded(['id'])]
class Document extends Model
{
    /**
     * Extension groups used by the document filter.
     *
     * @var array<string, list<string>>
     */
    public const EXTENSION_GROUPS = [
        'images' => ['jpg', 'jpeg', 'png', 'gif'],
        'documents' => ['pdf', 'doc', 'docx', 'txt'],
        'spreadsheets' => ['xls', 'xlsx', 'csv'],
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function categoryLabel(): ?string
    {
        return $this->category ? DocumentCategory::tryFrom($this->category)?->label() : null;
    }

    public function extensionGroup(): string
    {
        $extension = strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));

        foreach (self::EXTENSION_GROUPS as $group => $extensions) {
            if (in_array($extension, $extensions, true)) {
                return $group;
            }
        }

        return 'other';
    }
}
