<?php

namespace App\Helpers;

class AddressHelper
{
    /**
     * Сокращает адрес (берёт первые N частей через запятую)
     *
     * Пример:
     * "Южанин, 12а, Туркестанская улица, Мирабадский район, Ташкент"
     * → "Южанин, 12а"
     */
    public static function short(?string $address, int $parts = 2): string
    {
        if (empty($address)) {
            return '';
        }

        return collect(explode(',', $address))
            ->map(fn ($part) => trim($part))
            ->filter()
            ->take($parts)
            ->implode(', ');
    }
}
