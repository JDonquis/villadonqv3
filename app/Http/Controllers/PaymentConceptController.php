<?php

namespace App\Http\Controllers;

use App\Models\PaymentConcept;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentConceptController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
        ]);

        $concept = PaymentConcept::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'status' => 1,
            'created_by' => Auth::id(),
        ]);

        return response()->json(['concept' => $concept]);
    }

    public function update(Request $request, $id)
    {
        $concept = PaymentConcept::active()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
        ]);

        $concept->update($validated);

        return response()->json(['concept' => $concept]);
    }

    public function destroy($id)
    {
        $concept = PaymentConcept::active()->findOrFail($id);

        $concept->update([
            'status' => 0,
            'deleted_by' => Auth::id(),
        ]);

        return response()->json(['success' => true]);
    }
}