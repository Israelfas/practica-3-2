<?php

namespace App\Console\Commands;

use App\Models\Evento;
use Illuminate\Console\Command;

/** php artisan eventos:recorrido: muestra qué pasó con cada evento. */
class RecorridoEventos extends Command
{
    protected $signature = 'eventos:recorrido {--limite=20 : cuántos eventos mostrar}';

    protected $description = 'Muestra el estado, destino y consumidor de los últimos eventos';

    public function handle(): int
    {
        $eventos = Evento::orderBy('id')->limit((int) $this->option('limite'))->get();

        $this->table(
            ['id', 'tipo', 'prioridad', 'estado', 'destino', 'procesado por', 'resultado o motivo'],
            $eventos->map(fn (Evento $e) => [
                $e->message_id,
                $e->tipo,
                $e->prioridad,
                $e->estado,
                $e->destino ?? '-',
                $e->procesado_por ?? '-',
                mb_strimwidth($e->resultado ? json_encode($e->resultado, JSON_UNESCAPED_UNICODE) : (string) $e->motivo, 0, 60, '...'),
            ]),
        );

        return self::SUCCESS;
    }
}
