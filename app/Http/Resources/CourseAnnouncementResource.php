<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contexts\Notification\Infrastructure\Persistence\CourseAnnouncement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد إعلان المقرر (C3 — PRD §5.ط).
 *
 * يُمثّل إعلاناً واحداً كما يظهر للمتعلّم أو الطاقم.
 * النصّ مُعاد كما هو — العرض المسؤول عن الـ escape.
 *
 * @mixin CourseAnnouncement
 */
final class CourseAnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'body' => $this->body,
            'author' => [
                'id' => $this->author?->id,
                'name' => $this->author?->name,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
