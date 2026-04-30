import { render, screen, waitFor } from "@testing-library/react";
import { describe, it, expect, vi, beforeEach } from "vitest";
import type { PermissionName } from "@/types/permission";

// Mock next/navigation
const mockPush = vi.fn();
const mockSearchParams = new URLSearchParams();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: mockPush }),
  useSearchParams: () => mockSearchParams,
  usePathname: () => "/dashboard/users",
}));

// Mock AuthContext
const mockAuthValues = {
  user: {
    id: "user-1",
    tenant_id: "tenant-1",
    name: "Admin User",
    email: "admin@example.com",
    is_active: true,
    role: "admin" as const,
    last_login_at: null,
    created_at: "2024-01-01T00:00:00Z",
    updated_at: "2024-01-01T00:00:00Z",
  },
  role: "admin" as const,
  permissions: ["users.list", "manage_roles"] as PermissionName[],
  isAuthenticated: true,
  isLoading: false,
  logout: vi.fn(),
  hasPermission: (p: PermissionName) =>
    (["users.list", "manage_roles"] as PermissionName[]).includes(p),
};

vi.mock("@/contexts/AuthContext", () => ({
  useAuth: () => mockAuthValues,
}));

// Mock API client
const mockGet = vi.fn();
vi.mock("@/lib/api", () => ({
  apiClient: {
    get: (...args: unknown[]) => mockGet(...args),
  },
  ApiRequestError: class extends Error {
    status: number;
    code: string;
    constructor(status: number, apiError: { error: { code: string; message: string } }) {
      super(apiError.error.message);
      this.status = status;
      this.code = apiError.error.code;
    }
  },
}));

const { default: UsersPage } = await import("../page");

const mockUsersResponse = {
  data: {
    data: [
      {
        id: "u1",
        tenant_id: "t1",
        name: "Alice Smith",
        email: "alice@example.com",
        is_active: true,
        role: "admin",
        last_login_at: "2024-06-15T10:30:00Z",
        created_at: "2024-01-01T00:00:00Z",
        updated_at: "2024-01-01T00:00:00Z",
      },
      {
        id: "u2",
        tenant_id: "t1",
        name: "Bob Jones",
        email: "bob@example.com",
        is_active: false,
        role: "viewer",
        last_login_at: null,
        created_at: "2024-02-01T00:00:00Z",
        updated_at: "2024-02-01T00:00:00Z",
      },
    ],
    meta: {
      current_page: 1,
      per_page: 20,
      total: 2,
      last_page: 1,
    },
  },
};

describe("UsersPage", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGet.mockResolvedValue(mockUsersResponse);
    mockAuthValues.permissions = ["users.list", "manage_roles"] as PermissionName[];
    mockAuthValues.hasPermission = (p: PermissionName) =>
      mockAuthValues.permissions.includes(p);
  });

  it("renders page heading", async () => {
    render(<UsersPage />);
    expect(screen.getByRole("heading", { name: /users/i })).toBeInTheDocument();
  });

  it("shows loading state initially", () => {
    mockGet.mockReturnValue(new Promise(() => {})); // Never resolves
    render(<UsersPage />);
    expect(screen.getByRole("status", { name: /loading users/i })).toBeInTheDocument();
  });

  it("fetches users from the API with pagination params", async () => {
    render(<UsersPage />);
    await waitFor(() => {
      expect(mockGet).toHaveBeenCalledWith("/users?page=1&per_page=20");
    });
  });

  it("displays user names after loading (table + card views)", async () => {
    render(<UsersPage />);
    await waitFor(() => {
      // Both table and card views render, so names appear twice
      expect(screen.getAllByText("Alice Smith")).toHaveLength(2);
    });
    expect(screen.getAllByText("Bob Jones")).toHaveLength(2);
  });

  it("displays user emails", async () => {
    render(<UsersPage />);
    await waitFor(() => {
      expect(screen.getAllByText("alice@example.com")).toHaveLength(2);
    });
    expect(screen.getAllByText("bob@example.com")).toHaveLength(2);
  });

  it("displays active/inactive status badges", async () => {
    render(<UsersPage />);
    await waitFor(() => {
      // Each status appears in both table and card views
      expect(screen.getAllByText("Active")).toHaveLength(2);
    });
    expect(screen.getAllByText("Inactive")).toHaveLength(2);
  });

  it("displays role badges", async () => {
    render(<UsersPage />);
    await waitFor(() => {
      expect(screen.getAllByText("admin")).toHaveLength(2);
    });
    expect(screen.getAllByText("viewer")).toHaveLength(2);
  });

  it("shows Manage Role links when user has manage_roles permission", async () => {
    render(<UsersPage />);
    await waitFor(() => {
      const links = screen.getAllByRole("link", { name: /manage role/i });
      // 2 users × 2 views (table + card) = 4 links
      expect(links.length).toBeGreaterThan(0);
    });
  });

  it("hides Manage Role links when user lacks manage_roles permission", async () => {
    mockAuthValues.permissions = ["users.list"] as PermissionName[];
    mockAuthValues.hasPermission = (p: PermissionName) =>
      (["users.list"] as PermissionName[]).includes(p);

    render(<UsersPage />);
    await waitFor(() => {
      expect(screen.getAllByText("Alice Smith").length).toBeGreaterThan(0);
    });
    expect(screen.queryByRole("link", { name: /manage role/i })).not.toBeInTheDocument();
  });

  it("shows error message on API failure", async () => {
    const { ApiRequestError } = await import("@/lib/api");
    mockGet.mockRejectedValue(
      new ApiRequestError(500, {
        error: { code: "SERVER_ERROR", message: "Internal server error" },
      })
    );

    render(<UsersPage />);
    await waitFor(() => {
      expect(screen.getByRole("alert")).toHaveTextContent("Internal server error");
    });
  });

  it("shows 'No users found' when API returns empty list", async () => {
    mockGet.mockResolvedValue({
      data: {
        data: [],
        meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
      },
    });

    render(<UsersPage />);
    await waitFor(() => {
      expect(screen.getByText("No users found.")).toBeInTheDocument();
    });
  });

  it("does not show pagination when there is only one page", async () => {
    render(<UsersPage />);
    await waitFor(() => {
      expect(screen.getAllByText("Alice Smith").length).toBeGreaterThan(0);
    });
    expect(screen.queryByRole("navigation", { name: /pagination/i })).not.toBeInTheDocument();
  });

  it("shows pagination when there are multiple pages", async () => {
    mockGet.mockResolvedValue({
      data: {
        data: mockUsersResponse.data.data,
        meta: { current_page: 1, per_page: 20, total: 40, last_page: 2 },
      },
    });

    render(<UsersPage />);
    await waitFor(() => {
      expect(screen.getByRole("navigation", { name: /pagination/i })).toBeInTheDocument();
    });
  });
});
