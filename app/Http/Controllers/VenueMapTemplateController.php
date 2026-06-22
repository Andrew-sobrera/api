<?php

namespace App\Http\Controllers;

use App\Services\VenueMapTemplateService;
use Illuminate\Http\Request;

class VenueMapTemplateController extends Controller
{
    public function __construct(
        protected VenueMapTemplateService $templateService,
    ) {}

    public function index(Request $request)
    {
        return response()->json(
            $this->templateService->listForUser($request->user()?->id)
        );
    }

    public function show(int $id)
    {
        return response()->json($this->templateService->getById($id));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $template = $this->templateService->saveFromEvent(
            (int) $validated['event_id'],
            $validated['name'],
            $validated['description'] ?? null,
            $request->user()?->id,
        );

        return response()->json($template, 201);
    }

    public function destroy(int $id)
    {
        $this->templateService->delete($id);

        return response()->json(['message' => 'Modelo removido.']);
    }
}
