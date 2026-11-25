<?php

namespace App\Support;

use DateTimeInterface;

class Iso
{
    /** Fecha en el formato del contrato: 2026-09-29T15:00:00.000Z */
    public static function date(?DateTimeInterface $date): ?string
    {
        return $date ? \Illuminate\Support\Carbon::instance($date)->utc()->format('Y-m-d\TH:i:s.v\Z') : null;
    }
}
