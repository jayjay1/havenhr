import React from "react";

export interface ButtonProps {
  /** Button type attribute */
  type?: "button" | "submit" | "reset";
  /** Visual variant */
  variant?: "primary" | "secondary" | "danger";
  /** Size */
  size?: "sm" | "md" | "lg";
  /** Show loading spinner and disable interaction */
  loading?: boolean;
  /** Disable the button */
  disabled?: boolean;
  /** Button content */
  children: React.ReactNode;
  /** Click handler */
  onClick?: (e: React.MouseEvent<HTMLButtonElement>) => void;
}

const variantClasses: Record<string, string> = {
  primary:
    "bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-600 disabled:bg-blue-300",
  secondary:
    "bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 focus:ring-blue-600 disabled:bg-gray-100 disabled:text-gray-400",
  danger:
    "bg-red-600 text-white hover:bg-red-700 focus:ring-red-600 disabled:bg-red-300",
};

const sizeClasses: Record<string, string> = {
  sm: "px-3 py-1.5 text-sm",
  md: "px-4 py-2 text-sm",
  lg: "px-6 py-3 text-base",
};

/**
 * Accessible button with loading state, variant styling, and mobile-first sizing.
 * Full width on mobile, auto width on desktop (sm breakpoint).
 */
export function Button({
  type = "button",
  variant = "primary",
  size = "md",
  loading = false,
  disabled = false,
  children,
  onClick,
}: ButtonProps) {
  const isDisabled = disabled || loading;

  return (
    <button
      type={type}
      onClick={onClick}
      disabled={isDisabled}
      aria-busy={loading ? "true" : undefined}
      className={`
        inline-flex items-center justify-center gap-2
        w-full sm:w-auto
        rounded-md font-medium
        focus:outline-none focus:ring-2 focus:ring-offset-1
        disabled:cursor-not-allowed
        transition-colors
        ${variantClasses[variant]}
        ${sizeClasses[size]}
      `}
    >
      {loading && <Spinner />}
      {children}
    </button>
  );
}

function Spinner() {
  return (
    <svg
      className="animate-spin h-4 w-4"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
      aria-hidden="true"
      data-testid="spinner"
    >
      <circle
        className="opacity-25"
        cx="12"
        cy="12"
        r="10"
        stroke="currentColor"
        strokeWidth="4"
      />
      <path
        className="opacity-75"
        fill="currentColor"
        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
      />
    </svg>
  );
}
