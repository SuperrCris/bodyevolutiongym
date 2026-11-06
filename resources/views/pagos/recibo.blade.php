<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recibo de pago #{{ $pago->id }} | Body Evolution Gym</title>
    <style>
        * {
            box-sizing: border-box;
        }

        @page {
            margin: 3mm;
        }

        body {
            margin: 0;
            color: #111827;
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        .ticket {
            width: 100%;
            max-width: 76mm;
            margin: 0 auto;
        }

        .centrado {
            text-align: center;
        }

        .texto-ticket {
            white-space: pre-line;
        }

        .logo {
            display: block;
            width: 60mm;
            max-width: 100%;
            height: auto;
            margin: 0 auto 12px;
            filter: brightness(0);
        }

        .separador {
            margin: 10px 0;
            border: 0;
            border-top: 1px dashed #64748b;
        }

        .fila {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 3px 0;
        }

        .fila span:first-child {
            color: #475569;
        }

        .fila span:last-child {
            text-align: right;
            overflow-wrap: anywhere;
        }

        .total {
            font-size: 16px;
            font-weight: 700;
        }

        .acciones {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px auto;
        }

        .acciones button,
        .acciones a {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 10px 14px;
            color: #0f172a;
            background: #fff;
            font: inherit;
            text-decoration: none;
            cursor: pointer;
        }

        @media screen {
            body {
                min-height: 100vh;
                padding: 24px;
                background: #e2e8f0;
            }

            .ticket {
                padding: 20px;
                background: #fff;
                box-shadow: 0 10px 30px #0f172a1a;
            }
        }

    </style>
</head>
<body>
    <main class="ticket">
        <header class="centrado">
            <img class="logo" src="{{ asset('images/logo.png') }}" alt="Body Evolution Gym">
            @if (filled($configuracionTicket['encabezado']))
                <p class="texto-ticket">{{ $configuracionTicket['encabezado'] }}</p>
            @endif
            <p>RECIBO DE PAGO</p>
        </header>

        <hr class="separador">

        <div class="fila"><span>Folio</span><span>#{{ $pago->id }}</span></div>
        <div class="fila"><span>Fecha</span><span>{{ $pago->created_at?->format('d/m/Y H:i') ?? '—' }}</span></div>
        <div class="fila"><span>Atendió</span><span>{{ $pago->usuario?->name ?? '—' }}</span></div>

        <hr class="separador">

        <div class="fila"><span>{{ $pago->cliente ? 'Cliente' : 'Comprador' }}</span><span>{{ $pago->cliente ? (trim($pago->cliente->nombre.' '.$pago->cliente->apellido) ?: '—') : ($pago->nombre_comprador ?: 'Venta sin cliente') }}</span></div>
        <div class="fila"><span>Plan</span><span>{{ $pago->membresia?->plan?->nombre ?? $pago->plan?->nombre ?? '—' }}</span></div>
        @if ($pago->membresia)
            <div class="fila"><span>Vigencia</span><span>{{ $pago->membresia->comienzo?->format('d/m/Y') ?? '—' }} al {{ $pago->membresia->finalizacion?->format('d/m/Y') ?? '—' }}</span></div>
        @else
            <div class="fila"><span>Vigencia</span><span>No asignada</span></div>
        @endif

        <hr class="separador">

        <div class="fila"><span>Método de pago</span><span>{{ $metodoPago }}</span></div>
        @if (filled($pago->referencia))
            <div class="fila"><span>Referencia</span><span>{{ $pago->referencia }}</span></div>
        @endif
        <div class="fila total"><span>Total</span><span>${{ number_format((float) $pago->monto, 2) }}</span></div>

        <hr class="separador">
        @if (filled($configuracionTicket['pie']))
            <p class="centrado texto-ticket">{{ $configuracionTicket['pie'] }}</p>
        @endif
    </main>

    @if (session('error'))
        <p role="alert">{{ session('error') }}</p>
    @endif

    <nav class="acciones" aria-label="Acciones del recibo">
        <form method="POST" action="{{ route('pagos.imprimir', $pago) }}">
            @csrf
            <button type="submit">{{ session('error') ? 'Reintentar impresión silenciosa' : 'Enviar a impresora predeterminada' }}</button>
        </form>
        <a href="{{ route('clientes.index') }}">Volver a clientes</a>
    </nav>
</body>
</html>
