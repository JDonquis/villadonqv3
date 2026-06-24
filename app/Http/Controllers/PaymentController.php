<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\MainConfig;
use App\Services\MainConfigService;
use App\Services\PaymentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private MainConfigService $mainConfigService;

    private PaymentService $paymentService;

    public function __construct()
    {

        $this->mainConfigService = new MainConfigService;
        $this->paymentService = new PaymentService;
    }

    public function index(Request $request)
    {
        $prices = $this->mainConfigService->getPrices();
        $accounts = $this->mainConfigService->getAccounts();
        $result = $this->paymentService->getAll($request->all());
        $config = MainConfig::select('day_of_monthly_payment', 'grace_period')->first();

        return inertia('Dashboard/Pagos', [
            'data' => [
                'accounts' => $accounts,
                'payments' => $result['payments'],
                'prices' => $prices,
                'total_income' => $result['total_income'],
            ],
            'config' => $config,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePaymentRequest $request)
    {
        DB::beginTransaction();

        try {

            $this->paymentService->create($request->validated());

            DB::commit();

            return redirect('/dashboard/pagos');
        } catch (Exception $e) {

            DB::rollback();

            Log::error('Error al crear pago: ' . $e->getMessage());

            return redirect('/dashboard/pagos')->withErrors(['message' => $e->getMessage() ?? 'Ha ocurrido un error al crear el pago. Por favor, intente más tarde.']);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {

            $this->paymentService->delete($id);

            return redirect('/dashboard/pagos');
        } catch (Exception $e) {
            Log::error('Error al eliminar pago ID ' . $id . ': ' . $e->getMessage());

            return redirect('/dashboard/pagos')->withErrors(['data' => $e->getMessage()]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StorePaymentRequest $request, $id)
    {
        DB::beginTransaction();

        try {

            $this->paymentService->update($id, $request->validated());

            DB::commit();

            return redirect('/dashboard/pagos');
        } catch (Exception $e) {

            DB::rollback();

            Log::error('Error al actualizar pago ID ' . $id . ': ' . $e->getMessage());

            return redirect('/dashboard/pagos')->withErrors(['data' => $e->getMessage()]);
        }
    }
}
