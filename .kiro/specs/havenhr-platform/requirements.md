# Requirements Document

## Introduction

HavenHR is an AI-powered Hiring Operating System (SaaS) designed to serve multiple companies (tenants) on a single platform. This foundational specification covers the Multi-Tenant System, Authentication, Role-Based Access Control (RBAC), Core Database Models, and Security infrastructure. All future modules (jobs, candidates, pipelines, AI features) will build on top of this foundation.

The platform uses a Next.js (App Router) frontend with Tailwind CSS, a Laravel REST API backend, PostgreSQL for persistence, and Redis for caching and queues. The architecture is event-driven and must scale to 100,000+ tenants.

## Glossary

- **Tenant**: A company registered on the HavenHR platform. Each Tenant is a logically isolated unit of data and configuration.
- **Tenant_ID**: A unique identifier assigned to each Tenant, present on every tenant-scoped database row to enforce data isolation.
- **User**: An individual person who has registered an account within a specific Tenant.
- **Owner**: The User who initially registered the Tenant. The Owner has full administrative privileges over the Tenant.
- **Admin**: A User role with broad administrative privileges within a Tenant, excluding certain Owner-only actions such as deleting the Tenant.
- **Recruiter**: A User role focused on managing job postings and candidate pipelines within a Tenant.
- **Hiring_Manager**: A User role focused on reviewing candidates and making hiring decisions within a Tenant.
- **Viewer**: A read-only User role within a Tenant.
- **Role**: A named set of Permissions assigned to a User within a Tenant.
- **Permission**: A discrete, granular authorization to perform a specific action on a specific resource within a Tenant.
- **Auth_Service**: The backend service responsible for authentication, token management, and session handling.
- **RBAC_Middleware**: The middleware layer that intercepts API requests and enforces Role and Permission checks before allowing access to protected resources.
- **Audit_Logger**: The service responsible for recording User actions and system events into the Audit Log.
- **Audit_Log**: A persistent, append-only record of User actions and system events within a Tenant.
- **Access_Token**: A short-lived JWT issued by the Auth_Service to authenticate API requests.
- **Refresh_Token**: A long-lived, securely stored token used to obtain a new Access_Token without re-authentication.
- **Rate_Limiter**: The middleware that restricts the number of requests a client can make within a defined time window.
- **Registration_Service**: The backend service responsible for creating new Tenants and their initial Owner accounts.
- **Password_Hasher**: The component responsible for securely hashing and verifying User passwords using bcrypt.
- **Input_Validator**: The middleware and service layer responsible for validating and sanitizing all incoming request data.
- **Event_Bus**: The event-driven messaging system (backed by Redis queues) used for asynchronous processing of domain events.

## Requirements

### Requirement 1: Tenant Registration

**User Story:** As a company representative, I want to register my company on HavenHR, so that my organization gets its own isolated workspace for hiring operations.

#### Acceptance Criteria

1. WHEN a valid registration request containing company name, company email domain, owner name, owner email, and owner password is submitted, THE Registration_Service SHALL create a new Tenant record with a unique Tenant_ID, create the Owner User account associated with that Tenant_ID, assign the Owner Role to that User, and return a confirmation response within 2 seconds.
2. WHEN a registration request contains a company email domain that is already associated with an existing Tenant, THE Registration_Service SHALL reject the registration and return a descriptive error indicating the domain is already registered.
3. IF a registration request contains invalid or missing required fields, THEN THE Input_Validator SHALL reject the request and return a response listing each invalid field with a specific validation error message.
4. WHEN a Tenant is successfully created, THE Event_Bus SHALL publish a "tenant.created" event containing the Tenant_ID and a timestamp.
5. WHEN a Tenant is successfully created, THE Audit_Logger SHALL record the creation event with the Tenant_ID, Owner User ID, action type, and timestamp.

---

### Requirement 2: Tenant Data Isolation

**User Story:** As a platform operator, I want strict data isolation between tenants, so that no company can ever access another company's data.

#### Acceptance Criteria

