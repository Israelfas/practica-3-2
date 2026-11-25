<?php

namespace Tests\Feature;

use App\Enrutamiento\EnrutadorPorContenido;
use App\Enrutamiento\ProcesadorDeEventos;
use App\Enrutamiento\PublicadorEventos;
use App\Models\DamagedProduct;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class EnrutamientoTest extends TestCase
{
    use RefreshDatabase;

    /** Lo que habría llegado a cada cola: [destino, mensaje]. */
    private array $entregas = [];

    private User $manta;

    private DamagedProduct $moderado;

    private DamagedProduct $leve;

    protected function setUp(): void
    {
        parent::setUp();

        // Publicador falso: guarda la entrega en memoria en lugar de ir a RabbitMQ.
        $this->app->instance(PublicadorEventos::class, new class($this->entregas) implements PublicadorEventos {
            public function __construct(private array &$entregas) {}

            public function publicar(array $mensaje, string $destino): void
            {
                $this->entregas[] = [$destino, $mensaje];
            }
        });

        $this->manta = User::factory()->create();
        $this->moderado = $this->producto('EC100000002EC', 'moderado', 60.00, 2);
        $this->leve = $this->producto('EC100000001EC', 'leve', 24.50, 5);
        $this->actingAs($this->manta, 'sanctum');
    }

    private function producto(string $sku, string $nivel, float $precio, int $stock, string $estado = 'published'): DamagedProduct
    {
        return DamagedProduct::create([
            'sku' => $sku, 'title' => 'Producto '.$sku, 'damage_level' => $nivel,
            'price' => $precio, 'stock' => $stock, 'status' => $estado, 'created_by' => $this->manta->id,
        ]);
    }

    private function enviar(string $id, string $tipo, array $datos, string $prioridad = 'alta')
    {
        return $this->postJson('/api/eventos', compact('id', 'tipo', 'prioridad', 'datos'));
    }

    /** Casos 1 a 3: cada tipo conocido llega solo a su consumidor. */
    public function test_casos_1_a_3_cada_tipo_llega_a_su_destino(): void
    {
        $this->enviar('evt-1', 'aviso_remitente', ['product_id' => $this->moderado->id, 'canal' => 'sms'])
            ->assertStatus(202)->assertJsonPath('destino', 'notificaciones')->assertJsonPath('estado', 'enrutado');
        $this->enviar('evt-2', 'solicitud_reembolso', ['product_id' => $this->moderado->id], 'media')
            ->assertStatus(202)->assertJsonPath('destino', 'reembolsos');
        $this->enviar('evt-3', 'ajuste_inventario', ['product_id' => $this->moderado->id, 'cantidad' => 1], 'baja')
            ->assertStatus(202)->assertJsonPath('destino', 'inventario')
            ->assertJsonPath('cola', 'correos.eventos.inventario');

        $this->assertSame(['notificaciones', 'reembolsos', 'inventario'], array_column($this->entregas, 0));
        $this->assertSame(['evt-1', 'evt-2', 'evt-3'], array_map(fn ($e) => $e[1]['id'], $this->entregas));
    }

    /** Caso 4: un tipo que ninguna regla reconoce va a revisión, no se pierde. */
    public function test_caso_4_tipo_desconocido_va_a_no_enrutables(): void
    {
        $this->enviar('evt-4', 'cambio_de_direccion', ['product_id' => $this->moderado->id])
            ->assertStatus(202)
            ->assertJsonPath('destino', 'no_enrutables')
            ->assertJsonPath('estado', 'no_enrutable');

        $this->assertSame('no_enrutables', $this->entregas[0][0]);
        $this->assertDatabaseHas('eventos', ['message_id' => 'evt-4', 'estado' => 'no_enrutable']);
    }

    /** Caso 5: un reembolso de daño leve no pasa el filtro y no llega a ninguna cola. */
    public function test_caso_5_mensaje_filtrado_no_llega_al_consumidor(): void
    {
        $this->enviar('evt-5', 'solicitud_reembolso', ['product_id' => $this->leve->id])
            ->assertOk()
            ->assertJsonPath('aceptado', false)
            ->assertJsonPath('estado', 'filtrado')
            ->assertJsonPath('destino', null);

        $this->assertCount(0, $this->entregas);
        $this->assertDatabaseHas('eventos', ['message_id' => 'evt-5', 'estado' => 'filtrado']);
    }

    public function test_un_producto_archivado_tambien_se_filtra(): void
    {
        $archivado = $this->producto('EC100000004EC', 'severo', 18.00, 3, 'archived');

        $this->enviar('evt-5b', 'aviso_remitente', ['product_id' => $archivado->id])
            ->assertOk()->assertJsonPath('estado', 'filtrado');
        $this->assertCount(0, $this->entregas);
    }

    public function test_retirar_mas_unidades_que_el_stock_se_filtra(): void
    {
        $this->enviar('evt-5c', 'ajuste_inventario', ['product_id' => $this->moderado->id, 'cantidad' => 9])
            ->assertOk()->assertJsonPath('estado', 'filtrado');
        $this->assertCount(0, $this->entregas);
    }

    /** Caso 6: sin un campo obligatorio, la validación lo rechaza antes de filtrar o enrutar. */
    public function test_caso_6_mensaje_sin_campo_obligatorio_se_rechaza(): void
    {
        $this->postJson('/api/eventos', ['id' => 'evt-6', 'tipo' => 'aviso_remitente', 'datos' => ['product_id' => $this->moderado->id]])
            ->assertStatus(422)
            ->assertJsonPath('estado', 'invalido')
            ->assertJsonValidationErrors(['prioridad']);

        $this->assertCount(0, $this->entregas);
        $this->assertDatabaseCount('eventos', 0);
    }

    public function test_un_fallo_del_broker_no_se_confunde_con_un_rechazo_de_negocio(): void
    {
        $this->app->instance(PublicadorEventos::class, new class implements PublicadorEventos {
            public function publicar(array $mensaje, string $destino): void
            {
                throw new RuntimeException('Connection refused');
            }
        });

        $this->enviar('evt-7', 'aviso_remitente', ['product_id' => $this->moderado->id])
            ->assertStatus(503)->assertJsonPath('estado', 'error_transporte');
    }

    public function test_el_enrutador_usa_la_tabla_de_reglas(): void
    {
        $enrutador = new EnrutadorPorContenido;

        $this->assertSame('notificaciones', $enrutador->destino(['tipo' => 'aviso_remitente']));
        $this->assertSame('reembolsos', $enrutador->destino(['tipo' => 'solicitud_reembolso']));
        $this->assertSame('inventario', $enrutador->destino(['tipo' => 'ajuste_inventario']));
        $this->assertSame('no_enrutables', $enrutador->destino(['tipo' => 'otro']));
        $this->assertSame('no_enrutables', $enrutador->destino([]));
    }

    public function test_cada_consumidor_hace_su_trabajo(): void
    {
        $this->enviar('evt-n', 'aviso_remitente', ['product_id' => $this->moderado->id, 'canal' => 'sms']);
        $this->enviar('evt-r', 'solicitud_reembolso', ['product_id' => $this->moderado->id]);
        $this->enviar('evt-i', 'ajuste_inventario', ['product_id' => $this->moderado->id, 'cantidad' => 2]);
        $procesador = app(ProcesadorDeEventos::class);

        foreach ($this->entregas as [$destino, $mensaje]) {
            $procesador->procesar($destino, $mensaje);
        }

        $this->assertSame(['canal' => 'sms', 'sku' => 'EC100000002EC'], Evento::firstWhere('message_id', 'evt-n')->resultado);
        $this->assertSame(['porcentaje' => 30, 'monto' => 18], Evento::firstWhere('message_id', 'evt-r')->resultado);
        $this->assertSame(0, $this->moderado->fresh()->stock);
        $this->assertDatabaseHas('eventos', ['message_id' => 'evt-i', 'estado' => 'procesado', 'procesado_por' => 'inventario']);
    }

    public function test_un_consumidor_no_procesa_un_mensaje_de_otro_destino(): void
    {
        $this->enviar('evt-x', 'solicitud_reembolso', ['product_id' => $this->moderado->id]);

        $this->expectException(RuntimeException::class);
        app(ProcesadorDeEventos::class)->procesar('inventario', $this->entregas[0][1]);
    }
}
