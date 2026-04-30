import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, it, expect, vi, beforeEach } from "vitest";
import {
  filterNavItems,
  NAV_ITEMS,
  type NavItem,
} from "../Sidebar";
import type { PermissionName } from "@/types/permission";

// ---- Unit tests for filterNavItems (pure function, no mocking needed) ----

describe("filterNavItems", () => {
  const items: NavItem[] = [
    { label: "Dashboard", href: "/dashboard", permission: null, icon: "home" },
    { label: "Users", href: "/dashboard/users", permission: "users.list", icon: "users" },
    { label: "Roles", href: "/dashboard/roles", permission: "roles.list", icon: "roles" },
    { label: "Audit Logs", href: "/dashboard/audit-logs", permission: "audit_logs.view", icon: "audit" },
  ];

  it("shows all items when user has all permissions", () => {
    const hasPermission = () => true;
    const result = filterNavItems(items, hasPermission);
    expect(result).toHaveLength(4);
    expect(result.map((i) => i.label)).toEqual([
      "Dashboard",
      "Users",
      "Roles",
      "Audit Logs",
    ]);
  });

  it("always shows items with null permission", () => {
    const hasPermission = () => false;
    const result = filterNavItems(items, hasPermission);
    expect(result).toHaveLength(1);
    expect(result[0].label).toBe("Dashboard");
  });

  it("filters items based on specific permissions", () => {
    const allowed: PermissionName[] = ["users.list"];
    const hasPermission = (p: PermissionName) => allowed.includes(p);
    const result = filterNavItems(items, hasPermission);
    expect(result).toHaveLength(2);
    expect(result.map((i) => i.label)).toEqual(["Dashboard", "Users"]);
  });

  it("shows Users and Roles but not Audit Logs for recruiter-like permissions", () => {
    const allowed: PermissionName[] = ["users.list", "roles.list"];
    const hasPermission = (p: PermissionName) => allowed.includes(p);
    const result = filterNavItems(items, hasPermission);
    expect(result).toHaveLength(3);
    expect(result.map((i) => i.label)).toEqual(["Dashboard", "Users", "Roles"]);
  });

  it("returns empty array when given empty items", () => {
    const result = filterNavItems([], () => true);
    expect(result).toHaveLength(0);
  });

  it("uses the default NAV_ITEMS with expected structure", () => {
    expect(NAV_ITEMS).toHaveLength(4);
    expect(NAV_ITEMS[0].permission).toBeNull(); // Dashboard is always visible
    expect(NAV_ITEMS[1].permission).toBe("users.list");
    expect(NAV_ITEMS[2].permission).toBe("roles.list");
    expect(NAV_ITEMS[3].permission).toBe("audit_logs.view");
  });
});

// ---- Tests for Sidebar component rendering ----

// Mock next/navigation
const mockPathname = vi.fn().mockReturnValue("/dashboard");
const mockPush = vi.fn();

vi.mock("next/navigation", () => ({
  usePathname: () => mockPathname(),
  useRouter: () => ({ push: mockPush }),
  useSearchParams: () => new URLSearchParams(),
}));

// Mock AuthContext
const mockAuthValues = {
  user: {
    id: "user-1",
    tenant_id: "tenant-1",
    name: "Jane Doe",
    email: "jane@example.com",
    is_active: true,
    role: "owner" as const,
    last_login_at: null,
    created_at: "2024-01-01T00:00:00Z",
    updated_at: "2024-01-01T00:00:00Z",
  },
  role: "owner" as const,
  permissions: [
    "users.list",
    "roles.list",
    "audit_logs.view",
    "manage_roles",
  ] as PermissionName[],
  isAuthenticated: true,
  isLoading: false,
  logout: vi.fn(),
  hasPermission: (p: PermissionName) =>
    (
      [
        "users.list",
        "roles.list",
        "audit_logs.view",
        "manage_roles",
      ] as PermissionName[]
    ).includes(p),
};

vi.mock("@/contexts/AuthContext", () => ({
  useAuth: () => mockAuthValues,
}));

