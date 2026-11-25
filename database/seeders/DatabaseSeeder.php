<?php

namespace Database\Seeders;

use App\Models\DamagedProduct;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos ficticios para probar los permisos.
 * La contraseña sale de DEMO_PASSWORD en .env; no está escrita en el código.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('demo.password');

        if (blank($password)) {
            $this->command->warn('Defina DEMO_PASSWORD en .env para crear los usuarios de prueba.');

            return;
        }

        $admin = $this->usuario('Administración Correos', config('demo.admin_email'), User::ROL_ADMIN, $password);
        $manta = $this->usuario('Agencia Manta', config('demo.operador_email'), User::ROL_OPERADOR, $password);
        $porto = $this->usuario('Agencia Portoviejo', config('demo.operador2_email'), User::ROL_OPERADOR, $password);

        $this->producto('EC100000001EC', 'Licuadora Oster - caja golpeada', 'leve', 24.50, 5, $manta);
        $this->producto('EC100000002EC', 'Microondas - puerta rayada', 'moderado', 60.00, 2, $manta);
        $this->producto('EC100000003EC', 'Plancha - cable pelado', 'severo', 8.00, 1, $porto);
        // Archivado: sirve para comprobar el filtro de la práctica 3.2
        $this->producto('EC100000004EC', 'Tostadora - carcasa rota', 'severo', 18.00, 3, $manta, 'archived');
    }

    private function usuario(string $nombre, string $email, string $rol, string $password): User
    {
        $user = User::updateOrCreate(['email' => $email], ['name' => $nombre, 'password' => $password]);
        $user->role = $rol; // el rol se asigna aparte, no por asignación masiva
        $user->save();

        return $user;
    }

    private function producto(string $sku, string $titulo, string $nivel, float $precio, int $stock, User $dueno, string $estado = 'published'): void
    {
        DamagedProduct::updateOrCreate(['sku' => $sku], [
            'title' => $titulo,
            'damage_level' => $nivel,
            'price' => $precio,
            'stock' => $stock,
            'status' => $estado,
            'created_by' => $dueno->id,
        ]);
    }
}
