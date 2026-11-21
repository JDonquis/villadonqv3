<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasPushSubscriptions, Notifiable, SoftDeletes;

    protected $fillable =
        [
            'type_user_id',
            'ci',
            'name',
            'last_name',
            'email',
            'password',
            'provider',
            'provider_id',
            'phone_number',
            'phone_number2',
            'address',
            'state',
            'city',
            'photo',
            'email_verified_status',
            'is_admin',
        ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'user_modules', 'user_id', 'module_id');
    }

    public function matters()
    {
        return $this->belongsToMany(Matter::class, 'teacher_matter', 'teacher_id', 'matter_id');
    }

    public function evaluationPlans()
    {
        return $this->hasMany(EvaluationPlan::class);
    }

    public function isTeacher(): bool
    {
        return (int) $this->type_user_id === UserTypeEnum::Teacher->value;
    }

    public function isRepresentative(): bool
    {
        return (int) $this->type_user_id === UserTypeEnum::Representative->value;
    }

    /**
     * El acceso administrativo es un permiso adicional: un representante o
     * profesor conserva su rol original y puede recibir módulos del panel.
     */
    public function hasAdminAccess(): bool
    {
        if ((int) $this->type_user_id === UserTypeEnum::Administrator->value) {
            return true;
        }

        if ((bool) $this->is_admin) {
            return true;
        }

        if ($this->relationLoaded('modules')) {
            return $this->modules->isNotEmpty();
        }

        return $this->modules()->exists();
    }

    public function representative()
    {
        return $this->hasMany(Representative::class, 'user_id', 'id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentHistories()
    {
        return $this->hasMany(PaymentHistory::class);
    }

    public function findForCi($ci)
    {
        return self::where('ci', $ci)->with('modules')->first();
    }

    public static function reclaimDeletedIdentity(?string $email, ?string $ci, ?int $exceptUserId = null): void
    {
        if (blank($email) && blank($ci)) {
            return;
        }

        self::onlyTrashed()
            ->when($exceptUserId, fn ($query) => $query->where('id', '!=', $exceptUserId))
            ->where(function ($query) use ($email, $ci) {
                if (! blank($email)) {
                    $query->orWhere('email', $email);
                }

                if (! blank($ci)) {
                    $query->orWhere('ci', $ci);
                }
            })
            ->get()
            ->each(fn (self $user) => $user->tombstoneIdentity());
    }

    public function tombstoneIdentity(): void
    {
        $this->email = 'deleted+'.$this->id.'@deleted.local';
        $this->ci = 'del'.$this->id.substr(sha1((string) $this->ci), 0, 6);

        if (! is_null($this->provider) || ! is_null($this->provider_id)) {
            $this->provider = null;
            $this->provider_id = null;
        }

        $this->saveQuietly();
    }

    public function getPermissions($user)
    {
        $permissions = [];

        $modules = $user->modules;

        foreach ($modules as $module) {
            $permissions[] = strval($module->id);
        }

        $rol = null;

        switch ($user->type_user_id) {
            case 1:
                $rol = 'Administrador';
                break;
            case 2:
                $rol = 'Representante';
                break;

            case 3:
                $rol = 'Profesor';
                break;

            default:
                $rol = 'Representante';
                break;
        }

        $permissions[] = $rol;

        return $permissions;
    }
}
