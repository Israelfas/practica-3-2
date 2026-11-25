<?php

namespace Tests\Feature;

use App\Mensajeria\ProcesadorNotificacion;
use App\Mensajeria\PublicadorMensajes;
use App\Models\DamagedProduct;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class NotificacionesTest extends TestCase
{
    use RefreshDatabase;

    private array $publicados = [];

    private User $manta;

    private DamagedProduct $producto;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mensajeria.demora_envio' => 0]);

        // Productor falso: guarda los mensajes en memoria en lugar de enviarlos a RabbitMQ
        $this->app->instance(PublicadorMensajes::class, new class($this->publicados) implements PublicadorMensajes {
            public function __construct(private array &$publicados) {}

            public function publicar(array $mensaje): void
            {
                $this->publicados[] = $mensaje;
            }
        });

        $this->manta = User::factory()->create();
        $this->producto = DamagedProduct::create([
            'sku' => 'EC100000001EC', 'title' => 'Licuadora', 'damage_level' => 'leve',
            'price' => 10, 'stock' => 1, 'status' => 'published', 'created_by' => $this->manta->id,
        ]);
        $this->actingAs($this->manta, 'sanctum');
    }

    public function test_mensaje_valido_responde_202_y_queda_pendiente_en_la_cola(): void
    {
        $r = $this->postJson('/api/notificaciones', ['product_id' => $this->producto->id, 'canal' => 'email'])
            ->assertStatus(202)
            ->assertJsonPath('estado', 'pendiente');

        $this->assertCount(1, $this->publicados);
        $m = $this->publicados[0];
        $this->assertSame($r->json('message_id'), $m['message_id']);
        $this->assertSame('notificacion_producto_danado', $m['tipo']);
        $this->assertSame(['product_id' => $this->producto->id, 'sku' => 'EC100000001EC', 'canal' => 'email'], $m['payload']);
        $this->assertDatabaseHas('notificaciones', ['message_id' => $m['message_id'], 'estado' => 'pendiente', 'modo' => 'asincrono']);
    }

    public function test_conserva_el_correlation_id_que_envia_el_cliente(): void
    {
        $this->withHeader('X-Correlation-ID', 'danio-101')
            ->postJson('/api/notificaciones', ['product_id' => $this->producto->id, 'canal' => 'sms'])
            ->assertStatus(202)->assertJsonPath('correlation_id', 'danio-101');

        $this->assertSame('danio-101', $this->publicados[0]['correlation_id']);
    }

    public function test_genera_un_correlation_id_si_no_llega(): void
    {
        $cid = $this->postJson('/api/notificaciones', ['product_id' => $this->producto->id, 'canal' => 'email'])
            ->assertStatus(202)->json('correlation_id');

        $this->assertStringStartsWith('notif-', $cid);
    }

    public function test_mensaje_invalido_se_rechaza_con_422_y_no_se_publica(): void
    {
        $this->postJson('/api/notificaciones', ['product_id' => 999, 'canal' => 'fax'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_id', 'canal']);

        $this->assertCount(0, $this->publicados);
        $this->assertDatabaseCount('notificaciones', 0);
    }

    public function test_si_el_broker_no_responde_devuelve_503(): void
    {
        $this->app->instance(PublicadorMensajes::class, new class implements PublicadorMensajes {
            public function publicar(array $mensaje): void
            {
                throw new RuntimeException('Connection refused');
            }
        });

        $this->postJson('/api/notificaciones', ['product_id' => $this->producto->id, 'canal' => 'email'])
            ->assertStatus(503);
        $this->assertDatabaseHas('notificaciones', ['estado' => 'fallida', 'error' => 'Broker no disponible']);
    }

    public function test_el_consumidor_procesa_el_mensaje_y_marca_enviada(): void
    {
        $this->postJson('/api/notificaciones', ['product_id' => $this->producto->id, 'canal' => 'email']);

        app(ProcesadorNotificacion::class)->procesar($this->publicados[0]);

        $n = Notificacion::first();
        $this->assertSame('enviada', $n->estado);
        $this->assertNotNull($n->procesada_en);
    }

    public function test_el_consumidor_no_envia_dos_veces_el_mismo_mensaje(): void
    {
        $this->postJson('/api/notificaciones', ['product_id' => $this->producto->id, 'canal' => 'email']);
        $procesador = app(ProcesadorNotificacion::class);

        $procesador->procesar($this->publicados[0]);
        $primera = Notificacion::first()->procesada_en;
        $this->travel(1)->minutes();
        $procesador->procesar($this->publicados[0]);

        $this->assertEquals($primera, Notificacion::first()->procesada_en);
    }

    public function test_si_el_producto_ya_no_existe_el_consumidor_falla(): void
    {
        $this->postJson('/api/notificaciones', ['product_id' => $this->producto->id, 'canal' => 'email']);
        $this->producto->delete();
        $procesador = app(ProcesadorNotificacion::class);

        try {
            $procesador->procesar($this->publicados[0]);
            $this->fail('Debía lanzar una excepción');
        } catch (RuntimeException $e) {
            $procesador->marcarFallida($this->publicados[0], $e->getMessage());
        }

        $this->assertDatabaseHas('notificaciones', ['estado' => 'fallida', 'error' => 'El producto ya no existe']);
    }

    public function test_la_version_sincrona_responde_cuando_ya_termino(): void
    {
        $this->postJson('/api/notificaciones/sincrona', ['product_id' => $this->producto->id, 'canal' => 'email'])
            ->assertOk()->assertJsonPath('estado', 'enviada');

        $this->assertCount(0, $this->publicados);
        $this->assertDatabaseHas('notificaciones', ['modo' => 'sincrono', 'estado' => 'enviada']);
    }

    public function test_se_puede_seguir_una_operacion_por_correlation_id(): void
    {
        $this->withHeader('X-Correlation-ID', 'danio-777')
            ->postJson('/api/notificaciones', ['product_id' => $this->producto->id, 'canal' => 'email']);

        $this->getJson('/api/notificaciones?correlation_id=danio-777')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.estado', 'pendiente');
    }

    public function test_un_operador_no_puede_notificar_un_producto_ajeno(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->postJson('/api/notificaciones', ['product_id' => $this->producto->id, 'canal' => 'email'])
            ->assertForbidden();
        $this->assertCount(0, $this->publicados);
    }
}
