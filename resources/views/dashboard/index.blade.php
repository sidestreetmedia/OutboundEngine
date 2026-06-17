@extends('layouts.app')

@section('title', 'Dashboard · OutboundEngine')
@section('tag', 'the funnel')

@push('styles')
<style>
    .dash-intro { margin: 0 0 26px; max-width: 60ch; }
    .dash-intro h1 { font-size: 24px; letter-spacing: -0.02em; margin: 0 0 6px; }
    .dash-intro p { margin: 0; color: var(--muted); font-size: 14px; }

    .headline {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        margin-bottom: 22px;
    }

    .stat-card {
        flex: 1 1 150px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 16px 18px;
    }

    .stat-card.hero { border-color: #c9e6d8; background: var(--ok-soft); }

    .stat-num {
        font-size: 28px;
        font-weight: 680;
        letter-spacing: -0.02em;
        line-height: 1.1;
    }

    .stat-card.hero .stat-num { color: var(--ok); }

    .stat-label {
        font-size: 12px;
        color: var(--muted);
        margin-top: 3px;
    }

    .stat-sub {
        font-size: 12px;
        color: var(--muted);
        margin-top: 8px;
        font-family: var(--mono);
    }

    .card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 20px 22px;
        margin-bottom: 18px;
    }

    .card-eyebrow {
        font-family: var(--mono);
        font-size: 11px;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--accent);
        margin: 0 0 14px;
    }

    .funnel-row {
        display: grid;
        grid-template-columns: 120px 1fr 44px;
        align-items: center;
        gap: 12px;
        padding: 5px 0;
    }

    .funnel-label { font-size: 13px; color: var(--muted); }

    .funnel-track {
        background: var(--bg);
        border-radius: 6px;
        height: 22px;
        overflow: hidden;
    }

    .funnel-fill {
        height: 100%;
        background: var(--accent-soft);
        border-right: 2px solid var(--accent);
        border-radius: 6px 0 0 6px;
        min-width: 2px;
        transition: width 0.3s ease;
    }

    .funnel-fill.win { background: var(--ok-soft); border-right-color: var(--ok); }

    .funnel-count {
        font-family: var(--mono);
        font-size: 13px;
        text-align: right;
        font-weight: 600;
    }

    .chips { display: flex; flex-wrap: wrap; gap: 8px; }

    .chip {
        display: inline-flex;
        align-items: baseline;
        gap: 6px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 999px;
        padding: 5px 12px;
        font-size: 13px;
    }

    .chip.win { background: var(--ok-soft); border-color: #c9e6d8; color: var(--ok); }
    .chip b { font-family: var(--mono); }

    .cost-line {
        font-family: var(--mono);
        font-size: 13px;
        color: var(--ink);
    }
    .cost-line .muted { color: var(--muted); }

    .camp { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 18px; }
    .camp-name { font-weight: 620; font-size: 15px; flex: 1 1 160px; }
    .camp-stat { font-size: 12px; color: var(--muted); }
    .camp-stat b { font-family: var(--mono); color: var(--ink); font-size: 13px; }
    .camp-stat.win b { color: var(--ok); }

    .empty { color: var(--muted); font-size: 14px; }
</style>
@endpush

@section('content')
    @php
        $stages = [
            'leads' => 'Leads',
            'verified' => 'Verified',
            'generated' => 'Generated',
            'approved' => 'Approved',
            'pushed' => 'Pushed',
            'replied' => 'Replied',
            'positive' => 'Positive replies',
        ];
        $of = $overall['funnel'];
        $denom = max(1, $of['leads']);
        $rates = $overall['rates'];
        $replies = $overall['replies'];
        $cost = $overall['cost'];
    @endphp

    <div class="dash-intro">
        <h1>Funnel</h1>
        <p>Every stage from raw lead to a positive reply. Positive replies are the only score that matters — the target is a {{ $target }}% positive-reply rate on contacted leads.</p>
    </div>

    <div class="headline">
        <div class="stat-card hero">
            <div class="stat-num">{{ $rates['positive_reply_rate'] }}%</div>
            <div class="stat-label">positive reply rate</div>
            <div class="stat-sub">{{ $of['positive'] }} positive / {{ $of['pushed'] }} contacted</div>
        </div>
        <div class="stat-card">
            <div class="stat-num">{{ $of['pushed'] }}</div>
            <div class="stat-label">leads contacted</div>
            <div class="stat-sub">{{ $rates['reply_rate'] }}% replied</div>
        </div>
        <div class="stat-card">
            <div class="stat-num">{{ $replies['total'] }}</div>
            <div class="stat-label">replies in</div>
            <div class="stat-sub">{{ $of['suppressed'] }} suppressed</div>
        </div>
        <div class="stat-card">
            <div class="stat-num">${{ number_format($cost['total_usd'], 2) }}</div>
            <div class="stat-label">spend to date</div>
            <div class="stat-sub">no autonomous buys</div>
        </div>
    </div>

    <div class="card">
        <p class="card-eyebrow">Overall funnel</p>
        @foreach ($stages as $key => $label)
            <div class="funnel-row">
                <span class="funnel-label">{{ $label }}</span>
                <div class="funnel-track">
                    <div class="funnel-fill {{ $key === 'positive' ? 'win' : '' }}" style="width: {{ round($of[$key] / $denom * 100, 1) }}%"></div>
                </div>
                <span class="funnel-count">{{ $of[$key] }}</span>
            </div>
        @endforeach
    </div>

    @if ($replies['total'] > 0)
        <div class="card">
            <p class="card-eyebrow">Replies by type</p>
            <div class="chips">
                @foreach ($replies['by_class'] as $class => $count)
                    <span class="chip {{ $class === 'interested' ? 'win' : '' }}">{{ str_replace('_', ' ', $class) }} <b>{{ $count }}</b></span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card">
        <p class="card-eyebrow">Cost meter</p>
        <div class="cost-line">
            ${{ number_format($cost['total_usd'], 4) }} total
            @if (! empty($cost['by_category']))
                <span class="muted">·</span>
                @foreach ($cost['by_category'] as $cat => $amt)
                    {{ $cat }} ${{ number_format($amt, 4) }}@if (! $loop->last) <span class="muted">·</span> @endif
                @endforeach
            @endif
        </div>
    </div>

    <div class="card">
        <p class="card-eyebrow">By campaign</p>
        @forelse ($campaigns as $c)
            @php $cf = $c['funnel']; @endphp
            <div class="camp" @if (! $loop->first) style="border-top: 1px solid var(--border); padding-top: 14px; margin-top: 14px;" @endif>
                <span class="camp-name">{{ $c['scope'] }}</span>
                <span class="camp-stat">leads <b>{{ $cf['leads'] }}</b></span>
                <span class="camp-stat">pushed <b>{{ $cf['pushed'] }}</b></span>
                <span class="camp-stat">replied <b>{{ $cf['replied'] }}</b></span>
                <span class="camp-stat win">positive <b>{{ $cf['positive'] }}</b></span>
                <span class="camp-stat">rate <b>{{ $c['rates']['positive_reply_rate'] }}%</b></span>
                <span class="camp-stat">cost <b>${{ number_format($c['cost']['total_usd'], 2) }}</b></span>
            </div>
        @empty
            <p class="empty">No campaigns yet.</p>
        @endforelse
    </div>
@endsection
