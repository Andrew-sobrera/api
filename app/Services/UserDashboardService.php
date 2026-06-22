<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;

class UserDashboardService
{
    public function stats(User $user): array
    {
        $tickets = Ticket::query()
            ->with('event')
            ->where('buyer_email', $user->email)
            ->where('status', '!=', TicketStatus::CANCELLED)
            ->get();

        $now = now();

        $upcomingEvents = $tickets
            ->filter(fn (Ticket $ticket) => $ticket->event && $ticket->event->date && $ticket->event->date->isFuture())
            ->pluck('event_id')
            ->unique()
            ->count();

        $totalSpent = Order::query()
            ->where('user_id', $user->id)
            ->where('payment_status', PaymentStatus::PAID)
            ->where('status', OrderStatus::PAID)
            ->sum('total_amount');

        return [
            'total_tickets' => $tickets->count(),
            'upcoming_events' => $upcomingEvents,
            'total_spent' => (int) $totalSpent,
        ];
    }
}
