import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, it, expect, vi, beforeEach } from "vitest";
import LoginPage from "../page";

// Mock next/navigation
const pushMock = vi.fn();
vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: pushMock }),
  useSearchParams: () => new URLSearchParams(),
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

describe("LoginPage", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("renders email and password fields", () => {
    render(<LoginPage />);

    expect(
      screen.getByRole("heading", { name: /sign in to havenhr/i })
    ).toBeInTheDocument();
    expect(screen.getByLabelText(/^email/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/^password/i)).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: /sign in/i })
    ).toBeInTheDocument();
  });

  it("submits form and redirects to dashboard on success", async () => {
    const user = userEvent.setup();
    postMock.mockResolvedValueOnce({
      data: {
        user: {
          id: "u1",
          name: "Jane",
          email: "jane@acme.com",
          role: "owner",
        },
      },
    });

    render(<LoginPage />);

    await user.type(screen.getByLabelText(/^email/i), "jane@acme.com");
    await user.type(screen.getByLabelText(/^password/i), "SecurePass1!");
    await user.click(screen.getByRole("button", { name: /sign in/i }));

    await waitFor(() => {
      expect(postMock).toHaveBeenCalledWith("/auth/login", {
        email: "jane@acme.com",
        password: "SecurePass1!",
      });
    });

    await waitFor(() => {
      expect(pushMock).toHaveBeenCalledWith("/dashboard");
    });
  });

  it("displays generic error on 401", async () => {
    const user = userEvent.setup();
    const { ApiRequestError } = await import("@/lib/api");
    postMock.mockRejectedValueOnce(
      new ApiRequestError(401, {
        error: {
          code: "INVALID_CREDENTIALS",
          message: "Invalid credentials",
        },
      })
    );

    render(<LoginPage />);

    await user.type(screen.getByLabelText(/^email/i), "jane@acme.com");
    await user.type(screen.getByLabelText(/^password/i), "wrong");
    await user.click(screen.getByRole("button", { name: /sign in/i }));

    await waitFor(() => {
      expect(screen.getByText("Invalid credentials")).toBeInTheDocument();
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
              email: {
                value: "",
                messages: ["The email field is required."],
              },
            },
          },
        },
      })
    );

    render(<LoginPage />);

    await user.click(screen.getByRole("button", { name: /sign in/i }));

    await waitFor(() => {
      expect(
        screen.getByText("The email field is required.")
      ).toBeInTheDocument();
    });
  });

  it("has links to forgot password and register pages", () => {
    render(<LoginPage />);
    expect(
      screen.getByRole("link", { name: /forgot your password/i })
    ).toHaveAttribute("href", "/forgot-password");
    expect(screen.getByRole("link", { name: /register/i })).toHaveAttribute(
      "href",
      "/register"
    );
  });
});
