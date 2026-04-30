import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, it, expect, vi, beforeEach } from "vitest";
import CandidateRegisterPage from "../page";

// Mock next/navigation
const pushMock = vi.fn();
vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: pushMock }),
  useSearchParams: () => new URLSearchParams(),
}));

// Mock CandidateAuthContext
const registerMock = vi.fn();
vi.mock("@/contexts/CandidateAuthContext", () => ({
  useCandidateAuth: () => ({
    candidate: null,
    isAuthenticated: false,
    isLoading: false,
    register: registerMock,
    login: vi.fn(),
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

describe("CandidateRegisterPage", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("renders all registration form fields", () => {
    render(<CandidateRegisterPage />);

    expect(
      screen.getByRole("heading", { name: /create your account/i })
    ).toBeInTheDocument();
    expect(screen.getByLabelText(/full name/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/^email/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/^password/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/confirm password/i)).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: /create account/i })
    ).toBeInTheDocument();
  });

  it("submits form and calls register on success", async () => {
    const user = userEvent.setup();
    registerMock.mockResolvedValueOnce(undefined);

    render(<CandidateRegisterPage />);

    await user.type(screen.getByLabelText(/full name/i), "Jane Doe");
    await user.type(screen.getByLabelText(/^email/i), "jane@example.com");
    await user.type(screen.getByLabelText(/^password/i), "SecurePass123!");
    await user.type(screen.getByLabelText(/confirm password/i), "SecurePass123!");
    await user.click(screen.getByRole("button", { name: /create account/i }));

    await waitFor(() => {
      expect(registerMock).toHaveBeenCalledWith({
        name: "Jane Doe",
        email: "jane@example.com",
        password: "SecurePass123!",
      });
    });
  });

  it("shows error when passwords do not match", async () => {
    const user = userEvent.setup();

    render(<CandidateRegisterPage />);

    await user.type(screen.getByLabelText(/full name/i), "Jane Doe");
    await user.type(screen.getByLabelText(/^email/i), "jane@example.com");
    await user.type(screen.getByLabelText(/^password/i), "SecurePass123!");
    await user.type(screen.getByLabelText(/confirm password/i), "DifferentPass!");
    await user.click(screen.getByRole("button", { name: /create account/i }));

    await waitFor(() => {
      expect(screen.getByText("Passwords do not match.")).toBeInTheDocument();
    });

    expect(registerMock).not.toHaveBeenCalled();
  });

  it("displays inline validation errors on 422", async () => {
    const user = userEvent.setup();
    const { ApiRequestError } = await import("@/lib/api");
    registerMock.mockRejectedValueOnce(
      new ApiRequestError(422, {
        error: {
          code: "VALIDATION_ERROR",
          message: "The given data was invalid.",
          details: {
            fields: {
              email: {
                value: "invalid",
                messages: ["The email must be a valid email address."],
              },
            },
          },
        },
      })
    );

    render(<CandidateRegisterPage />);

    await user.type(screen.getByLabelText(/full name/i), "Jane Doe");
    await user.type(screen.getByLabelText(/^email/i), "invalid");
    await user.type(screen.getByLabelText(/^password/i), "SecurePass123!");
    await user.type(screen.getByLabelText(/confirm password/i), "SecurePass123!");
    await user.click(screen.getByRole("button", { name: /create account/i }));

    await waitFor(() => {
      expect(
        screen.getByText("The email must be a valid email address.")
      ).toBeInTheDocument();
    });
  });

  it("has a link to the login page", () => {
    render(<CandidateRegisterPage />);
    expect(screen.getByRole("link", { name: /sign in/i })).toHaveAttribute(
      "href",
      "/candidate/login"
    );
  });
});
