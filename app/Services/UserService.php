<?php

namespace App\Services;

use App\Enums\UserTypeEnum;
use App\Http\Resources\UserResource;
use App\Mail\PasswordSetupMail;
use App\Models\PasswordSetupToken;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class UserService
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User;
    }

    public function getUsers($filters)
    {
        $query = $this->userModel->query();

        $query->where('type_user_id', UserTypeEnum::Administrator->value);

        $query->when(isset($filters['search']), function ($q) use ($filters) {
            $q->where('name', 'like', '%'.$filters['search'].'%')
                ->orWhere('last_name', 'like', '%'.$filters['search'].'%')
                ->orWhere('ci', 'like', '%'.$filters['search'].'%')
                ->orWhere('email', 'like', '%'.$filters['search'].'%');
        });

        $users = $query->orderBy('id', 'desc')->get();

        return $users;
    }

    // public function createUser($dataToCreateUser)
    // {

    //     // $password = $this->userModel->generateNewRandomPassword();
    //     $entity = $this->hierarchyModel->where('code',$dataToCreateUser['entity_code'])->first();
    //     $dataToCreateUser['username'] = $dataToCreateUser['ci'];

    //     $search = $dataToCreateUser['name'] . ' ' . $dataToCreateUser['last_name'] . ' ' . $entity->name . ' ' . $dataToCreateUser['charge'] . ' ' . $dataToCreateUser['username'] . ' ' . $dataToCreateUser['ci'] . ' ' . $dataToCreateUser['phone_number'] . ' ' . $dataToCreateUser['address'] . ' ' . $dataToCreateUser['email'];

    //     $password = $dataToCreateUser['ci'];
    //     $dataToCreateUser['password'] = $password;
    //     $dataToCreateUser['search'] = $search;

    //     $this->userModel->fill($dataToCreateUser);
    //     $this->userModel->save();
    //     $this->userModel->modules()->attach($dataToCreateUser['permissions']);
    //     $this->userModel->fresh();

    //     $userWithFormat = new UserResource($this->userModel);

    //     //Envio de correo
    //     //Username  = ostisaludfalcon@gmail.com
    //     //Password = Ostifalcon01

    // 	return ['message' => 'Creado Exitosamente', 'newUser' => $userWithFormat];
    // }

    // public function updateUser($dataToUpdateUser,$user)
    // {

    //     $entity = $this->hierarchyModel->where('code',$dataToUpdateUser['entity_code'])->first();
    //     $dataToUpdateUser['username'] = $dataToUpdateUser['ci'];

    //     $search = $dataToUpdateUser['name'] . ' ' . $dataToUpdateUser['last_name'] . ' ' . $entity->name . ' ' . $dataToUpdateUser['charge'] . ' ' . $dataToUpdateUser['username'] . ' ' . $dataToUpdateUser['ci'] . ' ' . $dataToUpdateUser['phone_number'] . ' ' . $dataToUpdateUser['address'] . ' ' . $dataToUpdateUser['email'];

    //     $dataToUpdateUser['search'] = $search;

    //     $permissions = $dataToUpdateUser['permissions'];
    //     $permissionsFormat = $this->transformToStringPermissions($permissions);

    //     $permissionsFormat[] = $user->entity_code == '1'?'origin':'branch';

    //     $user->fill($dataToUpdateUser);
    //     $user->save();
    //     $user->modules()->sync($permissions);

    //     $user->tokens->each(function ($token) use ($permissionsFormat)
    //     {
    //         $newAbilities = $permissionsFormat;
    //         $token->abilities = $newAbilities;
    //         $token->save();
    //     });

    //     $user->fresh();

    //     $userWithFormat = new UserResource($user);
    //     return ['message' => 'Actualizado Exitosamente', 'updatedUser' => $userWithFormat];

    // }

    // public function deleteUser($id)
    // {
    //     $this->userModel->verifiIfExistsID($id);
    //     $user = $this->userModel->find($id);
    //     $user->user_id = $user->id;
    //     $this->userDeletedModel->fill($user->toArray());
    //     $this->userDeletedModel->save();

    //     $user->delete();

    //     return ['message' => 'Usuario eliminado exitosamente'];
    // }

    // public function isCurrentUserDeletingIdMatch($id)
    // {
    //     $userID = Auth::id();

    //     if($userID == $id)
    //         throw new GeneralExceptions('No puede eliminarse asi mismo',500);

    // }

    public function getPermissions($id)
    {
        $user = $this->userModel->where('id', $id)->with('modules')->first();

        return $user->modules->toArray();
    }

    public function formatToPermissions($permissionsArray)
    {
        if (count($permissionsArray) == 0) {
            return [];
        }

        $format = [];
        foreach ($permissionsArray as $module) {
            $format[$module['id']] = $module['name'];
        }
        $format = json_decode(json_encode($format));

        return $format;
    }

    public function createUser(array $data): User
    {
        $user = new User;
        $user->fill($data);
        $user->save();

        return $user;
    }

    public function updateUser(User $user, array $data): User
    {
        $user->fill($data);
        $user->save();

        return $user;
    }

    public function deleteUser(User $user): void
    {
        $user->delete();
    }

    public function getUserById(int $id): ?User
    {
        return User::find($id);
    }

    public function sendPasswordSetupEmail(User $user): string
    {
        $setupToken = PasswordSetupToken::generateForUser($user, 12, 'setup');

        $setupUrl = url('/establecer-contrasena?token='.$setupToken->token);

        Mail::to($user->email)->send(new PasswordSetupMail($user, $setupUrl));

        return $setupToken->token;
    }

    public function sendPasswordResetEmail(User $user): string
    {
        $resetToken = PasswordSetupToken::generateForUser($user, 12, 'reset');

        $resetUrl = url('/establecer-contrasena?token='.$resetToken->token);

        Mail::to($user->email)->send(new PasswordSetupMail($user, $resetUrl, 'reset'));

        return $resetToken->token;
    }

    private function transformToStringPermissions($permissions)
    {
        $result = [];
        foreach ($permissions as $permission) {
            $result[] = strval($permission);
        }

        return $result;
    }
}
