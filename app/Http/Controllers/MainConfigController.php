<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use App\Models\AccountPayment;
use App\Services\MainConfigService;
use App\Http\Requests\AccountRequest;
use App\Http\Requests\PaymentConfigRequest;
use App\Http\Resources\AccountPaymentResource;
use App\Models\SchoolLapse;

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
        $accounts  = $this->mainConfigService->getAccounts();
        $prices = $this->mainConfigService->getPrices();
        $schoolLapse = SchoolLapse::where('status', 1)->first();
        return inertia(
            'Dashboard/Configuracion',
            [
                'data' =>
                [
                    'prices' => $prices,
                    'accounts' => $accounts,
                    'methods' => $methods,
                    'schoolLapse' => $schoolLapse,
                ]

            ]

        );
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
        return redirect('/dashboard/configuracion#account-' . $account->id);
    }

    public function editAccount(AccountRequest $request, $id)
    {
        $this->mainConfigService->updateAccount($id, $request);
        return redirect('/dashboard/configuracion#account-' . $id);
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
