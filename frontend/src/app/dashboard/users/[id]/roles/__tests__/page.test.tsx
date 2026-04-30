import { describe, it, expect } from "vitest";
import { getAssignableRoles } from "../page";

describe("getAssignableRoles", () => {
  it("returns all 5 roles for owner", () => {
    const roles = getAssignableRoles("owner");
    expect(roles).toEqual([
      "owner",
      "admin",
      "recruiter",
      "hiring_manager",
      "viewer",
    ]);
  });

  it("returns all roles except owner for admin", () => {
    const roles = getAssignableRoles("admin");
    expect(roles).toEqual(["admin", "recruiter", "hiring_manager", "viewer"]);
    expect(roles).not.toContain("owner");
  });

  it("returns empty array for recruiter", () => {
    expect(getAssignableRoles("recruiter")).toEqual([]);
  });

  it("returns empty array for hiring_manager", () => {
    expect(getAssignableRoles("hiring_manager")).toEqual([]);
  });

  it("returns empty array for viewer", () => {
    expect(getAssignableRoles("viewer")).toEqual([]);
  });
});
