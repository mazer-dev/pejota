<?php

namespace App\Services\Evolution;

/**
 * Normalizes WhatsApp JIDs/numbers for allowlist matching. Brazilian mobile
 * numbers appear inconsistently with and without the ninth digit
 * (555499371490 ⇄ 5554999371490 — 12 vs 13 digits), so both spellings of a
 * number are generated and the allowlist check passes when ANY candidate of
 * the sender intersects ANY candidate of an allowlisted entry. Group JIDs
 * are rejected outright, and `@lid` JIDs (which carry no phone number)
 * resolve the number via the payload's top-level `sender` field — the same
 * rule the client-facing webhook handler already applies.
 */
class WhatsappJidNormalizer
{
    public function digits(string $value): string
    {
        return (string) preg_replace('/\D+/', '', str($value)->before('@')->toString());
    }

    /**
     * All plausible spellings of a Brazilian number: with and without the
     * ninth mobile digit. Non-BR numbers (or anything not matching the BR
     * shape) return just their own digits.
     *
     * @return array<int, string>
     */
    public function candidates(string $value): array
    {
        $digits = $this->digits($value);

        if ($digits === '') {
            return [];
        }

        $candidates = [$digits];

        if (str_starts_with($digits, '55')) {
            if (strlen($digits) === 13 && $digits[4] === '9') {
                // 55 + DDD + 9XXXXXXXX -> drop the ninth digit.
                $candidates[] = substr($digits, 0, 4).substr($digits, 5);
            } elseif (strlen($digits) === 12) {
                // 55 + DDD + XXXXXXXX -> add the ninth digit.
                $candidates[] = substr($digits, 0, 4).'9'.substr($digits, 4);
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Resolves the sender's phone number from a webhook payload + message
     * data, handling `@lid` JIDs (no embedded number) via payload['sender'].
     * Group JIDs never resolve.
     */
    public function senderNumber(array $payload, array $messageData): ?string
    {
        $jid = (string) (data_get($messageData, 'key.remoteJid')
            ?: data_get($messageData, 'remoteJid')
            ?: data_get($payload, 'sender')
            ?: '');

        if ($jid === '' || str_contains($jid, '@g.us')) {
            return null;
        }

        $candidate = str_contains($jid, '@lid')
            ? (string) ($payload['sender'] ?? '')
            : $jid;

        if (str_contains($candidate, '@g.us')) {
            return null;
        }

        $digits = $this->digits($candidate);

        return $digits === '' ? null : $digits;
    }

    /**
     * @param  array<int, string>  $allowedNumbers
     */
    public function isAllowed(?string $number, array $allowedNumbers): bool
    {
        if ($number === null || $number === '') {
            return false;
        }

        $senderCandidates = $this->candidates($number);

        foreach ($allowedNumbers as $allowed) {
            foreach ($this->candidates((string) $allowed) as $allowedCandidate) {
                if (in_array($allowedCandidate, $senderCandidates, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
