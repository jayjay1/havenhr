"use client";

import { useState, useCallback, useEffect, useRef } from "react";
import { usePathname } from "next/navigation";
import { useAuth } from "@/contexts/AuthContext";
import type { PermissionName } from "@/types/permission";

/**
 * Navigation item definition with required permission.
 */
export interface NavItem {
  /** Display label */
  label: string;
  /** Route path */
  href: string;
  /** Permission required to see this item. Null means always visible. */
  permission: PermissionName | null;
  /** Icon identifier */
  icon: "home" | "users" | "roles" | "audit" | "jobs";
}

/**
 * Default navigation items for the dashboard sidebar.
 */
export const NAV_ITEMS: NavItem[] = [
  {
    label: "Dashboard",
    href: "/dashboard",
    permission: null,
    icon: "home",
  },
  {
    label: "Jobs",
    href: "/dashboard/jobs",
    permission: "jobs.list",
    icon: "jobs",
  },
  {
    label: "Users",
    href: "/dashboard/users",
    permission: "users.list",
    icon: "users",
  },
  {
    label: "Roles",
    href: "/dashboard/roles",
    permission: "roles.list",
    icon: "roles",
  },
  {
    label: "Audit Logs",
    href: "/dashboard/audit-logs",
    permission: "audit_logs.view",
    icon: "audit",
  },
];

/**
 * Filter navigation items based on user permissions.
 */
export function filterNavItems(
  items: NavItem[],
  hasPermission: (p: PermissionName) => boolean
): NavItem[] {
  return items.filter(
    (item) => item.permission === null || hasPermission(item.permission)
  );
}

/**
 * SVG icons for navigation items.
 */
function NavIcon({ icon, className }: { icon: NavItem["icon"]; className?: string }) {
  const cls = className ?? "h-5 w-5";

  switch (icon) {
    case "home":
      return (
        <svg className={cls} fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
        </svg>
      );
    case "users":
      return (
        <svg className={cls} fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
        </svg>
      );
    case "roles":
      return (
        <svg className={cls} fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
        </svg>
      );
    case "jobs":
      return (
        <svg className={cls} fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z" />
        </svg>
      );
    case "audit":
      return (
        <svg className={cls} fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
        </svg>
      );
  }
}

/**
 * Hamburger / close button for mobile sidebar toggle.
 */
function MenuButton({
  isOpen,
  onClick,
}: {
  isOpen: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-expanded={isOpen}
      aria-controls="sidebar-nav"
      aria-label={isOpen ? "Close navigation menu" : "Open navigation menu"}
      className="lg:hidden inline-flex items-center justify-center rounded-md p-2 text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-600"
    >
      {isOpen ? (
        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      ) : (
        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
      )}
    </button>
  );
}

export interface SidebarProps {
  /** Override nav items for testing */
  items?: NavItem[];
}

/**
 * Dashboard sidebar with role-aware navigation.
 * Collapsible on mobile via hamburger menu.
 * WCAG 2.1 AA compliant with proper landmarks and keyboard navigation.
 */
