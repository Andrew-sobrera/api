@extends('emails.layout')

@section('title', 'Compra criada — evenTche')
@section('eyebrow', 'Pedido')
@section('heading', 'Compra criada aguardando pagamento')

@section('content')
    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#1a2b3c;">
        Olá, <strong>{{ $order->user->name }}</strong>!
    </p>
    <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#1a2b3c;">
        Sua compra foi registrada e está aguardando confirmação de pagamento.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f2f2ff;border-radius:16px;padding:20px;">
        <tr>
            <td style="padding:20px;">
                <p style="margin:0 0 8px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#6b7c8f;">Pedido #{{ $order->id }}</p>
                <p style="margin:0;font-size:28px;font-weight:800;color:#003366;">R$ {{ number_format($order->total_amount / 100, 2, ',', '.') }}</p>
            </td>
        </tr>
    </table>

    <p style="margin:24px 0 0;font-size:14px;line-height:1.6;color:#6b7c8f;">
        Assim que o pagamento for confirmado, você receberá um novo e-mail com a confirmação dos seus ingressos.
    </p>
@endsection
