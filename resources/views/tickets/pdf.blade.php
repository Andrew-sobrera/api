<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresso — {{ $ticket->event?->name ?? 'Evento' }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Pacifico&display=swap" rel="stylesheet">
    @include('tickets.partials.styles')
</head>
<body>
<div class="page">
    @include('tickets.partials.physical-ticket', ['ticket' => $ticket])

    <div class="page__actions">
        <button type="button" class="page__print-btn" onclick="window.print()">
            Imprimir / Salvar como PDF
        </button>
        <p class="page__hint">Use a opção &quot;Salvar como PDF&quot; na janela de impressão do navegador.</p>
    </div>
</div>
</body>
</html>
