<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountRequest;
use App\Http\Requests\PaymentConfigRequest;
use App\Http\Resources\AccountPaymentResource;
use App\Models\AccountPayment;
use App\Models\Course;
use App\Models\PaymentMethod;
use App\Models\SchoolLapse;
use App\Services\LapseService;
use App\Services\MainConfigService;
use App\Services\QuotaService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MainConfigController extends Controller
{
    private MainConfigService $mainConfigService;

    public function __construct()
    {
        $this->mainConfigService = new MainConfigService;
    }

    public function index()
    {

        $methods = $this->mainConfigService->getMethods();
        $accounts = $this->mainConfigService->getAccounts();
        $prices = $this->mainConfigService->getPrices();
        $schoolLapse = SchoolLapse::where('status', 1)->first();
        $quotas = (new QuotaService)->quotasForPeriod($schoolLapse?->id);
        $lapses = $schoolLapse ? (new LapseService)->forPeriod($schoolLapse) : [];

        return inertia(
            'Dashboard/Configuracion',
            [
                'data' => [
                    'prices' => $prices,
                    'accounts' => $accounts,
                    'methods' => $methods,
                    'schoolLapse' => $schoolLapse,
                    'quotas' => $quotas,
                    'lapses' => $lapses,
                    'courses' => Course::orderBy('id')->get(['id', 'name', 'plan_de_estudio_code']),
                ],

            ]

        );
    }

    public function updateMoments(Request $request)
    {
        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.id' => ['required', 'integer'],
            'rows.*.start' => ['required', 'date'],
            'rows.*.end' => ['required', 'date'],
        ]);

        try {
            $period = SchoolLapse::where('status', 1)->first();

            if (! $period) {
                throw new Exception('No hay un periodo escolar activo.');
            }

            (new LapseService)->saveMoments($period->id, $validated['rows']);

            return back()->with([
                'status' => true,
                'message' => 'Fechas de momentos guardadas correctamente.',
            ]);
        } catch (Exception $e) {
            Log::error('Error al guardar fechas de momentos: '.$e->getMessage());

            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function closeCurrentMoment(Request $request)
    {
        try {
            $period = SchoolLapse::where('status', 1)->first();

            if (! $period) {
                throw new Exception('No hay un periodo escolar activo.');
            }

            $result = (new LapseService)->closeAndAdvance($period);

            if (! isset($result['closed'])) {
                return back()->withErrors(['message' => $result['message']]);
            }

            return back()->with([
                'status' => true,
                'message' => $result['message'],
            ]);
        } catch (Exception $e) {
            Log::error('Error al cerrar momento: '.$e->getMessage());

            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function updateQuotas(Request $request)
    {
        $validated = $request->validate([
            'assigned' => ['required', 'array'],
            'assigned.*' => ['required', 'integer', 'min:0'],
        ]);

        (new QuotaService)->saveAssigned($validated['assigned']);

        return redirect('/dashboard/configuracion')->with([
            'status' => true,
            'message' => 'Cupos actualizados correctamente.',
        ]);
    }

    public function showCreateAccount($methodID)
    {
        $fields = $this->mainConfigService->getFieldsFromMethod($methodID);
        $method = PaymentMethod::where('id', $methodID)->first();

        return inertia('Dashboard/MetodosDePago/Crear', ['data' => ['fields' => $fields, 'method' => $method]]);
    }

    public function showEditAccount($id)
    {
        $account = AccountPayment::where('id', $id)->with('method')->first();
        $accountResource = new AccountPaymentResource($account);
        $method = PaymentMethod::where('id', $account->method->id)->first();

        return inertia('Dashboard/MetodosDePago/Editar', ['data' => ['account' => $accountResource, 'method' => $method]]);
    }

    public function createAccount(AccountRequest $request)
    {

        $account = $this->mainConfigService->createAccount($request);

        return redirect('/dashboard/configuracion#account-'.$account->id);
    }

    public function editAccount(AccountRequest $request, $id)
    {
        $this->mainConfigService->updateAccount($id, $request);

        return redirect('/dashboard/configuracion#account-'.$id);
    }

    public function deleteAccount($id)
    {
        $this->mainConfigService->deleteAccount($id);

        return redirect('/dashboard/configuracion');
    }

    public function updatePaymentConfig(PaymentConfigRequest $request)
    {
        $this->mainConfigService->updatePaymentConfig($request->validated());

        return redirect('/dashboard/configuracion');
    }

    public function updateCoursePlans(Request $request)
    {
        $validated = $request->validate([
            'codes' => ['required', 'array'],
            'codes.*' => ['nullable', 'string', 'max:10', 'regex:/^\d*$/'],
        ]);

        $existing = Course::whereIn('id', array_keys($validated['codes']))->pluck('id');
        $unknown = array_diff(array_keys($validated['codes']), $existing->all());

        if ($unknown) {
            return back()->withErrors(['message' => 'La solicitud contiene cursos no válidos.']);
        }

        foreach ($validated['codes'] as $id => $code) {
            Course::where('id', $id)->update(['plan_de_estudio_code' => $code !== '' && $code !== null ? $code : null]);
        }

        return redirect('/dashboard/configuracion')->with([
            'status' => true,
            'message' => 'Códigos de plan de estudio guardados correctamente.',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
