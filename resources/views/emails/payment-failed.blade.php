@extends('emails.layout')

@section('title', 'Pagamento recusado — evenTche')
@section('eyebrow', 'Pagamento')
@section('heading', 'Não foi possível confirmar o pagamento')

@section('content')
    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#1a2b3c;">
        Olá, <strong>{{ $order->user->name }}</strong>!
    </p>
    <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#1a2b3c;">
        O pagamento do pedido <strong>#{{ $order->id }}</strong> foi recusado ou não pôde ser concluído.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:rgba(231,76,60,0.1);border-left:4px solid #e74c3c;border-radius:0 16px 16px 0;">
        <tr>
            <td style="padding:20px 24px;">
                <p style="margin:0 0 4px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#c0392b;">Reserva liberada</p>
                <p style="margin:0;font-size:14px;line-height:1.6;color:#1a2b3c;">
                    Os ingressos reservados foram liberados. Você pode tentar novamente quando quiser.
                </p>
            </td>
        </tr>
    </table>

    <p style="margin:24px 0 0;font-size:14px;line-height:1.6;color:#6b7c8f;">
        Se acredita que isso é um erro, entre em contato com nosso suporte ou tente um novo pagamento.
    </p>
@endsection
