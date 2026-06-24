<?php

namespace App\Providers;

use App\Events\ReEnrollEvent;
use App\Events\StudentCreated;
use App\Events\StudentUpdated;
use App\Events\UpdateMonthlyPaymentEvent;
use App\Listeners\ChangeDebtsForStudents;
use App\Listeners\GenerateBalance;
use App\Listeners\GenerateInscription;
use App\Listeners\TakeQuota;
use App\Listeners\UpdateStudentExemptionBalance;
use App\Listeners\UpdateTakeQuota;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        StudentCreated::class => [
            TakeQuota::class,
            GenerateInscription::class,
            GenerateBalance::class,
        ],
        StudentUpdated::class => [
            UpdateTakeQuota::class,
            UpdateStudentExemptionBalance::class,
        ],
        ReEnrollEvent::class => [
            TakeQuota::class,
            GenerateInscription::class,
            GenerateBalance::class,
        ],
        UpdateMonthlyPaymentEvent::class => [
            ChangeDebtsForStudents::class
        ],


    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
