<?php

namespace App\Services\Leads;

use App\Models\Lead;
use App\Models\LeadImport;
use RuntimeException;
use Throwable;

/**
 * Imports leads from a CSV. Normalizes emails (the dedup key), drops invalid
 * addresses, collapses duplicates both within the file and against existing
 * leads, derives a company domain from work emails, and records per-run counts.
 * One bad row is counted and skipped, never fatal.
 */
class LeadImporter
{
    /** Free-mail domains are never treated as a company domain. */
    private const FREE_MAIL = [
        'gmail.com', 'googlemail.com', 'yahoo.com', 'ymail.com', 'hotmail.com',
        'outlook.com', 'live.com', 'msn.com', 'aol.com', 'icloud.com', 'me.com',
        'mac.com', 'protonmail.com', 'proton.me', 'gmx.com', 'gmx.net', 'mail.com',
    ];

    public function __construct(private readonly ColumnMapper $mapper)
    {
    }

    /**
     * @param  array{original_name?: string, stored_path?: string, campaign_id?: int|null}  $options
     */
    public function importFile(string $absolutePath, array $options = []): LeadImport
    {
        $import = LeadImport::create([
            'original_name' => $options['original_name'] ?? basename($absolutePath),
            'path' => $options['stored_path'] ?? null,
            'status' => LeadImport::STATUS_PROCESSING,
        ]);

        try {
            $this->process($import, $absolutePath, $options['campaign_id'] ?? null);
            $import->update(['status' => LeadImport::STATUS_COMPLETED]);
        } catch (Throwable $e) {
            $import->update(['status' => LeadImport::STATUS_FAILED, 'error' => $e->getMessage()]);
        }

        return $import->refresh();
    }

    private function process(LeadImport $import, string $path, ?int $campaignId): void
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Could not open CSV: {$path}");
        }

        try {
            $headers = fgetcsv($handle);

            if (! is_array($headers)) {
                throw new RuntimeException('The CSV appears to be empty.');
            }

            // Strip a UTF-8 BOM off the first header if present.
            $headers[0] = preg_replace('/^\x{FEFF}/u', '', (string) $headers[0]) ?? $headers[0];

            $map = $this->mapper->map($headers);

            if (! in_array('email', $map, true)) {
                throw new RuntimeException('No email column found. Headers seen: ' . implode(', ', $headers));
            }

            $import->update(['mapping' => $this->mappingLabels($headers, $map)]);

            $seen = [];
            $total = $imported = $duplicates = $invalid = $failed = 0;

            while (($row = fgetcsv($handle)) !== false) {
                if ($this->isBlankRow($row)) {
                    continue;
                }

                $total++;

                try {
                    $fields = $this->extractFields($row, $map);
                    $email = trim((string) ($fields['email'] ?? ''));
                    $normalized = mb_strtolower($email);

                    if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $invalid++;

                        continue;
                    }

                    if (isset($seen[$normalized]) || Lead::where('email_normalized', $normalized)->exists()) {
                        $duplicates++;

                        continue;
                    }

                    $seen[$normalized] = true;

                    $fields['email'] = $email;
                    $fields['email_normalized'] = $normalized;
                    $fields['source'] = 'import';
                    $fields['lead_import_id'] = $import->id;

                    if ($campaignId !== null) {
                        $fields['campaign_id'] = $campaignId;
                    }

                    if (blank($fields['company_domain'] ?? null)) {
                        $domain = $this->deriveDomain($normalized);
                        if ($domain !== null) {
                            $fields['company_domain'] = $domain;
                        }
                    }

                    Lead::create($fields);
                    $imported++;
                } catch (Throwable) {
                    $failed++;
                }
            }

            $import->update([
                'total_rows' => $total,
                'imported_count' => $imported,
                'duplicate_count' => $duplicates,
                'invalid_count' => $invalid,
                'failed_count' => $failed,
            ]);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<int, string>  $map
     * @return array<string, string>
     */
    private function extractFields(array $row, array $map): array
    {
        $fields = [];

        foreach ($map as $index => $field) {
            $value = trim((string) ($row[$index] ?? ''));

            if ($value !== '') {
                $fields[$field] = $value;
            }
        }

        return $fields;
    }

    private function deriveDomain(string $email): ?string
    {
        $at = strrpos($email, '@');

        if ($at === false) {
            return null;
        }

        $domain = substr($email, $at + 1);

        if ($domain === '' || in_array($domain, self::FREE_MAIL, true)) {
            return null;
        }

        return $domain;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $headers
     * @param  array<int, string>  $map
     * @return array<string, string>
     */
    private function mappingLabels(array $headers, array $map): array
    {
        $labels = [];

        foreach ($map as $index => $field) {
            $labels[$headers[$index] ?? "col{$index}"] = $field;
        }

        return $labels;
    }
}
