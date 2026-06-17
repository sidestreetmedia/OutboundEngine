<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\Analytics\FunnelMetrics;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(FunnelMetrics $metrics): View
    {
        $overall = $metrics->overall();

        $campaigns = Campaign::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn (Campaign $campaign) => $metrics->forCampaign($campaign));

        return view('dashboard.index', [
            'overall' => $overall,
            'campaigns' => $campaigns,
            'target' => config('outbound.targets.positive_reply_rate', 0.75),
        ]);
    }
}
