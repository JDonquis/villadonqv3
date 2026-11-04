<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class BncWebhookAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isPing($request)) {
            return $next($request);
        }

        $mode = (string) config('services.bnc.webhook.auth', 'api_key');

        if ($mode === 'none') {
            return $next($request);
        }

        if ($mode === 'api_key') {
            $expected = (string) config('services.bnc.webhook.api_key');
            $provided = (string) $request->header('x-api-key', '');

            if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
                return response('No autorizado: API Key inválida o ausente.', 401);
            }

            return $next($request);
        }

        if ($mode === 'jwt') {
            $header = (string) $request->header('Authorization', '');
            $token = str_starts_with($header, 'Bearer ') ? substr($header, 7) : '';

            if ($token === '' || ! Cache::has($this->tokenCacheKey($token))) {
                return response('No autorizado: token inválido o expirado.', 401);
            }

            return $next($request);
        }

        return response('No autorizado: modo de autenticación BNC no configurado.', 401);
    }

    public static function tokenCacheKey(string $token): string
    {
        return 'bnc.webhook.token.'.hash('sha256', $token);
    }

    private function isPing(Request $request): bool
    {
        if ($request->isMethod('GET')) {
            return true;
        }

        $payload = $request->all();
        $keys = array_map('strtolower', array_keys($payload));

        $notificationKeys = [
            'paymenttype',
            'originbankreference',
            'destinybankreference',
            'amount',
            'commerceid',
            'creditoraccount',
        ];

        $hasNotificationPayload = count(array_intersect($keys, $notificationKeys)) > 0;

        return $payload === []
            || $keys === ['ping']
            || ! $hasNotificationPayload;
    }
}
