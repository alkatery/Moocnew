<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use App\Contexts\Catalog\Domain\Course\LessonType;
use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use App\Contexts\Catalog\Infrastructure\Persistence\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for creating and updating a lesson. Authorization
 * defers to the owning course's update policy.
 */
final class LessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->resolveSection()?->course;

        return $this->user()?->can('update', $course) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:255'],
            'type' => [$required, Rule::enum(LessonType::class)],
            'content' => ['nullable', 'string'],
            'video_id' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_free_preview' => ['nullable', 'boolean'],
        ];
    }

    private function resolveSection(): ?Section
    {
        $lesson = $this->route('lesson');

        if ($lesson instanceof Lesson) {
            return $lesson->section;
        }

        $section = $this->route('section');

        return $section instanceof Section ? $section : null;
    }
}
