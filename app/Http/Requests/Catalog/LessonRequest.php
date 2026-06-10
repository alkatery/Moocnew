<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use App\Contexts\Assessment\Infrastructure\Persistence\Question;
use App\Contexts\Catalog\Domain\Course\LessonType;
use App\Contexts\Catalog\Domain\Course\VideoProvider;
use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use App\Contexts\Catalog\Infrastructure\Persistence\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'transcript' => ['nullable', 'string'],
            'checkpoints' => ['nullable', 'array', 'max:20'],
            'checkpoints.*.at_seconds' => ['required_with:checkpoints', 'integer', 'min:0'],
            'checkpoints.*.question_id' => ['required_with:checkpoints', 'integer'],
            'video_provider' => ['nullable', 'required_with:video_id', Rule::enum(VideoProvider::class)],
            'video_id' => ['nullable', 'string', 'max:255', 'required_with:video_provider'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_free_preview' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $checkpoints = (array) $this->input('checkpoints', []);
            $course = $this->resolveSection()?->course;

            if ($checkpoints === [] || $course === null) {
                return;
            }

            $ids = array_map(static fn ($cp) => (int) ($cp['question_id'] ?? 0), $checkpoints);
            $valid = Question::query()
                ->where('course_id', $course->getKey())
                ->whereIn('id', $ids)
                ->count();

            if ($valid !== count(array_unique($ids))) {
                $validator->errors()->add('checkpoints', 'كل أسئلة نقاط التحقق يجب أن تكون من بنك أسئلة الدورة نفسها.');
            }
        });
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
