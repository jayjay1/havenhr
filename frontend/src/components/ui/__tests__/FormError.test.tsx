import { render, screen } from "@testing-library/react";
import { describe, it, expect } from "vitest";
import { FormError } from "../FormError";

describe("FormError", () => {
  it("renders nothing when messages array is empty", () => {
    const { container } = render(<FormError id="err" messages={[]} />);
    expect(container.firstChild).toBeNull();
  });

  it("renders a single error message", () => {
    render(<FormError id="email-error" messages={["Email is required"]} />);
    expect(screen.getByText("Email is required")).toBeInTheDocument();
  });

  it("renders multiple error messages", () => {
    const messages = ["Too short", "Must contain a digit"];
    render(<FormError id="pw-error" messages={messages} />);
    expect(screen.getByText("Too short")).toBeInTheDocument();
    expect(screen.getByText("Must contain a digit")).toBeInTheDocument();
  });

  it("has role='alert' for screen reader announcement", () => {
    render(<FormError id="err" messages={["Error"]} />);
    expect(screen.getByRole("alert")).toBeInTheDocument();
  });

  it("has the correct id for aria-describedby association", () => {
    render(<FormError id="name-error" messages={["Required"]} />);
    const alert = screen.getByRole("alert");
    expect(alert).toHaveAttribute("id", "name-error");
  });

  it("displays error text in red", () => {
    render(<FormError id="err" messages={["Bad input"]} />);
    const message = screen.getByText("Bad input");
    expect(message.className).toContain("text-red-600");
  });
});
