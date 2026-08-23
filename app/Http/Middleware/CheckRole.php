<?php

namespace App\Http\Middleware;

use App\Enums\UserTypeEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    private const ROLES = [
        'administrator' => UserTypeEnum::Administrator->value,
        'representative' => UserTypeEnum::Representative->value,
        'teacher' => UserTypeEnum::Teacher->value,
    ];

    /**
     * Handle an incoming request.
     *
     * @param  string  ...$roles  Role names: administrator, representative, teacher
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $allowed = array_map(fn ($role) => self::ROLES[$role] ?? null, $roles);

        if (in_array((int) $user->type_user_id, $allowed, true)) {
            return $next($request);
        }

        abort(403);
    }
}
