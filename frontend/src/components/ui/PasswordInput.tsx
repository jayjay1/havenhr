import React, { useState, useMemo } from "react";
import { FormError } from "./FormError";

export interface PasswordInputProps {
  /** Unique id for the input element */
  id: string;
  /** Name attribute for the input */
  name: string;
  /** Visible label text */
  label: string;
  /** Placeholder text */
  placeholder?: string;
  /** Current input value */
  value: string;
  /** Change handler */
  onChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
  /** Validation error — single string or array of strings */
  error?: string | string[];
  /** Whether the field is required */
  required?: boolean;
  /** Whether the field is disabled */
  disabled?: boolean;
  /** Autocomplete hint */
  autoComplete?: string;
  /** Show password complexity indicator */
  showComplexity?: boolean;
}

interface ComplexityRule {
  label: string;
  test: (value: string) => boolean;
}

const complexityRules: ComplexityRule[] = [
  { label: "At least 12 characters", test: (v) => v.length >= 12 },
  { label: "One uppercase letter", test: (v) => /[A-Z]/.test(v) },
  { label: "One lowercase letter", test: (v) => /[a-z]/.test(v) },
  { label: "One digit", test: (v) => /\d/.test(v) },
  { label: "One special character", test: (v) => /[^A-Za-z0-9]/.test(v) },
];

/**
 * Password input with show/hide toggle and optional complexity indicator.
 * Extends FormInput accessibility features: label association, aria-describedby, aria-invalid.
 */
export function PasswordInput({
  id,
  name,
  label,
  placeholder,
  value,
  onChange,
  error,
  required = false,
  disabled = false,
  autoComplete,
  showComplexity = false,
}: PasswordInputProps) {
  const [visible, setVisible] = useState(false);

  const errorMessages = normalizeError(error);
  const hasError = errorMessages.length > 0;
  const errorId = `${id}-error`;
  const complexityId = `${id}-complexity`;

  const describedByParts: string[] = [];
  if (hasError) describedByParts.push(errorId);
  if (showComplexity) describedByParts.push(complexityId);
  const describedBy =
    describedByParts.length > 0 ? describedByParts.join(" ") : undefined;

  const complexityResults = useMemo(
    () =>
      complexityRules.map((rule) => ({
        ...rule,
        met: rule.test(value),
      })),
    [value]
  );

  return (
    <div className="w-full">
      <label
        htmlFor={id}
        className="block text-sm font-medium text-gray-700 mb-1"
      >
        {label}
        {required && (
          <span className="text-red-600 ml-0.5" aria-hidden="true">
            *
          </span>
        )}
      </label>
      <div className="relative">
        <input
          id={id}
          name={name}
          type={visible ? "text" : "password"}
          placeholder={placeholder}
          value={value}
          onChange={onChange}
          required={required}
          disabled={disabled}
          autoComplete={autoComplete}
          aria-invalid={hasError ? "true" : undefined}
          aria-describedby={describedBy}
          className={`
            block w-full rounded-md border px-3 py-2 pr-10 text-sm
            placeholder:text-gray-400
            focus:outline-none focus:ring-2 focus:ring-offset-1
            disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500
            ${
              hasError
                ? "border-red-500 focus:ring-red-500"
                : "border-gray-300 focus:ring-blue-600 focus:border-blue-600"
            }
          `}
        />
        <button
          type="button"
          onClick={() => setVisible((prev) => !prev)}
          aria-label={visible ? "Hide password" : "Show password"}
          className="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-1 rounded"
        >
          {visible ? <EyeOffIcon /> : <EyeIcon />}
        </button>
      </div>

      {hasError && <FormError id={errorId} messages={errorMessages} />}

      {showComplexity && value.length > 0 && (
        <ul id={complexityId} className="mt-2 space-y-1" aria-label="Password requirements">
          {complexityResults.map((rule) => (
            <li
              key={rule.label}
              className={`flex items-center gap-1.5 text-xs ${
                rule.met ? "text-green-600" : "text-gray-400"
              }`}
            >
              <span aria-hidden="true">{rule.met ? "✓" : "○"}</span>
              <span>
                {rule.label}
                {rule.met ? (
                  <span className="sr-only"> — met</span>
                ) : (
                  <span className="sr-only"> — not met</span>
                )}
              </span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

/** Normalizes error prop to a string array */
function normalizeError(error?: string | string[]): string[] {
  if (!error) return [];
  if (Array.isArray(error)) return error;
  return [error];
}

/* ---- Inline SVG icons (16×16) ---- */

function EyeIcon() {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      width="16"
      height="16"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
      <circle cx="12" cy="12" r="3" />
    </svg>
  );
}

function EyeOffIcon() {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      width="16"
      height="16"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94" />
      <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19" />
      <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24" />
      <line x1="1" y1="1" x2="23" y2="23" />
    </svg>
  );
}
