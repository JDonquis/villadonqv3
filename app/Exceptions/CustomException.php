<?php

namespace App\Exceptions;

use Exception;

class CustomException extends Exception
{
    public static function badLoginData()
    {
        return new self("Datos incorrectos, vuelva intentarlo", 401);
    }
    
}