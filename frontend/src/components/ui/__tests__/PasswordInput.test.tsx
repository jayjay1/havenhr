import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, it, expect, vi } from "vitest";
import { PasswordInput } from "../PasswordInput";

describe("PasswordInput", () => {
  const defaultProps = {
    id: "password",
    name: "password",
    label: "Password",
    value: "",
    onChange: vi.fn(),
  };

  it("renders as password type by default", () => {
    render(<PasswordInput {...defaultProps} />);
    const input = document.getElementById("password") as HTMLInputElement;
    expect(input.type).toBe("password");
  });

  it("toggles visibility when show/hide button is clicked", async () => {
    render(<PasswordInput {...defaultProps} value="secret123" />);
    const input = document.getElementById("password") as HTMLInputElement;
    const toggleBtn = screen.getByRole("button", { name: "Show password" });

    expect(input.type).toBe("password");

    await userEvent.click(toggleBtn);
    expect(input.type).toBe("text");
    expect(
      screen.getByRole("button", { name: "Hide password" })
    ).toBeInTheDocument();

    await userEvent.click(
      screen.getByRole("button", { name: "Hide password" })
    );
    expect(input.type).toBe("password");
  });

  it("toggle button has correct aria-label", () => {
    render(<PasswordInput {...defaultProps} />);
    expect(
      screen.getByRole("button", { name: "Show password" })
    ).toBeInTheDocument();
  });

  it("renders label with correct htmlFor association", () => {
    render(<PasswordInput {...defaultProps} />);
    const label = screen.getByText("Password");
    expect(label).toHaveAttribute("for", "password");
  });

  it("sets aria-invalid and aria-describedby when error is present", () => {
    render(<PasswordInput {...defaultProps} error="Too weak" />);
    const input = document.getElementById("password") as HTMLInputElement;
    expect(input).toHaveAttribute("aria-invalid", "true");
    expect(input.getAttribute("aria-describedby")).toContain("password-error");
    expect(screen.getByText("Too weak")).toBeInTheDocument();
  });

  it("shows complexity indicator when showComplexity is true and value is non-empty", () => {
    render(
      <PasswordInput
        {...defaultProps}
        value="Abc"
        showComplexity
      />
    );
    expect(screen.getByText("At least 12 characters")).toBeInTheDocument();
    expect(screen.getByText("One uppercase letter")).toBeInTheDocument();
    expect(screen.getByText("One lowercase letter")).toBeInTheDocument();
    expect(screen.getByText("One digit")).toBeInTheDocument();
    expect(screen.getByText("One special character")).toBeInTheDocument();
  });

  it("does not show complexity indicator when value is empty", () => {
    render(
      <PasswordInput {...defaultProps} value="" showComplexity />
    );
    expect(
      screen.queryByText("At least 12 characters")
    ).not.toBeInTheDocument();
  });

  it("marks met complexity rules with green styling", () => {
    render(
      <PasswordInput
        {...defaultProps}
        value="Abcdefghijkl1!"
        showComplexity
      />
    );
    // This password meets all rules: ≥12 chars, uppercase, lowercase, digit, special
    const items = screen.getAllByRole("listitem");
    items.forEach((item) => {
      expect(item.className).toContain("text-green-600");
    });
  });

  it("marks unmet complexity rules with gray styling", () => {
    render(
      <PasswordInput {...defaultProps} value="abc" showComplexity />
    );
    // "abc" only meets lowercase — others should be gray
    const lengthItem = screen.getByText("At least 12 characters").closest("li");
    expect(lengthItem?.className).toContain("text-gray-400");
  });
});
