<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property string $guard
 */
abstract class PermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guard' => [
                'required',
                'string',
                Rule::in(array_keys(config('auth.guards'))),
            ],
        ];
    }

    public function searchValue(): string
    {
        return trim($this->query('search', ''));
    }
}
