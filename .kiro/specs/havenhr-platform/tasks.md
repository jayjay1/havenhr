# Implementation Plan: HavenHR Multi-Tenant Foundation & Auth

## Overview

This plan implements the foundational layer of HavenHR: multi-tenant isolation, authentication, RBAC, audit logging, input validation, rate limiting, secure API communication, event-driven architecture, and frontend auth/dashboard pages. Tasks are ordered by dependency — database schema and core models first, then services, middleware, and finally frontend. Each task builds incrementally on previous work.

## Tasks

- [x] 1. Project scaffolding and database schema
  - [x] 1.1 Initialize Laravel project with PostgreSQL and Redis configuration
    - Create Laravel project with required dependencies (jwt-auth, uuid support)
    - Configure `.env` for PostgreSQL connection and Redis for cache/queues
    - Set up Pest testing framework with Faker
    - _Requirements: 10.1–10.7, 17.1_

  - [x] 1.2 Create database migrations for all core tables
    - Create `companies` migration: id (UUID PK), name, email_domain (unique), subscription_status (enum: trial/active/suspended/cancelled, default trial), settings (JSON), timestamps
    - Create `users` migration: id (UUID PK), tenant_id (FK to companies, NOT NULL, indexed), name, email, password_hash, email_verified_at, is_active (default true), last_login_at, timestamps; unique composite index on (tenant_id, email)
    - Create `roles` migration: id (UUID PK), tenant_id (FK to companies, NOT NULL), name, description, is_system_default, timestamps; unique composite index on (tenant_id, name)
    - Create `permissions` migration: id (UUID PK), name (unique), resource, action, description, timestamps
    - Create `role_permission` pivot migration: role_id (FK), permission_id (FK); composite PK on (role_id, permission_id)
    - Create `user_role` pivot migration: user_id (FK), role_id (FK), assigned_by (FK, nullable), assigned_at; composite PK on (user_id, role_id)
    - Create `refresh_tokens` migration: id (UUID PK), user_id (FK), tenant_id (FK, indexed), token_hash (indexed), expires_at, is_revoked (default false), created_at; composite index on (user_id, is_revoked)
    - Create `password_resets` migration: id (UUID PK), user_id (FK), token_hash (indexed), expires_at, is_used (default false), created_at; composite index on (user_id, is_used)
    - Create `audit_logs` migration: id (UUID PK), tenant_id (FK, NOT NULL), user_id (FK, nullable), action, resource_type, resource_id (nullable), previous_state (JSON, nullable), new_state (JSON, nullable), ip_address, user_agent, created_at; composite indexes on (tenant_id, created_at) and (tenant_id, action)
    - All tables use UUID primary keys
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 2.1, 2.2, 2.3_

  - [x] 1.3 Create Eloquent models with relationships and UUID traits
    - Create `Company` model with UUID trait, `hasMany` relationships to users, roles, audit_logs
    - Create `User` model with UUID trait, `belongsTo` Company, `belongsToMany` roles via user_role pivot, `hasMany` refresh_tokens and password_resets
    - Create `Role` model with UUID trait, `belongsTo` Company, `belongsToMany` permissions via role_permission pivot, `belongsToMany` users via user_role pivot
    - Create `Permission` model with UUID trait, `belongsToMany` roles
    - Create `RefreshToken` model with UUID trait, `belongsTo` User
    - Create `PasswordReset` model with UUID trait, `belongsTo` User
    - Create `AuditLog` model with UUID trait, `belongsTo` Company and User, disable updates/deletes at model level
    - _Requirements: 10.1–10.7, 11.2_

  - [x] 1.4 Create database seeder for permissions and default role templates
    - Seed all system permissions: users.create, users.view, users.list, users.update, users.delete, roles.list, roles.view, manage_roles, audit_logs.view, tenant.update, tenant.delete, jobs.create, jobs.view, jobs.list, jobs.update, jobs.delete, candidates.create, candidates.view, candidates.list, candidates.update, candidates.delete, pipeline.manage, reports.view, owner.assign
    - Define role-permission mappings: Owner gets all permissions; Admin gets all except tenant.delete and owner.assign; Recruiter gets jobs.*, candidates.*, pipeline.manage; Hiring_Manager gets jobs.view, jobs.list, candidates.view, candidates.list, reports.view; Viewer gets *.view and *.list only
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7_

