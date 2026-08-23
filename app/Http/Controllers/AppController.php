<?php

namespace App\Http\Controllers;

use App\Models\SchoolLapse;
use App\Services\ChartService;
use App\Services\SchoolChargeService;
use Inertia\Response;

class AppController
{
    public function index(): Response
    {
        return inertia('Index');
    }

    public function dashboard(): Response
    {
        $schoolLapse = SchoolLapse::get();

        $schoolChargeService = new SchoolChargeService;

        return inertia('Dashboard/Index', [
            'schoolLapses' => $schoolLapse,
            'schoolCharges' => $schoolChargeService->summary(),
            'totalSchoolCharges' => $schoolChargeService->totalAccumulated(),
            'schoolChargesByLapse' => $schoolChargeService->byLapse(),
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

    public function maquinas(): Response
    {
        return inertia('Dashboard/Maquinas');
    }
}
