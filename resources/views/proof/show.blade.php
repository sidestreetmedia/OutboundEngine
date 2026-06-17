<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    @php
        $first = $lead->first_name ?: 'there';
        $company = $lead->company ?: 'your team';
        $domain = $lead->audit?->domain ?: $lead->company_domain;
        $headline = $valueProp?->headline ?: 'A quick, honest look at your website';
    @endphp
    <title>{{ $headline }}</title>
    <style>
        :root {
            --ink: #16181d;
            --muted: #5d6470;
            --line: #e6e8ec;
            --accent: #2f49d1;
            --win: #0c7a54;
            --gap: #9a6b00;
            --paper: #fbfbfc;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--paper);
            color: var(--ink);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
        }
        .wrap { max-width: 600px; margin: 0 auto; padding: 56px 24px 72px; }
        .eyebrow {
            font-size: 13px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--accent);
            font-weight: 600;
            margin-bottom: 14px;
        }
        h1 { font-size: 30px; line-height: 1.2; letter-spacing: -0.02em; margin: 0 0 16px; }
        .lede { font-size: 17px; color: var(--muted); margin: 0 0 36px; }
        .card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 26px 28px;
            margin-bottom: 28px;
        }
        .card h2 { font-size: 15px; letter-spacing: -0.01em; margin: 0 0 6px; }
        .card .src { font-size: 13px; color: var(--muted); margin: 0 0 18px; }
        .summary { font-size: 15px; margin: 0 0 20px; }
        ul.obs { list-style: none; margin: 0; padding: 0; }
        ul.obs li {
            display: flex;
            gap: 11px;
            align-items: flex-start;
            padding: 8px 0;
            border-top: 1px solid var(--line);
            font-size: 15px;
        }
        ul.obs li:first-child { border-top: none; }
        .mark { flex: none; width: 18px; height: 18px; margin-top: 3px; font-size: 13px; font-weight: 700; text-align: center; line-height: 18px; border-radius: 50%; }
        .mark.win { color: var(--win); background: #e7f4ee; }
        .mark.gap { color: var(--gap); background: #fbf2dd; }
        .mark.info { color: var(--muted); background: #eef0f3; }
        .cta {
            font-size: 17px;
            background: #fff;
            border: 1px solid var(--line);
            border-left: 3px solid var(--accent);
            border-radius: 10px;
            padding: 18px 22px;
        }
        footer { margin-top: 36px; font-size: 12px; color: var(--muted); }
    </style>
</head>
<body>
    <div class="wrap">
        <p class="eyebrow">Prepared for {{ $first }} · {{ $company }}</p>
        <h1>{{ $headline }}</h1>

        <p class="lede">
            @if ($valueProp && $valueProp->problem)
                {{ $valueProp->problem }}
            @else
                I put together a quick, no-strings look at what’s publicly visible on your site — the kind of thing worth a glance whether or not we ever talk.
            @endif
        </p>

        @if (! empty($observations))
            <div class="card">
                <h2>A quick look{{ $domain ? ' at ' . $domain : '' }}</h2>
                <p class="src">Observed from your public homepage — nothing purchased, nothing guessed.</p>

                @if ($lead->audit?->summary)
                    <p class="summary">{{ $lead->audit->summary }}</p>
                @endif

                <ul class="obs">
                    @foreach ($observations as $o)
                        <li>
                            <span class="mark {{ $o['kind'] }}">@if ($o['kind'] === 'win')✓@elseif ($o['kind'] === 'gap')!@else·@endif</span>
                            <span>{{ $o['text'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="cta">
            Want the full breakdown and what I’d fix first? Just reply to my email — happy to walk {{ $company }} through it.
        </div>

        <footer>
            Built from public information about {{ $domain ?: $company }}. No data was purchased, and nothing here is invented.
        </footer>
    </div>
</body>
</html>
