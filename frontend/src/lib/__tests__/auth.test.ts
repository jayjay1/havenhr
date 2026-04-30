import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { refreshAccessToken, withTokenRefresh, logout } from "@/lib/auth";
import { ApiRequestError } from "@/lib/api";

// Mock the apiClient module
vi.mock("@/lib/api", async () => {
  const actual = await vi.importActual<typeof import("@/lib/api")>("@/lib/api");
  return {
    ...actual,
    apiClient: {
      get: vi.fn(),
      post: vi.fn(),
      put: vi.fn(),
      del: vi.fn(),
    },
  };
});

// Get the mocked apiClient
import { apiClient } from "@/lib/api";
const mockedApiClient = vi.mocked(apiClient);

describe("refreshAccessToken", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("should call POST /auth/refresh and return true on success", async () => {
    mockedApiClient.post.mockResolvedValueOnce({ data: {} });

    const result = await refreshAccessToken();

    expect(result).toBe(true);
    expect(mockedApiClient.post).toHaveBeenCalledWith("/auth/refresh");
  });

  it("should return false when refresh fails", async () => {
    mockedApiClient.post.mockRejectedValueOnce(
      new ApiRequestError(401, {
        error: { code: "TOKEN_EXPIRED", message: "Refresh token expired" },
      })
    );

    const result = await refreshAccessToken();

    expect(result).toBe(false);
  });

  it("should prevent concurrent refresh attempts", async () => {
    // Create a slow refresh that we can control
    let resolveRefresh: (value: unknown) => void;
    const slowRefresh = new Promise((resolve) => {
      resolveRefresh = resolve;
    });
    mockedApiClient.post.mockReturnValueOnce(slowRefresh as never);

    // Start first refresh (will be pending)
    const firstRefresh = refreshAccessToken();

    // Start second refresh while first is still pending
    const secondResult = await refreshAccessToken();

    // Second should return false immediately (concurrent protection)
    expect(secondResult).toBe(false);

    // Resolve the first refresh
    resolveRefresh!({ data: {} });
    const firstResult = await firstRefresh;
    expect(firstResult).toBe(true);

    // Only one API call should have been made
    expect(mockedApiClient.post).toHaveBeenCalledTimes(1);
  });
});

describe("withTokenRefresh", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("should return the result when request succeeds", async () => {
    const expectedResponse = { data: { id: "1", name: "Test" } };
    const requestFn = vi.fn().mockResolvedValue(expectedResponse);

    const result = await withTokenRefresh(requestFn);

    expect(result).toEqual(expectedResponse);
    expect(requestFn).toHaveBeenCalledTimes(1);
  });

  it("should retry after successful token refresh on 401", async () => {
    const expectedResponse = { data: { id: "1", name: "Test" } };
    const requestFn = vi
      .fn()
      .mockRejectedValueOnce(
        new ApiRequestError(401, {
          error: { code: "TOKEN_EXPIRED", message: "Token expired" },
        })
      )
      .mockResolvedValueOnce(expectedResponse);

    // Mock successful refresh
    mockedApiClient.post.mockResolvedValueOnce({ data: {} });

    const result = await withTokenRefresh(requestFn);

    expect(result).toEqual(expectedResponse);
    expect(requestFn).toHaveBeenCalledTimes(2);
    expect(mockedApiClient.post).toHaveBeenCalledWith("/auth/refresh");
  });

  it("should redirect to /login when refresh fails on 401", async () => {
    const requestFn = vi.fn().mockRejectedValue(
      new ApiRequestError(401, {
        error: { code: "TOKEN_EXPIRED", message: "Token expired" },
      })
    );

    // Mock failed refresh
    mockedApiClient.post.mockRejectedValueOnce(
      new ApiRequestError(401, {
        error: { code: "REFRESH_FAILED", message: "Refresh failed" },
      })
    );

    // Mock window.location
    const originalLocation = window.location;
    Object.defineProperty(window, "location", {
      writable: true,
      value: { href: "" },
    });

    await expect(withTokenRefresh(requestFn)).rejects.toThrow(ApiRequestError);
    expect(window.location.href).toBe("/login");

    // Restore
    Object.defineProperty(window, "location", {
      writable: true,
      value: originalLocation,
    });
  });

  it("should rethrow non-401 errors without attempting refresh", async () => {
    const requestFn = vi.fn().mockRejectedValue(
      new ApiRequestError(422, {
        error: { code: "VALIDATION_ERROR", message: "Invalid data" },
      })
    );

    await expect(withTokenRefresh(requestFn)).rejects.toThrow(ApiRequestError);
    expect(mockedApiClient.post).not.toHaveBeenCalled();
    expect(requestFn).toHaveBeenCalledTimes(1);
  });

  it("should rethrow non-ApiRequestError errors without attempting refresh", async () => {
    const requestFn = vi.fn().mockRejectedValue(new Error("Network error"));

    await expect(withTokenRefresh(requestFn)).rejects.toThrow("Network error");
    expect(mockedApiClient.post).not.toHaveBeenCalled();
  });
});

describe("logout", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    // Restore window.location if modified
  });

  it("should call POST /auth/logout and redirect to /login", async () => {
    mockedApiClient.post.mockResolvedValueOnce({ data: {} });

    const originalLocation = window.location;
    Object.defineProperty(window, "location", {
      writable: true,
      value: { href: "" },
    });

    await logout();

    expect(mockedApiClient.post).toHaveBeenCalledWith("/auth/logout");
    expect(window.location.href).toBe("/login");

    Object.defineProperty(window, "location", {
      writable: true,
      value: originalLocation,
    });
  });

  it("should redirect to /login even if API call fails", async () => {
    mockedApiClient.post.mockRejectedValueOnce(new Error("Network error"));

    const originalLocation = window.location;
    Object.defineProperty(window, "location", {
      writable: true,
      value: { href: "" },
    });

    await logout();

    expect(window.location.href).toBe("/login");

    Object.defineProperty(window, "location", {
      writable: true,
      value: originalLocation,
    });
  });
});
