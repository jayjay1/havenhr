"use client";

import { useState, type FormEvent } from "react";
import { FormInput } from "@/components/ui/FormInput";
import { Button } from "@/components/ui/Button";
import { FormError } from "@/components/ui/FormError";
import { apiClient, ApiRequestError } from "@/lib/api";
import type { ForgotPasswordResponse } from "@/types/auth";
import type { ValidationErrorDetails } from "@/types/api";

type FieldErrors = Record<string, string[]>;

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState("");
  const [loading, setLoading] = useState(false);
  const [formError, setFormError] = useState("");
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [submitted, setSubmitted] = useState(false);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setLoading(true);
    setFormError("");
    setFieldErrors({});

    try {
      await apiClient.post<ForgotPasswordResponse>("/auth/password/forgot", {
        email,
      });

      setSubmitted(true);
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
        } else {
          // The API always returns success for valid emails to prevent enumeration,
          // so treat non-422 errors as success too.
          setSubmitted(true);
        }
      } else {
        setFormError("An unexpected error occurred. Please try again.");
      }
    } finally {
      setLoading(false);
    }
  }

  if (submitted) {
    return (
      <main className="min-h-screen flex items-center justify-center bg-gray-50 px-4 py-8">
        <div className="w-full max-w-md bg-white rounded-lg shadow-sm border border-gray-200 p-6 sm:p-8">
          <h1 className="text-2xl font-bold text-gray-900 text-center mb-4">
            Check your email
          </h1>
          <p className="text-sm text-gray-600 text-center mb-6">
            If an account exists for <strong>{email}</strong>, we&apos;ve sent a
            password reset link. Please check your inbox.
          </p>
          <div className="text-center">
            <a
              href="/login"
              className="font-medium text-blue-600 hover:text-blue-500 focus:outline-none focus:underline text-sm"
            >
              Back to sign in
            </a>
          </div>
        </div>
      </main>
    );
  }

  return (
    <main className="min-h-screen flex items-center justify-center bg-gray-50 px-4 py-8">
      <div className="w-full max-w-md bg-white rounded-lg shadow-sm border border-gray-200 p-6 sm:p-8">
        <h1 className="text-2xl font-bold text-gray-900 text-center mb-2">
          Forgot your password?
        </h1>
        <p className="text-sm text-gray-600 text-center mb-6">
          Enter your email and we&apos;ll send you a reset link.
        </p>

        {formError && (
          <FormError id="form-error" messages={[formError]} />
        )}

        <form onSubmit={handleSubmit} noValidate className="space-y-4 mt-4">
          <FormInput
            id="email"
            name="email"
            label="Email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            error={fieldErrors.email}
            required
            autoComplete="email"
          />

          <div className="pt-2">
            <Button type="submit" loading={loading}>
              Send reset link
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
