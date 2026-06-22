@extends('emails.layout')

@section('content')
    <h1 style="margin:0 0 16px;font-size:1.25rem;color:#1a1a2e;">Seus ingressos estão prontos</h1>
    <p>Olá, {{ $order->user->name }}!</p>
    <p>Seu pagamento foi confirmado. Abaixo estão os ingressos do pedido #{{ $order->id }}.</p>

    @foreach($tickets as $ticket)
        <div style="border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin:16px 0;">
            <strong>{{ $ticket->eventTicket?->name ?? 'Ingresso' }}</strong><br>
            <span style="font-family:monospace;">{{ $ticket->hash }}</span>
        </div>
    @endforeach

    <p>Acesse <strong>Meus ingressos</strong> no app para ver o QR Code de cada ingresso.</p>
@endsection
