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
            'status' => 'required|string|in:active,inactive,closed',
            'ticket_type' => 'required|string|in:simple,sector,batch',
            'slug' => 'nullable|string|max:255',
            'ticket.price' => 'required_if:ticket_type,simple|numeric|min:0',
            'ticket.quantity' => 'required_if:ticket_type,simple|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $event = $this->eventService->create($request->all());

        return new EventResource($event);
    }

    public function uploadBanner(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'banner' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $event = $this->eventService->uploadBanner($id, $request->file('banner'));

        return new EventResource($event);
    }
}