// Dynamic import after mocks are set up
const { Sidebar } = await import("../Sidebar");

describe("Sidebar component", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockPathname.mockReturnValue("/dashboard");
    mockAuthValues.isLoading = false;
    mockAuthValues.permissions = [
      "users.list",
      "roles.list",
      "audit_logs.view",
      "manage_roles",
    ] as PermissionName[];
    mockAuthValues.hasPermission = (p: PermissionName) =>
      mockAuthValues.permissions.includes(p);
  });

  it("renders navigation landmark", () => {
    render(<Sidebar />);
    expect(screen.getByRole("navigation", { name: /main navigation/i })).toBeInTheDocument();
  });

  it("renders all nav items for owner role", () => {
    render(<Sidebar />);
    const nav = screen.getByRole("navigation", { name: /main navigation/i });
    const links = within(nav).getAllByRole("link");
    expect(links).toHaveLength(4);
    expect(links.map((l) => l.textContent?.trim())).toEqual([
      "Dashboard",
      "Users",
      "Roles",
      "Audit Logs",
    ]);
  });

  it("filters nav items when user has limited permissions", () => {
    mockAuthValues.permissions = [] as PermissionName[];
    mockAuthValues.hasPermission = () => false;

    render(<Sidebar />);
    const nav = screen.getByRole("navigation", { name: /main navigation/i });
    const links = within(nav).getAllByRole("link");
    // Only Dashboard (null permission) should be visible
    expect(links).toHaveLength(1);
    expect(links[0]).toHaveTextContent("Dashboard");
  });

  it("highlights the active navigation item", () => {
    mockPathname.mockReturnValue("/dashboard/users");
    render(<Sidebar />);
    const usersLink = screen.getByRole("link", { name: /users/i });
    expect(usersLink).toHaveAttribute("aria-current", "page");
  });

  it("does not highlight inactive items", () => {
    mockPathname.mockReturnValue("/dashboard");
    render(<Sidebar />);
    const dashboardLink = screen.getByRole("link", { name: /^Dashboard$/i });
    const usersLink = screen.getByRole("link", { name: /users/i });
    expect(dashboardLink).toHaveAttribute("aria-current", "page");
    expect(usersLink).not.toHaveAttribute("aria-current");
  });

  it("displays user name and role", () => {
    render(<Sidebar />);
    expect(screen.getByText("Jane Doe")).toBeInTheDocument();
    expect(screen.getByText("owner")).toBeInTheDocument();
  });

  it("renders sign out button", () => {
    render(<Sidebar />);
    expect(screen.getByRole("button", { name: /sign out/i })).toBeInTheDocument();
  });

  it("calls logout when sign out is clicked", async () => {
    const user = userEvent.setup();
    render(<Sidebar />);
    await user.click(screen.getByRole("button", { name: /sign out/i }));
    expect(mockAuthValues.logout).toHaveBeenCalledOnce();
  });

  it("renders mobile menu toggle button", () => {
    render(<Sidebar />);
    expect(
      screen.getByRole("button", { name: /open navigation menu/i })
    ).toBeInTheDocument();
  });

  it("has proper aria-controls on mobile toggle", () => {
    render(<Sidebar />);
    const toggle = screen.getByRole("button", { name: /open navigation menu/i });
    expect(toggle).toHaveAttribute("aria-controls", "sidebar-nav");
    expect(toggle).toHaveAttribute("aria-expanded", "false");
  });

  it("returns null when loading", () => {
    mockAuthValues.isLoading = true;
    const { container } = render(<Sidebar />);
    // Should only render the mobile header bar, not the sidebar nav
    expect(screen.queryByRole("navigation", { name: /main navigation/i })).not.toBeInTheDocument();
  });

  it("renders brand link to dashboard", () => {
    render(<Sidebar />);
    const brandLinks = screen.getAllByRole("link", { name: /havenhr/i });
    expect(brandLinks.length).toBeGreaterThan(0);
    expect(brandLinks[0]).toHaveAttribute("href", "/dashboard");
  });
});
