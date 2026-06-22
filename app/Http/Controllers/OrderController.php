<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Repositories\OrderRepository;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(protected OrderRepository $orderRepository)
    {
    }

    public function index(Request $request)
    {
        $orders = \App\Models\Order::query()
            ->with(['items.eventTicket', 'event', 'issuedTickets', 'reservations'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return OrderResource::collection($orders);
    }

    public function show(Request $request, int $id)
    {
        $order = $this->orderRepository->findById($id);

        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        return new OrderResource($order->load(['issuedTickets', 'event', 'reservations']));
    }
}
