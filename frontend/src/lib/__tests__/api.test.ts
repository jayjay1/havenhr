import { describe, it, expect, vi, beforeEach } from "vitest";
import { apiClient, ApiRequestError } from "@/lib/api";

describe("apiClient", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it("should make a GET request with correct headers", async () => {
    const mockResponse = { data: { id: "1", name: "Test" } };
    const fetchSpy = vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(JSON.stringify(mockResponse), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      })
    );

    const result = await apiClient.get("/users");

    expect(fetchSpy).toHaveBeenCalledWith(
      "http://localhost:8000/api/v1/users",
      expect.objectContaining({
        method: "GET",
        credentials: "include",
        headers: expect.objectContaining({ Accept: "application/json" }),
      })
    );
    expect(result).toEqual(mockResponse);
  });

  it("should make a POST request with JSON body", async () => {
    const mockResponse = { data: { id: "1" } };
    const fetchSpy = vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(JSON.stringify(mockResponse), {
        status: 201,
        headers: { "Content-Type": "application/json" },
      })
    );

    const body = { name: "Test", email: "test@example.com" };
    const result = await apiClient.post("/users", body);

    expect(fetchSpy).toHaveBeenCalledWith(
      "http://localhost:8000/api/v1/users",
      expect.objectContaining({
        method: "POST",
        credentials: "include",
        headers: expect.objectContaining({
          "Content-Type": "application/json",
          Accept: "application/json",
        }),
        body: JSON.stringify(body),
      })
    );
    expect(result).toEqual(mockResponse);
  });

  it("should make a PUT request with JSON body", async () => {
    const mockResponse = { data: { id: "1", name: "Updated" } };
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(JSON.stringify(mockResponse), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      })
    );

    const body = { name: "Updated" };
    const result = await apiClient.put("/users/1", body);

    expect(result).toEqual(mockResponse);
  });

  it("should make a DELETE request", async () => {
    const mockResponse = { data: null };
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(JSON.stringify(mockResponse), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      })
    );

    const result = await apiClient.del("/users/1");

    expect(result).toEqual(mockResponse);
  });

  it("should throw ApiRequestError on error responses", async () => {
    const errorBody = {
      error: {
        code: "VALIDATION_ERROR",
        message: "The given data was invalid.",
        details: { fields: { email: { value: "bad", messages: ["Invalid email"] } } },
      },
    };
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(JSON.stringify(errorBody), {
        status: 422,
        headers: { "Content-Type": "application/json" },
      })
    );

    await expect(apiClient.post("/register", {})).rejects.toThrow(
      ApiRequestError
    );
  });

  it("should parse error details from API error responses", async () => {
    const errorBody = {
      error: {
        code: "VALIDATION_ERROR",
        message: "The given data was invalid.",
        details: { fields: { email: { value: "bad", messages: ["Invalid email"] } } },
      },
    };
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(JSON.stringify(errorBody), {
        status: 422,
        headers: { "Content-Type": "application/json" },
      })
    );

    try {
      await apiClient.post("/register", {});
      expect.unreachable("Should have thrown");
    } catch (err) {
      const apiErr = err as ApiRequestError;
      expect(apiErr.status).toBe(422);
      expect(apiErr.code).toBe("VALIDATION_ERROR");
      expect(apiErr.message).toBe("The given data was invalid.");
      expect(apiErr.details).toEqual(errorBody.error.details);
    }
  });

  it("should handle non-JSON error responses gracefully", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response("Internal Server Error", {
        status: 500,
        statusText: "Internal Server Error",
      })
    );

    try {
      await apiClient.get("/broken");
    } catch (err) {
      const apiErr = err as ApiRequestError;
      expect(apiErr.status).toBe(500);
      expect(apiErr.code).toBe("UNKNOWN_ERROR");
      expect(apiErr.message).toBe("Internal Server Error");
    }
  });

  it("should handle path with leading slash", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(JSON.stringify({ data: {} }), { status: 200 })
    );

    await apiClient.get("/users");

    expect(globalThis.fetch).toHaveBeenCalledWith(
      "http://localhost:8000/api/v1/users",
      expect.anything()
    );
  });

  it("should handle path without leading slash", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(JSON.stringify({ data: {} }), { status: 200 })
    );

    await apiClient.get("users");

    expect(globalThis.fetch).toHaveBeenCalledWith(
      "http://localhost:8000/api/v1/users",
      expect.anything()
    );
  });
});
