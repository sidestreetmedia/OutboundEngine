<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\Personalization\ValuePropSelector;
use Illuminate\View\View;

class ProofController extends Controller
{
    public function show(string $token, ValuePropSelector $selector): View
    {
        $lead = Lead::where('public_token', $token)->firstOrFail();
        $lead->load(['audit', 'campaign.product']);

        $product = $lead->campaign?->product;
        $valueProp = $product ? $selector->forStep($lead, $product, 0) : null;

        return view('proof.show', [
            'lead' => $lead,
            'product' => $product,
            'valueProp' => $valueProp,
            'observations' => $this->observations($lead->audit?->findings ?? []),
        ]);
    }

    /**
     * Turn raw findings into plain-language observations, each tagged win/gap/info.
     * Every line is derived from a real signal — nothing is invented.
     *
     * @param  array<string, mixed>  $f
     * @return list<array{text:string, kind:string}>
     */
    private function observations(array $f): array
    {
        if ($f === []) {
            return [];
        }

        $obs = [];

        $obs[] = ($f['https'] ?? false)
            ? ['text' => 'Loads securely over HTTPS', 'kind' => 'win']
            : ['text' => 'Not served over HTTPS — a trust and SEO hit', 'kind' => 'gap'];

        $obs[] = ($f['mobile_viewport'] ?? false)
            ? ['text' => 'Mobile-friendly viewport is set', 'kind' => 'win']
            : ['text' => 'No mobile viewport tag — likely rough on phones', 'kind' => 'gap'];

        $obs[] = ($f['has_meta_description'] ?? false)
            ? ['text' => 'Has a meta description for search results', 'kind' => 'win']
            : ['text' => 'No meta description — search engines are guessing your summary', 'kind' => 'gap'];

        $obs[] = ($f['open_graph'] ?? false)
            ? ['text' => 'Open Graph tags set — links preview cleanly when shared', 'kind' => 'win']
            : ['text' => 'No social-preview tags — shared links look bare', 'kind' => 'gap'];

        $obs[] = ($f['analytics'] ?? false)
            ? ['text' => 'Analytics installed — you can see your traffic', 'kind' => 'win']
            : ['text' => 'No analytics detected — hard to know what is working', 'kind' => 'gap'];

        if ($f['structured_data'] ?? false) {
            $obs[] = ['text' => 'Structured data present — helps rich search results', 'kind' => 'win'];
        }

        if (! empty($f['platform'])) {
            $obs[] = ['text' => "Built on {$f['platform']}", 'kind' => 'info'];
        }

        $social = $f['social_links'] ?? [];
        $obs[] = ! empty($social)
            ? ['text' => 'Linked social profiles: ' . implode(', ', $social), 'kind' => 'info']
            : ['text' => 'No social profiles linked from the homepage', 'kind' => 'gap'];

        return $obs;
    }
}
