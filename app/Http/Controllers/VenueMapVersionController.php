<?php

namespace App\Http\Controllers;

use App\Services\VenueMapVersionService;
use Illuminate\Http\Request;

class VenueMapVersionController extends Controller
{
    public function __construct(
        protected VenueMapVersionService $versionService,
    ) {}

    public function index(int $eventId)
    {
        return response()->json($this->versionService->listForEvent($eventId));
    }

    public function store(Request $request, int $eventId)
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:120'],
        ]);

        $version = $this->versionService->saveVersion(
            $eventId,
            $validated['label'],
            $request->user()?->id,
        );

        return response()->json($version, 201);
    }

    public function restore(Request $request, int $eventId, int $versionId)
    {
        $version = $this->versionService->restoreVersion(
            $eventId,
            $versionId,
            $request->user()?->id,
        );

        return response()->json($version);
    }
}
