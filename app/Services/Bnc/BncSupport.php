<?php

namespace App\Services\Bnc;

use App\Models\AccountPayment;
use App\Models\MainConfig;
use Carbon\Carbon;

class BncSupport
{
    public static function resolveAccountNumber(AccountPayment $account): ?string
    {
        $candidates = [$account->account_number, config('services.bnc.account_number')];

        foreach ($candidates as $candidate) {
            $normalized = self::normalizeAccount($candidate);

            if ($normalized && strlen($normalized) === 20) {
                return $normalized;
            }
        }

        foreach ($candidates as $candidate) {
            $normalized = self::normalizeAccount($candidate);

            if ($normalized) {
                return $normalized;
            }
        }

        return null;
    }

    public static function resolveClientId(): ?string
    {
        return self::normalizeRif(
            config('services.bnc.client_id') ?: MainConfig::first()?->rif
        );
    }

    public static function accountIsEligible(AccountPayment $account): bool
    {
        if (! config('services.bnc.enabled')) {
            return false;
        }

        if (! filled(config('services.bnc.client_guid')) || ! filled(config('services.bnc.master_key'))) {
            return false;
        }

        if (! filled(self::resolveAccountNumber($account)) || ! filled(self::resolveClientId())) {
            return false;
        }

        if ((bool) config('services.bnc.force_all_accounts')) {
            return true;
        }

        $accountIds = array_map('intval', (array) config('services.bnc.account_ids', []));

        if (in_array((int) $account->id, $accountIds, true)) {
            return true;
        }

        $bank = mb_strtolower((string) $account->bank);

        return str_contains($bank, 'bnc')
            || str_contains($bank, 'nacional de credito')
            || str_contains($bank, 'nacional de crédito');
    }

    public static function normalizeReference(?string $reference): ?string
    {
        if ($reference === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $reference);

        return $digits !== '' ? $digits : null;
    }

    public static function lastSix(?string $reference): ?string
    {
        $digits = self::normalizeReference($reference);

        if ($digits === null) {
            return null;
        }

        return strlen($digits) > 6 ? substr($digits, -6) : $digits;
    }

    public static function referenceCandidates(?string $reference): array
    {
        $candidates = [];
        $raw = trim((string) $reference);
        $digits = self::normalizeReference($reference);
        $lastSix = self::lastSix($reference);

        foreach ([$raw, $digits, $lastSix] as $candidate) {
            if ($candidate !== null && $candidate !== '' && ! in_array($candidate, $candidates, true)) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }

    public static function referencesMatch(?string $a, ?string $b): bool
    {
        if (blank($a) || blank($b)) {
            return false;
        }

        $candidatesA = self::referenceCandidates($a);
        $candidatesB = self::referenceCandidates($b);

        return count(array_intersect($candidatesA, $candidatesB)) > 0;
    }

    public static function normalizeAccount(?string $account): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $account);

        return $digits !== '' ? $digits : null;
    }

    public static function normalizeRif(?string $rif): ?string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $rif));

        if ($clean === '') {
            return null;
        }

        if (preg_match('/^([JGVEP])(\d{1,9})$/', $clean, $matches)) {
            return $matches[1].str_pad($matches[2], 9, '0', STR_PAD_LEFT);
        }

        if (preg_match('/^\d{1,9}$/', $clean)) {
            return 'J'.str_pad($clean, 9, '0', STR_PAD_LEFT);
        }

        return $clean;
    }

    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '58')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '58'.substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '58'.$digits;
        }

        return $digits;
    }

    public static function normalizeCurrency(?string $currency): string
    {
        $value = strtoupper(trim((string) $currency));

        return match ($value) {
            '0928', '928', 'VES', 'BS', 'BS.', 'BOLIVAR', 'BOLIVARES', 'BOLÍVARES' => 'VES',
            '0840', '840', 'USD', 'US$', 'DOLAR', 'DÓLAR', 'DOLARES', 'DÓLARES' => 'USD',
            default => $value !== '' ? $value : 'VES',
        };
    }

    public static function amountForCurrency(float $amountUsd, float $amountVes, string $currency): float
    {
        return round(self::normalizeCurrency($currency) === 'USD' ? $amountUsd : $amountVes, 2);
    }

    public static function amountsMatch(float $expected, float $actual, ?float $tolerance = null): bool
    {
        $tolerance = $tolerance ?? (float) config('services.bnc.amount_tolerance', 0.01);

        return abs(round($expected, 2) - round($actual, 2)) <= max(0.0, $tolerance);
    }

    public static function dateOnly($date): ?string
    {
        if (blank($date)) {
            return null;
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function dateForApi($date): ?string
    {
        $date = self::dateOnly($date);

        return $date;
    }

    public static function dateTimeForApi($date): ?string
    {
        $date = self::dateOnly($date);

        return $date ? $date.'T00:00:00' : null;
    }

    public static function datesClose($a, $b, int $days = 2): bool
    {
        $a = self::dateOnly($a);
        $b = self::dateOnly($b);

        if (! $a || ! $b) {
            return false;
        }

        return abs(Carbon::parse($a)->diffInDays(Carbon::parse($b))) <= $days;
    }

    public static function movementAmount(array $movement): float
    {
        return round((float) ($movement['Amount'] ?? $movement['amount'] ?? 0), 2);
    }

    public static function movementReferences(array $movement): array
    {
        return array_values(array_filter([
            $movement['ReferenceA'] ?? null,
            $movement['ReferenceB'] ?? null,
            $movement['ReferenceC'] ?? null,
            $movement['ReferenceD'] ?? null,
            $movement['ControlNumber'] ?? null,
            $movement['DestinyBankReference'] ?? null,
            $movement['OriginBankReference'] ?? null,
        ], fn ($value) => filled($value)));
    }

    public static function movementMatches(array $movement, string $reference, float $expectedAmount): bool
    {
        $referenceMatches = false;

        foreach (self::movementReferences($movement) as $candidate) {
            if (self::referencesMatch($reference, (string) $candidate)) {
                $referenceMatches = true;
                break;
            }
        }

        if (! $referenceMatches) {
            return false;
        }

        return self::amountsMatch($expectedAmount, self::movementAmount($movement));
    }

    public static function flattenHistory(array $data): array
    {
        if ($data === []) {
            return [];
        }

        if (array_is_list($data)) {
            return $data;
        }

        $rows = [];

        foreach ($data as $value) {
            if (! is_array($value)) {
                continue;
            }

            if (array_is_list($value)) {
                foreach ($value as $row) {
                    if (is_array($row)) {
                        $rows[] = $row;
                    }
                }
            } else {
                $rows[] = $value;
            }
        }

        return $rows;
    }

    public static function findMovement(array $history, string $reference, float $amount, ?string $date = null): ?array
    {
        foreach (self::flattenHistory($history) as $movement) {
            if (! is_array($movement)) {
                continue;
            }

            if (! self::movementMatches($movement, $reference, $amount)) {
                continue;
            }

            if ($date !== null) {
                $movementDate = $movement['Date'] ?? null;
                if ($movementDate && ! self::datesClose($movementDate, $date, 2)) {
                    continue;
                }
            }

            return $movement;
        }

        return null;
    }
}
