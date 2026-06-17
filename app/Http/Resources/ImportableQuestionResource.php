<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contexts\Assessment\Infrastructure\Persistence\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * عرض سؤال المصدر في شاشة الاستيراد (E5).
 * يكشف ما يكفي للاختيار والمعاينة — لا مفتاح الإجابة، لا config.
 *
 * @mixin Question
 */
final class ImportableQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'body' => $this->body,
            'points' => $this->points,
            'choices_count' => is_array($this->choices) ? count($this->choices) : null,
            'source_course' => $this->whenLoaded('course', fn () => [
                'id' => $this->course->getKey(),
                'title' => $this->course->title,
                'slug' => $this->course->slug,
            ]),
        ];
    }
}
