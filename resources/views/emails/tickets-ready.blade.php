@extends('emails.layout')

@section('content')
    <h1 style="margin:0 0 16px;font-size:1.25rem;color:#1a1a2e;">Seus ingressos estão prontos</h1>
    <p>Olá, {{ $order->user->name }}!</p>
    <p>
        Seu pagamento foi confirmado. Os ingressos virtuais do pedido
        <strong>#{{ $order->id }}</strong> ({{ $order->event?->name }}) estão anexados a este e-mail.
    </p>

    <div style="background:#f2f2ff;border-radius:12px;padding:20px;margin:24px 0;border-left:4px solid #ff6600;">
        <p style="margin:0 0 12px;font-weight:700;color:#003366;">Como baixar em PDF</p>
        <ol style="margin:0;padding-left:20px;color:#1a2b3c;line-height:1.7;">
            <li>Abra o anexo <strong>pedido-{{ $order->id }}-ingressos.html</strong> no navegador (Chrome, Edge ou Safari).</li>
            <li>Clique em <strong>Imprimir / Salvar como PDF</strong> ou use <strong>Ctrl+P</strong> (Windows) / <strong>Cmd+P</strong> (Mac).</li>
            <li>Na janela de impressão, escolha <strong>Salvar como PDF</strong>.</li>
        </ol>
        @if($ticketCount > 1)
            <p style="margin:16px 0 0;font-size:14px;color:#6b7c8f;">
                Também enviamos cada ingresso em um arquivo separado
                (<strong>ingresso-1-…</strong>, <strong>ingresso-2-…</strong>, etc.) caso prefira baixar um por vez.
            </p>
        @endif
    </div>

    <p style="margin:0 0 8px;font-weight:700;color:#003366;">Resumo do pedido</p>
    @foreach($tickets as $ticket)
        <div style="border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin:12px 0;">
            <strong>{{ $ticket->eventTicket?->name ?? 'Ingresso' }}</strong>
            @if($ticket->sector?->name)
                <br><span style="font-size:14px;color:#6b7c8f;">Setor: {{ $ticket->sector->name }}</span>
            @endif
            @if($ticket->seat?->label)
                <br><span style="font-size:14px;color:#6b7c8f;">Assento: {{ $ticket->seat->label }}</span>
            @endif
        </div>
    @endforeach

    <p style="margin-top:20px;color:#6b7c8f;font-size:14px;">
        Você também pode acessar <strong>Minha conta → Meus ingressos</strong> na EvenTche para baixar novamente ou apresentar o QR Code na entrada.
    </p>
@endsection