- [x] 2. Tenant isolation and global scopes
  - [x] 2.1 Implement TenantScope global query scope
    - Create `TenantScope` class implementing Laravel's `Scope` interface
    - Apply automatic `WHERE tenant_id = ?` clause to all queries on tenant-scoped models
    - Create `BelongsToTenant` trait that boots the global scope and sets tenant_id on creating events
    - Apply trait to: User, Role, RefreshToken, AuditLog models
    - _Requirements: 2.4, 2.6_

  - [x] 2.2 Implement TenantResolver middleware
    - Extract tenant_id from JWT claims and bind to request context
    - Set the current tenant_id in a singleton `TenantContext` service so global scopes can access it
    - Reject requests where the resolved tenant_id does not match the resource's tenant_id with 403 Forbidden
    - _Requirements: 2.4, 2.5_

  - [ ]* 2.3 Write property tests for tenant isolation (Properties 4, 5)
    - **Property 4: Tenant scoping applied to all queries** — Generate random tenant contexts and verify every query on tenant-scoped models includes the tenant_id WHERE clause
    - **Validates: Requirements 2.4**
    - **Property 5: Cross-tenant resource access denied** — Generate two tenants, create resources in tenant A, attempt access from tenant B context, verify 403 response
    - **Validates: Requirements 2.5**

- [x] 3. Checkpoint — Run migrations and verify schema
  - Ensure all migrations run cleanly, all models instantiate correctly, and tenant scope is applied. Ask the user if questions arise.

- [x] 4. Event bus and audit logging
  - [x] 4.1 Implement Event Bus with Redis-backed queues
    - Configure Laravel queue connection to use Redis
    - Create base `DomainEvent` class with payload schema: event_type, tenant_id, user_id, data (JSON), timestamp (ISO 8601)
    - Create tenant-specific queue channels (`tenant:{id}:events`) for per-tenant ordering
    - Configure retry policy: 3 attempts with exponential backoff (1s, 4s, 16s)
    - Configure failed events to move to `failed_jobs` table with full payload and error details
    - Create concrete event classes: TenantCreated, UserRegistered, UserLogin, UserLoginFailed, UserLogout, UserPasswordReset, RoleAssigned, RoleChanged
    - _Requirements: 17.1, 17.2, 17.3, 17.4, 17.5_

  - [ ]* 4.2 Write property tests for Event Bus (Properties 34, 35, 36)
    - **Property 34: Event payload contains required schema fields** — Generate random event payloads and verify all required fields are present and correctly typed
    - **Validates: Requirements 17.2**
    - **Property 35: Events processed in publication order within a tenant** — Publish multiple events for a single tenant and verify processing order matches publication order
    - **Validates: Requirements 17.4**
    - **Property 36: Events delivered to all registered listeners** — Register multiple listeners for an event type, publish event, verify all listeners receive it
    - **Validates: Requirements 17.5**

  - [x] 4.3 Implement Audit Logger service and listener
    - Create `AuditLoggerService` that writes to `audit_logs` table
    - Create event listener that subscribes to all domain events and writes audit log entries asynchronously
    - Ensure all required fields are populated: id, tenant_id, user_id, action, resource_type, resource_id, previous_state, new_state, ip_address, user_agent, created_at
    - Implement read-only API endpoint `GET /api/v1/audit-logs` with tenant scoping and `audit_logs.view` permission
    - Support action types: user.registered, user.login, user.login_failed, user.logout, user.password_reset, role.assigned, role.changed, tenant.created, tenant.updated
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_

  - [ ]* 4.4 Write property test for audit log structure (Property 23)
    - **Property 23: Audit log entries contain all required fields** — Generate random state-changing actions and verify resulting audit log entries contain all required fields with correct types
    - **Validates: Requirements 11.1**

