<?php

namespace App\Enums;

enum StudentObservationTypeEnum: string
{
    case Positive = 'positive';
    case Neutral = 'neutral';
    case Negative = 'negative';

    public function label(): string
    {
        return match ($this) {
            self::Positive => 'Positiva',
            self::Neutral => 'Neutra',
            self::Negative => 'Negativa',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Positive => 'mdi:arrow-up-bold-circle',
            self::Neutral => 'mdi:minus-circle',
            self::Negative => 'mdi:arrow-down-bold-circle',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
