<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationSmokeTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $suffix): User
    {
        return User::create([
            'type_user_id' => 1,
            'ci' => 'TESTNOTIF'.$suffix,
            'name' => 'Notif',
            'last_name' => 'Test',
            'email' => 'notif'.$suffix.'@example.test',
            'password' => bcrypt('secret'),
        ]);
    }

    private function notificationFor(User $user): string
    {
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => AppNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'type' => 'test',
                'title' => 'Título de prueba',
                'body' => 'Cuerpo de prueba',
                'url' => '/dashboard',
                'icon' => 'mdi:bell',
                'severity' => 'info',
                'meta' => [],
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_notification_center_and_read_delete_actions(): void
    {
        $user = $this->user('a');
        $id = $this->notificationFor($user);

        $this->actingAs($user)
            ->get('/dashboard/notificaciones')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Notificaciones')
                ->where('unread_count', 1)
                ->has('items', 1)
                ->where('items.0.title', 'Título de prueba')
                ->where('notification_state.unread_count', 1));

        $this->actingAs($user)
            ->post("/dashboard/notificaciones/{$id}/leer")
            ->assertRedirect();

        $this->assertNotNull(DB::table('notifications')->where('id', $id)->value('read_at'));

        $this->actingAs($user)
            ->delete("/dashboard/notificaciones/{$id}")
            ->assertRedirect();

        $this->assertNull(DB::table('notifications')->where('id', $id)->first());
    }

    public function test_private_notification_channel_authorization(): void
    {
        $user = $this->user('d');
        $other = $this->user('e');

        $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-App.Models.User.'.$user->id,
            ])
            ->assertOk();

        $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-App.Models.User.'.$other->id,
            ])
            ->assertForbidden();
    }

    public function test_push_subscription_can_be_stored_and_removed(): void
    {
        $user = $this->user('f');
        $endpoint = 'https://push.example.test/subscription/123';

        $this->actingAs($user)
            ->postJson('/dashboard/push/subscribe', [
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => 'public-key',
                    'auth' => 'auth-token',
                ],
                'content_encoding' => 'aes128gcm',
            ])
            ->assertOk();

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_type' => User::class,
            'subscribable_id' => $user->id,
            'endpoint' => $endpoint,
        ]);

        $this->actingAs($user)
            ->deleteJson('/dashboard/push/subscribe', ['endpoint' => $endpoint])
            ->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint' => $endpoint,
        ]);
    }

    public function test_user_cannot_delete_another_users_notification(): void
    {
        $owner = $this->user('b');
        $other = $this->user('c');
        $id = $this->notificationFor($owner);

        $this->actingAs($other)
            ->delete("/dashboard/notificaciones/{$id}")
            ->assertRedirect();

        $this->assertNotNull(DB::table('notifications')->where('id', $id)->first());
    }
}
