<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quiz
 */
final class QuizResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'section_id' => $this->section_id,
            'title' => $this->title,
            'time_limit_minutes' => $this->time_limit_minutes,
            'shuffle' => $this->shuffle,
            'draw_count' => $this->draw_count,
            'max_attempts' => $this->max_attempts,
            'pass_mark' => $this->pass_mark,
            'weight' => $this->weight,
            'questions' => QuestionResource::collection($this->whenLoaded('questions')),
            'questions_count' => $this->whenCounted('questions'),
        ];
    }
}
