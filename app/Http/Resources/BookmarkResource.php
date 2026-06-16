<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contexts\Learning\Infrastructure\Persistence\Bookmark;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * تمثيل العلامة المرجعية في استجابات API — يطابق شكل §1.أ من العقد D1.
 * يفترض eager-load مسبق لـ lesson.section.course قبل التحويل.
 *
 * @mixin Bookmark
 */
final class BookmarkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $course = $this->lesson->section->course;

        return [
            'id' => $this->id,
            'lesson' => [
                'id' => $this->lesson->id,
                'title' => $this->lesson->title,
                'type' => $this->lesson->type->value,
            ],
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
