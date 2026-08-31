<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethodEnum;
use App\Http\Requests\StorePaymentRequest;
use App\Models\BalanceStudent;
use App\Models\MainConfig;
use App\Services\MainConfigService;
use App\Services\PaymentService;
use App\Services\RepresentativeService;
use App\Support\ErrorTranslator;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class RepresentativePaymentController extends Controller
{
    private MainConfigService $mainConfigService;

    private PaymentService $paymentService;

    private RepresentativeService $representativeService;

    public function __construct()
    {
        $this->mainConfigService = new MainConfigService;
        $this->paymentService = new PaymentService;
        $this->representativeService = new RepresentativeService;
    }

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $allowedStudentIds = $this->representativeService->getStudents($user)->pluck('id')->all();

        $prices = $this->mainConfigService->getPrices();
        $accounts = $this->mainConfigService->getAccountsByMethods([
            PaymentMethodEnum::PagoMovil->value,
            PaymentMethodEnum::Transferencia->value,
        ]);
        $result = $this->paymentService->getAll($request->all(), $allowedStudentIds);
        $config = MainConfig::select('day_of_monthly_payment', 'grace_period')->first();

        return inertia('Dashboard/MisPagos', [
            'data' => [
                'students' => $this->representativeService->misPagos($user)['students'],
                'accounts' => $accounts,
                'payments' => $result['payments'],
                'prices' => $prices,
                'total_income' => $result['total_income'],
            ],
            'config' => $config,
        ]);
    }

    public function store(StorePaymentRequest $request)
    {
        $user = Auth::user();
        $allowedStudentIds = $this->representativeService->getStudents($user)->pluck('id')->all();

        $validated = $request->validated();

        $submittedStudentIds = collect($validated['students'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($submittedStudentIds->diff($allowedStudentIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'students' => 'No puedes registrar pagos para estudiantes que no te pertenecen.',
            ]);
        }

        foreach ($validated['students'] as $studentData) {
            $balanceIds = collect($studentData['balances'])
                ->pluck('id')
                ->map(fn ($id) => (int) $id);

            $ownedBalanceIds = BalanceStudent::where('student_id', $studentData['id'])
                ->whereIn('id', $balanceIds)
                ->pluck('id');

            if ($ownedBalanceIds->count() !== $balanceIds->count()) {
                throw ValidationException::withMessages([
                    'students' => 'Uno o más balances no pertenecen al estudiante seleccionado.',
                ]);
            }
        }

        DB::beginTransaction();

        try {
            $this->paymentService->create($validated, $allowedStudentIds);

            DB::commit();

            return redirect('/dashboard/mis-pagos');
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error al crear pago (representante): '.$e->getMessage());

            return redirect('/dashboard/mis-pagos')->withErrors(['message' => ErrorTranslator::translate($e)]);
        }
    }
}
