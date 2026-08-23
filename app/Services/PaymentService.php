<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;

class PaymentService
{
    public function getAll($params = [], ?array $allowedStudentIds = null)
    {
        $query = Payment::query()
            ->with('students', 'accountPayment.method', 'user', 'deletedBy')
            ->when($allowedStudentIds, function ($q) use ($allowedStudentIds) {
                $q->whereHas('students', function ($query) use ($allowedStudentIds) {
                    $query->whereIn('students.id', $allowedStudentIds);
                });
            })
            ->when(isset($params['search']), function ($q) use ($params) {
                $search = $params['search'];
                $q->where(function ($query) use ($search) {
                    $query->where('reference', 'like', '%'.$search.'%')
                        ->orWhere('observations', 'like', '%'.$search.'%')
                        ->orWhereHas('user', function ($q) use ($search) {
                            $q->whereRaw("CONCAT(name, ' ', last_name) LIKE ?", ['%'.$search.'%'])
                                ->orWhere('name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('accountPayment.method', function ($q) use ($search) {
                            $q->where('name', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('students', function ($q) use ($search) {
                            $q->whereRaw("CONCAT(name, ' ', last_name) LIKE ?", ['%'.$search.'%'])
                                ->orWhere('name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%')
                                ->orWhere('ci', 'like', '%'.$search.'%')
                                ->orWhereHas('representative.user', function ($q) use ($search) {
                                    $q->whereRaw("CONCAT(name, ' ', last_name) LIKE ?", ['%'.$search.'%'])
                                        ->orWhere('name', 'like', '%'.$search.'%')
                                        ->orWhere('last_name', 'like', '%'.$search.'%')
                                        ->orWhere('ci', 'like', '%'.$search.'%');
                                });
                        });
                });
            })
            ->when(isset($params['start_date']), function ($q) use ($params) {
                $startDate = is_numeric($params['start_date'])
                    ? date('Y-m-d', $params['start_date'] / 1000)
                    : $params['start_date'];
                $q->whereDate('date', '>=', $startDate);
            })
            ->when(isset($params['end_date']), function ($q) use ($params) {
                $endDate = is_numeric($params['end_date'])
                    ? date('Y-m-d', $params['end_date'] / 1000)
                    : $params['end_date'];
                $q->whereDate('date', '<=', $endDate);
            })
            ->when(isset($params['account_payment_id']), function ($q) use ($params) {
                $accountPaymentIds = is_array($params['account_payment_id'])
                    ? $params['account_payment_id']
                    : [$params['account_payment_id']];
                $q->whereIn('account_payment_id', $accountPaymentIds);
            });

        $totalIncome = (clone $query)->where('status', '!=', 0)->sum('total_in_dolars');

        $query->orderBy('created_at', 'desc');

        $payments = $query->paginate($params['per_page'] ?? 25)->withQueryString();

        return [
            'payments' => $payments,
            'total_income' => $totalIncome,
        ];
    }

    public function create(array $data, ?array $allowedStudentIds = null): Payment
    {
        // Obtener usuario
        $userId = Auth::id() ?? 1;

        // Crear pago
        $payment = Payment::create([
            'user_id' => $userId,
            'account_payment_id' => $data['account_payment_id'],
            'date' => $data['date'],
            'total_in_dolars' => $data['total_in_dolars'],
            'total_in_bs' => $data['total_in_bs'],
            'reference' => $data['reference'] ?? null,
            'status' => 1,
            'observations' => $data['observations'] ?? null,
            'reported_date' => $data['reported_date'] ?? null,
        ]);

        // Asociar estudiantes con el pago

        $studentsData = collect($data['students']);

        $balanceService = new BalanceService;

        foreach ($studentsData as $studentData) {
            $student = Student::where('id', $studentData['id'])
                ->when($allowedStudentIds, function ($q) use ($allowedStudentIds) {
                    $q->whereIn('id', $allowedStudentIds);
                })
                ->where(function ($q) {
                    $q->where('status', '!=', 0)
                        ->orWhere('graduate', 1);
                })
                ->firstOrFail();

            $payment->students()->attach($studentData['id'], [
                'amount_in_dolars' => $studentData['amount_in_dolars'],
            ]);

            $balanceService->updateStudentBalance($payment, $student, $studentData['balances']);
        }

        $payment->load('students', 'accountPayment');

        return $payment;
    }

    public function delete($id)
    {
        $payment = Payment::findOrFail($id);

        if ((int) $payment->status === 0) {
            throw new \Exception('Este pago ya ha sido eliminado anteriormente.');
        }

        $balanceService = new BalanceService;

        foreach ($payment->students as $student) {
            $balanceService->revertStudentBalance($payment, $student);
        }

        $payment->status = 0;
        $payment->deleted_by = Auth::id();
        $payment->save();
    }

    public function update(int $id, array $data): Payment
    {
        $balanceService = new BalanceService;
        $existingPayment = Payment::findOrFail($id);

        if ((int) $existingPayment->status === 0) {
            throw new \Exception('No se puede editar un pago que ya fue eliminado.');
        }

        foreach ($existingPayment->students as $student) {
            $balanceService->revertStudentBalance($existingPayment, $student);
        }

        $existingPayment->status = 0;
        $existingPayment->deleted_by = Auth::id();
        $existingPayment->save();

        return $this->create($data);
    }
}
