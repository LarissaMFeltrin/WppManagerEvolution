<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Verificar se o usuário tem uma das roles permitidas.
     * Uso: middleware('role:admin,supervisor')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, $roles)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Acesso negado'], 403);
            }
            abort(403, 'Acesso negado. Você não tem permissão para acessar esta página.');
        }

        return $next($request);
    }
}
