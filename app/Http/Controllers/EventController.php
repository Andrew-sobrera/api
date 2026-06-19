<?php

namespace App\Http\Controllers;

use App\Services\EventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\EventResource;

class EventController extends Controller
{
    protected $eventService;

    public function __construct(EventService $eventService)
    {
        $this->eventService = $eventService;
    }

    public function index()
    {
        $events = $this->eventService->getAll();
        return EventResource::collection($events);
    }

    public function show(int $id)
    {
        $event = $this->eventService->getById($id);
        return new EventResource($event);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'category' => 'required|string|max:255',
            'status' => 'required|string|max:255',
            'ticket_type' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $event = $this->eventService->create($request->all());

        return new EventResource($event);
    }
}