- [x] 5. Input validation framework
  - [x] 5.1 Create base Form Request classes with validation and sanitization
    - Create abstract `BaseFormRequest` that rejects unknown fields (return 422 for extra fields)
    - Implement email validation using RFC 5322 compliant rules
    - Implement password complexity rule: min 12 chars, 1 uppercase, 1 lowercase, 1 digit, 1 special character
    - Implement string sanitization using parameterized queries (Laravel default) and HTML entity encoding for output
    - Format validation error responses as structured JSON: field name → { value (redacted for passwords), messages[] }
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5, 12.6_

  - [x] 5.2 Create specific Form Request classes for each endpoint
    - `RegisterTenantRequest`: company_name (required, max:255), company_email_domain (required, unique, valid domain), owner_name (required, max:255), owner_email (required, RFC 5322), owner_password (required, password complexity)
    - `LoginRequest`: email (required, RFC 5322), password (required)
    - `CreateUserRequest`: name (required, max:255), email (required, RFC 5322), password (required, password complexity), role (required, valid role name)
    - `ForgotPasswordRequest`: email (required, RFC 5322)
    - `ResetPasswordRequest`: token (required), password (required, password complexity), password_confirmation (required, matches password)
    - `AssignRoleRequest`: role_id (required, valid UUID, exists in roles)
    - _Requirements: 1.1, 1.3, 3.1, 4.1, 6.1, 6.3, 12.1–12.6_

  - [ ]* 5.3 Write property tests for input validation (Properties 24, 25, 26, 27, 28)
    - **Property 24: Input validation rejects invalid data before controller with structured errors** — Generate random invalid payloads and verify 422 responses with structured field errors
    - **Validates: Requirements 12.1, 12.6**
    - **Property 25: Unknown request fields are rejected** — Generate payloads with extra fields and verify 422 rejection
    - **Validates: Requirements 12.2**
    - **Property 26: Email validation follows RFC 5322** — Generate valid and invalid email strings and verify acceptance/rejection
    - **Validates: Requirements 12.3**
    - **Property 27: Password complexity validation** — Generate passwords with varying complexity and verify acceptance/rejection based on rules
    - **Validates: Requirements 12.4**
    - **Property 28: Input sanitization prevents injection** — Generate strings with SQL injection and XSS payloads and verify they are safely handled via parameterized queries and HTML encoding
    - **Validates: Requirements 12.5**

- [x] 6. Checkpoint — Verify event bus, audit logging, and validation
  - Ensure event dispatch and listener processing works, audit logs are written asynchronously, and validation rejects invalid input correctly. Ask the user if questions arise.

- [x] 7. Registration service
  - [x] 7.1 Implement tenant registration endpoint
    - Create `TenantController` with `register` action at `POST /api/v1/register`
    - Create `RegistrationService` with transactional logic: validate input → check domain uniqueness → begin transaction → create Company record → create User record → seed default roles for tenant → assign Owner role → commit → dispatch `tenant.created` event → return response
    - Return 201 with tenant and user data on success
    - Return 409 with descriptive error if domain already exists
    - Return 422 with field errors if validation fails
    - Ensure response within 2 seconds
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

  - [ ]* 7.2 Write property tests for registration (Properties 1, 2, 3)
    - **Property 1: Registration creates tenant, owner, and role assignment** — Generate random valid registration payloads and verify tenant, user, and role assignment records are created with matching tenant_id
    - **Validates: Requirements 1.1**
    - **Property 2: Duplicate domain registration is rejected** — Register a tenant, then attempt registration with the same domain and verify rejection with no new records
    - **Validates: Requirements 1.2**
    - **Property 3: Invalid registration input produces field-specific errors** — Generate payloads with various invalid/missing fields and verify structured error responses
    - **Validates: Requirements 1.3**

