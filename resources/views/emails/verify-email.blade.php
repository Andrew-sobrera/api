@extends('emails.layout')

@section('title', 'Confirme seu e-mail — evenTche')
@section('eyebrow', 'Verificação de conta')
@section('heading', 'Confirme seu endereço de e-mail')
@section('action_url', $verificationUrl)
@section('action_text', 'Confirmar e-mail')

@section('content')
    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#1a2b3c;">
        Olá, <strong>{{ $user->name }}</strong>!
    </p>
    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#1a2b3c;">
        Obrigado por se cadastrar na <strong>evenTche</strong>. Para ativar sua conta e começar a comprar ingressos, confirme seu e-mail clicando no botão abaixo.
    </p>
    <p style="margin:0;font-size:14px;line-height:1.6;color:#6b7c8f;">
        Este link expira em {{ config('auth.verification.expire', 60) }} minutos. Se você não criou uma conta, ignore este e-mail.
    </p>
@endsection
