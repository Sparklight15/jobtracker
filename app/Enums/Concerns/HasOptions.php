<?php

namespace App\Enums\Concerns;

trait HasOptions
{
    /**
     * Daftar [nilai => label] untuk dropdown.
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}