- [x] 8. Authentication service
  - [x] 8.1 Implement login endpoint
    - Create `AuthController` with `login` action at `POST /api/v1/auth/login`
    - Create `AuthService` login method: validate input → look up user by email (timing-safe: always hash even if not found) → verify bcrypt password → generate JWT Access_Token (15 min, claims: user_id, tenant_id, role, jti) → generate opaque Refresh_Token, store SHA-256 hash in refresh_tokens table (7 day expiry) → set HTTP-only Secure SameSite=Strict cookies → dispatch user.login audit event → return user profile
    - Return generic "Invalid credentials" error for wrong password or non-existent email (same response time)
    - Dispatch user.login_failed audit event on failure with email, IP, user agent
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_

  - [x] 8.2 Implement token refresh endpoint
    - Create `refresh` action at `POST /api/v1/auth/refresh`
    - Extract refresh token from cookie → hash with SHA-256 → look up in refresh_tokens table
    - If not found or revoked: revoke ALL user refresh tokens (replay detection), return 401
    - If expired: return 401
    - Revoke current refresh token → issue new Access_Token + Refresh_Token pair → set cookies → return tokens
    - _Requirements: 5.1, 5.2, 5.3, 5.4_

  - [x] 8.3 Implement logout endpoint
    - Create `logout` action at `POST /api/v1/auth/logout`
    - Extract Access_Token JTI → add to Redis blocklist with TTL = remaining token lifetime
    - Revoke associated Refresh_Token in DB
    - Clear cookies → dispatch user.logout audit event → return success
    - _Requirements: 7.1, 7.2, 7.3, 7.4_

  - [x] 8.4 Implement password reset flow (forgot + reset)
    - Create `forgotPassword` action at `POST /api/v1/auth/password/forgot`: always return same success response regardless of email existence; if user exists, generate 64-byte hex token, store SHA-256 hash in password_resets table with 60-min expiry, queue email
    - Create `resetPassword` action at `POST /api/v1/auth/password/reset`: verify token hash → update password (bcrypt cost 12) → mark token as used → revoke all refresh tokens → dispatch user.password_reset audit event
    - Return descriptive error for expired or already-used tokens
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

  - [ ]* 8.5 Write property tests for authentication (Properties 8, 10, 11, 12, 13, 14, 15, 16)
    - **Property 8: Passwords are hashed with bcrypt cost factor ≥ 12** — Generate random passwords, hash them, verify the stored hash is valid bcrypt with cost ≥ 12 and verification succeeds
    - **Validates: Requirements 3.4**
    - **Property 10: Valid login issues correctly-structured tokens** — Generate valid user credentials, login, verify Access_Token contains user_id, tenant_id, role claims with 15-min expiry and Refresh_Token has 7-day expiry
    - **Validates: Requirements 4.1, 4.2**
    - **Property 11: Token refresh issues new pair and invalidates old** — Generate valid refresh tokens, submit them, verify new pair issued and old token revoked
    - **Validates: Requirements 5.1**
    - **Property 12: Reused refresh token triggers full revocation** — Revoke a refresh token, resubmit it, verify ALL user refresh tokens are revoked and 401 returned
    - **Validates: Requirements 5.3**
    - **Property 13: Password reset generates time-limited token** — Generate registered emails, request reset, verify token record with 60-min expiry and hashed token
    - **Validates: Requirements 6.1**
    - **Property 14: Valid reset token updates password and revokes sessions** — Generate valid reset tokens with new passwords, submit reset, verify password updated, token marked used, all refresh tokens revoked
    - **Validates: Requirements 6.3**
    - **Property 15: Logout invalidates refresh token and blocklists access token** — Generate authenticated sessions, logout, verify refresh token revoked and access token JTI in Redis blocklist
    - **Validates: Requirements 7.1**
    - **Property 16: Blocklisted access token is rejected** — Add access token JTIs to Redis blocklist, attempt API requests, verify 401 responses
    - **Validates: Requirements 7.3**

- [x] 9. Checkpoint — Verify registration and authentication flows
  - Ensure tenant registration, login, token refresh, logout, and password reset all work end-to-end. Ask the user if questions arise.

- [x] 10. User management service
  - [x] 10.1 Implement user CRUD endpoints
    - Create `UserController` with CRUD actions: index (GET /api/v1/users, paginated), store (POST /api/v1/users), show (GET /api/v1/users/{id}), update (PUT /api/v1/users/{id}), destroy (DELETE /api/v1/users/{id})
    - Create `UserService` with business logic: create user with bcrypt password hash (cost 12), assign specified role, enforce email uniqueness per tenant (allow same email across tenants)
    - All queries automatically scoped by tenant_id via global scope
    - Dispatch user.registered audit event on creation
    - Enforce permission checks: users.list, users.create, users.view, users.update, users.delete
    - Support pagination with `?page=1&per_page=20` query parameters
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

  - [ ]* 10.2 Write property tests for user management (Properties 6, 7)
    - **Property 6: User creation with valid role assignment** — Generate valid user payloads with authorized requesters, verify user and role assignment records created with correct tenant_id
    - **Validates: Requirements 3.1**
    - **Property 7: Email uniqueness is enforced per-tenant** — Generate duplicate emails within same tenant (verify rejection) and across different tenants (verify both succeed)
    - **Validates: Requirements 3.2, 3.3**

