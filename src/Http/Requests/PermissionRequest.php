<?php

namespace BBSLab\NovaPermission\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Class PermissionRequest.
 *
 * @property string $guard
 */
abstract class PermissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'guard' => [
                'required',
                'string',
                Rule::in(array_keys(config('auth.guards'))),
            ],
        ];
    }

    /**
     * Get the query search value.
     *
     * @return string
     */
    public function searchValue(): string
    {
        return trim($this->query('search', ''));
    }
}
