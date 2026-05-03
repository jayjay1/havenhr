"use client";

import { useState, useEffect, useRef, useCallback } from "react";
import { usePathname } from "next/navigation";
import { useCandidateAuth } from "@/contexts/CandidateAuthContext";

/**
 * Navigation item for the candidate top nav bar.
 */
interface CandidateNavItem {
  label: string;
  href: string;
}

const NAV_ITEMS: CandidateNavItem[] = [
  { label: "Dashboard", href: "/candidate/dashboard" },
  { label: "My Applications", href: "/candidate/applications" },
  { label: "Profile", href: "/candidate/profile" },
  { label: "Resumes", href: "/candidate/resumes" },
  { label: "Job Board", href: "/candidate/jobs" },
  { label: "Settings", href: "/candidate/settings" },
];

/**
 * Candidate top navigation bar with teal/green color scheme.
 * Distinct from the employer sidebar — uses a horizontal top nav.
 * Mobile-responsive with hamburger menu.
 */
export function CandidateNav() {
  const pathname = usePathname();
  const { candidate, logout } = useCandidateAuth();
  const [mobileOpen, setMobileOpen] = useState(false);
  const navRef = useRef<HTMLElement>(null);

  const toggleMobile = useCallback(() => {
    setMobileOpen((prev) => !prev);
  }, []);

  // Close mobile menu on route change
  useEffect(() => {
    setMobileOpen(false);
  }, [pathname]);

  // Close mobile menu on Escape key
  useEffect(() => {
    function handleKeyDown(e: KeyboardEvent) {
      if (e.key === "Escape" && mobileOpen) {
        setMobileOpen(false);
      }
    }
    document.addEventListener("keydown", handleKeyDown);
    return () => document.removeEventListener("keydown", handleKeyDown);
  }, [mobileOpen]);

  function isActive(href: string): boolean {
    if (href === "/candidate/dashboard") {
      return pathname === "/candidate/dashboard";
    }
    return pathname.startsWith(href);
  }

  return (
    <header className="bg-white border-b border-gray-200 sticky top-0 z-30">
      <nav
        ref={navRef}
        className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"
        aria-label="Candidate navigation"
      >
        <div className="flex items-center justify-between h-16">
          {/* Brand */}
          <div className="flex items-center gap-8">
            <a
              href="/candidate/dashboard"
              className="text-xl font-bold text-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500 rounded"
            >
              HavenHR
            </a>

            {/* Desktop nav links */}
            <div className="hidden md:flex items-center gap-1">
              {NAV_ITEMS.map((item) => {
                const active = isActive(item.href);
                return (
                  <a
                    key={item.href}
                    href={item.href}
                    aria-current={active ? "page" : undefined}
                    className={`
                      px-3 py-2 rounded-md text-sm font-medium transition-colors
                      focus:outline-none focus:ring-2 focus:ring-teal-500
                      ${
                        active
                          ? "bg-teal-50 text-teal-700"
                          : "text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                      }
                    `}
                  >
                    {item.label}
                  </a>
                );
              })}
            </div>
          </div>

          {/* Desktop user section */}
          <div className="hidden md:flex items-center gap-4">
            {candidate && (
              <>
                <div className="flex items-center gap-2">
                  <div
                    className="flex items-center justify-center h-8 w-8 rounded-full bg-teal-100 text-teal-700 text-sm font-medium"
                    aria-hidden="true"
                  >
                    {candidate.name.charAt(0).toUpperCase()}
                  </div>
                  <span className="text-sm font-medium text-gray-700">
                    {candidate.name}
                  </span>
                </div>
                <button
                  type="button"
                  onClick={logout}
                  className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-teal-500"
                >
                  <svg
                    className="h-4 w-4"
                    fill="none"
                    viewBox="0 0 24 24"
                    strokeWidth={1.5}
                    stroke="currentColor"
                    aria-hidden="true"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"
                    />
                  </svg>
                  Sign out
                </button>
              </>
            )}
          </div>

          {/* Mobile hamburger */}
          <button
            type="button"
            onClick={toggleMobile}
            aria-expanded={mobileOpen}
            aria-controls="candidate-mobile-menu"
            aria-label={mobileOpen ? "Close navigation menu" : "Open navigation menu"}
            className="md:hidden inline-flex items-center justify-center rounded-md p-2 text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-teal-500"
          >
            {mobileOpen ? (
              <svg
                className="h-6 w-6"
                fill="none"
                viewBox="0 0 24 24"
                strokeWidth={1.5}
                stroke="currentColor"
                aria-hidden="true"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            ) : (
              <svg
                className="h-6 w-6"
                fill="none"
                viewBox="0 0 24 24"
                strokeWidth={1.5}
                stroke="currentColor"
                aria-hidden="true"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"
                />
              </svg>
            )}
          </button>
        </div>

        {/* Mobile menu */}
        {mobileOpen && (
          <div id="candidate-mobile-menu" className="md:hidden pb-4 border-t border-gray-200 mt-2 pt-2">
            <div className="space-y-1">
              {NAV_ITEMS.map((item) => {
                const active = isActive(item.href);
                return (
                  <a
                    key={item.href}
                    href={item.href}
                    aria-current={active ? "page" : undefined}
                    className={`
                      block px-3 py-2 rounded-md text-base font-medium transition-colors
                      focus:outline-none focus:ring-2 focus:ring-teal-500
                      ${
                        active
                          ? "bg-teal-50 text-teal-700"
                          : "text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                      }
                    `}
                  >
                    {item.label}
                  </a>
                );
              })}
            </div>

            {candidate && (
              <div className="mt-4 pt-4 border-t border-gray-200">
                <div className="flex items-center gap-3 px-3 mb-3">
                  <div
                    className="flex items-center justify-center h-8 w-8 rounded-full bg-teal-100 text-teal-700 text-sm font-medium"
                    aria-hidden="true"
                  >
                    {candidate.name.charAt(0).toUpperCase()}
                  </div>
                  <span className="text-sm font-medium text-gray-700">
                    {candidate.name}
                  </span>
                </div>
                <button
                  type="button"
                  onClick={logout}
                  className="w-full flex items-center gap-2 px-3 py-2 text-base font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-teal-500"
                >
                  <svg
                    className="h-4 w-4"
                    fill="none"
                    viewBox="0 0 24 24"
                    strokeWidth={1.5}
                    stroke="currentColor"
                    aria-hidden="true"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"
                    />
                  </svg>
                  Sign out
                </button>
              </div>
            )}
          </div>
        )}
      </nav>
    </header>
  );
}
