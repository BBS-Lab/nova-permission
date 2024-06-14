<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * @property string|null $group
 */
class PermissionByGroupRequest extends PermissionRequest
{
    public function rules(): array
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
