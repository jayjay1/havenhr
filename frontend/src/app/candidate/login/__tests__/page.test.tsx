import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, it, expect, vi, beforeEach } from "vitest";
import CandidateLoginPage from "../page";

// Mock next/navigation
const pushMock = vi.fn();
vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: pushMock }),
  useSearchParams: () => new URLSearchParams(),
}));

// Mock CandidateAuthContext
const loginMock = vi.fn();
vi.mock("@/contexts/CandidateAuthContext", () => ({
  useCandidateAuth: () => ({
    candidate: null,
    isAuthenticated: false,
    isLoading: false,
    register: vi.fn(),
    login: loginMock,
    logout: vi.fn(),
    refresh: vi.fn(),
  }),
}));

// Mock ApiRequestError for error handling tests
vi.mock("@/lib/api", () => ({
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

describe("CandidateLoginPage", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("renders email and password fields", () => {
    render(<CandidateLoginPage />);

    expect(
      screen.getByRole("heading", { name: /sign in to your account/i })
    ).toBeInTheDocument();
    expect(screen.getByLabelText(/^email/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/^password/i)).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: /sign in/i })
    ).toBeInTheDocument();
  });

  it("submits form and calls login on success", async () => {
    const user = userEvent.setup();
    loginMock.mockResolvedValueOnce(undefined);

    render(<CandidateLoginPage />);

    await user.type(screen.getByLabelText(/^email/i), "jane@example.com");
    await user.type(screen.getByLabelText(/^password/i), "SecurePass123!");
    await user.click(screen.getByRole("button", { name: /sign in/i }));

    await waitFor(() => {
      expect(loginMock).toHaveBeenCalledWith({
        email: "jane@example.com",
        password: "SecurePass123!",
      });
    });
  });

  it("displays generic error on 401 (invalid credentials)", async () => {
    const user = userEvent.setup();
    const { ApiRequestError } = await import("@/lib/api");
    loginMock.mockRejectedValueOnce(
      new ApiRequestError(401, {
        error: {
          code: "INVALID_CREDENTIALS",
          message: "Invalid credentials",
        },
      })
    );

    render(<CandidateLoginPage />);

    await user.type(screen.getByLabelText(/^email/i), "jane@example.com");
    await user.type(screen.getByLabelText(/^password/i), "wrong");
    await user.click(screen.getByRole("button", { name: /sign in/i }));

    await waitFor(() => {
      expect(screen.getByText("Invalid credentials")).toBeInTheDocument();
    });
  });

  it("displays inline validation errors on 422", async () => {
    const user = userEvent.setup();
    const { ApiRequestError } = await import("@/lib/api");
    loginMock.mockRejectedValueOnce(
      new ApiRequestError(422, {
        error: {
          code: "VALIDATION_ERROR",
          message: "The given data was invalid.",
          details: {
            fields: {
              email: {
                value: "",
                messages: ["The email field is required."],
              },
            },
          },
        },
      })
    );

    render(<CandidateLoginPage />);

    await user.click(screen.getByRole("button", { name: /sign in/i }));

    await waitFor(() => {
      expect(
        screen.getByText("The email field is required.")
      ).toBeInTheDocument();
    });
  });

  it("has a link to the registration page", () => {
    render(<CandidateLoginPage />);
    expect(
      screen.getByRole("link", { name: /create account/i })
    ).toHaveAttribute("href", "/candidate/register");
  });
});