1. THE Database Schema SHALL include a Tenant_ID column on every tenant-scoped table.
2. THE Database Schema SHALL enforce a NOT NULL constraint on every Tenant_ID column.
3. THE Database Schema SHALL include a composite index containing Tenant_ID on every tenant-scoped table to optimize tenant-scoped queries.
4. WHEN any database query is executed against a tenant-scoped table, THE Data Access Layer SHALL include a WHERE clause filtering by the authenticated Tenant_ID.
5. WHEN a User attempts to access a resource belonging to a different Tenant, THE Data Access Layer SHALL deny the request and return a 403 Forbidden response.
6. THE Data Access Layer SHALL use Laravel global query scopes to automatically apply Tenant_ID filtering to all tenant-scoped Eloquent models.

---

### Requirement 3: User Registration

**User Story:** As a Tenant Admin, I want to invite and register users into my company workspace, so that team members can access the hiring platform.

#### Acceptance Criteria

1. WHEN an Admin or Owner submits a user registration request with a valid name, email, password, and Role, THE Auth_Service SHALL create a new User record associated with the current Tenant_ID and assign the specified Role.
2. WHEN a user registration request contains an email that already exists within the same Tenant, THE Auth_Service SHALL reject the request and return an error indicating the email is already registered in this workspace.
3. WHEN a user registration request contains an email that exists in a different Tenant, THE Auth_Service SHALL allow the registration, creating a separate User record for the current Tenant.
4. THE Password_Hasher SHALL hash all User passwords using bcrypt with a minimum cost factor of 12 before storing them in the database.
5. WHEN a User is successfully registered, THE Audit_Logger SHALL record the registration event with the Tenant_ID, new User ID, inviting User ID, assigned Role, and timestamp.
6. IF a user registration request specifies a Role that the requesting User does not have permission to assign, THEN THE RBAC_Middleware SHALL reject the request and return a 403 Forbidden response.

---

### Requirement 4: User Authentication — Login

**User Story:** As a registered user, I want to log in to my company workspace, so that I can access the hiring platform securely.

#### Acceptance Criteria

1. WHEN a User submits valid email and password credentials, THE Auth_Service SHALL verify the credentials, generate an Access_Token with a 15-minute expiration and a Refresh_Token with a 7-day expiration, and return both tokens in the response.
2. THE Access_Token SHALL contain the User ID, Tenant_ID, Role, and token expiration timestamp as claims.
3. WHEN a User submits an incorrect password, THE Auth_Service SHALL return a generic "Invalid credentials" error without revealing whether the email or password was incorrect.
4. WHEN a User submits an email that does not exist within any Tenant, THE Auth_Service SHALL return the same generic "Invalid credentials" error and respond within the same time range as a valid-email attempt to prevent timing-based enumeration.
5. WHEN a User successfully logs in, THE Audit_Logger SHALL record the login event with the Tenant_ID, User ID, IP address, user agent, and timestamp.
6. WHEN a User fails to log in, THE Audit_Logger SHALL record the failed attempt with the provided email (without the password), IP address, user agent, and timestamp.

---

### Requirement 5: User Authentication — Token Refresh

**User Story:** As a logged-in user, I want my session to remain active without frequent re-authentication, so that I have a seamless experience while maintaining security.

#### Acceptance Criteria

1. WHEN a valid, non-expired Refresh_Token is submitted, THE Auth_Service SHALL issue a new Access_Token with a 15-minute expiration and a new Refresh_Token with a 7-day expiration, and invalidate the previous Refresh_Token.
2. WHEN an expired Refresh_Token is submitted, THE Auth_Service SHALL reject the request and return a 401 Unauthorized response.
3. WHEN a previously invalidated Refresh_Token is submitted, THE Auth_Service SHALL reject the request, invalidate all Refresh_Tokens for that User, and return a 401 Unauthorized response.
4. THE Auth_Service SHALL store Refresh_Tokens in the database with the associated User ID, Tenant_ID, token hash, expiration timestamp, and revocation status.

---

