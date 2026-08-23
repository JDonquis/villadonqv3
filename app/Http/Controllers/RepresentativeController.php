<?php

namespace App\Http\Controllers;

use App\Services\RepresentativeService;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;

class RepresentativeController extends Controller
{
    private RepresentativeService $representativeService;

    public function __construct()
    {
        $this->representativeService = new RepresentativeService;
    }

    public function home(): Response
    {
        $user = Auth::user();

        return inertia('Dashboard/Representante', $this->representativeService->home($user));
    }

    public function misHijos(): Response
    {
        $user = Auth::user();

        return inertia('Dashboard/MisHijos', $this->representativeService->misHijos($user));
    }
}
