"use client";

import { useState, useEffect, useCallback } from "react";
import { apiClient, ApiRequestError } from "@/lib/api";

interface CompanyData {
  id: string;
  name: string;
  email_domain: string;
  subscription_status: string;
  settings: Record<string, unknown>;
}

export default function SettingsPage() {
  const [company, setCompany] = useState<CompanyData | null>(null);
  const [companyName, setCompanyName] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  const fetchCompany = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const response = await apiClient.get<CompanyData>("/company");
      setCompany(response.data);
      setCompanyName(response.data.name);
    } catch (err) {
      if (err instanceof ApiRequestError) {
        setError(err.message || "Failed to load company settings.");
      } else {
        setError("An unexpected error occurred.");
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchCompany();
  }, [fetchCompany]);

  async function handleSave(e: React.FormEvent) {
    e.preventDefault();
    setError("");
    setSuccess("");
    setSaving(true);

    try {
      const response = await apiClient.put<CompanyData>("/company", {
        name: companyName,
      });
      setCompany(response.data);
      setCompanyName(response.data.name);
      setSuccess("Company settings saved successfully.");
    } catch (err) {
      if (err instanceof ApiRequestError) {
        setError(err.message || "Failed to save company settings.");
      } else {
        setError("An unexpected error occurred.");
      }
    } finally {
      setSaving(false);
    }
  }

  const comingSoonSections = [
    {
      title: "Logo Upload",
      description: "Upload your company logo to personalize your workspace.",
    },
    {
      title: "Branding Colors",
      description:
        "Customize the color scheme to match your company branding.",
    },
    {
      title: "Notification Preferences",
      description:
        "Configure email and in-app notification settings for your team.",
    },
  ];

  if (loading) {
    return (
      <div>
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-900">Settings</h1>
          <p className="mt-1 text-sm text-gray-500">
            Manage your company settings.
          </p>
        </div>
        <div className="flex items-center justify-center py-12">
          <div
            className="inline-block h-8 w-8 animate-spin rounded-full border-4 border-blue-600 border-r-transparent"
            role="status"
            aria-label="Loading settings"
          />
        </div>
      </div>
    );
  }

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Settings</h1>
        <p className="mt-1 text-sm text-gray-500">
          Manage your company settings.
        </p>
      </div>

      {/* Company Information */}
      <div className="bg-white rounded-lg border border-gray-200 p-6 mb-6">
        <h2 className="text-lg font-semibold text-gray-900 mb-4">
          Company Information
        </h2>

        {success && (
          <div
            role="status"
            className="mb-4 rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-700"
          >
            {success}
          </div>
        )}

        {error && (
          <div
            role="alert"
            className="mb-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700"
          >
            {error}
          </div>
        )}

        <form onSubmit={handleSave}>
          <div className="space-y-4">
            <div>
              <label
                htmlFor="company-name"
                className="block text-sm font-medium text-gray-700 mb-1"
              >
                Company Name
              </label>
              <input
                id="company-name"
                type="text"
                value={companyName}
                onChange={(e) => setCompanyName(e.target.value)}
                required
                className="w-full max-w-md rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
            </div>

            <div>
              <label
                htmlFor="email-domain"
                className="block text-sm font-medium text-gray-700 mb-1"
              >
                Email Domain
              </label>
              <input
                id="email-domain"
                type="text"
                value={company?.email_domain || ""}
                readOnly
                className="w-full max-w-md rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500 cursor-not-allowed"
              />
              <p className="mt-1 text-xs text-gray-400">
                Email domain cannot be changed.
              </p>
            </div>
          </div>

          <div className="mt-6">
            <button
              type="submit"
              disabled={saving}
              className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {saving ? (
                <span className="flex items-center gap-2">
                  <span
                    className="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-r-transparent"
                    role="status"
                    aria-label="Saving"
                  />
                  Saving...
                </span>
              ) : (
                "Save"
              )}
            </button>
          </div>
        </form>
      </div>

      {/* Coming Soon Sections */}
      <div className="space-y-4">
        {comingSoonSections.map((section) => (
          <div
            key={section.title}
            className="bg-white rounded-lg border border-gray-200 p-6 opacity-75"
          >
            <div className="flex items-center gap-3 mb-2">
              <h2 className="text-lg font-semibold text-gray-900">
                {section.title}
              </h2>
              <span className="inline-flex items-center rounded-full bg-yellow-100 text-yellow-800 px-2.5 py-0.5 text-xs font-medium">
                Coming Soon
              </span>
            </div>
            <p className="text-sm text-gray-500">{section.description}</p>
          </div>
        ))}
      </div>
    </div>
  );
}
