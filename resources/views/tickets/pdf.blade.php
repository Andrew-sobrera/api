<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Ingresso {{ $ticket->hash }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #1a1a2e; }
        .ticket { border: 2px dashed #3d5a80; border-radius: 12px; padding: 24px; max-width: 480px; }
        h1 { font-size: 1.25rem; margin: 0 0 8px; }
        .meta { color: #666; font-size: 0.875rem; margin-bottom: 16px; }
        .code { font-family: monospace; font-size: 1rem; background: #f4f4f8; padding: 8px 12px; border-radius: 6px; }
        .qr { margin-top: 16px; }
        .qr img { max-width: 180px; }
    </style>
</head>
<body>
    <div class="ticket">
        <h1>{{ $ticket->event?->name ?? 'Evento' }}</h1>
        <p class="meta">
            {{ $ticket->event?->date?->format('d/m/Y H:i') }}
            @if($ticket->event?->location)
                · {{ $ticket->event->location }}
            @endif
        </p>
        <p><strong>{{ $ticket->eventTicket?->name ?? 'Ingresso' }}</strong></p>
        <p><strong>Comprador:</strong> {{ $ticket->buyer_name }}</p>
        @if($ticket->seat)
            <p><strong>Assento:</strong> {{ $ticket->seat->label ?? $ticket->seat->id }}</p>
        @endif
        <p class="code">{{ $ticket->hash }}</p>
        @if($ticket->qr_code_url)
            <div class="qr">
                <img src="{{ $ticket->qr_code_url }}" alt="QR Code">
            </div>
        @endif
    </div>
</body>
</html>
