import { describe, it, expect } from "vitest";
import { isProtectedRoute, isPublicAuthRoute } from "@/middleware";

describe("middleware route classification", () => {
  describe("isProtectedRoute", () => {
    it("should identify /dashboard as protected", () => {
      expect(isProtectedRoute("/dashboard")).toBe(true);
    });

    it("should identify /dashboard/users as protected", () => {
      expect(isProtectedRoute("/dashboard/users")).toBe(true);
    });

    it("should identify /dashboard/users/123/roles as protected", () => {
      expect(isProtectedRoute("/dashboard/users/123/roles")).toBe(true);
    });

    it("should not identify /login as protected", () => {
      expect(isProtectedRoute("/login")).toBe(false);
    });

    it("should not identify /register as protected", () => {
      expect(isProtectedRoute("/register")).toBe(false);
    });

    it("should not identify /forgot-password as protected", () => {
      expect(isProtectedRoute("/forgot-password")).toBe(false);
    });

    it("should not identify /reset-password/abc123 as protected", () => {
      expect(isProtectedRoute("/reset-password/abc123")).toBe(false);
    });

    it("should not identify / as protected", () => {
      expect(isProtectedRoute("/")).toBe(false);
    });
  });

  describe("isPublicAuthRoute", () => {
    it("should identify /login as public auth route", () => {
      expect(isPublicAuthRoute("/login")).toBe(true);
    });

    it("should identify /register as public auth route", () => {
      expect(isPublicAuthRoute("/register")).toBe(true);
    });

    it("should identify /forgot-password as public auth route", () => {
      expect(isPublicAuthRoute("/forgot-password")).toBe(true);
    });

    it("should identify /reset-password/token123 as public auth route", () => {
      expect(isPublicAuthRoute("/reset-password/token123")).toBe(true);
    });

    it("should identify /reset-password as public auth route", () => {
      expect(isPublicAuthRoute("/reset-password")).toBe(true);
    });

    it("should not identify /dashboard as public auth route", () => {
      expect(isPublicAuthRoute("/dashboard")).toBe(false);
    });

    it("should not identify /dashboard/users as public auth route", () => {
      expect(isPublicAuthRoute("/dashboard/users")).toBe(false);
    });

    it("should not identify / as public auth route", () => {
      expect(isPublicAuthRoute("/")).toBe(false);
    });
  });

  describe("route classification is mutually exclusive", () => {
    const routes = [
      "/dashboard",
      "/dashboard/users",
      "/dashboard/users/123/roles",
      "/login",
      "/register",
      "/forgot-password",
      "/reset-password/abc",
      "/",
      "/about",
    ];

    it("no route should be both protected and public auth", () => {
      for (const route of routes) {
        const isProtected = isProtectedRoute(route);
        const isPublicAuth = isPublicAuthRoute(route);
        expect(
          isProtected && isPublicAuth,
          `Route "${route}" should not be both protected and public auth`
        ).toBe(false);
      }
    });
  });
});
