<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Autenticação do app mobile (equipe do parque) via token Bearer
 * (laravel/sanctum). O painel admin web usa sessão normal
 * (routes/web.php + Auth::routes), não este controller.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::where('email', $dados['email'])->first();

        if (! $user || ! Hash::check($dados['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Credenciais inválidas.']);
        }

        if ($user->status !== 'ativo') {
            throw ValidationException::withMessages(['email' => 'Credenciais inválidas.']);
        }

        $user->forceFill(['ultimo_login_at' => now(), 'ultimo_login_ip' => $request->ip()])->save();

        $token = $user->createToken($dados['device_name'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->only(['id', 'name', 'email', 'empresa_id', 'unidade_id']),
            'roles' => $user->getRoleNames(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sessão encerrada.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('unidade'));
    }
}
