<?php

namespace App\Enums;

enum Gender: string
{
    case Female = 'female';
    case Male = 'male';

    public function label(): string
    {
        return match ($this) {
            self::Female => 'Kadın',
            self::Male => 'Erkek',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Female => 'K',
            self::Male => 'E',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $g) => [$g->value => $g->label()])->all();
    }
}
