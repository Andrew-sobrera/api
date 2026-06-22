<?php

namespace App\Http\Controllers;

use App\Http\Resources\TicketResource;
use App\Repositories\TicketRepository;
use App\Services\TicketValidationService;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(
        protected \App\Repositories\TicketRepository $ticketRepository,
        protected TicketValidationService $validationService,
    ) {
    }

    public function myTickets(Request $request)
    {
        $tickets = $this->ticketRepository->getForUserEmail($request->user()->email);

        return TicketResource::collection($tickets);
    }

    public function eventTickets(Request $request, int $eventId)
    {
        $tickets = $this->ticketRepository->getForEvent($eventId);

        return TicketResource::collection($tickets);
    }

    public function validate(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);

        $result = $this->validationService->validate($request->input('code'));

        if (! $result['valid']) {
            return response()->json($result, 422);
        }

        return response()->json([
            'valid' => true,
            'ticket' => new TicketResource($result['ticket']),
        ]);
    }
}
