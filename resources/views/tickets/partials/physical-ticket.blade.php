@php
    $statusLabel = match ($ticket->status->value ?? $ticket->status) {
        'used' => 'Utilizado',
        'cancelled' => 'Cancelado',
        default => 'Confirmado',
    };
    $statusClass = match ($ticket->status->value ?? $ticket->status) {
        'used' => 'physical-ticket__badge--used',
        'cancelled' => 'physical-ticket__badge--cancelled',
        default => '',
    };
    $stripeClass = ($ticket->status->value ?? $ticket->status) === 'used' ? 'physical-ticket__stripe--used' : '';
@endphp

<article class="physical-ticket">
    <div class="physical-ticket__stripe {{ $stripeClass }}"></div>

    <div class="physical-ticket__main">
        <div class="physical-ticket__brand">
            even<span class="physical-ticket__brand-accent">Tche</span>
        </div>
        <p class="physical-ticket__eyebrow">Ingresso digital</p>
        <h1 class="physical-ticket__event">{{ $ticket->event?->name ?? 'Evento' }}</h1>
        <p class="physical-ticket__meta">
            @if($ticket->event?->date)
                {{ $ticket->event->date->format('d/m/Y \à\s H:i') }}
            @endif
            @if($ticket->event?->location)
                · {{ $ticket->event->location }}
            @endif
        </p>

        <div class="physical-ticket__details">
            <div>
                <span class="physical-ticket__label">Tipo</span>
                <span class="physical-ticket__value">{{ $ticket->eventTicket?->name ?? 'Ingresso' }}</span>
            </div>
            <div>
                <span class="physical-ticket__label">Comprador</span>
                <span class="physical-ticket__value">{{ $ticket->buyer_name }}</span>
            </div>
            @if($ticket->seat)
                <div>
                    <span class="physical-ticket__label">Assento</span>
                    <span class="physical-ticket__value">{{ $ticket->seat->label ?? $ticket->seat->id }}</span>
                </div>
            @endif
            @if($ticket->sector)
                <div>
                    <span class="physical-ticket__label">Setor</span>
                    <span class="physical-ticket__value">{{ $ticket->sector->name }}</span>
                </div>
            @endif
            <div>
                <span class="physical-ticket__label">Status</span>
                <span class="physical-ticket__badge {{ $statusClass }}">{{ $statusLabel }}</span>
            </div>
        </div>
    </div>

    <div class="physical-ticket__tear" aria-hidden="true"></div>

    <aside class="physical-ticket__stub">
        <p class="physical-ticket__qr-label">Apresente na entrada</p>
        @if($ticket->qr_code_url)
            <div class="physical-ticket__qr-frame">
                <img src="{{ $ticket->qr_code_url }}" alt="QR Code do ingresso">
            </div>
        @endif
        <p class="physical-ticket__stub-type">{{ $ticket->eventTicket?->name ?? 'Ingresso' }}</p>
        <p class="physical-ticket__stub-hint">Guarde este ingresso até o dia do evento</p>
    </aside>
</article>
