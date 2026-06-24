<?php

namespace App\Services;

use App\Enums\PaymentMethodEnum;
use App\Events\StudentCreated;
use App\Events\StudentUpdated;
use App\Events\UpdateMonthlyPaymentEvent;
use App\Http\Resources\AccountPaymentCollection;
use App\Http\Resources\StudentCollection;
use App\Http\Resources\StudentResource;
use App\Http\Resources\UserResource;
use App\Models\AccountPayment;
use App\Models\Activity;
use App\Models\CourseSection;
use App\Models\MainConfig;
use App\Models\PaymentMethod;
use App\Models\Representative;
use App\Models\Student;
use App\Models\User;
use DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class MainConfigService
{
    private MainConfig $mainConfigModel;


    public function __construct()
    {
        $this->mainConfigModel = MainConfig::first();
    }

    public function getAccounts()
    {
        $accounts = AccountPayment::where('status', 1)->with('method')->get();

        return new AccountPaymentCollection($accounts);
    }

    public function getAccountsWhereId($id)
    {
        $accounts = AccountPayment::where('status', 1)->where('payment_method_id', $id)->with('method')->get();

        return new AccountPaymentCollection($accounts);
    }

    public function getMethods()
    {
        $methods = PaymentMethod::get();

        return $methods;
    }

    public function getConfigData()
    {
        return $this->mainConfigModel->first();
    }

    public function getPrices()
    {
        return [
            'regular_inscription_price' => $this->mainConfigModel->regular_inscription_price,
            'new_inscription_price' => $this->mainConfigModel->new_inscription_price,
            'monthly_payment' => $this->mainConfigModel->monthly_payment,
            'ame_price' => $this->mainConfigModel->ame_price,
            'investment_plan_price' => $this->mainConfigModel->investment_plan_price,
            'day_of_monthly_payment' => $this->mainConfigModel->day_of_monthly_payment,
            'grace_period' => $this->mainConfigModel->grace_period,
            'payment_carton_price' => $this->mainConfigModel->payment_carton_price,
        ];
    }

    public function updatePaymentConfig($data)
    {
        $oldPrice = $this->mainConfigModel->monthly_payment;
        $this->mainConfigModel->update($data);

        if ($data['monthly_payment'] != $oldPrice) {
            event(new UpdateMonthlyPaymentEvent($this->mainConfigModel->monthly_payment));
        }
    }

    public function createAccount($request)
    {
        return AccountPayment::create($request->all());
    }

    public function updateAccount($id, $request)
    {
        $account = AccountPayment::find($id);

        $account->update($request->all());

        $account->touch();

        return 0;
    }

    public function deleteAccount($id)
    {
        AccountPayment::where('id', $id)->update(['status' => 2]);
        return 0;
    }

    public function getFieldsFromMethod($methodID)
    {
        $method = PaymentMethodEnum::from($methodID);

        return match ($method) {
            PaymentMethodEnum::Efectivo => ['cash_currency'],
            PaymentMethodEnum::PagoMovil => ['ci', 'phone_number', 'bank'],
            PaymentMethodEnum::Transferencia => ['account_number', 'person_name', 'ci', 'phone_number', 'bank'],
            PaymentMethodEnum::Zelle => ['username', 'email'],
            PaymentMethodEnum::Binance => ['email'],
            PaymentMethodEnum::PuntoDeVenta => ['bank', 'comision'],
            default => null,
            //a
        };
    }
}
