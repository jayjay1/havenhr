"use client";

import { useState, type FormEvent } from "react";
import { useRouter, useParams } from "next/navigation";
import { PasswordInput } from "@/components/ui/PasswordInput";
import { Button } from "@/components/ui/Button";
import { FormError } from "@/components/ui/FormError";
import { apiClient, ApiRequestError } from "@/lib/api";
import type { ResetPasswordResponse } from "@/types/auth";
import type { ValidationErrorDetails } from "@/types/api";

type FieldErrors = Record<string, string[]>;

export default function ResetPasswordPage() {
  const router = useRouter();
  const params = useParams<{ token: string }>();
  const token = params.token;

  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");

  const [loading, setLoading] = useState(false);
  const [formError, setFormError] = useState("");
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setLoading(true);
    setFormError("");
    setFieldErrors({});

    try {
      await apiClient.post<ResetPasswordResponse>("/auth/password/reset", {
        token,
        password,
        password_confirmation: passwordConfirmation,
      });

      router.push("/login?reset=true");
    } catch (err) {
      if (err instanceof ApiRequestError) {
        if (err.status === 422) {
          const details = err.details as unknown as ValidationErrorDetails;
          if (details?.fields) {
            const mapped: FieldErrors = {};
            for (const [field, info] of Object.entries(details.fields)) {
              mapped[field] = info.messages;
            }
            setFieldErrors(mapped);
          }
        } else if (err.status === 400 || err.status === 401) {
          // Expired, already used, or invalid token
          setFormError(
            err.message || "This reset link is invalid or has expired."
          );
        } else {
          setFormError(err.message || "An unexpected error occurred.");
        }
      } else {
        setFormError("An unexpected error occurred. Please try again.");
      }
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="min-h-screen flex items-center justify-center bg-gray-50 px-4 py-8">
      <div className="w-full max-w-md bg-white rounded-lg shadow-sm border border-gray-200 p-6 sm:p-8">
        <h1 className="text-2xl font-bold text-gray-900 text-center mb-6">
          Reset your password
        </h1>

        {formError && (
          <FormError id="form-error" messages={[formError]} />
        )}

        <form onSubmit={handleSubmit} noValidate className="space-y-4 mt-4">
          <PasswordInput
            id="password"
            name="password"
            label="New password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            error={fieldErrors.password}
            required
            autoComplete="new-password"
            showComplexity
          />

          <PasswordInput
            id="password_confirmation"
            name="password_confirmation"
            label="Confirm password"
            value={passwordConfirmation}
            onChange={(e) => setPasswordConfirmation(e.target.value)}
            error={fieldErrors.password_confirmation}
            required
            autoComplete="new-password"
          />

          <div className="pt-2">
            <Button type="submit" loading={loading}>
              Reset password
            </Button>
          </div>
        </form>

        <p className="mt-6 text-center text-sm text-gray-600">
          <a
            href="/login"
            className="font-medium text-blue-600 hover:text-blue-500 focus:outline-none focus:underline"
          >
            Back to sign in
          </a>
        </p>
      </div>
    </main>
  );
}
