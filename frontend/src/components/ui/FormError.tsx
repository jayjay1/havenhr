import React from "react";

export interface FormErrorProps {
  /** Unique id for the error container, used for aria-describedby association */
  id: string;
  /** One or more error messages to display */
  messages: string[];
}

/**
 * Displays one or more inline validation error messages.
 * Uses role="alert" so screen readers announce errors immediately.
 */
export function FormError({ id, messages }: FormErrorProps) {
  if (messages.length === 0) return null;

  return (
    <div id={id} role="alert" className="mt-1 space-y-0.5">
      {messages.map((message, index) => (
        <p key={index} className="text-sm text-red-600">
          {message}
        </p>
      ))}
    </div>
  );
}
