import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, it, expect, vi } from "vitest";
import { FormInput } from "../FormInput";

describe("FormInput", () => {
  const defaultProps = {
    id: "email",
    name: "email",
    label: "Email",
    value: "",
    onChange: vi.fn(),
  };

  it("renders label with correct htmlFor association", () => {
    render(<FormInput {...defaultProps} />);
    const label = screen.getByText("Email");
    expect(label).toHaveAttribute("for", "email");
    const input = screen.getByRole("textbox");
    expect(input).toHaveAttribute("id", "email");
  });

  it("renders required indicator when required", () => {
    render(<FormInput {...defaultProps} required />);
    const input = screen.getByRole("textbox");
    expect(input).toBeRequired();
    expect(screen.getByText("*")).toBeInTheDocument();
  });

  it("sets aria-invalid and aria-describedby when error is present (string)", () => {
    render(<FormInput {...defaultProps} error="Invalid email" />);
    const input = screen.getByRole("textbox");
    expect(input).toHaveAttribute("aria-invalid", "true");
    expect(input).toHaveAttribute("aria-describedby", "email-error");
    expect(screen.getByText("Invalid email")).toBeInTheDocument();
  });

  it("sets aria-invalid and aria-describedby when error is present (array)", () => {
    render(
      <FormInput {...defaultProps} error={["Too short", "Missing @"]} />
    );
    const input = screen.getByRole("textbox");
    expect(input).toHaveAttribute("aria-invalid", "true");
    expect(input).toHaveAttribute("aria-describedby", "email-error");
    expect(screen.getByText("Too short")).toBeInTheDocument();
    expect(screen.getByText("Missing @")).toBeInTheDocument();
  });

  it("does not set aria-invalid or aria-describedby when no error", () => {
    render(<FormInput {...defaultProps} />);
    const input = screen.getByRole("textbox");
    expect(input).not.toHaveAttribute("aria-invalid");
    expect(input).not.toHaveAttribute("aria-describedby");
  });

  it("calls onChange when user types", async () => {
    const onChange = vi.fn();
    render(<FormInput {...defaultProps} onChange={onChange} />);
    const input = screen.getByRole("textbox");
    await userEvent.type(input, "a");
    expect(onChange).toHaveBeenCalled();
  });

  it("renders as disabled when disabled prop is set", () => {
    render(<FormInput {...defaultProps} disabled />);
    expect(screen.getByRole("textbox")).toBeDisabled();
  });

  it("passes autoComplete to the input", () => {
    render(<FormInput {...defaultProps} autoComplete="email" />);
    expect(screen.getByRole("textbox")).toHaveAttribute(
      "autocomplete",
      "email"
    );
  });

  it("renders placeholder text", () => {
    render(<FormInput {...defaultProps} placeholder="you@example.com" />);
    expect(
      screen.getByPlaceholderText("you@example.com")
    ).toBeInTheDocument();
  });
});
