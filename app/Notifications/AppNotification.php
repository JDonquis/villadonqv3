<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{type:string,title:string,body:string,icon?:string,severity?:string,meta?:array}  $payload
     */
    public function __construct(
        public array $payload,
        public ?string $url = null
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (
            class_exists(WebPushChannel::class)
            && method_exists($notifiable, 'pushSubscriptions')
        ) {
            $channels[] = WebPushChannel::class;
        }

        $channels[] = 'broadcast';

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return array_merge($this->payload, [
            'url' => $this->url ?? ($this->payload['url'] ?? null),
            'created_at' => now()->toIso8601String(),
        ]);
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastType(): string
    {
        return $this->payload['type'] ?? 'app-notification';
    }

    public function toWebPush(object $notifiable, Notification $notification)
    {
        $message = (new WebPushMessage)
            ->title($this->payload['title'] ?? 'VillaDonq')
            ->body($this->payload['body'] ?? '')
            ->icon('/img/icon-192x192.png')
            ->badge('/img/icon-144x144.png')
            ->tag($this->payload['type'] ?? 'villadonq')
            ->data([
                'url' => $this->url ?? ($this->payload['url'] ?? '/dashboard'),
            ]);

        if (! empty($this->payload['meta'])) {
            $message->data(array_merge(
                ['url' => $this->url ?? ($this->payload['url'] ?? '/dashboard')],
                $this->payload['meta']
            ));
        }

        return $message;
    }
}
