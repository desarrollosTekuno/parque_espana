<?php

namespace App\Support;

use Carbon\Carbon;

class SpanishDate
{
    private const MONTHS = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    private const WEEKDAYS = [
        0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
        4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado',
    ];

    public static function month(int $month): string
    {
        return self::MONTHS[$month];
    }

    public static function weekday(int $dayOfWeek): string
    {
        return self::WEEKDAYS[$dayOfWeek];
    }

    public static function fullDate(Carbon $date): string
    {
        return $date->format('j') . ' ' . self::MONTHS[$date->month] . ' ' . $date->format('Y');
    }

    /**
     * "Hoy" / "Mañana" para el día actual/siguiente, y el nombre del día (ej. "Domingo") para el resto.
     */
    public static function relativeLabel(Carbon $date, Carbon $today): string
    {
        return self::relative($date, $today) ?? self::weekday($date->dayOfWeek);
    }

    /**
     * "Hoy" / "Mañana" para el día actual/siguiente, y la fecha completa (ej. "12 julio 2026") para el resto.
     */
    public static function relativeFullLabel(Carbon $date, Carbon $today): string
    {
        return self::relative($date, $today) ?? self::fullDate($date);
    }

    private static function relative(Carbon $date, Carbon $today): ?string
    {
        $target = $date->copy()->startOfDay();
        $today = $today->copy()->startOfDay();

        if ($target->eq($today)) {
            return 'Hoy';
        }

        if ($target->eq($today->copy()->addDay())) {
            return 'Mañana';
        }

        return null;
    }
}