### Requirement 6: User Authentication — Password Reset

**User Story:** As a user who has forgotten my password, I want to reset it securely, so that I can regain access to my account.

#### Acceptance Criteria

1. WHEN a password reset request is submitted with a registered email, THE Auth_Service SHALL generate a cryptographically random reset token with a 60-minute expiration and send a reset link to the email address.
2. WHEN a password reset request is submitted with an unregistered email, THE Auth_Service SHALL return the same success response as for a registered email to prevent email enumeration.
3. WHEN a valid, non-expired reset token and a new password are submitted, THE Auth_Service SHALL update the User password, invalidate the reset token, invalidate all existing Refresh_Tokens for that User, and return a success response.
4. WHEN an expired or already-used reset token is submitted, THE Auth_Service SHALL reject the request and return a descriptive error.
5. WHEN a password is successfully reset, THE Audit_Logger SHALL record the event with the Tenant_ID, User ID, IP address, and timestamp.

---

### Requirement 7: User Authentication — Logout

**User Story:** As a logged-in user, I want to log out of my account, so that my session is terminated and my tokens are invalidated.

#### Acceptance Criteria

1. WHEN a logout request is submitted with a valid Access_Token, THE Auth_Service SHALL invalidate the associated Refresh_Token and add the Access_Token to a blocklist cached in Redis until the Access_Token expiration time.
2. WHEN a logout request is processed, THE Auth_Service SHALL return a success response confirming the session has been terminated.
3. WHEN a blocklisted Access_Token is submitted with any API request, THE Auth_Service SHALL reject the request and return a 401 Unauthorized response.
4. WHEN a User successfully logs out, THE Audit_Logger SHALL record the logout event with the Tenant_ID, User ID, and timestamp.

---

### Requirement 8: Role-Based Access Control — Role Definitions

**User Story:** As a platform architect, I want a well-defined set of roles with specific permissions, so that access control is consistent and predictable across all tenants.

#### Acceptance Criteria

1. THE RBAC_Middleware SHALL enforce the following five Roles: Owner, Admin, Recruiter, Hiring_Manager, and Viewer.
2. THE Owner Role SHALL include all Permissions available in the system, including Tenant deletion and Role management for all other Roles.
3. THE Admin Role SHALL include all Permissions except Tenant deletion and the ability to modify the Owner Role assignment.
4. THE Recruiter Role SHALL include Permissions for managing job postings, candidate records, and pipeline stages within the Tenant.
5. THE Hiring_Manager Role SHALL include Permissions for viewing job postings, reviewing candidates, and submitting hiring decisions within the Tenant.
6. THE Viewer Role SHALL include only read Permissions for viewing job postings, candidates, and reports within the Tenant.
7. THE Database Schema SHALL store Roles and Permissions in dedicated tables with a many-to-many relationship, scoped by Tenant_ID.

---

### Requirement 9: Role-Based Access Control — Enforcement

**User Story:** As a platform operator, I want every API request to be checked against the user's role and permissions, so that unauthorized actions are blocked before reaching business logic.

#### Acceptance Criteria

1. WHEN an authenticated API request is received, THE RBAC_Middleware SHALL extract the User Role and Permissions from the Access_Token and verify the User has the required Permission for the requested resource and action before forwarding the request to the controller.
2. WHEN a User lacks the required Permission for a requested action, THE RBAC_Middleware SHALL reject the request and return a 403 Forbidden response with a message indicating insufficient permissions.
3. WHEN an Owner or Admin assigns or changes a Role for a User within the Tenant, THE RBAC_Middleware SHALL verify the requesting User has the "manage_roles" Permission before allowing the operation.
4. WHEN a Role assignment is changed, THE Auth_Service SHALL invalidate all existing Access_Tokens for the affected User so that the new Role takes effect on the next token refresh.
5. WHEN a Role assignment is changed, THE Audit_Logger SHALL record the change with the Tenant_ID, affected User ID, previous Role, new Role, requesting User ID, and timestamp.

---

### Requirement 10: Core Database Models

