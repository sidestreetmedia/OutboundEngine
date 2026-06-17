@extends('layouts.app')

@section('title', 'Settings · OutboundEngine')
@section('tag', 'keys & configuration')

@push('styles')
<style>
    .settings-intro {
        margin: 0 0 28px;
        max-width: 60ch;
    }

    .settings-intro h1 {
        font-size: 24px;
        letter-spacing: -0.02em;
        margin: 0 0 6px;
    }

    .settings-intro p {
        margin: 0;
        color: var(--muted);
        font-size: 14px;
    }

    .group-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 4px 22px 8px;
        margin-bottom: 18px;
    }

    .group-eyebrow {
        font-family: var(--mono);
        font-size: 11px;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--accent);
        padding: 16px 0 4px;
        border-bottom: 1px solid var(--border);
        margin-bottom: 4px;
    }

    .setting {
        padding: 18px 0;
        border-bottom: 1px solid var(--border);
    }

    .setting:last-child { border-bottom: none; }

    .setting-head {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 9px;
    }

    .setting-label { font-weight: 580; font-size: 14.5px; }

    .setting-key {
        font-family: var(--mono);
        font-size: 12px;
        color: var(--muted);
        margin-top: 1px;
    }

    .badge {
        font-family: var(--mono);
        font-size: 10.5px;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        padding: 3px 8px;
        border-radius: 999px;
        flex: none;
    }

    .badge-saved { background: var(--ok-soft); color: var(--ok); }
    .badge-env { background: var(--warn-soft); color: var(--warn); }
    .badge-unset { background: #eef0f3; color: var(--muted); }

    .field input[type="text"],
    .field input[type="password"] {
        width: 100%;
        font-family: var(--mono);
        font-size: 13.5px;
        color: var(--ink);
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: #fcfcfd;
        transition: border-color 0.12s, box-shadow 0.12s;
    }

    .field input::placeholder { color: #aab0bd; }

    .field input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-soft);
        background: var(--surface);
    }

    .clear-row {
        display: flex;
        align-items: center;
        gap: 7px;
        margin-top: 9px;
        font-size: 13px;
        color: var(--muted);
    }

    .clear-row input { accent-color: var(--accent); }

    .actions {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-top: 26px;
    }

    .btn {
        font: inherit;
        font-weight: 580;
        font-size: 14px;
        color: #fff;
        background: var(--accent);
        border: none;
        padding: 11px 20px;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.12s;
    }

    .btn:hover { background: #2a2da8; }
    .btn:focus-visible { outline: 3px solid var(--accent-soft); outline-offset: 2px; }

    .actions-note { font-size: 13px; color: var(--muted); }
</style>
@endpush

@section('content')
    <div class="settings-intro">
        <h1>Settings</h1>
        <p>Keys entered here override your <code>.env</code> and take effect immediately — no redeploy. API keys are encrypted before they're stored, and only the last four characters are ever shown back.</p>
    </div>

    <form method="POST" action="{{ route('settings.update') }}">
        @csrf

        @foreach ($groups as $groupName => $rows)
            <section class="group-card">
                <div class="group-eyebrow">{{ $groupName }}</div>

                @foreach ($rows as $row)
                    <div class="setting">
                        <div class="setting-head">
                            <div>
                                <div class="setting-label">{{ $row['label'] }}</div>
                                <div class="setting-key">{{ $row['key'] }}</div>
                            </div>
                            @if ($row['source'] === 'saved')
                                <span class="badge badge-saved">saved</span>
                            @elseif ($row['source'] === 'env')
                                <span class="badge badge-env">from .env</span>
                            @else
                                <span class="badge badge-unset">not set</span>
                            @endif
                        </div>

                        <div class="field">
                            @if ($row['secret'])
                                <input type="password" name="{{ $row['key'] }}" autocomplete="off"
                                    placeholder="@if ($row['source'] === 'saved'){{ $row['preview'] }} saved — type to replace@elseif ($row['source'] === 'env')set in .env — type to override@else{{ $row['placeholder'] ?: 'not set' }}@endif">
                            @else
                                <input type="text" name="{{ $row['key'] }}" autocomplete="off"
                                    value="{{ $row['source'] === 'saved' ? $row['preview'] : '' }}"
                                    placeholder="{{ $row['source'] === 'env' ? $row['preview'] : ($row['placeholder'] ?: 'not set') }}">
                            @endif

                            @if ($row['source'] === 'saved')
                                <label class="clear-row">
                                    <input type="checkbox" name="clear_{{ $row['key'] }}" value="1">
                                    Remove saved value (fall back to .env)
                                </label>
                            @endif
                        </div>
                    </div>
                @endforeach
            </section>
        @endforeach

        <div class="actions">
            <button type="submit" class="btn">Save settings</button>
            <span class="actions-note">Leave a key blank to keep what's already saved.</span>
        </div>
    </form>
@endsection
