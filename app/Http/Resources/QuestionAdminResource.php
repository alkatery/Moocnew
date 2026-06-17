<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contexts\Assessment\Infrastructure\Persistence\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Authoring view of a question, including the answer key — only returned to
 * users who may manage the course.
 *
 * @mixin Question
 */
final class QuestionAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'type' => $this->type->value,
            'body' => $this->body,
            'choices' => $this->choices,
            'correct' => $this->correct,
            'config' => $this->config,
            'explanation' => $this->explanation,
            'points' => $this->points,
        ];
    }
}
