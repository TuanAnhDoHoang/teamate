<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadProjectItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $project = $this->route('project');
        $user = $this->user();

        return $project instanceof Project
            && $user instanceof User
            && $project->canUserEdit($user);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $project = $this->route('project');
        abort_unless($project instanceof Project, 404);

        return [
            'file' => ['required', 'file', 'max:51200'], // Max 50MB per file
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('project_items', 'id')
                    ->where('project_id', $project->id)
                    ->where('kind', 'folder'),
            ],
            'relative_path' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Custom friendly validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please select a file to upload.',
            'file.file' => 'The uploaded item must be a valid file.',
            'file.max' => 'The file size exceeds the 50MB limit allowed by Teamate security policy.',
            'parent_id.exists' => 'The target folder could not be found.',
        ];
    }
}
