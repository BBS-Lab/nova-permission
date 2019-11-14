<?php

namespace BBSLab\NovaPermission\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Class AttachRequest.
 *
 * @property array $permissions
 * @property bool $attach
 */
class AttachRequest extends FormRequest
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
