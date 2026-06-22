<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Support\Facades\Crypt;

class TicketHashService
{
    private const SEPARATOR = '.';

    public function generate(Event $event, string $buyerEmail, int $ticketId): string
    {
        $hash1 = $this->encode(Crypt::encryptString(config('app.key').$buyerEmail));
        $hash2 = $this->encode(Crypt::encryptString($event->id.$buyerEmail));

        return $hash1.self::SEPARATOR.$hash2.self::SEPARATOR.$ticketId;
    }

    public function parse(string $payload): ?array
    {
        $parts = explode(self::SEPARATOR, $payload);

        if (count($parts) < 3) {
            return null;
        }

        $ticketId = (int) array_pop($parts);
        $hash2 = array_pop($parts);
        $hash1 = implode(self::SEPARATOR, $parts);

        return [
            'hash1' => $hash1,
            'hash2' => $hash2,
            'ticket_id' => $ticketId,
        ];
    }

    public function validate(Ticket $ticket, string $payload): bool
    {
        $parsed = $this->parse($payload);

        if (! $parsed || $parsed['ticket_id'] !== $ticket->id) {
            return false;
        }

        try {
            $email1 = str_replace(config('app.key'), '', Crypt::decryptString($this->decode($parsed['hash1'])));
            $email2 = str_replace((string) $ticket->event_id, '', Crypt::decryptString($this->decode($parsed['hash2'])));

            return hash_equals($ticket->buyer_email, $email1)
                && hash_equals($ticket->buyer_email, $email2);
        } catch (\Throwable) {
            return false;
        }
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/'));
    }
}
