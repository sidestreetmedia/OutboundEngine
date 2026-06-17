<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'OutboundEngine')</title>
    <style>
        :root {
            --bg: #f3f4f7;
            --surface: #ffffff;
            --border: #e4e7ec;
            --ink: #181b22;
            --muted: #687083;
            --accent: #3538cd;
            --accent-soft: #eceefe;
            --ok: #0c7a54;
            --ok-soft: #e7f4ee;
            --warn: #8a6100;
            --warn-soft: #fbf2dd;
            --radius: 10px;
            --sans: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            --mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: var(--sans);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .topbar {
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }

        .topbar-inner {
            max-width: 760px;
            margin: 0 auto;
            padding: 18px 24px;
            display: flex;
            align-items: baseline;
            gap: 12px;
        }

        .wordmark {
            font-weight: 680;
            font-size: 17px;
            letter-spacing: -0.01em;
            text-decoration: none;
            color: var(--ink);
        }

        .wordmark span { color: var(--accent); }

        .topbar-tag {
            font-size: 13px;
            color: var(--muted);
        }

        .topnav {
            margin-left: auto;
            display: flex;
            gap: 18px;
        }

        .topnav a {
            font-size: 13px;
            color: var(--muted);
            text-decoration: none;
        }

        .topnav a:hover { color: var(--ink); }

        .page {
            max-width: 760px;
            margin: 0 auto;
            padding: 32px 24px 80px;
        }

        .flash {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--ok-soft);
            border: 1px solid #c9e6d8;
            color: var(--ok);
            padding: 11px 14px;
            border-radius: var(--radius);
            font-size: 14px;
            margin-bottom: 24px;
        }

        .flash-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--ok);
            flex: none;
        }

        @media (prefers-reduced-motion: no-preference) {
            .flash { animation: rise 0.25s ease-out; }
            @keyframes rise { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: none; } }
        }
    </style>
    @stack('styles')
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a href="{{ url('/') }}" class="wordmark">Outbound<span>Engine</span></a>
            <span class="topbar-tag">@yield('tag', 'the brain around your cold email')</span>
            <nav class="topnav">
                <a href="{{ route('dashboard') }}">Dashboard</a>
                <a href="{{ route('settings.edit') }}">Settings</a>
            </nav>
        </div>
    </header>

    <main class="page">
        @if (session('status'))
            <div class="flash"><span class="flash-dot"></span>{{ session('status') }}</div>
        @endif

        @yield('content')
    </main>
</body>
</html>
