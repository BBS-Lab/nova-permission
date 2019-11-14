<?php

namespace BBSLab\NovaPermission\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Class PermissionByGroupRequest.
 *
 * @property string|null $group
 */
class PermissionByGroupRequest extends PermissionRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return array_merge(parent::rules(), [
            'group' => [
                'nullable',
                'string',
                Rule::exists(config('permission.table_names.permissions'), 'group')
                    ->where(function ($query) {
                        $query->whereNull(['authorizable_id', 'authorizable_type']);
                    }),
            ],
        ]);
    }
}
