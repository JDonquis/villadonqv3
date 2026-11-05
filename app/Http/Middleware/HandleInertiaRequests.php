<?php

namespace App\Http\Middleware;

use App\Models\Representative;
use App\Models\Student;
use App\Support\NotificationPresenter;
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
        $user = auth()->user();

        $hasRepStudents = false;
        if ($user) {
            $representative = Representative::where('user_id', $user->id)->first();

            $hasRepStudents = $representative && Student::where('representative_id', $representative->id)
                ->where('status', '!=', 0)
                ->exists();
        }

        return array_merge(parent::share($request), [

            'auth' => [
                'id' => $user->id ?? null,
                'name' => $user->name ?? null,
                'last_name' => $user->last_name ?? null,
                'is_admin' => $user->is_admin ?? null,
                'type_user_id' => $user->type_user_id ?? null,
                'photo' => $user->photo ?? null,
                'has_rep_students' => $hasRepStudents,
                'modules' => $user ? $user->modules()->pluck('slug')->all() : [],
            ],
            'flash' => [
                'import' => $request->session()->pull('import_summary'),
                'payment' => $request->session()->pull('payment_flash'),
            ],
            'notification_state' => $user ? [
                'unread_count' => $user->unreadNotifications()->count(),
                'items' => $user->notifications()
                    ->latest()
                    ->limit(15)
                    ->get()
                    ->map(fn ($notification) => NotificationPresenter::make($notification))
                    ->values()
                    ->all(),
            ] : [
                'unread_count' => 0,
                'items' => [],
            ],
        ]);
    }
}