**User Story:** As a developer, I want a well-structured foundational database schema, so that all future modules can build on a consistent and scalable data model.

#### Acceptance Criteria

1. THE Database Schema SHALL include a "companies" table with columns for id (UUID primary key), name, email_domain (unique), subscription_status, settings (JSON), created_at, and updated_at.
2. THE Database Schema SHALL include a "users" table with columns for id (UUID primary key), tenant_id (foreign key to companies), name, email, password_hash, email_verified_at, is_active, last_login_at, created_at, and updated_at, with a unique composite constraint on (tenant_id, email).
3. THE Database Schema SHALL include a "roles" table with columns for id (UUID primary key), tenant_id (foreign key to companies), name, description, is_system_default, created_at, and updated_at.
4. THE Database Schema SHALL include a "permissions" table with columns for id (UUID primary key), name, resource, action, description, created_at, and updated_at.
5. THE Database Schema SHALL include a "role_permission" pivot table with columns for role_id (foreign key to roles) and permission_id (foreign key to permissions), with a unique composite constraint on (role_id, permission_id).
6. THE Database Schema SHALL include a "user_role" pivot table with columns for user_id (foreign key to users), role_id (foreign key to roles), assigned_by (foreign key to users, nullable), assigned_at, with a unique composite constraint on (user_id, role_id).
7. THE Database Schema SHALL use UUID primary keys for all tables to support distributed systems and prevent ID enumeration.

---

### Requirement 11: Audit Logging

**User Story:** As a compliance officer, I want all user actions and system events to be logged immutably, so that the organization has a complete audit trail for security and compliance purposes.

#### Acceptance Criteria

1. THE Audit_Logger SHALL record every state-changing API request with the following fields: id (UUID), tenant_id, user_id, action, resource_type, resource_id, previous_state (JSON, nullable), new_state (JSON, nullable), ip_address, user_agent, created_at.
2. THE Database Schema SHALL define the "audit_logs" table as append-only by not providing any UPDATE or DELETE API endpoints for audit log records.
3. WHEN an auditable action is performed, THE Audit_Logger SHALL write the log entry asynchronously via the Event_Bus to avoid impacting API response times.
4. THE Audit_Logger SHALL record the following action types at minimum: user.registered, user.login, user.login_failed, user.logout, user.password_reset, role.assigned, role.changed, tenant.created, tenant.updated.
5. WHEN an audit log query is executed, THE Data Access Layer SHALL filter by Tenant_ID so that audit logs from one Tenant are not visible to another Tenant.

---

### Requirement 12: Input Validation and Sanitization

**User Story:** As a security engineer, I want all user input to be validated and sanitized, so that the platform is protected against injection attacks and malformed data.

#### Acceptance Criteria

1. THE Input_Validator SHALL validate all incoming API request data against a defined schema before the request reaches the controller layer.
2. THE Input_Validator SHALL reject requests containing fields that are not defined in the request schema and return a 422 Unprocessable Entity response.
3. THE Input_Validator SHALL validate email fields using RFC 5322 compliant email format validation.
4. THE Input_Validator SHALL enforce password complexity rules requiring a minimum of 12 characters, at least one uppercase letter, one lowercase letter, one digit, and one special character.
5. THE Input_Validator SHALL sanitize all string inputs to prevent SQL injection and cross-site scripting (XSS) by using parameterized queries and HTML entity encoding for output.
6. IF a request fails validation, THEN THE Input_Validator SHALL return a 422 response with a JSON body listing each invalid field, the rejected value (excluding sensitive fields such as passwords), and a human-readable error message.

---

### Requirement 13: Rate Limiting

**User Story:** As a platform operator, I want to limit the rate of API requests, so that the platform is protected against brute-force attacks and abuse.

#### Acceptance Criteria

