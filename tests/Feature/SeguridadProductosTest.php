<?php

namespace Tests\Feature;

use App\Models\DamagedProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SeguridadProductosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $manta;

    private User $porto;

    private DamagedProduct $deManta;

    private DamagedProduct $dePorto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->usuario(User::ROL_ADMIN);
        $this->manta = $this->usuario(User::ROL_OPERADOR);
        $this->porto = $this->usuario(User::ROL_OPERADOR);

        $this->deManta = $this->producto('EC100000001EC', $this->manta);
        $this->dePorto = $this->producto('EC100000003EC', $this->porto);
    }

    private function usuario(string $rol): User
    {
        $user = User::factory()->create(['password' => 'clave-de-prueba']);
        $user->role = $rol;
        $user->save();

        return $user;
    }

    private function producto(string $sku, User $dueno): DamagedProduct
    {
        return DamagedProduct::create([
            'sku' => $sku, 'title' => 'Producto '.$sku, 'damage_level' => 'leve',
            'price' => 10, 'stock' => 1, 'status' => 'published', 'created_by' => $dueno->id,
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('api')->plainTextToken;
    }

    // ---------- P1: acceso permitido ----------

    public function test_p1_admin_ve_todos_los_productos(): void
    {
        $this->withToken($this->token($this->admin))->getJson('/api/products')
            ->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_p1_operador_solo_ve_sus_productos(): void
    {
        $this->withToken($this->token($this->manta))->getJson('/api/products')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.sku', 'EC100000001EC');
    }

    public function test_p1_operador_crea_un_producto_y_queda_como_dueno(): void
    {
        $this->withToken($this->token($this->manta))->postJson('/api/products', [
            'name' => 'Tostadora - golpe lateral', 'price' => 12.5, 'stock' => 1, 'sku' => 'EC100000009EC', 'damageLevel' => 'leve',
        ])->assertCreated()->assertJsonPath('data.createdBy', $this->manta->id);
    }

    public function test_p1_operador_modifica_su_propio_producto(): void
    {
        $this->withToken($this->token($this->manta))->patchJson("/api/products/{$this->deManta->id}", ['stock' => 4])
            ->assertOk()->assertJsonPath('data.stock', 4);
    }

    public function test_p1_admin_elimina_un_producto(): void
    {
        $this->withToken($this->token($this->admin))->deleteJson("/api/products/{$this->dePorto->id}")->assertNoContent();
        $this->assertDatabaseMissing('damaged_products', ['id' => $this->dePorto->id]);
    }

    // ---------- P2: sin autenticación ----------

    public function test_p2_sin_token_responde_401(): void
    {
        $this->getJson('/api/products')
            ->assertUnauthorized()->assertExactJson(['message' => 'No autenticado. Envíe un token Bearer válido.']);
    }

    public function test_p2_token_inventado_responde_401(): void
    {
        $this->withToken('1|token-falso')->getJson('/api/products')->assertUnauthorized();
    }

    public function test_p2_token_vencido_responde_401(): void
    {
        $token = $this->token($this->admin);
        $this->travel(61)->minutes();

        $this->withToken($token)->getJson('/api/products')->assertUnauthorized();
    }

    // ---------- P3: autenticado sin permisos ----------

    public function test_p3_operador_no_puede_eliminar(): void
    {
        $this->withToken($this->token($this->manta))->deleteJson("/api/products/{$this->deManta->id}")
            ->assertForbidden()->assertExactJson(['message' => 'No tiene permiso para realizar esta acción.']);
        $this->assertDatabaseHas('damaged_products', ['id' => $this->deManta->id]);
    }

    public function test_p3_operador_no_ve_un_producto_ajeno(): void
    {
        $this->withToken($this->token($this->manta))->getJson("/api/products/{$this->dePorto->id}")->assertForbidden();
    }

    public function test_p3_operador_no_modifica_un_producto_ajeno(): void
    {
        $this->withToken($this->token($this->manta))->patchJson("/api/products/{$this->dePorto->id}", ['stock' => 0])
            ->assertForbidden();
        $this->assertSame(1, $this->dePorto->fresh()->stock);
    }

    // ---------- Login y secretos ----------

    public function test_login_correcto_entrega_un_token_bearer(): void
    {
        $this->postJson('/api/login', ['email' => $this->manta->email, 'password' => 'clave-de-prueba'])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.role', 'operador')
            ->assertJsonStructure(['access_token', 'expires_in_minutes']);
    }

    public function test_login_fallido_no_registra_la_contrasena(): void
    {
        Log::spy();

        $this->postJson('/api/login', ['email' => $this->manta->email, 'password' => 'clave-equivocada'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Credenciales incorrectas.']);

        Log::shouldHaveReceived('warning')->once()->withArgs(function ($mensaje, $contexto) {
            return ! str_contains(json_encode($contexto), 'clave-equivocada')
                && ! array_key_exists('password', $contexto)
                && str_contains($contexto['email'], '***@');
        });
    }

    public function test_el_login_se_bloquea_tras_cinco_intentos(): void
    {
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/login', ['email' => $this->manta->email, 'password' => 'mala'])->assertUnauthorized();
        }

        $this->postJson('/api/login', ['email' => $this->manta->email, 'password' => 'mala'])->assertStatus(429);
    }
}
