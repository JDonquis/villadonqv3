<?php

namespace App\Http\Controllers;

use App\Support\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->input('filter') === 'unread' ? 'unread' : 'all';
        $user = $request->user();

        $query = $user->notifications()->latest();

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }

        $paginator = $query->paginate(20)->withQueryString();

        return inertia('Dashboard/Notificaciones', [
            'items' => collect($paginator->items())
                ->map(fn ($notification) => NotificationPresenter::make($notification))
                ->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'next_page_url' => $paginator->nextPageUrl(),
            ],
            'filter' => $filter,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return $this->respond($request);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->respond($request);
    }

    public function destroy(Request $request, string $id)
    {
        $request->user()->notifications()->whereKey($id)->delete();

        return $this->respond($request);
    }

    public function destroyAll(Request $request)
    {
        $request->user()->notifications()->delete();

        return $this->respond($request);
    }

    public function storePushSubscription(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['nullable', 'string'],
            'keys.auth' => ['nullable', 'string'],
            'content_encoding' => ['nullable', 'string'],
        ]);

        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'] ?? null,
            $data['keys']['auth'] ?? null,
            $data['content_encoding'] ?? null
        );

        return response()->json(['ok' => true]);
    }

    public function destroyPushSubscription(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->json(['ok' => true]);
    }

    private function respond(Request $request)
    {
        $payload = [
            'ok' => true,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ];

        if ($request->expectsJson()) {
            return new JsonResponse($payload);
        }

        return back();
    }
}
