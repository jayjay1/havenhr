import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, it, expect, vi, beforeEach } from "vitest";
import ForgotPasswordPage from "../page";

// Mock apiClient
const postMock = vi.fn();
vi.mock("@/lib/api", () => ({
  apiClient: { post: (...args: unknown[]) => postMock(...args) },
  ApiRequestError: class ApiRequestError extends Error {
    status: number;
    code: string;
    details: Record<string, unknown> | undefined;
    constructor(
      status: number,
      apiError: {
        error: {
          code: string;
          message: string;
          details?: Record<string, unknown>;
        };
      }
    ) {
      super(apiError.error.message);
      this.name = "ApiRequestError";
      this.status = status;
      this.code = apiError.error.code;
      this.details = apiError.error.details;
    }
  },
}));

describe("ForgotPasswordPage", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("renders email field and submit button", () => {
    render(<ForgotPasswordPage />);

    expect(
      screen.getByRole("heading", { name: /forgot your password/i })
    ).toBeInTheDocument();
    expect(screen.getByLabelText(/email/i)).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: /send reset link/i })
    ).toBeInTheDocument();
  });

  it("shows success message after submission", async () => {
    const user = userEvent.setup();
    postMock.mockResolvedValueOnce({
      data: { message: "Reset link sent." },
    });

    render(<ForgotPasswordPage />);

    await user.type(screen.getByLabelText(/email/i), "jane@acme.com");
    await user.click(
      screen.getByRole("button", { name: /send reset link/i })
    );

    await waitFor(() => {
      expect(postMock).toHaveBeenCalledWith("/auth/password/forgot", {
        email: "jane@acme.com",
      });
    });

    await waitFor(() => {
      expect(
        screen.getByRole("heading", { name: /check your email/i })
      ).toBeInTheDocument();
    });

    expect(screen.getByText(/jane@acme.com/)).toBeInTheDocument();
  });

  it("displays inline validation errors on 422", async () => {
    const user = userEvent.setup();
    const { ApiRequestError } = await import("@/lib/api");
    postMock.mockRejectedValueOnce(
      new ApiRequestError(422, {
        error: {
          code: "VALIDATION_ERROR",
          message: "The given data was invalid.",
          details: {
            fields: {
              email: {
                value: "bad",
                messages: ["The email must be a valid email address."],
              },
            },
          },
        },
      })
    );

    render(<ForgotPasswordPage />);

    await user.type(screen.getByLabelText(/email/i), "bad");
    await user.click(
      screen.getByRole("button", { name: /send reset link/i })
    );

    await waitFor(() => {
      expect(
        screen.getByText("The email must be a valid email address.")
      ).toBeInTheDocument();
    });
  });

  it("has a link back to login", () => {
    render(<ForgotPasswordPage />);
    expect(
      screen.getByRole("link", { name: /back to sign in/i })
    ).toHaveAttribute("href", "/login");
  });
});
