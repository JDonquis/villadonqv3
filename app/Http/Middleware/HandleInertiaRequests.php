<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [

            'auth' => [
                'name' => auth()->user()->name ?? null,
                'last_name' => auth()->user()->last_name ?? null,
                'is_admin' => auth()->user()->is_admin ?? null,
                'type_user_id' => auth()->user()->type_user_id ?? null,
                'photo' => auth()->user()->photo ?? null,
            ],
        ]);
    }
}
