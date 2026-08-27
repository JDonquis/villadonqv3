<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMatterRequest;
use App\Http\Requests\UpdateMatterRequest;
use App\Models\Matter;
use App\Services\MatterService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MatterController extends Controller
{
    private MatterService $matterService;

    public function __construct()
    {
        $this->matterService = new MatterService;
    }

    public function index(Request $request)
    {
        return inertia('Dashboard/Materias', [
            'data' => [
                'matters' => $this->matterService->getMatters(),
            ],
            'filters' => [
                'search' => $request->input('search') ?? null,
            ],
        ]);
    }

    public function store(StoreMatterRequest $request)
    {
        try {
            $this->matterService->createMatter($request->validated());

            return back()->with(['status' => true, 'message' => 'Materia creada exitosamente.']);
        } catch (Exception $e) {
            Log::error('Error al crear materia: '.$e->getMessage());

            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function update(UpdateMatterRequest $request, $id)
    {
        $matter = Matter::findOrFail($id);

        try {
            $this->matterService->updateMatter($matter, $request->validated());

            return back()->with(['status' => true, 'message' => 'Materia actualizada exitosamente.']);
        } catch (Exception $e) {
            Log::error('Error al actualizar materia ID '.$id.': '.$e->getMessage());

            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        $matter = Matter::findOrFail($id);

        try {
            $this->matterService->deleteMatter($matter);

            return back()->with(['status' => true, 'message' => 'Materia eliminada exitosamente.']);
        } catch (Exception $e) {
            Log::error('Error al eliminar materia ID '.$id.': '.$e->getMessage());

            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }
}
