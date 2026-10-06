<?php

namespace App\Http\Controllers;

use App\Models\SchoolLapse;
use App\Services\ChartService;
use App\Services\DashboardService;
use App\Support\DashboardWidgets;
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

        $widgets = DashboardWidgets::enabledFor(auth()->user());
        $dashboardService = new DashboardService;

        return inertia('Dashboard/Index', [
            'schoolLapses' => $schoolLapse,
            'widgets' => $widgets,
            'kpiData' => $dashboardService->getKpiData(null, $widgets),
        ]);
    }

    public function annualVsMonthlyFlow($schoolLapse = null)
    {
        $this->authorizeWidgetKey('chart_projection_vs_real');

        $schoolLapse = $this->resolveLapse($schoolLapse);

        $chartService = new ChartService;
        $data = $chartService->annualVsMonthlyFlow($schoolLapse);

        return response()->json(['data' => $data, 'schoolLapseID' => $schoolLapse?->id]);
    }

    public function debtByCourse($schoolLapse = null)
    {
        $this->authorizeWidgetKey('chart_debt_by_course');

        $schoolLapse = $this->resolveLapse($schoolLapse);

        $chartService = new ChartService;
        $data = $chartService->debtByCourse($schoolLapse);

        return response()->json(['data' => $data, 'schoolLapseID' => $schoolLapse?->id]);
    }

    public function collectionRateTrend($years = 5)
    {
        $this->authorizeWidgetKey('chart_revenue_trend');

        $chartService = new ChartService;
        $data = $chartService->collectionRateTrend($years);

        return response()->json(['data' => $data]);
    }

    public function aging($schoolLapse = null)
    {
        $this->authorizeWidgetKey('chart_aging');

        $chartService = new ChartService;
        $data = $chartService->aging($schoolLapse);

        return response()->json(['data' => $data]);
    }

    public function collectionByChannel($schoolLapse = null)
    {
        $this->authorizeWidgetKey('chart_collection_by_channel');

        $chartService = new ChartService;
        $data = $chartService->collectionByChannel($schoolLapse);

        return response()->json(['data' => $data]);
    }

    public function attendanceSummary($schoolLapse = null)
    {
        $widgets = DashboardWidgets::enabledFor(auth()->user());

        $canWithdrawn = in_array('stat_withdrawn', $widgets, true);
        $canGraduated = in_array('stat_graduated', $widgets, true);
        $canAttendance = in_array('stat_attendance_today', $widgets, true);

        abort_unless($canWithdrawn || $canGraduated || $canAttendance, 403);

        $chartService = new ChartService;
        $data = $chartService->attendanceSummary($schoolLapse);

        return response()->json(['data' => [
            'active' => $data['active'] ?? null,
            'withdrawn' => $canWithdrawn ? $data['withdrawn'] : null,
            'graduated' => $canGraduated ? $data['graduated'] : null,
            'attendance' => $canAttendance ? $data['attendance'] : null,
        ]]);
    }

    public function metrics($schoolLapse = null)
    {
        $widgets = DashboardWidgets::enabledFor(auth()->user());
        $dashboardService = new DashboardService;
        $data = $dashboardService->getKpiData(
            $schoolLapse ? (int) $schoolLapse : null,
            $widgets
        );

        return response()->json(['data' => $data]);
    }

    public function topDebtors($limit = 10, $schoolLapse = null)
    {
        $this->authorizeWidgetKey('chart_top_debtors');

        $schoolLapse = $this->resolveLapse($schoolLapse);

        $chartService = new ChartService;
        $data = $chartService->topDebtors($limit, $schoolLapse);

        return response()->json(['data' => $data, 'schoolLapseID' => $schoolLapse?->id]);
    }

    public function maquinas(): Response
    {
        return inertia('Dashboard/Maquinas');
    }

    private function resolveLapse($schoolLapse): ?SchoolLapse
    {
        if (! $schoolLapse) {
            return SchoolLapse::where('status', 1)->first();
        }

        return SchoolLapse::where('id', $schoolLapse)->first();
    }

    private function authorizeWidgetKey(string $key): void
    {
        abort_unless(DashboardWidgets::enabled(auth()->user(), $key), 403);
    }
}
