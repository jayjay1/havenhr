import React from "react";
import { FormError } from "./FormError";

export interface FormInputProps {
  /** Unique id for the input element */
  id: string;
  /** Name attribute for the input */
  name: string;
  /** Visible label text */
  label: string;
  /** Input type (text, email, etc.) */
  type?: string;
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
}

/**
 * Accessible form input with label association, error display, and WCAG 2.1 AA support.
 */
export function FormInput({
  id,
  name,
  label,
  type = "text",
  placeholder,
  value,
  onChange,
  error,
  required = false,
  disabled = false,
  autoComplete,
}: FormInputProps) {
  const errorMessages = normalizeError(error);
  const hasError = errorMessages.length > 0;
  const errorId = `${id}-error`;

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
      <input
        id={id}
        name={name}
        type={type}
        placeholder={placeholder}
        value={value}
        onChange={onChange}
        required={required}
        disabled={disabled}
        autoComplete={autoComplete}
        aria-invalid={hasError ? "true" : undefined}
        aria-describedby={hasError ? errorId : undefined}
        className={`
          block w-full rounded-md border px-3 py-2 text-sm
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
      {hasError && <FormError id={errorId} messages={errorMessages} />}
    </div>
  );
}

/** Normalizes error prop to a string array */
function normalizeError(error?: string | string[]): string[] {
  if (!error) return [];
  if (Array.isArray(error)) return error;
  return [error];
}
