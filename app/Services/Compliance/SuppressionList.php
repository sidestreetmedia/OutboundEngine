<?php

namespace App\Services\Compliance;

use App\Models\Suppression;
use Illuminate\Support\Carbon;

/**
 * The do-not-contact list. Bounces, unsubscribes, and manual entries all land
 * here, and the push step checks it before handing any lead to a sender — so a
 * suppressed address stays suppressed even if it's re-imported later.
 */
class SuppressionList
{
    public function suppress(string $value, string $type, string $reason): Suppression
    {
        return Suppression::updateOrCreate(
            ['type' => $type, 'value' => $this->normalize($value)],
            ['reason' => $reason, 'suppressed_at' => Carbon::now()],
        );
    }

    public function suppressEmail(string $email, string $reason): Suppression
    {
        return $this->suppress($email, Suppression::TYPE_EMAIL, $reason);
    }

    public function suppressDomain(string $domain, string $reason): Suppression
    {
        return $this->suppress($domain, Suppression::TYPE_DOMAIN, $reason);
    }

    /**
     * True if the address itself is suppressed, or its whole domain is.
     */
    public function isSuppressed(string $email): bool
    {
        $email = $this->normalize($email);

        if ($email === '') {
            return false;
        }

        if (Suppression::where('type', Suppression::TYPE_EMAIL)->where('value', $email)->exists()) {
            return true;
        }

        $at = strrpos($email, '@');

        if ($at !== false) {
            $domain = substr($email, $at + 1);

            if ($domain !== ''
                && Suppression::where('type', Suppression::TYPE_DOMAIN)->where('value', $domain)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
