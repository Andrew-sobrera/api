@extends('emails.layout')

@section('title', 'Pagamento aprovado — evenTche')
@section('eyebrow', 'Pagamento confirmado')
@section('heading', 'Pagamento aprovado com sucesso!')

@section('content')
    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#1a2b3c;">
        Olá, <strong>{{ $order->user->name }}</strong>!
    </p>
    <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#1a2b3c;">
        Ótima notícia! O pagamento do seu pedido foi aprovado.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:rgba(26,188,156,0.1);border-left:4px solid #1abc9c;border-radius:0 16px 16px 0;">
        <tr>
            <td style="padding:20px 24px;">
                <p style="margin:0 0 4px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#12806a;">Status</p>
                <p style="margin:0 0 12px;font-size:16px;font-weight:700;color:#12806a;">Ingressos confirmados</p>
                <p style="margin:0;font-size:14px;color:#1a2b3c;">Pedido <strong>#{{ $order->id }}</strong> · R$ {{ number_format($order->total_amount / 100, 2, ',', '.') }}</p>
            </td>
        </tr>
    </table>

    <p style="margin:24px 0 0;font-size:14px;line-height:1.6;color:#6b7c8f;">
        Seus ingressos estão garantidos. Nos vemos no evento!
    </p>
@endsection
