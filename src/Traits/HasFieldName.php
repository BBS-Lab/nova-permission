<?php

namespace BBSLab\NovaPermission\Traits;

use Illuminate\Support\Str;

trait HasFieldName
{
    protected function getTranslatedFieldName(string $name): string
    {
        $key = Str::snake($name);

        $validationAttributeKey = "validation.attributes.{$key}";
        $trans = __($validationAttributeKey);

        if ($trans !== $validationAttributeKey) {
            return ucfirst($trans);
        }

        return __($name);
    }
}
