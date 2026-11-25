<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NotificacionRequest;
use App\Mensajeria\PublicadorMensajes;
use App\Models\DamagedProduct;
use App\Models\Notificacion;
use App\Services\NotificadorDanio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/** Notificar al remitente que su paquete llegó dañado. */
class NotificacionController extends Controller
{
    /** POST /api/notificaciones: productor. Publica en la cola y responde 202 sin esperar el envío. */
    public function store(NotificacionRequest $request, PublicadorMensajes $publicador): JsonResponse
    {
        $producto = DamagedProduct::findOrFail($request->integer('product_id'));
        Gate::authorize('view', $producto);

        $notificacion = $this->registrar($request, $producto, 'asincrono');
        $mensaje = $this->armarMensaje($notificacion, $producto);

        try {
            $publicador->publicar($mensaje);
        } catch (Throwable $e) {
            $notificacion->update(['estado' => 'fallida', 'error' => 'Broker no disponible']);
            Log::error('[productor] No se pudo publicar', ['correlation_id' => $notificacion->correlation_id]);

            return response()->json([
                'mensaje' => 'La cola no está disponible. Intente más tarde.',
                'correlation_id' => $notificacion->correlation_id,
            ], 503);
        }

        return response()->json([
            'mensaje' => 'Solicitud recibida. La notificación se enviará en segundo plano.',
            'message_id' => $notificacion->message_id,
            'correlation_id' => $notificacion->correlation_id,
            'estado' => 'pendiente',
        ], 202)->header('Location', url('/api/notificaciones/'.$notificacion->message_id));
    }

    /** POST /api/notificaciones/sincrona: la misma operación, pero el cliente espera a que termine. */
    public function sincrona(NotificacionRequest $request, NotificadorDanio $notificador): JsonResponse
    {
        $producto = DamagedProduct::findOrFail($request->integer('product_id'));
        Gate::authorize('view', $producto);

        $notificacion = $this->registrar($request, $producto, 'sincrono');
        $notificador->enviar($producto, $notificacion->canal, $notificacion->correlation_id);
        $notificacion->update(['estado' => 'enviada', 'procesada_en' => now()]);

        return response()->json([
            'mensaje' => 'Notificación enviada.',
            'message_id' => $notificacion->message_id,
            'correlation_id' => $notificacion->correlation_id,
            'estado' => 'enviada',
        ]);
    }

    /** GET /api/notificaciones?correlation_id=...: seguimiento por correlación. */
    public function index(Request $request): JsonResponse
    {
        $lista = Notificacion::query()
            ->when($request->query('correlation_id'), fn ($q, $cid) => $q->where('correlation_id', $cid))
            ->latest('id')->limit(20)
            ->get(['message_id', 'correlation_id', 'modo', 'product_id', 'canal', 'estado', 'error', 'created_at', 'procesada_en']);

        return response()->json(['data' => $lista]);
    }

    /** GET /api/notificaciones/{message_id}: estado de un mensaje. */
    public function show(string $messageId): JsonResponse
    {
        $n = Notificacion::where('message_id', $messageId)->firstOrFail();

        return response()->json(['data' => $n->only(['message_id', 'correlation_id', 'modo', 'product_id', 'canal', 'estado', 'error', 'created_at', 'procesada_en'])]);
    }

    private function registrar(NotificacionRequest $request, DamagedProduct $producto, string $modo): Notificacion
    {
        return Notificacion::create([
            'message_id' => (string) Str::uuid(),
            'correlation_id' => $this->correlationId($request),
            'modo' => $modo,
            'product_id' => $producto->id,
            'canal' => $request->input('canal'),
            'estado' => 'pendiente',
        ]);
    }

    /** Se conserva el que manda el cliente (cabecera o cuerpo); si no manda, se genera uno. */
    private function correlationId(NotificacionRequest $request): string
    {
        $cabecera = $request->header('X-Correlation-ID');
        if (is_string($cabecera) && preg_match('/^[A-Za-z0-9._-]{1,100}$/', $cabecera)) {
            return $cabecera;
        }

        return $request->input('correlation_id') ?? 'notif-'.Str::lower(Str::random(10));
    }

    private function armarMensaje(Notificacion $n, DamagedProduct $producto): array
    {
        return [
            'message_id' => $n->message_id,
            'correlation_id' => $n->correlation_id,
            'tipo' => 'notificacion_producto_danado',
            'ocurrido_en' => now()->utc()->toIso8601ZuluString(),
            'payload' => [
                'product_id' => $producto->id,
                'sku' => $producto->sku,
                'canal' => $n->canal,
            ],
        ];
    }
}
