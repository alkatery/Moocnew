<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use App\Contexts\Assessment\Infrastructure\Persistence\AssignmentSubmission;
use Illuminate\Foundation\Http\FormRequest;

final class GradeSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $submission = $this->route('submission');
        $course = $submission instanceof AssignmentSubmission
            ? $submission->assignment->course
            : null;

        return $this->user()?->can('update', $course) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $max = $this->route('submission')?->assignment?->points ?? 100;

        return [
            // Either a direct grade, or per-criterion rubric scores summed
            // into the grade by the controller.
            'grade' => ['required_without:rubric_scores', 'integer', 'min:0', "max:{$max}"],
            'rubric_scores' => ['required_without:grade', 'array'],
            'rubric_scores.*' => ['integer', 'min:0'],
            'feedback' => ['nullable', 'string'],
        ];
    }
}
