<?php

namespace App\Http\Resources;

use App\Models\LeaseDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeaseDocument */
class LeaseDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lease_id' => $this->lease_id,
            'type' => $this->type,
            'type_label' => LeaseDocument::TYPE_LABELS[$this->type] ?? $this->type,
            'title' => $this->title,
            'original_name' => $this->original_name,
            'mime' => $this->mime,
            'size' => $this->size,
            'size_label' => $this->formatSize((int) $this->size),
            'uploader' => $this->whenLoaded('uploader', function () {
                if (! $this->uploader) {
                    return null;
                }

                return [
                    'id' => $this->uploader->id,
                    'name' => $this->uploader->name,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }
}
