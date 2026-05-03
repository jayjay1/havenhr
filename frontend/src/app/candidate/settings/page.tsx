"use client";

import { useState, useEffect, useCallback } from "react";
import { candidateApiClient } from "@/lib/candidateApi";
import { ApiRequestError } from "@/lib/api";

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface NotificationPreferences {
  stage_change_emails: boolean;
  application_confirmation_emails: boolean;
}

// ---------------------------------------------------------------------------
// Toggle Switch
// ---------------------------------------------------------------------------

function ToggleSwitch({
  id,
  label,
  description,
  checked,
  disabled,
  onChange,
}: {
  id: string;
  label: string;
  description: string;
  checked: boolean;
  disabled?: boolean;
  onChange: (value: boolean) => void;
}) {
  return (
    <div className="flex items-center justify-between py-4">
      <div className="flex-1 min-w-0 pr-4">
        <label htmlFor={id} className="text-sm font-medium text-gray-900 cursor-pointer">
          {label}
        </label>
        <p className="text-xs text-gray-500 mt-0.5">{description}</p>
      </div>
      <button
        id={id}
        type="button"
        role="switch"
        aria-checked={checked}
        disabled={disabled}
        onClick={() => onChange(!checked)}
        className={`relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed ${
          checked ? "bg-teal-500" : "bg-gray-300"
        }`}
      >
        <span
          className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
            checked ? "translate-x-6" : "translate-x-1"
          }`}
        />
      </button>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Main Settings Page
// ---------------------------------------------------------------------------

export default function CandidateSettingsPage() {
  const [prefs, setPrefs] = useState<NotificationPreferences | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [saving, setSaving] = useState(false);
  const [successMessage, setSuccessMessage] = useState("");

  const fetchPreferences = useCallback(async () => {
    try {
      const response = await candidateApiClient.get<NotificationPreferences>(
        "/candidate/profile/notification-preferences"
      );
      setPrefs(response.data);
    } catch (err) {
      setError(
        err instanceof ApiRequestError
          ? err.message
          : "Failed to load notification preferences."
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchPreferences();
  }, [fetchPreferences]);

  async function handleToggle(
    key: keyof NotificationPreferences,
    value: boolean
  ) {
    if (!prefs) return;

    const previousPrefs = { ...prefs };
    const updatedPrefs = { ...prefs, [key]: value };

    // Optimistic update
    setPrefs(updatedPrefs);
    setSaving(true);
    setError("");
    setSuccessMessage("");

    try {
      await candidateApiClient.put(
        "/candidate/profile/notification-preferences",
        updatedPrefs as unknown as Record<string, unknown>
      );
      setSuccessMessage("Preferences saved.");
      // Clear success message after 3 seconds
      setTimeout(() => setSuccessMessage(""), 3000);
    } catch (err) {
      // Revert on failure
      setPrefs(previousPrefs);
      setError(
        err instanceof ApiRequestError
          ? err.message
          : "Failed to save preferences."
      );
    } finally {
      setSaving(false);
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <div
          className="inline-block h-6 w-6 animate-spin rounded-full border-4 border-teal-600 border-r-transparent"
          role="status"
          aria-label="Loading settings"
        />
        <span className="ml-2 text-sm text-gray-500">Loading settings…</span>
      </div>
    );
  }

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Settings</h1>
        <p className="mt-1 text-sm text-gray-500">
          Manage your notification preferences and account settings.
        </p>
      </div>

      {/* Error */}
      {error && (
        <div
          role="alert"
          className="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-700 mb-6"
        >
          {error}
        </div>
      )}

      {/* Success */}
      {successMessage && (
        <div
          role="status"
          className="rounded-md bg-green-50 border border-green-200 p-4 text-sm text-green-700 mb-6"
        >
          {successMessage}
        </div>
      )}

      {/* Notification Preferences */}
      <section className="bg-white rounded-lg border border-gray-200 p-6">
        <h2 className="text-lg font-semibold text-gray-900 mb-2">
          Email Notifications
        </h2>
        <p className="text-sm text-gray-500 mb-4">
          Choose which email notifications you&apos;d like to receive.
        </p>

        {prefs ? (
          <div className="divide-y divide-gray-100">
            <ToggleSwitch
              id="stage-change-emails"
              label="Stage Change Notifications"
              description="Receive an email when your application moves to a new pipeline stage."
              checked={prefs.stage_change_emails}
              disabled={saving}
              onChange={(val) => handleToggle("stage_change_emails", val)}
            />
            <ToggleSwitch
              id="application-confirmation-emails"
              label="Application Confirmation"
              description="Receive a confirmation email when you submit a new job application."
              checked={prefs.application_confirmation_emails}
              disabled={saving}
              onChange={(val) =>
                handleToggle("application_confirmation_emails", val)
              }
            />
          </div>
        ) : (
          <p className="text-sm text-gray-500">
            Unable to load notification preferences.
          </p>
        )}
      </section>
    </div>
  );
}
