<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Http\Requests;

/**
 * @property int $id
 * @property string $type
 */
class PermissionByAuthorizableRequest extends PermissionRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'id' => 'required',
            'type' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (!class_exists($value)) {
                        $fail($attribute.'is invalid');
                    }
                },
            ],
        ]);
    }
}
