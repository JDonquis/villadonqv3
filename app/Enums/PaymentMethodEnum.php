<?php

namespace App\Enums;

enum PaymentMethodEnum: int
{
    case Efectivo = 1;
    case PagoMovil = 2;
    case Transferencia = 3;
    case Zelle = 4;
    case Binance = 5;
    case PuntoDeVenta = 6;

    public function label(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::PagoMovil => 'Pago Móvil',
            self::Transferencia => 'Transferencia',
            self::Zelle => 'Zelle',
            self::Binance => 'Binance',
            self::PuntoDeVenta => 'Punto de Venta',
        };
    }
}
