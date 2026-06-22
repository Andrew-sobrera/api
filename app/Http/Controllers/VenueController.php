<?php

namespace App\Http\Controllers;

use App\Services\VenueService;
use Illuminate\Http\Request;

class VenueController extends Controller
{
    public function __construct(
        protected VenueService $venueService,
    ) {}

    public function index(Request $request)
    {
        return response()->json($this->venueService->listForUser($request->user()?->id));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $venue = $this->venueService->create($validated, $request->user()?->id);

        return response()->json($venue, 201);
    }

    public function saveMapFromEvent(Request $request, int $venueId)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
        ]);

        return response()->json(
            $this->venueService->saveMapFromEvent($venueId, (int) $validated['event_id'])
        );
    }

    public function applyToEvent(Request $request, int $venueId)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
        ]);

        $this->venueService->applyVenueToEvent($venueId, (int) $validated['event_id']);

        return response()->json(['message' => 'Mapa do local aplicado ao evento.']);
    }
}
