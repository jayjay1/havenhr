<?php

namespace App\Services;

use App\Models\Candidate;

class NotificationPreferenceService
{
    /**
     * Default notification preferences for new candidates.
     */
    public const DEFAULT_PREFERENCES = [
        'stage_change_emails' => true,
        'application_confirmation_emails' => true,
    ];

    /**
     * Allowed preference keys.
     */
    public const ALLOWED_KEYS = [
        'stage_change_emails',
        'application_confirmation_emails',
    ];

    /**
     * Get the notification preferences for a candidate, merged with defaults.
     *
     * @return array<string, bool>
     */
    public function getPreferences(Candidate $candidate): array
    {
        return array_merge(
            self::DEFAULT_PREFERENCES,
            $candidate->notification_preferences ?? [],
        );
    }

    /**
     * Update the notification preferences for a candidate.
     *
     * Only allowed keys are accepted; values are cast to boolean.
     *
     * @return array<string, bool>
     */
    public function updatePreferences(Candidate $candidate, array $preferences): array
    {
        $current = $this->getPreferences($candidate);

        foreach ($preferences as $key => $value) {
            if (in_array($key, self::ALLOWED_KEYS, true)) {
                $current[$key] = (bool) $value;
            }
        }

        $candidate->notification_preferences = $current;
        $candidate->save();

        return $current;
    }
}
