<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\MainConfig;
use App\Models\Payment;
use App\Models\PaymentConcept;
use App\Services\MainConfigService;
use App\Services\PaymentInvoiceService;
use App\Services\PaymentService;
use App\Services\SalesBookService;
use App\Support\ErrorTranslator;
use Barryvdh\DomPDF\Facade\Pdf;
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
        $config = MainConfig::select('day_of_monthly_payment', 'grace_period', 'ame_price', 'investment_plan_price')->first();
        $concepts = PaymentConcept::active()->orderBy('name')->get();

        return inertia('Dashboard/Pagos', [
            'data' => [
                'accounts' => $accounts,
                'payments' => $result['payments'],
                'prices' => $prices,
                'total_income' => $result['total_income'],
                'concepts' => $concepts,
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

            Log::error('Error al crear pago: '.$e->getMessage());

            return redirect('/dashboard/pagos')->withErrors(['data' => ErrorTranslator::translate($e)]);
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
            Log::error('Error al eliminar pago ID '.$id.': '.$e->getMessage());

            return redirect('/dashboard/pagos')->withErrors(['data' => ErrorTranslator::translate($e)]);
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

            Log::error('Error al actualizar pago ID '.$id.': '.$e->getMessage());

            return redirect('/dashboard/pagos')->withErrors(['data' => ErrorTranslator::translate($e)]);
        }
    }

    public function libroVentas(Request $request)
    {
        $filters = $request->all();

        $columns = $request->input('columns', []);
        if (is_string($columns)) {
            $columns = array_filter(array_map('trim', explode(',', $columns)));
        }

        $format = (string) $request->input('format', 'xlsx');

        $payments = $this->paymentService->getAllForExport($filters);

        return (new SalesBookService)->export(
            $payments,
            'libro_ventas_'.now()->format('Y_m_d'),
            $filters,
            (array) $columns,
            $format
        );
    }

    public function factura(Request $request, $id)
    {
        $payment = Payment::with([
            'students.course',
            'students.section',
            'students.representative.user',
            'accountPayment.method',
            'paymentConcept',
            'balancePayments',
            'allocations.paymentConcept',
        ])->findOrFail($id);

        $repStudentId = $request->input('rep_student_id');
        $repType = $request->input('rep_type', 'legal');

        $data = (new PaymentInvoiceService)->buildData(
            $payment,
            $repStudentId ? (int) $repStudentId : null,
            (string) $repType
        );

        return Pdf::loadView('reportes.factura-pago', $data)
            ->setPaper('letter')
            ->setOption('isRemoteEnabled', true)
            ->download('factura_'.$payment->id.'.pdf');
    }
}
