<?php

use App\Http\Controllers\AccountStatementController;
use App\Http\Controllers\AppController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MainConfigController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SchoolLapseController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [AppController::class, 'index'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/olvidar-contrasena', [AuthController::class, 'showForgotPassword']);
Route::post('/olvidar-contrasena', [AuthController::class, 'requestResetPassword']);

Route::get('/establecer-contrasena', [AuthController::class, 'showSetupPassword']);
Route::post('/establecer-contrasena', [AuthController::class, 'setupPassword']);

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [AppController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboard/graficos/annual-vs-monthly-flow/{schoolLapse?}', [AppController::class, 'annualVsMonthlyFlow']);

    Route::get('/dashboard/personal', [UserController::class, 'index'])->name('personal.index');
    Route::post('/dashboard/personal', [UserController::class, 'store'])->name('personal.store');
    Route::put('/dashboard/personal/{id}', [UserController::class, 'update'])->name('personal.update');
    Route::delete('/dashboard/personal/{id}', [UserController::class, 'destroy'])->name('personal.destroy');

    Route::get('/dashboard/matricula', [StudentController::class, 'index']);
    Route::post('/dashboard/matricula', [StudentController::class, 'store']);
    Route::put('/dashboard/matricula/{id}', [StudentController::class, 'update']);
    Route::delete('/dashboard/matricula/{studentId}', [StudentController::class, 'destroy']);
    Route::post('/dashboard/matricula/reinscribir', [StudentController::class, 'reEnrollment']);

    Route::get('/dashboard/matricula/search-representative/{ci}', [StudentController::class, 'searchRepresentativeByCI']);
    Route::get('/dashboard/matricula/search-second_representative/{ci}', [StudentController::class, 'searchSecondRepresentativeByCI']);

    Route::post('/dashboard/secciones', [SectionController::class, 'store']);
    Route::delete('/dashboard/secciones/{course_id}/{section_id}', [SectionController::class, 'destroy']);

    Route::get('/dashboard/pagos/search-student', [StudentController::class, 'searchStudent']);
    Route::get('/dashboard/pagos', [PaymentController::class, 'index']);
    Route::get('/dashboard/pagos/search-representative', [StudentController::class, 'searchRepresentative']);
    Route::post('/dashboard/pagos', [PaymentController::class, 'store']);
    Route::put('/dashboard/pagos/{id}', [PaymentController::class, 'update']);
    Route::delete('/dashboard/pagos/{id}', [PaymentController::class, 'destroy']);

    Route::get('/dashboard/estados-de-cuenta', [AccountStatementController::class, 'index']);

    Route::post('/dashboard/periodo-escolar/iniciar-proximo', [SchoolLapseController::class, 'startNext']);

    Route::get('/dashboard/configuracion', [MainConfigController::class, 'index']);
    Route::get('/dashboard/configuracion/editar-cuenta/{id}', [MainConfigController::class, 'showEditAccount']);
    Route::get('/dashboard/configuracion/crear-cuenta/{methodID}', [MainConfigController::class, 'showCreateAccount']);
    Route::post('/dashboard/configuracion/crear-cuenta', [MainConfigController::class, 'createAccount']);
    Route::put('/dashboard/configuracion/editar-cuenta/{id}', [MainConfigController::class, 'editAccount']);
    Route::delete('/dashboard/configuracion/eliminar-cuenta/{id}', [MainConfigController::class, 'deleteAccount']);

    Route::put('/dashboard/configuracion/pagos', [MainConfigController::class, 'updatePaymentConfig']);
});
