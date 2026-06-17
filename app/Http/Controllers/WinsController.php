<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Reply;
use App\Services\Crm\CrmSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WinsController extends Controller
{
    public function index(CrmSync $crm): View
    {
        $leads = Lead::query()
            ->whereHas('replies', fn ($q) => $q->where('classification', Reply::CLASS_INTERESTED))
            ->with([
                'campaign.product',
                'replies' => fn ($q) => $q->where('classification', Reply::CLASS_INTERESTED)->latest('received_at'),
            ])
            ->withMax(['replies as last_positive_at' => fn ($q) => $q->where('classification', Reply::CLASS_INTERESTED)], 'received_at')
            ->orderByDesc('last_positive_at')
            ->limit(100)
            ->get();

        return view('wins.index', [
            'leads' => $leads,
            'hubspotReady' => $crm->isReady(),
        ]);
    }

    public function push(Lead $lead, CrmSync $crm): RedirectResponse
    {
        $result = $crm->pushLead($lead);

        if ($result['ok']) {
            $note = $result['noted'] ? '' : ' (contact added; note skipped)';
            $status = "Added {$lead->email} to HubSpot.{$note}";
        } elseif ($result['skipped']) {
            $status = "Couldn't add {$lead->email}: {$result['skipped']}.";
        } else {
            $status = "HubSpot push failed for {$lead->email}: {$result['error']}";
        }

        return redirect()->route('wins.index')->with('status', $status);
    }
}
