<?php

namespace App\Http\Controllers;

use App\Models\SchoolLapse;
use App\Services\ChartService;
use App\Services\DashboardService;
use App\Services\SchoolChargeService;
use App\Support\HomeRoute;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class AppController
{
    public function index(): Response|RedirectResponse
    {
        $user = auth()->user();

        return $user ? redirect(HomeRoute::forUser($user)) : inertia('Index');
    }

    public function dashboard(): Response
    {
        $schoolLapse = SchoolLapse::get();

        $schoolChargeService = new SchoolChargeService;
        $dashboardService = new DashboardService;
        $kpiData = $dashboardService->getKpiData();

        return inertia('Dashboard/Index', [
            'schoolLapses' => $schoolLapse,
            'schoolCharges' => $schoolChargeService->summary(),
            'totalSchoolCharges' => $schoolChargeService->totalAccumulated(),
            'schoolChargesByLapse' => $schoolChargeService->byLapse(),
            'kpiData' => $kpiData,
        ]);
    }

    public function annualVsMonthlyFlow($schoolLapse = null)
    {

        if (! $schoolLapse) {
            $schoolLapse = SchoolLapse::where('status', 1)->first();
        } else {
            $schoolLapse = SchoolLapse::where('id', $schoolLapse)->first();
        }

        $chartService = new ChartService;
        $data = $chartService->annualVsMonthlyFlow($schoolLapse);

        return response()->json(['data' => $data, 'schoolLapseID' => $schoolLapse->id]);
    }

    public function debtByCourse($schoolLapse = null)
    {
        if (! $schoolLapse) {
            $schoolLapse = SchoolLapse::where('status', 1)->first();
        } else {
            $schoolLapse = SchoolLapse::where('id', $schoolLapse)->first();
        }

        $chartService = new ChartService;
        $data = $chartService->debtByCourse($schoolLapse);

        return response()->json(['data' => $data, 'schoolLapseID' => $schoolLapse?->id]);
    }

    public function collectionRateTrend($years = 5)
    {
        $chartService = new ChartService;
        $data = $chartService->collectionRateTrend($years);

        return response()->json(['data' => $data]);
    }

    public function topDebtors($limit = 10, $schoolLapse = null)
    {
        if (! $schoolLapse) {
            $schoolLapse = SchoolLapse::where('status', 1)->first();
        } else {
            $schoolLapse = SchoolLapse::where('id', $schoolLapse)->first();
        }

        $chartService = new ChartService;
        $data = $chartService->topDebtors($limit, $schoolLapse);

        return response()->json(['data' => $data, 'schoolLapseID' => $schoolLapse?->id]);
    }

    public function maquinas(): Response
    {
        return inertia('Dashboard/Maquinas');
    }
}
