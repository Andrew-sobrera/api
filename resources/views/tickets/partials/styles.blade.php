<style>
    :root {
        --blue: #003366;
        --orange: #ff6600;
        --teal: #1abc9c;
        --yellow: #ffc107;
        --gray: #f2f2f2;
        --lavender: #f2f2ff;
        --white: #ffffff;
        --text: #1a2b3c;
        --text-muted: #6b7c8f;
        --radius: 16px;
        --radius-sm: 10px;
        --shadow: 0 4px 24px rgba(0, 51, 102, 0.08);
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        padding: 32px 16px;
        font-family: 'Montserrat', Arial, sans-serif;
        background: var(--gray);
        color: var(--text);
        -webkit-font-smoothing: antialiased;
    }

    .page {
        max-width: 720px;
        margin: 0 auto;
    }

    .physical-ticket {
        display: grid;
        grid-template-columns: 8px 1fr auto;
        background: var(--white);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        overflow: hidden;
        page-break-inside: avoid;
    }

    .physical-ticket__stripe {
        background: linear-gradient(180deg, var(--orange) 0%, var(--yellow) 100%);
    }

    .physical-ticket__stripe--used {
        background: var(--text-muted);
    }

    .physical-ticket__main {
        padding: 28px 28px 24px;
        min-width: 0;
    }

    .physical-ticket__brand {
        font-size: 1.125rem;
        font-weight: 800;
        color: var(--blue);
        letter-spacing: -0.02em;
        margin-bottom: 4px;
    }

    .physical-ticket__brand-accent {
        font-family: 'Pacifico', cursive;
        color: var(--orange);
        font-weight: 400;
    }

    .physical-ticket__eyebrow {
        margin: 0 0 12px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--orange);
    }

    .physical-ticket__event {
        margin: 0 0 8px;
        font-size: 1.375rem;
        font-weight: 800;
        line-height: 1.2;
        color: var(--blue);
        letter-spacing: -0.02em;
    }

    .physical-ticket__meta {
        margin: 0 0 20px;
        font-size: 0.875rem;
        color: var(--text-muted);
        line-height: 1.5;
    }

    .physical-ticket__details {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px 24px;
    }

    .physical-ticket__label {
        display: block;
        margin-bottom: 4px;
        font-size: 0.6875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--text-muted);
    }

    .physical-ticket__value {
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--text);
    }

    .physical-ticket__badge {
        display: inline-flex;
        padding: 4px 12px;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        background: rgba(26, 188, 156, 0.15);
        color: #12806a;
    }

    .physical-ticket__badge--used {
        background: rgba(107, 124, 143, 0.15);
        color: var(--text-muted);
    }

    .physical-ticket__badge--cancelled {
        background: rgba(231, 76, 60, 0.12);
        color: #c0392b;
    }

    .physical-ticket__tear {
        width: 0;
        border-left: 2px dashed var(--gray);
        margin: 16px 0;
    }

    .physical-ticket__stub {
        width: 220px;
        padding: 24px 20px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        background: var(--lavender);
    }

    .physical-ticket__qr-label {
        margin: 0 0 10px;
        font-size: 0.6875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--blue);
    }

    .physical-ticket__qr-frame {
        padding: 12px;
        background: var(--white);
        border-radius: var(--radius-sm);
        border: 2px solid rgba(0, 51, 102, 0.12);
        box-shadow: 0 8px 24px rgba(0, 51, 102, 0.1);
    }

    .physical-ticket__qr-frame img {
        display: block;
        width: 168px;
        height: 168px;
        object-fit: contain;
    }

    .physical-ticket__stub-type {
        margin: 14px 0 0;
        font-size: 0.8125rem;
        font-weight: 700;
        color: var(--blue);
    }

    .physical-ticket__stub-hint {
        margin: 8px 0 0;
        font-size: 0.6875rem;
        color: var(--text-muted);
        line-height: 1.4;
    }

    .page__actions {
        margin-top: 20px;
        text-align: center;
    }

    .page__print-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        border: none;
        border-radius: 9999px;
        background: var(--orange);
        color: var(--white);
        font-family: inherit;
        font-size: 0.9375rem;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 6px 20px rgba(255, 102, 0, 0.25);
    }

    .page__print-btn:hover {
        background: #e55a00;
    }

    .page__hint {
        margin: 12px 0 0;
        font-size: 0.8125rem;
        color: var(--text-muted);
    }

    .order-tickets {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .order-tickets__header {
        text-align: center;
        margin-bottom: 8px;
    }

    .order-tickets__title {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--blue);
    }

    @media print {
        body {
            padding: 0;
            background: var(--white);
        }

        .page__actions {
            display: none;
        }

        .physical-ticket {
            box-shadow: none;
            border: 1px solid rgba(0, 51, 102, 0.12);
        }
    }

    @media (max-width: 640px) {
        .physical-ticket {
            grid-template-columns: 6px 1fr;
        }

        .physical-ticket__tear,
        .physical-ticket__stub {
            grid-column: 1 / -1;
        }

        .physical-ticket__tear {
            width: auto;
            height: 0;
            border-left: none;
            border-top: 2px dashed var(--gray);
            margin: 0 16px;
        }

        .physical-ticket__stub {
            width: auto;
        }

        .physical-ticket__details {
            grid-template-columns: 1fr;
        }
    }
</style>
