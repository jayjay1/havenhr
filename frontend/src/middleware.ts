import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";

/**
 * Check if a pathname matches a protected route.
 */
export function isProtectedRoute(pathname: string): boolean {
  return pathname === "/dashboard" || pathname.startsWith("/dashboard/");
}

/**
 * Check if a pathname matches a public auth route.
 */
export function isPublicAuthRoute(pathname: string): boolean {
  const publicRoutes = ["/login", "/register", "/forgot-password"];
  if (publicRoutes.includes(pathname)) return true;
  return pathname.startsWith("/reset-password");
}

/**
 * Minimal middleware — auth is handled client-side via AuthContext.
 * We can't check localStorage from middleware (server-side),
 * so we just let all requests through.
 */
export function middleware(_request: NextRequest) {
  return NextResponse.next();
}

export const config = {
  matcher: [
    "/dashboard/:path*",
    "/login",
    "/register",
    "/forgot-password",
    "/reset-password/:path*",
  ],
};
