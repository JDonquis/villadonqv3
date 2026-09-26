<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    /**
     * Relación entre el primer segmento de la ruta administrativa y el slug del módulo.
     */
    private const PATH_MODULE = [
        'configuracion' => 'configuracion',
        'periodo-escolar' => 'configuracion',
        'matricula' => 'matricula',
        'secciones' => 'matricula',
        'importaciones-fallidas' => 'matricula',
        'pagos' => 'pagos',
        'estados-de-cuenta' => 'estados-cuenta',
        'reportes' => 'estados-cuenta',
        'personal' => 'personal',
        'profesores' => 'profesores',
        'importaciones-fallidas-profesores' => 'profesores',
        'materias' => 'materias',
        'planes-evaluacion' => 'planes-evaluacion',
        'horarios' => 'horarios',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        // Los administradores completos tienen acceso a todos los módulos.
        if ($user->is_admin) {
            return $next($request);
        }

        $assigned = $user->modules()->pluck('slug')->all();

        $segments = explode('/', trim($request->path(), '/'));
        $module = self::PATH_MODULE[$segments[1] ?? ''] ?? null;

        if ($module === null || in_array($module, $assigned, true)) {
            return $next($request);
        }

        abort(403);
    }
}
