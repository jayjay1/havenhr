"use client";

import { useState, type FormEvent } from "react";
import { FormInput } from "@/components/ui/FormInput";
import { PasswordInput } from "@/components/ui/PasswordInput";
import { Button } from "@/components/ui/Button";
import { FormError } from "@/components/ui/FormError";
import { useCandidateAuth } from "@/contexts/CandidateAuthContext";
import { ApiRequestError } from "@/lib/api";
import type { ValidationErrorDetails } from "@/types/api";

type FieldErrors = Record<string, string[]>;

export default function CandidateRegisterPage() {
  const { register } = useCandidateAuth();

  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");

  const [loading, setLoading] = useState(false);
  const [formError, setFormError] = useState("");
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setLoading(true);
    setFormError("");
    setFieldErrors({});

    // Client-side confirm password check
    if (password !== confirmPassword) {
      setFieldErrors({ confirm_password: ["Passwords do not match."] });
      setLoading(false);
      return;
    }

    try {
      await register({ name, email, password });
      // Redirect is handled by the context
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
        } else if (err.status === 409) {
          setFormError(
            err.message || "An account with this email already exists."
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
        <h1 className="text-2xl font-bold text-gray-900 text-center mb-2">
          Create your account
        </h1>
        <p className="text-sm text-gray-500 text-center mb-6">
          Build professional resumes with AI assistance
        </p>

        {formError && <FormError id="form-error" messages={[formError]} />}

        <form onSubmit={handleSubmit} noValidate className="space-y-4 mt-4">
          <FormInput
            id="name"
            name="name"
            label="Full name"
            value={name}
            onChange={(e) => setName(e.target.value)}
            error={fieldErrors.name}
            required
            autoComplete="name"
          />

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
            autoComplete="new-password"
            showComplexity
          />

          <PasswordInput
            id="confirm_password"
            name="confirm_password"
            label="Confirm password"
            value={confirmPassword}
            onChange={(e) => setConfirmPassword(e.target.value)}
            error={fieldErrors.confirm_password}
            required
            autoComplete="new-password"
          />

          <div className="pt-2">
            <Button type="submit" loading={loading}>
              Create account
            </Button>
          </div>
        </form>

        <p className="mt-6 text-center text-sm text-gray-600">
          Already have an account?{" "}
          <a
            href="/candidate/login"
            className="font-medium text-teal-600 hover:text-teal-500 focus:outline-none focus:underline"
          >
            Sign in
          </a>
        </p>
      </div>
    </main>
  );
}
