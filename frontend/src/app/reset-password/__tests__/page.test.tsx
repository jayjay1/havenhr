import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, it, expect, vi, beforeEach } from "vitest";
import ResetPasswordPage from "../[token]/page";

// Mock next/navigation
const pushMock = vi.fn();
vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: pushMock }),
  useParams: () => ({ token: "test-reset-token-abc123" }),
}));

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

describe("ResetPasswordPage", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("renders password and confirmation fields", () => {
    render(<ResetPasswordPage />);

    expect(
      screen.getByRole("heading", { name: /reset your password/i })
    ).toBeInTheDocument();
    expect(screen.getByLabelText(/new password/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/confirm password/i)).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: /reset password/i })
    ).toBeInTheDocument();
  });

  it("submits form with token and redirects on success", async () => {
    const user = userEvent.setup();
    postMock.mockResolvedValueOnce({
      data: { message: "Password reset successfully." },
    });

    render(<ResetPasswordPage />);

    await user.type(
      screen.getByLabelText(/new password/i),
      "NewSecurePass1!"
    );
    await user.type(
      screen.getByLabelText(/confirm password/i),
      "NewSecurePass1!"
    );
    await user.click(screen.getByRole("button", { name: /reset password/i }));

    await waitFor(() => {
      expect(postMock).toHaveBeenCalledWith("/auth/password/reset", {
        token: "test-reset-token-abc123",
        password: "NewSecurePass1!",
        password_confirmation: "NewSecurePass1!",
      });
    });

    await waitFor(() => {
      expect(pushMock).toHaveBeenCalledWith("/login?reset=true");
    });
  });

  it("displays error for expired or invalid token", async () => {
    const user = userEvent.setup();
    const { ApiRequestError } = await import("@/lib/api");
    postMock.mockRejectedValueOnce(
      new ApiRequestError(400, {
        error: {
          code: "TOKEN_EXPIRED",
          message: "This reset link has expired.",
        },
      })
    );

    render(<ResetPasswordPage />);

    await user.type(
      screen.getByLabelText(/new password/i),
      "NewSecurePass1!"
    );
    await user.type(
      screen.getByLabelText(/confirm password/i),
      "NewSecurePass1!"
    );
    await user.click(screen.getByRole("button", { name: /reset password/i }));

    await waitFor(() => {
      expect(
        screen.getByText("This reset link has expired.")
      ).toBeInTheDocument();
    });
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
              password: {
                value: "[REDACTED]",
                messages: ["The password must be at least 12 characters."],
              },
            },
          },
        },
      })
    );

    render(<ResetPasswordPage />);

    await user.type(screen.getByLabelText(/new password/i), "short");
    await user.click(screen.getByRole("button", { name: /reset password/i }));

    await waitFor(() => {
      expect(
        screen.getByText("The password must be at least 12 characters.")
      ).toBeInTheDocument();
    });
  });

  it("has a link back to login", () => {
    render(<ResetPasswordPage />);
    expect(
      screen.getByRole("link", { name: /back to sign in/i })
    ).toHaveAttribute("href", "/login");
  });
});
