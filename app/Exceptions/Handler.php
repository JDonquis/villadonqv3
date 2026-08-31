<?php

namespace App\Exceptions;

use App\Support\ErrorTranslator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Psr\Log\LogLevel;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<Throwable>, LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->renderable(function (CustomException $e, Request $request) {

            return response()->json(['message' => $e->getMessage()], $e->getCode());

        });

        $this->renderable(function (QueryException $e, Request $request) {
            if ($request->header('X-Inertia')) {
                Log::error('Excepción de base de datos: '.$e->getMessage());

                return redirect()->back()->withErrors(['message' => ErrorTranslator::translate($e)]);
            }

            return null;
        });
    }
}
