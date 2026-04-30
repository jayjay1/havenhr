"use client";

import { CandidateAuthProvider, useCandidateAuth } from "@/contexts/CandidateAuthContext";
import { CandidateNav } from "@/components/candidate/CandidateNav";
import { usePathname } from "next/navigation";

/**
 * Routes that don't require authentication and don't show the nav bar.
 */
const PUBLIC_ROUTES = ["/candidate/login", "/candidate/register"];

function CandidateContent({ children }: { children: React.ReactNode }) {
  const { isLoading, isAuthenticated } = useCandidateAuth();
  const pathname = usePathname();

  const isPublicRoute = PUBLIC_ROUTES.includes(pathname);

  // Public routes (login/register) render without nav or auth check
  if (isPublicRoute) {
    return <>{children}</>;
  }

  // Protected routes show loading state while checking auth
  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <div
            className="inline-block h-8 w-8 animate-spin rounded-full border-4 border-teal-600 border-r-transparent"
            role="status"
            aria-label="Loading"
          />
          <p className="mt-2 text-sm text-gray-500">Loading…</p>
        </div>
      </div>
    );
  }

  // Authenticated protected routes show nav + content
  if (isAuthenticated) {
    return (
      <div className="min-h-screen bg-gray-50">
        <CandidateNav />
        <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8" id="main-content">
          {children}
        </main>
      </div>
    );
  }

  // Not authenticated on a protected route — the context will redirect to login
  return null;
}

export default function CandidateLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <CandidateAuthProvider>
      <CandidateContent>{children}</CandidateContent>
    </CandidateAuthProvider>
  );
}
