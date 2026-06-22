<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title', 'evenTche')</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Pacifico&display=swap" rel="stylesheet">
</head>
<body style="margin:0;padding:0;background-color:#f2f2f2;font-family:'Montserrat',Arial,Helvetica,sans-serif;color:#1a2b3c;-webkit-font-smoothing:antialiased;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f2f2f2;">
    <tr>
        <td align="center" style="padding:32px 16px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;">
                {{-- Header --}}
                <tr>
                    <td style="background:linear-gradient(135deg,#003366 0%,#1abc9c 100%);border-radius:24px 24px 0 0;padding:28px 32px;text-align:center;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td align="center" style="padding-bottom:12px;">
                                    <img src="{{ url('Gemini_Generated_Image_jqhzpzjqhzpzjqhz.png') }}" alt="" width="48" height="48" style="display:block;border-radius:12px;">
                                </td>
                            </tr>
                            <tr>
                                <td align="center">
                                    <span style="font-size:28px;font-weight:800;color:#ffffff;letter-spacing:-0.02em;">even</span><span style="font-family:'Pacifico',cursive;font-size:28px;color:#ff6600;">Tche</span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Body --}}
                <tr>
                    <td style="background-color:#ffffff;padding:36px 32px;box-shadow:0 4px 24px rgba(0,51,102,0.08);">
                        @hasSection('eyebrow')
                            <p style="margin:0 0 8px;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#ff6600;">@yield('eyebrow')</p>
                        @endif

                        @hasSection('heading')
                            <h1 style="margin:0 0 20px;font-size:24px;font-weight:800;line-height:1.2;color:#003366;letter-spacing:-0.02em;">@yield('heading')</h1>
                        @endif

                        @yield('content')

                        @hasSection('action_url')
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:28px auto 8px;">
                                <tr>
                                    <td align="center" style="border-radius:9999px;background-color:#ff6600;">
                                        <a href="@yield('action_url')" target="_blank" style="display:inline-block;padding:14px 32px;font-family:'Montserrat',Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:9999px;">@yield('action_text', 'Acessar')</a>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        @hasSection('action_url')
                            <p style="margin:16px 0 0;font-size:12px;line-height:1.6;color:#6b7c8f;text-align:center;">
                                Se o botão não funcionar, copie e cole este link no navegador:<br>
                                <a href="@yield('action_url')" style="color:#004d99;word-break:break-all;">@yield('action_url')</a>
                            </p>
                        @endif
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="background-color:#003366;border-radius:0 0 24px 24px;padding:24px 32px;text-align:center;">
                        <p style="margin:0 0 8px;font-size:13px;color:rgba(255,255,255,0.85);">
                            <span style="font-weight:800;">even</span><span style="font-family:'Pacifico',cursive;color:#ff6600;">Tche</span> — sua plataforma de eventos
                        </p>
                        <p style="margin:0;font-size:11px;color:rgba(255,255,255,0.55);">
                            © {{ date('Y') }} evenTche. Todos os direitos reservados.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
