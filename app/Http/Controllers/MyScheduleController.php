<?php

namespace App\Http\Controllers;

use App\Services\MyScheduleService;
use Illuminate\Http\Request;

class MyScheduleController extends Controller
{
    private MyScheduleService $service;

    public function __construct()
    {
        $this->service = new MyScheduleService;
    }

    public function index(Request $request)
    {
        $teacherId = auth()->id();

        $schoolLapseId = (int) $request->input('school_lapse_id');

        $data = $this->service->getIndexData($teacherId, $schoolLapseId ?: null);

        return inertia('Dashboard/MiHorario', [
            'data' => array_merge($data, [
                'filters' => [
                    'school_lapse_id' => $data['lapse_id'],
                ],
            ]),
        ]);
    }
}
