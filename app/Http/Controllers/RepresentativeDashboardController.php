<?php

namespace App\Http\Controllers;

use App\Services\RepresentativeDashboardService;
use Inertia\Response;

class RepresentativeDashboardController extends Controller
{
    public function index(): Response
    {
        $service = new RepresentativeDashboardService;

        return inertia('Dashboard/Inicio', [
            'data' => $service->getDashboardData(auth()->user()),
        ]);
    }
}
