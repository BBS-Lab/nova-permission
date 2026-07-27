<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property array<int, int> $permissions
 * @property bool $attach
 */
class AttachRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permissions' => 'required|array',
            'permissions.*' => [
                'required',
                'integer',
                Rule::exists(config('permission.table_names.permissions'), 'id'),
            ],
            'attach' => 'required|boolean',
        ];
    }
}
