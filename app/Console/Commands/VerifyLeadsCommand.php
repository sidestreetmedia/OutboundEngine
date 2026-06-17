<?php

namespace App\Console\Commands;

use App\Contracts\EmailVerifier;
use App\Models\Lead;
use Illuminate\Console\Command;

class VerifyLeadsCommand extends Command
{
    protected $signature = 'leads:verify
        {--limit=200 : Maximum number of leads to verify this run}
        {--status=new : Only verify leads currently in this status}
        {--all : Verify regardless of status}';

    protected $description = 'Verify lead emails (syntax + domain deliverability) and update their status';

    public function handle(EmailVerifier $verifier): int
    {
        $query = Lead::query();

        if (! $this->option('all')) {
            $query->where('status', $this->option('status'));
        }

        $leads = $query->limit((int) $this->option('limit'))->get();

        if ($leads->isEmpty()) {
            $this->info('No leads to verify.');

            return self::SUCCESS;
        }

        $this->info("Verifying {$leads->count()} lead(s)...");
        $bar = $this->output->createProgressBar($leads->count());
        $bar->start();

        $tally = [
            EmailVerifier::VALID => 0,
            EmailVerifier::INVALID => 0,
            EmailVerifier::RISKY => 0,
            EmailVerifier::UNKNOWN => 0,
        ];

        foreach ($leads as $lead) {
            $result = $verifier->verify($lead->email);
            $lead->applyVerification($result);
            $tally[$result] = ($tally[$result] ?? 0) + 1;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->line("  valid (now verified): {$tally[EmailVerifier::VALID]}");
        $this->line("  invalid:              {$tally[EmailVerifier::INVALID]}");
        $this->line("  risky:                {$tally[EmailVerifier::RISKY]}");
        $this->line("  unknown:              {$tally[EmailVerifier::UNKNOWN]}");

        return self::SUCCESS;
    }
}
