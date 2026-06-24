<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlaceResource;
use App\Services\PlaceService;
use Illuminate\Http\Request;

class PlaceController extends Controller
{
    public function __construct(
        protected PlaceService $placeService,
    ) {
    }

    public function index()
    {
        return PlaceResource::collection($this->placeService->list());
    }

    public function show(int $id)
    {
        return new PlaceResource($this->placeService->getById($id));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $place = $this->placeService->create($validated);

        return (new PlaceResource($place))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'address' => ['sometimes', 'required', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        return new PlaceResource($this->placeService->update($id, $validated));
    }

    public function destroy(int $id)
    {
        $this->placeService->delete($id);

        return response()->json(['message' => 'Local removido com sucesso.']);
    }
}
