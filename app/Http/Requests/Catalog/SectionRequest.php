<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use App\Contexts\Catalog\Infrastructure\Persistence\Section;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared validation for creating and updating a section. Authorization
 * defers to the parent course's update policy.
 */
final class SectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');
        $course = $section instanceof Section ? $section->course : $this->route('course');

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
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
