"use client";

import { useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { FormInput } from "@/components/ui/FormInput";
import { PasswordInput } from "@/components/ui/PasswordInput";
import { Button } from "@/components/ui/Button";
import { FormError } from "@/components/ui/FormError";
import { apiClient, ApiRequestError } from "@/lib/api";
import type { RegisterResponse } from "@/types/auth";
import type { ValidationErrorDetails } from "@/types/api";

type FieldErrors = Record<string, string[]>;

export default function RegisterPage() {
  const router = useRouter();

  const [companyName, setCompanyName] = useState("");
  const [companyEmailDomain, setCompanyEmailDomain] = useState("");
  const [ownerName, setOwnerName] = useState("");
  const [ownerEmail, setOwnerEmail] = useState("");
  const [ownerPassword, setOwnerPassword] = useState("");

  const [loading, setLoading] = useState(false);
  const [formError, setFormError] = useState("");
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setLoading(true);
    setFormError("");
    setFieldErrors({});

    try {
      await apiClient.post<RegisterResponse>("/register", {
        company_name: companyName,
        company_email_domain: companyEmailDomain,
        owner_name: ownerName,
        owner_email: ownerEmail,
        owner_password: ownerPassword,
      });

      router.push("/login?registered=true");
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
          setFormError(err.message || "This email domain is already registered.");
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
          Register your company
        </h1>

        {formError && (
          <FormError id="form-error" messages={[formError]} />
        )}

        <form onSubmit={handleSubmit} noValidate className="space-y-4 mt-4">
          <FormInput
            id="company_name"
            name="company_name"
            label="Company name"
            value={companyName}
            onChange={(e) => setCompanyName(e.target.value)}
            error={fieldErrors.company_name}
            required
            autoComplete="organization"
          />

          <FormInput
            id="company_email_domain"
            name="company_email_domain"
            label="Company email domain"
            placeholder="example.com"
            value={companyEmailDomain}
            onChange={(e) => setCompanyEmailDomain(e.target.value)}
            error={fieldErrors.company_email_domain}
            required
          />

          <FormInput
            id="owner_name"
            name="owner_name"
            label="Your name"
            value={ownerName}
            onChange={(e) => setOwnerName(e.target.value)}
            error={fieldErrors.owner_name}
            required
            autoComplete="name"
          />

          <FormInput
            id="owner_email"
            name="owner_email"
            label="Your email"
            type="email"
            value={ownerEmail}
            onChange={(e) => setOwnerEmail(e.target.value)}
            error={fieldErrors.owner_email}
            required
            autoComplete="email"
          />

          <PasswordInput
            id="owner_password"
            name="owner_password"
            label="Password"
            value={ownerPassword}
            onChange={(e) => setOwnerPassword(e.target.value)}
            error={fieldErrors.owner_password}
            required
            autoComplete="new-password"
            showComplexity
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
            href="/login"
            className="font-medium text-blue-600 hover:text-blue-500 focus:outline-none focus:underline"
          >
            Sign in
          </a>
        </p>
      </div>
    </main>
  );
}
