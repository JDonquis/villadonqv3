<?php

namespace App\Support;

class NotificationPresenter
{
    public static function make($notification): array
    {
        $data = is_array($notification->data)
            ? $notification->data
            : (json_decode((string) $notification->data, true) ?: []);

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? 'info',
            'title' => $data['title'] ?? 'Notificación',
            'body' => $data['body'] ?? '',
            'url' => $data['url'] ?? null,
            'icon' => $data['icon'] ?? 'mdi:bell-outline',
            'severity' => $data['severity'] ?? 'info',
            'meta' => $data['meta'] ?? [],
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'created_at_label' => $notification->created_at?->locale('es')->diffForHumans(),
            'is_read' => $notification->read_at !== null,
        ];
    }
}