export function Sidebar({ items }: SidebarProps) {
  const pathname = usePathname();
  const { user, hasPermission, logout, isLoading } = useAuth();
  const [mobileOpen, setMobileOpen] = useState(false);
  const sidebarRef = useRef<HTMLElement>(null);

  const navItems = items ?? NAV_ITEMS;
  const visibleItems = filterNavItems(navItems, hasPermission);

  const toggleMobile = useCallback(() => {
    setMobileOpen((prev) => !prev);
  }, []);

  // Close mobile sidebar on route change
  useEffect(() => {
    setMobileOpen(false);
  }, [pathname]);

  // Close mobile sidebar on Escape key
  useEffect(() => {
    function handleKeyDown(e: KeyboardEvent) {
      if (e.key === "Escape" && mobileOpen) {
        setMobileOpen(false);
      }
    }
    document.addEventListener("keydown", handleKeyDown);
    return () => document.removeEventListener("keydown", handleKeyDown);
  }, [mobileOpen]);

  // Trap focus within sidebar when mobile menu is open
  useEffect(() => {
    if (!mobileOpen) return;

    function handleFocusOut(e: FocusEvent) {
      if (
        sidebarRef.current &&
        e.relatedTarget instanceof Node &&
        !sidebarRef.current.contains(e.relatedTarget)
      ) {
        // Focus escaped sidebar — pull it back
        const firstLink = sidebarRef.current.querySelector("a, button");
        if (firstLink instanceof HTMLElement) {
          firstLink.focus();
        }
      }
    }

    const sidebar = sidebarRef.current;
    sidebar?.addEventListener("focusout", handleFocusOut);
    return () => sidebar?.removeEventListener("focusout", handleFocusOut);
  }, [mobileOpen]);

  function isActive(href: string): boolean {
    if (href === "/dashboard") {
      return pathname === "/dashboard";
    }
    return pathname.startsWith(href);
  }

  if (isLoading) {
    return null;
  }

  return (
    <>
      {/* Mobile header bar */}
      <div className="lg:hidden flex items-center justify-between bg-white border-b border-gray-200 px-4 py-3">
        <span className="text-lg font-semibold text-gray-900">HavenHR</span>
        <MenuButton isOpen={mobileOpen} onClick={toggleMobile} />
      </div>

      {/* Mobile overlay */}
      {mobileOpen && (
        <div
          className="lg:hidden fixed inset-0 z-30 bg-black/50"
          aria-hidden="true"
          onClick={() => setMobileOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        ref={sidebarRef}
        id="sidebar-nav"
        className={`
          fixed inset-y-0 left-0 z-40 w-64 bg-white border-r border-gray-200
          transform transition-transform duration-200 ease-in-out
          lg:translate-x-0 lg:static lg:z-auto
          ${mobileOpen ? "translate-x-0" : "-translate-x-full"}
        `}
        aria-label="Dashboard navigation"
      >
        <div className="flex flex-col h-full">
          {/* Logo / brand */}
          <div className="flex items-center h-16 px-6 border-b border-gray-200">
            <a href="/dashboard" className="text-xl font-bold text-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600 rounded">
              HavenHR
            </a>
          </div>

          {/* Navigation */}
          <nav className="flex-1 overflow-y-auto py-4 px-3" aria-label="Main navigation">
            <ul role="list" className="space-y-1">
              {visibleItems.map((item) => {
                const active = isActive(item.href);
                return (
                  <li key={item.href}>
                    <a
                      href={item.href}
                      aria-current={active ? "page" : undefined}
                      className={`
                        flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium
                        transition-colors
                        focus:outline-none focus:ring-2 focus:ring-blue-600
                        ${
                          active
                            ? "bg-blue-50 text-blue-700"
                            : "text-gray-700 hover:bg-gray-100 hover:text-gray-900"
                        }
                      `}
                    >
                      <NavIcon icon={item.icon} />
                      {item.label}
                    </a>
                  </li>
                );
              })}
            </ul>
          </nav>

          {/* User info and logout */}
          {user && (
            <div className="border-t border-gray-200 p-4">
              <div className="flex items-center gap-3 mb-3">
                <div
                  className="flex items-center justify-center h-8 w-8 rounded-full bg-blue-100 text-blue-700 text-sm font-medium"
                  aria-hidden="true"
                >
                  {user.name.charAt(0).toUpperCase()}
                </div>
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium text-gray-900 truncate">
                    {user.name}
                  </p>
                  <p className="text-xs text-gray-500 truncate capitalize">
                    {user.role.replace("_", " ")}
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={logout}
                className="w-full flex items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-600 transition-colors"
              >
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                </svg>
                Sign out
              </button>
            </div>
          )}
        </div>
      </aside>
    </>
  );
}
