<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class LocationController extends Controller
{
    /**
     * Display a listing of the locations.
     */
    public function index(): JsonResponse
    {
        $locations = Location::all();
        return response()->json($locations);
    }

    /**
     * Store a newly created location in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'latitude' => 'required|numeric|min:-90|max:90',
            'longitude' => 'required|numeric|min:-180|max:180',
        ]);

        $location = Location::create($validated);

        return response()->json($location, 201);
    }

    /**
     * Display the specified location.
     */
    public function show(string $id): JsonResponse
    {
        $location = Location::findOrFail($id);
        return response()->json($location);
    }

    /**
     * Update the specified location.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $location = Location::findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'latitude' => 'sometimes|required|numeric|min:-90|max:90',
            'longitude' => 'sometimes|required|numeric|min:-180|max:180',
        ]);

        $location->update($validated);

        return response()->json($location);
    }

    /**
     * Remove the specified location.
     */
    public function destroy(string $id): JsonResponse
    {
        $location = Location::findOrFail($id);
        $location->delete();

        return response()->json(null, 204);
    }
}