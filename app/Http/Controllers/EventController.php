<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Services\EventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    public function __construct(protected EventService $eventService)
    {
    }

    public function index()
    {
        return EventResource::collection($this->eventService->getAll());
    }

    public function show(int $id)
    {
        return new EventResource($this->eventService->getById($id));
    }

    public function create(Request $request)
    {
        $validator = $this->makeValidator($request);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $event = $this->eventService->create($request->all());

        return (new EventResource($event))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id)
    {
        $validator = $this->makeValidator($request, isUpdate: true);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        return new EventResource($this->eventService->update($id, $request->all()));
    }

    public function uploadBanner(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'banner' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        return new EventResource($this->eventService->uploadBanner($id, $request->file('banner')));
    }

    private function makeValidator(Request $request, bool $isUpdate = false): \Illuminate\Validation\Validator
    {
        $rules = [
            'name' => ($isUpdate ? 'sometimes|' : '').'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'location_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'place_id' => 'nullable|integer|exists:places,id',
            'date' => ($isUpdate ? 'sometimes|' : '').'required|date',
            'category' => ($isUpdate ? 'sometimes|' : '').'required|string|max:255',
            'status' => ($isUpdate ? 'sometimes|' : '').'required|string|in:active,inactive,closed',
            'ticket_type' => ($isUpdate ? 'sometimes|' : '').'required|string|in:simple,sector,batch',
            'has_seats' => 'sometimes|boolean',
            'slug' => 'nullable|string|max:255',
            'ticket.name' => 'nullable|string|max:255',
            'ticket.price' => 'required_if:ticket_type,simple|numeric|min:0',
            'ticket.quantity' => 'required_if:ticket_type,simple|integer|min:1',
            'sectors' => 'required_if:ticket_type,sector|array|min:1',
            'sectors.*.name' => 'required|string|max:255',
            'sectors.*.quantity' => 'required|integer|min:1',
            'sectors.*.price' => 'required|numeric|min:0',
            'sectors.*.description' => 'nullable|string',
            'sectors.*.batches' => 'nullable|array',
            'sectors.*.batches.*.name' => 'required_with:sectors.*.batches|string|max:255',
            'sectors.*.batches.*.quantity' => 'required_with:sectors.*.batches|integer|min:1',
            'sectors.*.batches.*.price' => 'required_with:sectors.*.batches|numeric|min:0',
            'sectors.*.batches.*.starts_at' => 'nullable|date',
            'sectors.*.batches.*.ends_at' => 'nullable|date|after:sectors.*.batches.*.starts_at',
            'batches' => 'required_if:ticket_type,batch|array|min:1',
            'batches.*.name' => 'required|string|max:255',
            'batches.*.quantity' => 'required|integer|min:1',
            'batches.*.price' => 'required|numeric|min:0',
            'batches.*.starts_at' => 'nullable|date',
            'batches.*.ends_at' => 'nullable|date',
            'seats_config' => 'nullable|array',
            'seats_config.sectors' => 'nullable|array',
            'seats_config.sectors.*.name' => 'required|string',
            'seats_config.sectors.*.naming_scheme' => 'nullable|string|in:row_letter,numeric_sequential,numeric_row_prefix',
            'seats_config.sectors.*.row_seats' => 'nullable|array|min:1|max:50',
            'seats_config.sectors.*.row_seats.*' => 'integer|min:1|max:100',
            'seats_config.sectors.*.rows' => 'nullable|integer|min:1|max:50',
            'seats_config.sectors.*.seats_per_row' => 'nullable|integer|min:1|max:100',
            'seats_config.sectors.*.color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'map_template_id' => 'nullable|integer|exists:venue_map_templates,id',
        ];

        return Validator::make($request->all(), $rules);
    }
}
