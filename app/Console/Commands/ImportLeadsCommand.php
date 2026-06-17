<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\LeadImport;
use App\Services\Leads\LeadImporter;
use Illuminate\Console\Command;

class ImportLeadsCommand extends Command
{
    protected $signature = 'leads:import
        {file : Absolute path to a CSV}
        {--campaign= : Assign imported leads to this campaign (id or slug)}';

    protected $description = 'Import leads from a CSV (normalizes, dedupes, derives company domain)';

    public function handle(LeadImporter $importer): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $campaignId = null;

        if ($this->option('campaign')) {
            $campaign = Campaign::query()
                ->where('id', $this->option('campaign'))
                ->orWhere('slug', $this->option('campaign'))
                ->first();

            if (! $campaign) {
                $this->error("Campaign not found: {$this->option('campaign')}");

                return self::FAILURE;
            }

            $campaignId = $campaign->id;
        }

        $import = $importer->importFile($file, ['campaign_id' => $campaignId]);

        if ($import->status === LeadImport::STATUS_FAILED) {
            $this->error("Import failed: {$import->error}");

            return self::FAILURE;
        }

        $this->info("Import #{$import->id} complete.");
        $this->line("  imported:   {$import->imported_count}");
        $this->line("  duplicates: {$import->duplicate_count}");
        $this->line("  invalid:    {$import->invalid_count}");
        $this->line("  failed:     {$import->failed_count}");
        $this->line("  total rows: {$import->total_rows}");

        return self::SUCCESS;
    }
}
