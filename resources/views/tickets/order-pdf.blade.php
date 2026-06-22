<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Ingressos do pedido</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #1a1a2e; }
        .ticket { border: 2px dashed #3d5a80; border-radius: 12px; padding: 24px; max-width: 480px; margin-bottom: 24px; page-break-inside: avoid; }
        h1 { font-size: 1.25rem; margin: 0 0 8px; }
        .meta { color: #666; font-size: 0.875rem; margin-bottom: 16px; }
        .code { font-family: monospace; font-size: 1rem; background: #f4f4f8; padding: 8px 12px; border-radius: 6px; }
    </style>
</head>
<body>
    @foreach($tickets as $ticket)
        @include('tickets.pdf', ['ticket' => $ticket])
    @endforeach
</body>
</html>