- [x] 11. RBAC middleware and role management
  - [x] 11.1 Implement RBAC middleware
    - Create `RbacMiddleware` that extracts user role and permissions from JWT claims
    - Verify user has the required permission for the requested route before forwarding to controller
    - Return 403 Forbidden with "insufficient permissions" message when permission check fails
    - Define route-to-permission mapping for all API endpoints
    - _Requirements: 9.1, 9.2_

  - [x] 11.2 Implement role management endpoints
    - Create `RoleController` with actions: index (GET /api/v1/roles), show (GET /api/v1/roles/{id}), assignRole (POST /api/v1/users/{id}/roles), updateRole (PUT /api/v1/users/{id}/roles)
    - Verify requesting user has `manage_roles` permission
    - Enforce role assignment hierarchy: Owner can assign all roles; Admin cannot assign Owner
    - On role change: invalidate all Access_Tokens for affected user (add JTI to Redis blocklist)
    - Dispatch role.changed audit event with previous role, new role, requesting user
    - _Requirements: 9.3, 9.4, 9.5, 3.6_

  - [ ]* 11.3 Write property tests for RBAC (Properties 9, 17, 18, 19, 20, 21, 22)
    - **Property 9: Unauthorized role assignment is rejected** — Generate users without role assignment permission, attempt role assignment, verify 403 and no change
    - **Validates: Requirements 3.6**
    - **Property 17: Owner role includes all system permissions** — Verify Owner role has every permission in the system
    - **Validates: Requirements 8.2**
    - **Property 18: Admin role includes all permissions except tenant deletion and owner role modification** — Verify Admin has all permissions except tenant.delete and owner.assign
    - **Validates: Requirements 8.3**
    - **Property 19: Viewer role has only read permissions** — Verify every permission on Viewer role has action = view or list
    - **Validates: Requirements 8.6**
    - **Property 20: RBAC grants access if and only if user has required permission** — Generate users with various roles, attempt various endpoints, verify access granted/denied matches permission set
    - **Validates: Requirements 9.1, 9.2**
    - **Property 21: Role assignment requires manage_roles permission** — Generate users with and without manage_roles, attempt role assignment, verify only authorized succeed
    - **Validates: Requirements 9.3**
    - **Property 22: Role change invalidates affected user's access tokens** — Change a user's role, verify their access tokens are blocklisted in Redis
    - **Validates: Requirements 9.4**

- [x] 12. Security middleware stack
  - [x] 12.1 Implement rate limiting middleware
    - Create `RateLimiter` middleware using Redis counters with automatic key expiration
    - Enforce 60 requests/min per authenticated user for general API endpoints
    - Enforce 5 requests/min per IP for auth endpoints (login, register, password reset)
    - Return 429 Too Many Requests with `Retry-After` header when limit exceeded
    - Dispatch audit event when rate limit exceeded on auth endpoints
    - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5_

  - [x] 12.2 Implement security headers middleware
    - Create `SecurityHeaders` middleware that adds to all responses: X-Content-Type-Options: nosniff, X-Frame-Options: DENY, Strict-Transport-Security: max-age=31536000; includeSubDomains, Content-Security-Policy with restrictive default-src
    - _Requirements: 14.2_

  - [x] 12.3 Implement CORS middleware and HTTPS enforcement
    - Configure CORS with origin allowlist, reject requests from non-allowlisted origins
    - Implement ForceHttps middleware to redirect HTTP → HTTPS
    - _Requirements: 14.3, 14.4_

  - [x] 12.4 Register middleware stack in correct order
    - Register middleware in Laravel kernel in exact order: ForceHttps → SecurityHeaders → CorsMiddleware → RateLimiter → JwtAuth → TenantResolver → RbacMiddleware → ValidateInput
    - Apply auth-specific rate limits to auth route group, general rate limits to authenticated route group
    - _Requirements: 13.1, 13.2, 14.2, 14.3, 14.4_

  - [ ]* 12.5 Write property tests for security (Properties 29, 30)
    - **Property 29: Security headers present on all responses** — Make requests to various endpoints and verify all required security headers are present with correct values
    - **Validates: Requirements 14.2**
    - **Property 30: CORS enforces origin allowlist** — Generate requests from various origins and verify CORS headers only present for allowlisted origins
    - **Validates: Requirements 14.3**

- [x] 13. Checkpoint — Verify backend API is complete
  - Ensure all backend endpoints work: registration, auth, user CRUD, role management, audit logs. Verify middleware stack (rate limiting, security headers, CORS, RBAC) is applied correctly. Ask the user if questions arise.

- [x] 14. Frontend — Next.js project setup and shared components
  - [x] 14.1 Initialize Next.js project with App Router and Tailwind CSS
    - Set up Next.js project with TypeScript, App Router, and Tailwind CSS
    - Configure API client with base URL, cookie-based auth, and error interceptors
    - Install fast-check for property-based testing and configure test runner
    - Set up shared types for API responses, user, tenant, role, and permission interfaces
    - _Requirements: 15.1–15.6, 16.1–16.5_

  - [x] 14.2 Create shared form components with accessibility support
    - Create reusable `FormInput` component with label association, aria-describedby for errors, keyboard navigation support
    - Create reusable `FormError` component for inline validation error display
    - Create `PasswordInput` component with show/hide toggle and complexity indicator
    - Create `Button` component with loading state
    - Ensure all components meet WCAG 2.1 AA: proper labels, focus management, color contrast ≥ 4.5:1
    - _Requirements: 15.5, 15.6_

