<?php

namespace App\Services;

use App\Enums\BalanceStudentStatusEnum;
use App\Models\Payment;
use App\Models\PaymentConcept;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\StudentChargePayment;
use Illuminate\Support\Facades\Auth;

class PaymentService
{
    private const CHARGE_TYPES = [
        StudentCharge::TYPE_AME,
        StudentCharge::TYPE_INVESTMENT_PLAN,
    ];

    public function getAll($params = [], ?array $allowedStudentIds = null)
    {
        $query = Payment::query()
            ->with(
                'students.course',
                'students.section',
                'students.representative.user',
                'accountPayment.method',
                'user',
                'deletedBy',
                'paymentConcept'
            )
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
                        ->orWhereHas('paymentConcept', function ($q) use ($search) {
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
            })
            ->when(isset($params['payment_concept_id']), function ($q) use ($params) {
                $conceptIds = is_array($params['payment_concept_id'])
                    ? $params['payment_concept_id']
                    : [$params['payment_concept_id']];
                $q->where(function ($query) use ($conceptIds) {
                    $regular = false;
                    $ids = [];
                    foreach ($conceptIds as $id) {
                        if (in_array((string) $id, ['regular', '0'], true)) {
                            $regular = true;
                        } else {
                            $ids[] = $id;
                        }
                    }

                    if ($regular && ! $ids) {
                        $query->whereNull('payment_concept_id');
                    } elseif ($ids && ! $regular) {
                        $query->whereIn('payment_concept_id', $ids);
                    } else {
                        $query->where(function ($sub) use ($ids) {
                            $sub->whereNull('payment_concept_id')
                                ->orWhereIn('payment_concept_id', $ids);
                        });
                    }
                });
            })
            ->when(! empty($params['month']), function ($q) use ($params) {
                $month = $params['month'];

                if ($month === 'inscription') {
                    $q->whereHas('balancePayments', function ($sub) {
                        $sub->where('is_inscription', true);
                    });

                    return;
                }

                $validMonths = [
                    'september',
                    'october',
                    'november',
                    'december',
                    'january',
                    'february',
                    'march',
                    'april',
                    'may',
                    'june',
                    'july',
                    'august',
                ];

                if (in_array($month, $validMonths, true)) {
                    $q->whereHas('balancePayments', function ($sub) use ($month) {
                        $sub->where('month', $month);
                    });
                }
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

        $exchangeRate = isset($data['exchange_rate']) && $data['exchange_rate'] !== ''
            ? (float) $data['exchange_rate']
            : (
                (! empty($data['total_in_dolars']) && (float) $data['total_in_dolars'] > 0 && ! empty($data['total_in_bs']))
                    ? ((float) $data['total_in_bs'] / (float) $data['total_in_dolars'])
                    : null
            );

        // Crear pago
        $payment = Payment::create([
            'user_id' => $userId,
            'account_payment_id' => $data['account_payment_id'],
            'payment_concept_id' => ! empty($data['payment_concept_id']) ? $data['payment_concept_id'] : null,
            'date' => $data['date'],
            'total_in_dolars' => $data['total_in_dolars'],
            'total_in_bs' => $data['total_in_bs'],
            'exchange_rate' => $exchangeRate,
            'reference' => $data['reference'] ?? null,
            'status' => 1,
            'observations' => $data['observations'] ?? null,
            'reported_date' => $data['reported_date'] ?? null,
        ]);

        // Asociar estudiantes con el pago

        $studentsData = collect($data['students']);

        $hasConcept = ! empty($data['payment_concept_id']);

        $concept = $hasConcept ? PaymentConcept::find($data['payment_concept_id']) : null;
        $chargeType = $concept?->type;
        $isChargePayment = in_array($chargeType, self::CHARGE_TYPES, true);

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

            if ($isChargePayment) {
                $this->applyToStudentCharge($payment, $student, $studentData['amount_in_dolars'], $chargeType);
            } elseif (! $hasConcept) {
                $balanceService->updateStudentBalance($payment, $student, $studentData['balances']);
            }
        }

        $payment->load('students', 'accountPayment', 'paymentConcept');

        return $payment;
    }

    /**
     * Registra un pago de conceptos separados (AME / Plan de inversión) para el representante.
     * Crea un Payment por cada tipo presente en los items, sin tocar el balance del estudiante.
     */
    public function createForStudentCharges(array $data, ?array $allowedStudentIds = null): array
    {
        $userId = Auth::id() ?? 1;

        $items = collect($data['items'])
            ->filter(fn ($item) => in_array($item['type'] ?? null, self::CHARGE_TYPES, true))
            ->groupBy('type');

        $payments = [];

        foreach ($items as $type => $typeItems) {
            $concept = PaymentConcept::ofType($type)->first();

            $totalDolars = (float) $typeItems->sum('amount_in_dolars');
            $totalBs = (float) $typeItems->sum(fn ($item) => $item['amount_in_bs'] ?? 0);

            $payment = Payment::create([
                'user_id' => $userId,
                'account_payment_id' => $data['account_payment_id'],
                'payment_concept_id' => $concept?->id,
                'date' => $data['date'],
                'total_in_dolars' => $totalDolars,
                'total_in_bs' => $totalBs,
                'reference' => $data['reference'] ?? null,
                'status' => 1,
                'observations' => $data['observations'] ?? null,
                'reported_date' => $data['reported_date'] ?? null,
            ]);

            foreach ($typeItems as $item) {
                $student = Student::where('id', $item['student_id'])
                    ->when($allowedStudentIds, function ($q) use ($allowedStudentIds) {
                        $q->whereIn('id', $allowedStudentIds);
                    })
                    ->where(function ($q) {
                        $q->where('status', '!=', 0)
                            ->orWhere('graduate', 1);
                    })
                    ->firstOrFail();

                $payment->students()->attach($student->id, [
                    'amount_in_dolars' => $item['amount_in_dolars'],
                ]);

                $this->applyToStudentCharge($payment, $student, $item['amount_in_dolars'], $type);
            }

            $payments[] = $payment->load('students', 'accountPayment', 'paymentConcept');
        }

        return $payments;
    }

    /**
     * Aplica un monto al cargo separado (AME / Plan) del estudiante.
     */
    private function applyToStudentCharge(Payment $payment, Student $student, $amount, string $type): void
    {
        $remainingAmount = (float) $amount;

        if ($remainingAmount <= 0) {
            return;
        }

        $charges = StudentCharge::where('student_id', $student->id)
            ->where('type', $type)
            ->where('status', '!=', BalanceStudentStatusEnum::Paid->value)
            ->orderBy('school_lapse_id')
            ->get();

        foreach ($charges as $charge) {
            if ($remainingAmount <= 0) {
                break;
            }

            $debt = $charge->remaining();

            if ($debt <= 0) {
                continue;
            }

            $applied = min($remainingAmount, $debt);

            StudentChargePayment::create([
                'student_charge_id' => $charge->id,
                'payment_id' => $payment->id,
                'amount' => $applied,
            ]);

            $charge->paid_amount = round((float) $charge->paid_amount + $applied, 2);
            $charge->status = $charge->isPaid()
                ? BalanceStudentStatusEnum::Paid->value
                : BalanceStudentStatusEnum::PartiallyPaid->value;
            $charge->save();

            $remainingAmount -= $applied;
        }
    }

    /**
     * Revierte los abonos a cargos separados hechos por un pago.
     */
    private function revertStudentChargePayments(Payment $payment): void
    {
        $entries = StudentChargePayment::where('payment_id', $payment->id)->get();

        if ($entries->isEmpty()) {
            return;
        }

        $chargeIds = $entries->pluck('student_charge_id')->unique();

        foreach ($entries as $entry) {
            $entry->delete();
        }

        foreach (StudentCharge::whereIn('id', $chargeIds)->get() as $charge) {
            $charge->syncFromPayments();
        }
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

        $this->revertStudentChargePayments($payment);

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

        $this->revertStudentChargePayments($existingPayment);

        $existingPayment->status = 0;
        $existingPayment->deleted_by = Auth::id();
        $existingPayment->save();

        return $this->create($data);
    }
}
