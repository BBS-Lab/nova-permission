<?php

namespace BBSLab\NovaPermission\Http\Requests;

/**
 * Class PermissionByAuthorizableRequest
 *
 * @package BBSLab\NovaPermission\Http\Requests
 * @property integer $id
 * @property string $type
 */
class PermissionByAuthorizableRequest extends PermissionRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
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
