<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable =
        [
            'type_user_id',
            'ci',
            'name',
            'last_name',
            'email',
            'password',
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