1. THE Rate_Limiter SHALL enforce a limit of 60 requests per minute per authenticated User for general API endpoints.
2. THE Rate_Limiter SHALL enforce a limit of 5 requests per minute per IP address for authentication endpoints (login, registration, password reset).
3. WHEN a client exceeds the rate limit, THE Rate_Limiter SHALL return a 429 Too Many Requests response with a "Retry-After" header indicating the number of seconds until the limit resets.
4. THE Rate_Limiter SHALL use Redis to track request counts with automatic key expiration matching the rate limit window.
5. WHEN a rate limit is exceeded on an authentication endpoint, THE Audit_Logger SHALL record the event with the IP address, endpoint, and timestamp.

---

### Requirement 14: Secure API Communication

**User Story:** As a security engineer, I want all API communication to follow security best practices, so that data in transit and at rest is protected.

#### Acceptance Criteria

1. THE Auth_Service SHALL set Access_Tokens and Refresh_Tokens as HTTP-only, Secure, SameSite=Strict cookies when serving browser-based clients.
2. THE API SHALL include the following security headers on all responses: X-Content-Type-Options: nosniff, X-Frame-Options: DENY, Strict-Transport-Security with a max-age of 31536000 and includeSubDomains, and Content-Security-Policy with a restrictive default-src policy.
3. THE API SHALL enable CORS with an allowlist of permitted origins, rejecting requests from origins not on the allowlist.
4. THE API SHALL enforce HTTPS for all endpoints by redirecting HTTP requests to HTTPS.

---

### Requirement 15: Frontend — Authentication Pages

**User Story:** As a user, I want responsive, accessible authentication pages, so that I can register, log in, and reset my password from any device.

#### Acceptance Criteria

1. THE Frontend SHALL provide a Tenant registration page with fields for company name, company email domain, owner name, owner email, and owner password, following a mobile-first responsive layout.
2. THE Frontend SHALL provide a login page with fields for email and password, following a mobile-first responsive layout.
3. THE Frontend SHALL provide a password reset request page with a field for email, following a mobile-first responsive layout.
4. THE Frontend SHALL provide a password reset confirmation page with fields for new password and password confirmation, following a mobile-first responsive layout.
5. THE Frontend SHALL display inline validation errors for each form field when the Input_Validator returns validation errors.
6. THE Frontend SHALL meet WCAG 2.1 Level AA accessibility standards for all authentication pages, including proper form labels, keyboard navigation, focus management, and sufficient color contrast ratios.

---

### Requirement 16: Frontend — Dashboard and Role Management

**User Story:** As an Owner or Admin, I want a dashboard to manage users and roles within my company workspace, so that I can control who has access and what they can do.

#### Acceptance Criteria

1. WHEN an authenticated User accesses the dashboard, THE Frontend SHALL display a navigation layout appropriate to the User's Role, showing only menu items the User has Permission to access.
2. WHEN an Owner or Admin accesses the user management page, THE Frontend SHALL display a paginated list of Users within the Tenant, including each User's name, email, Role, status, and last login timestamp.
3. WHEN an Owner or Admin initiates a Role change for a User, THE Frontend SHALL present a Role selection interface showing only the Roles the requesting User has Permission to assign.
4. THE Frontend SHALL follow a mobile-first responsive layout for all dashboard pages, adapting to viewport widths from 320px to 2560px.
5. THE Frontend SHALL meet WCAG 2.1 Level AA accessibility standards for all dashboard pages.

---

### Requirement 17: Event-Driven Architecture Foundation

**User Story:** As a platform architect, I want an event-driven foundation, so that modules can communicate asynchronously and the system remains decoupled and scalable.

#### Acceptance Criteria

1. THE Event_Bus SHALL use Redis-backed Laravel queues to process domain events asynchronously.
2. THE Event_Bus SHALL support publishing events with a payload containing event_type, tenant_id, user_id, data (JSON), and timestamp.
3. WHEN an event fails processing after 3 retry attempts, THE Event_Bus SHALL move the event to a failed-jobs queue and record the failure with the event payload and error details.
4. THE Event_Bus SHALL process events in the order they are published within a single Tenant context.
5. WHEN a domain event is published, THE Event_Bus SHALL deliver the event to all registered listeners for that event type.
