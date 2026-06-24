<?php  

namespace App\Services;

use App\Exceptions\CustomException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginService
{	
	private User $userModel;

    public function __construct()
    {
        $this->userModel = new User;
    }

	public function tryLoginOrFail($dataUser)
	{
		if(!Auth::attempt($dataUser))
			return false;
		
		return true;
	}

	public function generateToken($dataUser)
	{
		$user = $this->userModel->findForCi($dataUser['ci']);

		$permissions = $this->userModel->getPermissions($user);

		$token = $user->createToken("permissionsToken",$permissions)->plainTextToken;

		return $token;

	}

	public function tryChangePassword($data)
	{	
		$user = Auth::user();

		if (!Hash::check($data['oldPassword'], $user->password)) 
			throw new CustomException('Contraseña actual incorrecta',422);   

		if(!$data['newPassword'] == $data['confirmPassword'])
			throw new CustomException('La nueva contraseña no coincide con la confirmación',422);   

		$user->password = Hash::make($data['newPassword']);
    	$user->save();

	}


}
