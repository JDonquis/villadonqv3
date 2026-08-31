<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Throwable;

class ErrorTranslator
{
    public static function translate(Throwable $e): string
    {
        if ($e instanceof QueryException) {
            return self::translateQueryException($e);
        }

        if ($e instanceof ValidationException) {
            return self::firstValidationMessage($e);
        }

        $message = trim($e->getMessage());

        return $message !== '' ? $message : 'Ha ocurrido un error al procesar la solicitud. Por favor, intente nuevamente.';
    }

    private static function translateQueryException(QueryException $e): string
    {
        $errorInfo = $e->errorInfo ?? [];
        $sqlState = $errorInfo[0] ?? null;
        $driverCode = (int) ($errorInfo[1] ?? 0);
        $message = $e->getMessage();

        if ($sqlState !== '23000') {
            return 'Ha ocurrido un error al procesar la solicitud. Por favor, intente nuevamente.';
        }

        if ($driverCode === 1062 || $driverCode === 19) {
            return self::duplicateMessage($message);
        }

        if ($driverCode === 1451) {
            return 'No se puede eliminar porque hay registros relacionados.';
        }

        if ($driverCode === 1452) {
            return 'No se pudo guardar: los datos de referencia no son válidos.';
        }

        return 'No se pudo completar la operación. Verifique los datos ingresados.';
    }

    private static function duplicateMessage(string $message): string
    {
        $key = self::extractKey($message);

        return match ($key) {
            'users_email_unique' => 'El correo electrónico del representante ya está registrado. Verifique los datos.',
            'users_ci_unique' => 'La cédula del representante ya está registrada.',
            'students_ci_unique' => 'La cédula del estudiante ya está registrada.',
            'users.email' => 'El correo electrónico del representante ya está registrado. Verifique los datos.',
            'users.ci' => 'La cédula del representante ya está registrada.',
            'students.ci' => 'La cédula del estudiante ya está registrada.',
            default => 'Ya existe un registro con los datos ingresados.',
        };
    }

    private static function extractKey(string $message): ?string
    {
        if (preg_match("/for key '([^']+)'/", $message, $matches)) {
            return $matches[1];
        }

        if (preg_match('/UNIQUE constraint failed: ([^,]+)/', $message, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private static function firstValidationMessage(ValidationException $e): string
    {
        $errors = $e->errors();

        if (empty($errors)) {
            return 'Los datos ingresados no son válidos. Verifique e intente nuevamente.';
        }

        $first = reset($errors);

        return is_array($first) ? (string) ($first[0] ?? 'Los datos ingresados no son válidos. Verifique e intente nuevamente.') : (string) $first;
    }
}
