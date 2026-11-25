<?php

namespace App\Http\Controllers\Api;

use App\Enrutamiento\EnrutadorPorContenido;
use App\Enrutamiento\FiltroDeMensajes;
use App\Enrutamiento\PublicadorEventos;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventoRequest;
use App\Models\DamagedProduct;
use App\Models\Evento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Productor de eventos de un producto dañado.
 * Recorrido: validación (EventoRequest) → filtro → enrutador → cola del consumidor.
 */
class EventoController extends Controller
{
    public function store(
        EventoRequest $request,
        FiltroDeMensajes $filtro,
        EnrutadorPorContenido $enrutador,
        PublicadorEventos $publicador,
    ): JsonResponse {
        $producto = DamagedProduct::findOrFail($request->integer('datos.product_id'));
        Gate::authorize('view', $producto);

        $mensaje = [
            'id' => $request->input('id'),
            'correlation_id' => $this->correlationId($request),
            'tipo' => $request->input('tipo'),
            'prioridad' => $request->input('prioridad'),
            'ocurrido_en' => now()->utc()->toIso8601ZuluString(),
            'datos' => [...$request->validated('datos'), 'sku' => $producto->sku],
        ];

        $evento = Evento::create([
            'message_id' => $mensaje['id'],
            'correlation_id' => $mensaje['correlation_id'],
            'tipo' => $mensaje['tipo'],
            'prioridad' => $mensaje['prioridad'],
            'product_id' => $producto->id,
            'datos' => $mensaje['datos'],
            'estado' => Evento::RECIBIDO,
        ]);

        // Message Filter: un mensaje válido que no debe procesarse no llega a ninguna cola.
        $motivo = $filtro->motivoDeRechazo($mensaje, $producto);
        if ($motivo !== null) {
            $evento->update(['estado' => Evento::FILTRADO, 'motivo' => $motivo]);
            Log::notice('[filtro] Mensaje rechazado por regla de negocio', [
                'correlation_id' => $mensaje['correlation_id'],
                'id' => $mensaje['id'],
                'tipo' => $mensaje['tipo'],
                'motivo' => $motivo,
            ]);

            return $this->respuesta($evento, 200, false);
        }

        // Content-Based Router: el tipo decide la cola.
        $destino = $enrutador->destino($mensaje);
        $evento->destino = $destino;

        try {
            $publicador->publicar($mensaje, $destino);
        } catch (Throwable $e) {
            // Fallo de transporte: es distinto de un rechazo por regla de negocio.
            $evento->update(['estado' => Evento::ERROR_TRANSPORTE, 'motivo' => 'Broker no disponible']);
            Log::error('[enrutador] No se pudo publicar en el broker', [
                'correlation_id' => $mensaje['correlation_id'],
                'id' => $mensaje['id'],
                'destino' => $destino,
            ]);

            return $this->respuesta($evento, 503, false);
        }

        $noEnrutable = $enrutador->esNoEnrutable($destino);
        $evento->update([
            'estado' => $noEnrutable ? Evento::NO_ENRUTABLE : Evento::ENRUTADO,
            'motivo' => $noEnrutable ? 'Ninguna regla reconoce el tipo '.$mensaje['tipo'] : null,
        ]);

        Log::info($noEnrutable ? '[enrutador] Tipo desconocido: enviado a revisión' : '[enrutador] Mensaje enrutado', [
            'correlation_id' => $mensaje['correlation_id'],
            'id' => $mensaje['id'],
            'tipo' => $mensaje['tipo'],
            'destino' => $destino,
        ]);

        return $this->respuesta($evento, 202, true);
    }

    /** GET /api/eventos?correlation_id=...: recorrido de los eventos. */
    public function index(Request $request): JsonResponse
    {
        $eventos = Evento::query()
            ->when($request->query('correlation_id'), fn ($q, $cid) => $q->where('correlation_id', $cid))
            ->latest('id')->limit(20)->get();

        return response()->json(['data' => $eventos->map(fn (Evento $e) => $this->resumen($e))]);
    }

    /** GET /api/eventos/{id}: estado de un evento. */
    public function show(string $id): JsonResponse
    {
        return response()->json(['data' => $this->resumen(Evento::where('message_id', $id)->firstOrFail())]);
    }

    private function respuesta(Evento $evento, int $status, bool $aceptado): JsonResponse
    {
        return response()->json(['aceptado' => $aceptado, ...$this->resumen($evento)], $status);
    }

    private function resumen(Evento $e): array
    {
        return [
            'id' => $e->message_id,
            'correlation_id' => $e->correlation_id,
            'tipo' => $e->tipo,
            'prioridad' => $e->prioridad,
            'estado' => $e->estado,
            'destino' => $e->destino,
            'cola' => $e->destino ? config('enrutamiento.colas')[$e->destino] : null,
            'motivo' => $e->motivo,
            'procesado_por' => $e->procesado_por,
            'resultado' => $e->resultado,
        ];
    }

    /** Se conserva el de la cabecera o el cuerpo; si no llega, se genera uno. */
    private function correlationId(EventoRequest $request): string
    {
        $cabecera = $request->header('X-Correlation-ID');
        if (is_string($cabecera) && preg_match('/^[A-Za-z0-9._-]{1,100}$/', $cabecera)) {
            return $cabecera;
        }

        return $request->input('correlation_id') ?? 'evt-'.Str::lower(Str::random(10));
    }
}
