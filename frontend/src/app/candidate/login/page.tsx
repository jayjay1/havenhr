"use client";

import { useState, type FormEvent } from "react";
import { useSearchParams } from "next/navigation";
import { FormInput } from "@/components/ui/FormInput";
import { PasswordInput } from "@/components/ui/PasswordInput";
import { Button } from "@/components/ui/Button";
import { FormError } from "@/components/ui/FormError";
import { useCandidateAuth } from "@/contexts/CandidateAuthContext";
import { ApiRequestError } from "@/lib/api";
import type { ValidationErrorDetails } from "@/types/api";

type FieldErrors = Record<string, string[]>;

export default function CandidateLoginPage() {
  const { login } = useCandidateAuth();
  const searchParams = useSearchParams();
  const registered = searchParams.get("registered") === "true";

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");

  const [loading, setLoading] = useState(false);
  const [formError, setFormError] = useState("");
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setLoading(true);
    setFormError("");
    setFieldErrors({});

    try {
      await login({ email, password });
      // Redirect is handled by the context
    } catch (err) {
      if (err instanceof ApiRequestError) {
        if (err.status === 401) {
          setFormError("Invalid credentials");
        } else if (err.status === 422) {
          const details = err.details as unknown as ValidationErrorDetails;
          if (details?.fields) {
            const mapped: FieldErrors = {};
            for (const [field, info] of Object.entries(details.fields)) {
              mapped[field] = info.messages;
            }
            setFieldErrors(mapped);
          }
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
          Sign in to your account
        </h1>

        {registered && (
          <div
            role="status"
            className="mb-4 rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-700"
          >
            Registration successful. Please sign in.
          </div>
        )}

        {formError && <FormError id="form-error" messages={[formError]} />}

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

          <PasswordInput
            id="password"
            name="password"
            label="Password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            error={fieldErrors.password}
            required
            autoComplete="current-password"
          />

          <div className="pt-2">
            <Button type="submit" loading={loading}>
              Sign in
            </Button>
          </div>
        </form>

        <p className="mt-6 text-center text-sm text-gray-600">
          Don&apos;t have an account?{" "}
          <a
            href="/candidate/register"
            className="font-medium text-teal-600 hover:text-teal-500 focus:outline-none focus:underline"
          >
            Create account
          </a>
        </p>
      </div>
    </main>
  );
}
