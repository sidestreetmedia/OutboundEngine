@extends('layouts.app')

@section('title', 'Wins · OutboundEngine')
@section('tag', 'positive replies')

@push('styles')
<style>
    .wins-intro { margin: 0 0 24px; max-width: 62ch; }
    .wins-intro h1 { font-size: 24px; letter-spacing: -0.02em; margin: 0 0 6px; }
    .wins-intro p { margin: 0; color: var(--muted); font-size: 14px; }

    .notice {
        display: flex; align-items: center; gap: 10px;
        background: var(--warn-soft); border: 1px solid #f0e0b8; color: var(--warn);
        padding: 11px 14px; border-radius: var(--radius); font-size: 13.5px; margin-bottom: 20px;
    }
    .notice a { color: var(--warn); font-weight: 600; }

    .win {
        background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
        padding: 18px 20px; margin-bottom: 14px;
        display: flex; gap: 16px; align-items: flex-start; justify-content: space-between; flex-wrap: wrap;
    }
    .win-main { flex: 1 1 340px; min-width: 0; }
    .win-name { font-weight: 640; font-size: 16px; letter-spacing: -0.01em; }
    .win-meta { font-size: 13px; color: var(--muted); margin-top: 2px; }

    .win-tags { display: flex; flex-wrap: wrap; gap: 6px; margin: 11px 0 10px; }
    .tag {
        font-size: 12px; padding: 3px 10px; border-radius: 999px;
        background: var(--bg); border: 1px solid var(--border); color: var(--muted);
    }
    .tag.cta { background: var(--accent-soft); border-color: #d2d4fb; color: var(--accent); }
    .tag b { color: var(--ink); font-weight: 600; }

    .win-reply {
        font-size: 13.5px; color: var(--ink); background: var(--bg);
        border-left: 3px solid #c9e6d8; border-radius: 0 8px 8px 0;
        padding: 9px 13px; margin: 0;
    }
    .win-reply .lead-in { color: var(--muted); font-size: 12px; display: block; margin-bottom: 2px; }

    .win-action { flex: none; display: flex; flex-direction: column; align-items: flex-end; gap: 6px; }

    /* Per-contact push control: unsynced renders the action button, synced renders a status pill. */
    .hs-toggle {
        appearance: none; font: inherit; cursor: pointer;
        display: inline-flex; align-items: center; gap: 8px;
        font-size: 13.5px; font-weight: 580;
        padding: 8px 14px 8px 11px; border-radius: 999px;
        border: 1px solid var(--accent); color: var(--accent); background: var(--surface);
        transition: background 0.12s, color 0.12s;
    }
    .hs-toggle:hover { background: var(--accent-soft); }
    .hs-toggle .knob {
        width: 26px; height: 16px; border-radius: 999px; background: #cdd0e8; position: relative; flex: none;
        transition: background 0.15s;
    }
    .hs-toggle .knob::after {
        content: ''; position: absolute; top: 2px; left: 2px; width: 12px; height: 12px;
        border-radius: 50%; background: #fff; transition: left 0.15s;
    }
    .hs-toggle:hover .knob { background: var(--accent); }

    .hs-on {
        display: inline-flex; align-items: center; gap: 8px;
        font-size: 13.5px; font-weight: 580; color: var(--ok);
        padding: 8px 14px 8px 11px; border-radius: 999px;
        border: 1px solid #c9e6d8; background: var(--ok-soft);
    }
    .hs-on .knob {
        width: 26px; height: 16px; border-radius: 999px; background: var(--ok); position: relative; flex: none;
    }
    .hs-on .knob::after {
        content: ''; position: absolute; top: 2px; left: 12px; width: 12px; height: 12px; border-radius: 50%; background: #fff;
    }
    .hs-when { font-size: 11.5px; color: var(--muted); font-family: var(--mono); }
    .hs-when a { color: var(--muted); text-decoration: underline; }

    .hs-toggle:disabled { cursor: not-allowed; opacity: 0.5; }

    .empty {
        background: var(--surface); border: 1px dashed var(--border); border-radius: var(--radius);
        padding: 40px 24px; text-align: center; color: var(--muted); font-size: 14px;
    }
    form.inline { margin: 0; }
</style>
@endpush

@section('content')
    <div class="wins-intro">
        <h1>Wins</h1>
        <p>Contacts who replied positively to the current CTA. Flip the toggle to add one to HubSpot — it creates the contact and drops in a note with the campaign and offer they responded to.</p>
    </div>

    @unless ($hubspotReady)
        <div class="notice">
            <span>HubSpot isn't connected yet, so the toggles are disabled.</span>
            <a href="{{ route('settings.edit') }}">Add your token in Settings →</a>
        </div>
    @endunless

    @forelse ($leads as $lead)
        @php
            $reply = $lead->replies->first();
            $offer = $lead->campaign?->product?->name;
            $name = trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? '')) ?: $lead->email;
        @endphp
        <div class="win">
            <div class="win-main">
                <div class="win-name">{{ $name }}</div>
                <div class="win-meta">
                    {{ $lead->title ?: 'Contact' }}{{ $lead->company ? ' · ' . $lead->company : '' }} · {{ $lead->email }}
                </div>

                <div class="win-tags">
                    @if ($lead->campaign)
                        <span class="tag">campaign <b>{{ $lead->campaign->name }}</b></span>
                    @endif
                    @if ($offer)
                        <span class="tag cta">CTA <b>{{ $offer }}</b></span>
                    @endif
                </div>

                @if ($reply && filled($reply->body))
                    <p class="win-reply">
                        <span class="lead-in">Their reply{{ $reply->received_at ? ' · ' . $reply->received_at->diffForHumans() : '' }}</span>
                        {{ \Illuminate\Support\Str::limit(trim($reply->body), 180) }}
                    </p>
                @endif
            </div>

            <div class="win-action">
                @if ($lead->hubspot_contact_id)
                    <span class="hs-on"><span class="knob"></span> In HubSpot</span>
                    <span class="hs-when">
                        synced {{ $lead->hubspot_synced_at?->diffForHumans() }}
                        @if ($hubspotReady)
                            ·
                            <form method="POST" action="{{ route('wins.push', $lead) }}" class="inline" style="display:inline">
                                @csrf
                                <button type="submit" style="background:none;border:none;padding:0;color:var(--muted);text-decoration:underline;cursor:pointer;font:inherit;font-size:11.5px">re-sync</button>
                            </form>
                        @endif
                    </span>
                @else
                    <form method="POST" action="{{ route('wins.push', $lead) }}" class="inline">
                        @csrf
                        <button type="submit" class="hs-toggle" {{ $hubspotReady ? '' : 'disabled' }}>
                            <span class="knob"></span> Add to HubSpot
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <div class="empty">No positive replies yet — they'll show up here as replies come in and get classified.</div>
    @endforelse
@endsection
