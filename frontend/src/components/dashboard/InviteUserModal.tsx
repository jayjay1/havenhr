"use client";

import { useState, useEffect, useCallback } from "react";
import { apiClient, ApiRequestError } from "@/lib/api";
import type { Role } from "@/types/permission";

interface InviteUserModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

interface RolesApiResponse {
  data: Role[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

/**
 * Generate a secure 16-character temporary password containing
 * uppercase, lowercase, digits, and special characters.
 */
export function generateTemporaryPassword(): string {
  const uppercase = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
  const lowercase = "abcdefghijklmnopqrstuvwxyz";
  const digits = "0123456789";
  const special = "!@#$%^&*";
  const allChars = uppercase + lowercase + digits + special;

  const array = new Uint32Array(16);
  crypto.getRandomValues(array);

  // Ensure at least one of each required type
  const required = [
    uppercase[array[0] % uppercase.length],
    lowercase[array[1] % lowercase.length],
    digits[array[2] % digits.length],
    special[array[3] % special.length],
  ];

  // Fill remaining characters from the full set
  const remaining: string[] = [];
  for (let i = 4; i < 16; i++) {
    remaining.push(allChars[array[i] % allChars.length]);
  }

  // Combine and shuffle using Fisher-Yates
  const combined = [...required, ...remaining];
  const shuffleArray = new Uint32Array(combined.length);
  crypto.getRandomValues(shuffleArray);
  for (let i = combined.length - 1; i > 0; i--) {
    const j = shuffleArray[i] % (i + 1);
    [combined[i], combined[j]] = [combined[j], combined[i]];
  }

  return combined.join("");
}

export function InviteUserModal({
  isOpen,
  onClose,
  onSuccess,
}: InviteUserModalProps) {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [roleId, setRoleId] = useState("");
  const [roles, setRoles] = useState<Role[]>([]);
  const [rolesLoading, setRolesLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [successData, setSuccessData] = useState<{
    email: string;
    password: string;
  } | null>(null);
  const [copied, setCopied] = useState(false);

  const fetchRoles = useCallback(async () => {
    setRolesLoading(true);
    try {
      const response = await apiClient.get<RolesApiResponse>("/roles");
      const rolesData = response.data;
      if (Array.isArray(rolesData)) {
        setRoles(rolesData);
      } else if (rolesData && Array.isArray(rolesData.data)) {
        setRoles(rolesData.data);
      }
    } catch {
      // Silently fail — user can still type a role or retry
    } finally {
      setRolesLoading(false);
    }
  }, []);

  useEffect(() => {
    if (isOpen) {
      fetchRoles();
      // Reset form state when opening
      setName("");
      setEmail("");
      setRoleId("");
      setError("");
      setSuccessData(null);
      setCopied(false);
    }
  }, [isOpen, fetchRoles]);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError("");

    if (!name.trim()) {
      setError("Name is required.");
      return;
    }
    if (!email.trim()) {
      setError("Email is required.");
      return;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      setError("Please enter a valid email address.");
      return;
    }

    setSubmitting(true);
    const tempPassword = generateTemporaryPassword();

    try {
      const body: Record<string, unknown> = {
        name: name.trim(),
        email: email.trim(),
        password: tempPassword,
      };
      if (roleId) {
        body.role_id = roleId;
      }

      await apiClient.post("/users", body);
      setSuccessData({ email: email.trim(), password: tempPassword });
      onSuccess();
    } catch (err) {
      if (err instanceof ApiRequestError) {
        if (err.status === 409) {
          setError("A user with this email already exists.");
        } else {
          setError(err.message || "Failed to create user.");
        }
      } else {
        setError("An unexpected error occurred.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  async function handleCopyPassword() {
    if (successData) {
      try {
        await navigator.clipboard.writeText(successData.password);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
      } catch {
        // Clipboard API may not be available
      }
    }
  }

  if (!isOpen) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center"
      role="dialog"
      aria-modal="true"
      aria-labelledby="invite-user-title"
    >
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/50"
        aria-hidden="true"
        onClick={onClose}
      />

      {/* Modal */}
      <div className="relative bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6">
        <h2
          id="invite-user-title"
          className="text-lg font-semibold text-gray-900 mb-4"
        >
          Invite User
        </h2>

        {successData ? (
          <div>
            <div className="rounded-md bg-green-50 border border-green-200 p-4 mb-4">
              <p className="text-sm font-medium text-green-800">
                User invited successfully!
              </p>
              <p className="text-sm text-green-700 mt-1">
                An account has been created for{" "}
                <span className="font-medium">{successData.email}</span>.
              </p>
              <div className="mt-3">
                <p className="text-xs text-green-600 mb-1">
                  Temporary Password:
                </p>
                <div className="flex items-center gap-2">
                  <code className="flex-1 bg-white border border-green-200 rounded px-3 py-1.5 text-sm font-mono text-gray-900 select-all">
                    {successData.password}
                  </code>
                  <button
                    type="button"
                    onClick={handleCopyPassword}
                    className="shrink-0 rounded-md bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-600"
                  >
                    {copied ? "Copied!" : "Copy"}
                  </button>
                </div>
              </div>
            </div>
            <button
              type="button"
              onClick={onClose}
              className="w-full rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-600"
            >
              Close
            </button>
          </div>
        ) : (
          <form onSubmit={handleSubmit}>
            {error && (
              <div
                role="alert"
                className="mb-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700"
              >
                {error}
              </div>
            )}

            <div className="space-y-4">
              <div>
                <label
                  htmlFor="invite-name"
                  className="block text-sm font-medium text-gray-700 mb-1"
                >
                  Name <span className="text-red-500">*</span>
                </label>
                <input
                  id="invite-name"
                  type="text"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  required
                  className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  placeholder="Full name"
                />
              </div>

              <div>
                <label
                  htmlFor="invite-email"
                  className="block text-sm font-medium text-gray-700 mb-1"
                >
                  Email <span className="text-red-500">*</span>
                </label>
                <input
                  id="invite-email"
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  required
                  className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  placeholder="user@example.com"
                />
              </div>

              <div>
                <label
                  htmlFor="invite-role"
                  className="block text-sm font-medium text-gray-700 mb-1"
                >
                  Role
                </label>
                <select
                  id="invite-role"
                  value={roleId}
                  onChange={(e) => setRoleId(e.target.value)}
                  disabled={rolesLoading}
                  className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400"
                >
                  <option value="">
                    {rolesLoading ? "Loading roles..." : "Select a role"}
                  </option>
                  {roles.map((role) => (
                    <option key={role.id} value={role.id}>
                      {role.name
                        .split("_")
                        .map(
                          (w) => w.charAt(0).toUpperCase() + w.slice(1)
                        )
                        .join(" ")}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            <div className="mt-6 flex items-center justify-end gap-3">
              <button
                type="button"
                onClick={onClose}
                className="rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-600"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={submitting}
                className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {submitting ? (
                  <span className="flex items-center gap-2">
                    <span
                      className="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-r-transparent"
                      role="status"
                      aria-label="Submitting"
                    />
                    Inviting...
                  </span>
                ) : (
                  "Invite User"
                )}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}
