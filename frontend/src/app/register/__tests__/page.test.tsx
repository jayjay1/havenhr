import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, it, expect, vi, beforeEach } from "vitest";
import RegisterPage from "../page";

// Mock next/navigation
const pushMock = vi.fn();
vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: pushMock }),
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

describe("RegisterPage", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("renders all form fields", () => {
    render(<RegisterPage />);

    expect(
      screen.getByRole("heading", { name: /register your company/i })
    ).toBeInTheDocument();
    expect(screen.getByLabelText(/company name/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/company email domain/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/your name/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/your email/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/^password/i)).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: /create account/i })
    ).toBeInTheDocument();
  });

  it("submits form and redirects on success", async () => {
    const user = userEvent.setup();
    postMock.mockResolvedValueOnce({
      data: {
        tenant: { id: "t1", name: "Acme", email_domain: "acme.com" },
        user: { id: "u1", name: "Jane", email: "jane@acme.com", role: "owner" },
      },
    });

    render(<RegisterPage />);

    await user.type(screen.getByLabelText(/company name/i), "Acme Inc");
    await user.type(
      screen.getByLabelText(/company email domain/i),
      "acme.com"
    );
    await user.type(screen.getByLabelText(/your name/i), "Jane Doe");
    await user.type(screen.getByLabelText(/your email/i), "jane@acme.com");
    await user.type(screen.getByLabelText(/^password/i), "SecurePass1!");

    await user.click(screen.getByRole("button", { name: /create account/i }));

    await waitFor(() => {
      expect(postMock).toHaveBeenCalledWith("/register", {
        company_name: "Acme Inc",
        company_email_domain: "acme.com",
        owner_name: "Jane Doe",
        owner_email: "jane@acme.com",
        owner_password: "SecurePass1!",
      });
    });

    await waitFor(() => {
      expect(pushMock).toHaveBeenCalledWith("/login?registered=true");
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
              owner_email: {
                value: "bad",
                messages: ["The owner email must be a valid email address."],
              },
            },
          },
        },
      })
    );

    render(<RegisterPage />);

    await user.type(screen.getByLabelText(/your email/i), "bad");
    await user.click(screen.getByRole("button", { name: /create account/i }));

    await waitFor(() => {
      expect(
        screen.getByText("The owner email must be a valid email address.")
      ).toBeInTheDocument();
    });
  });

  it("displays domain already registered error on 409", async () => {
    const user = userEvent.setup();
    const { ApiRequestError } = await import("@/lib/api");
    postMock.mockRejectedValueOnce(
      new ApiRequestError(409, {
        error: {
          code: "DOMAIN_TAKEN",
          message: "This email domain is already registered.",
        },
      })
    );

    render(<RegisterPage />);

    await user.type(screen.getByLabelText(/company name/i), "Acme");
    await user.click(screen.getByRole("button", { name: /create account/i }));

    await waitFor(() => {
      expect(
        screen.getByText("This email domain is already registered.")
      ).toBeInTheDocument();
    });
  });

  it("has a link to the login page", () => {
    render(<RegisterPage />);
    const link = screen.getByRole("link", { name: /sign in/i });
    expect(link).toHaveAttribute("href", "/login");
  });
});