- [x] 15. Frontend — Authentication pages
  - [x] 15.1 Implement tenant registration page
    - Create `/register` route with form fields: company name, company email domain, owner name, owner email, owner password
    - Mobile-first responsive layout (320px–2560px)
    - Inline validation errors mapped from API 422 responses
    - Loading state during API call, success redirect to login
    - WCAG 2.1 AA compliant
    - _Requirements: 15.1, 15.5, 15.6_

  - [x] 15.2 Implement login page
    - Create `/login` route with form fields: email, password
    - Mobile-first responsive layout
    - Display generic "Invalid credentials" error from API
    - Loading state, success redirect to dashboard
    - WCAG 2.1 AA compliant
    - _Requirements: 15.2, 15.5, 15.6_

  - [x] 15.3 Implement password reset pages
    - Create `/forgot-password` route with email field
    - Create `/reset-password/[token]` route with new password and confirmation fields
    - Mobile-first responsive layout for both pages
    - Inline validation errors, loading states, success messages
    - WCAG 2.1 AA compliant
    - _Requirements: 15.3, 15.4, 15.5, 15.6_

  - [ ]* 15.4 Write property test for frontend validation error display (Property 31)
    - **Property 31: Frontend displays inline validation errors** — Generate random sets of field-level validation errors and verify each error is displayed inline next to its corresponding form field
    - **Validates: Requirements 15.5**

- [x] 16. Frontend — Dashboard and role management
  - [x] 16.1 Implement dashboard layout with role-aware navigation
    - Create `/dashboard` layout with sidebar navigation
    - Filter navigation menu items based on user's role permissions (server-side check)
    - Show only menu items the user has permission to access
    - Mobile-first responsive layout (320px–2560px), collapsible sidebar on mobile
    - WCAG 2.1 AA compliant
    - _Requirements: 16.1, 16.4, 16.5_

  - [x] 16.2 Implement user management page
    - Create `/dashboard/users` route with paginated user list (server-side pagination)
    - Display columns: name, email, role, status (active/inactive), last login
    - Support pagination via `?page=1&per_page=20` query parameters
    - Mobile-first responsive layout with table/card view adaptation
    - WCAG 2.1 AA compliant
    - _Requirements: 16.2, 16.4, 16.5_

  - [x] 16.3 Implement role management interface
    - Create `/dashboard/users/[id]/roles` route for role assignment
    - Show role selection filtered by requesting user's assignable roles (Owner sees all, Admin sees all except Owner)
    - Submit role change via API, handle success/error responses
    - WCAG 2.1 AA compliant
    - _Requirements: 16.3, 16.4, 16.5_

  - [ ]* 16.4 Write property tests for dashboard (Properties 32, 33)
    - **Property 32: Role-aware navigation shows only permitted items** — Generate users with various roles and verify navigation only shows items matching their permissions
    - **Validates: Requirements 16.1**
    - **Property 33: Role selection filtered by requester's assignable roles** — Generate Owner and Admin users and verify role selection options are correctly filtered
    - **Validates: Requirements 16.3**

- [x] 17. Frontend — Auth middleware and token management
  - [x] 17.1 Implement frontend auth middleware and protected routes
    - Create Next.js middleware to check authentication on protected routes
    - Implement automatic token refresh when Access_Token expires (intercept 401, call refresh, retry)
    - Redirect unauthenticated users to login page
    - Implement logout flow: call API logout endpoint, clear client state, redirect to login
    - Set up cookie handling for HTTP-only Secure SameSite=Strict tokens
    - _Requirements: 4.1, 5.1, 7.1, 14.1_

- [x] 18. Final checkpoint — Full integration verification
  - Ensure all backend and frontend components work together end-to-end. Verify: tenant registration → login → dashboard with role-aware navigation → user management → role assignment → logout. Ensure all tests pass. Ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation at key milestones
- Property tests validate universal correctness properties from the design document (36 properties total)
- Backend uses PHP/Laravel with Pest + Faker for property-based testing
- Frontend uses TypeScript/Next.js with fast-check for property-based testing
- All database operations use UUID primary keys and tenant-scoped global query scopes
