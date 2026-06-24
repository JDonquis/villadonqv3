<?php

namespace App\Enums;

enum BalanceStudentStatusEnum: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Debt = 'debt';
    case PartiallyPaid = 'partially_paid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Paid => 'Pagado',
            self::Debt => 'Deuda',
            self::PartiallyPaid => 'Pago Parcial',
        };
    }
}
