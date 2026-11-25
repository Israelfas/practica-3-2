<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /** POST /api/login: devuelve un token Bearer de Sanctum. */
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $datos['email'])->first();

        if (! $user || ! Hash::check($datos['password'], $user->password)) {
            // Nunca se registra la contraseña; el correo va enmascarado.
            Log::warning('Inicio de sesión fallido', [
                'email' => $this->enmascarar($datos['email']),
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Credenciales incorrectas.'], 401);
        }

        $token = $user->createToken('api');

        // Solo se registra el id del token, no el token.
        Log::info('Inicio de sesión', ['user_id' => $user->id, 'token_id' => $token->accessToken->id]);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_in_minutes' => (int) config('sanctum.expiration'),
            'user' => ['id' => $user->id, 'name' => $user->name, 'role' => $user->role],
        ]);
    }

    /** POST /api/logout: revoca el token usado en la petición. */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /** GET /api/me: quién soy (útil para comprobar el token). */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['id' => $user->id, 'name' => $user->name, 'role' => $user->role]);
    }

    private function enmascarar(string $email): string
    {
        [$nombre, $dominio] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($nombre, 0, 2).'***@'.$dominio;
    }
}
