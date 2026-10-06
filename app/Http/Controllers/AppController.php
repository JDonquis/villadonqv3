<?php

namespace App\Http\Controllers;

use App\Models\SchoolLapse;
use App\Services\ChartService;
use App\Services\DashboardService;
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
        $schoolLapse = SchoolLapse::orderByDesc('start')->get();

        $canSeeMoney = $this->canSeeMoney();
        $dashboardService = new DashboardService;

        return inertia('Dashboard/Index', [
            'schoolLapses' => $schoolLapse,
            'canSeeMoney' => $canSeeMoney,
            'kpiData' => $dashboardService->getKpiData(null, $canSeeMoney),
        ]);
    }

    public function annualVsMonthlyFlow($schoolLapse = null)
    {
        $this->authorizeMoney();

        if (! $schoolLapse) {
            $schoolLapse = SchoolLapse::where('status', 1)->first();
        } else {
            $schoolLapse = SchoolLapse::where('id', $schoolLapse)->first();
        }

        $chartService = new ChartService;
        $data = $chartService->annualVsMonthlyFlow($schoolLapse);

        return response()->json(['data' => $data, 'schoolLapseID' => $schoolLapse?->id]);
    }

    public function debtByCourse($schoolLapse = null)
    {
        $this->authorizeMoney();

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
        $this->authorizeMoney();

        $chartService = new ChartService;
        $data = $chartService->collectionRateTrend($years);

        return response()->json(['data' => $data]);
    }

    public function aging($schoolLapse = null)
    {
        $this->authorizeMoney();

        $chartService = new ChartService;
        $data = $chartService->aging($schoolLapse);

        return response()->json(['data' => $data]);
    }

    public function collectionByChannel($schoolLapse = null)
    {
        $this->authorizeMoney();

        $chartService = new ChartService;
        $data = $chartService->collectionByChannel($schoolLapse);

        return response()->json(['data' => $data]);
    }

    public function attendanceSummary($schoolLapse = null)
    {
        $chartService = new ChartService;
        $data = $chartService->attendanceSummary($schoolLapse);

        return response()->json(['data' => $data]);
    }

    public function metrics($schoolLapse = null)
    {
        $dashboardService = new DashboardService;
        $data = $dashboardService->getKpiData(
            $schoolLapse ? (int) $schoolLapse : null,
            $this->canSeeMoney()
        );

        return response()->json(['data' => $data]);
    }

    public function topDebtors($limit = 10, $schoolLapse = null)
    {
        $this->authorizeMoney();

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

    /**
     * Sólo el administrador total (is_admin = 1) ve información financiera.
     */
    private function canSeeMoney(): bool
    {
        return (bool) (auth()->user()->is_admin ?? false);
    }

    private function authorizeMoney(): void
    {
        abort_unless($this->canSeeMoney(), 403);
    }
}
