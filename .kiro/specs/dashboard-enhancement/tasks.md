# Implementation Plan: Dashboard Enhancement

## Overview

This plan implements the dashboard enhancement feature in incremental steps, starting with backend API endpoints, then frontend components, and finally wiring everything together. Backend work uses PHP/Laravel 11; frontend work uses TypeScript/Next.js 15 with React. Each task builds on previous tasks so there is no orphaned code.

## Tasks

- [x] 1. Create DashboardController with metrics and applications-by-stage endpoints
  - [x] 1.1 Create `backend/app/Http/Controllers/DashboardController.php` with `metrics()` method
    - Compute `open_jobs_count` from `JobPosting::where('status', 'published')->count()`
    - Compute `total_candidates` as distinct `candidate_id` count from `JobApplication` joined with tenant's `JobPosting`
    - Compute `applications_this_week` from `JobApplication` where `applied_at >= now()->subDays(7)` scoped to tenant
    - Compute `pipeline_conversion_rate` as percentage of applications at stages with `sort_order > 1`, rounded to 1 decimal; return 0.0 when no applications
    - Return `{ data: { open_jobs_count, total_candidates, applications_this_week, pipeline_conversion_rate } }`
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6_

  - [x] 1.2 Add `applicationsByStage()` method to DashboardController
    - Join `pipeline_stages` → `job_applications` → `job_postings` (tenant-scoped)
    - Group by `pipeline_stages.name`, order by `pipeline_stages.sort_order` ASC
    - Return `{ data: [{ stage_name, count }] }`
    - _Requirements: 5.1, 5.2_

  - [x] 1.3 Register dashboard routes in `backend/routes/api.php`
    - Add `GET /dashboard/metrics` → `DashboardController@metrics` with `havenhr.auth` and `tenant.resolve` middleware
    - Add `GET /dashboard/applications-by-stage` → `DashboardController@applicationsByStage` with same middleware
    - _Requirements: 1.6, 1.7_

  - [ ]* 1.4 Write property tests for DashboardController metrics
    - **Property 1: Open jobs count matches published postings**
    - **Property 2: Total candidates is a distinct count**
    - **Property 3: Applications this week counts only recent applications**
    - **Property 4: Pipeline conversion rate calculation**
    - **Validates: Requirements 1.2, 1.3, 1.4, 1.5**

  - [ ]* 1.5 Write property test for applications-by-stage ordering
    - **Property 7: Applications-by-stage ordering**
    - **Validates: Requirements 5.2**

- [x] 2. Enhance AuditLogController with date range and user filters
  - [x] 2.1 Update `backend/app/Http/Controllers/AuditLogController.php` index method
    - Add `from` query parameter: filter `created_at >= start of date` (silently ignore invalid dates)
    - Add `to` query parameter: filter `created_at <= end of date` (silently ignore invalid dates)
    - Add `user_id` query parameter: filter `where('user_id', $userId)`
    - Support combining all filters (`action`, `from`, `to`, `user_id`) simultaneously
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5, 14.6_

  - [ ]* 2.2 Write property tests for audit log filters
    - **Property 10: Audit log action filter**
    - **Property 11: Audit log date range filter**
    - **Property 12: Audit log user filter**
    - **Property 13: Audit log combined filters**
    - **Validates: Requirements 8.3, 9.3, 10.3, 14.1, 14.2, 14.3, 14.6**

- [x] 3. Enhance RoleController to eager-load permissions
  - [x] 3.1 Update `RoleController::show()` in `backend/app/Http/Controllers/RoleController.php`
    - Change `Role::find($id)` to `Role::with('permissions')->find($id)` to eager-load the permissions relationship
    - Ensure the response includes the `permissions` array with `id`, `name`, `resource`, `action`, `description` fields
    - Return empty `permissions` array when role has no permissions
    - _Requirements: 15.1, 15.2, 15.3_

  - [ ]* 3.2 Write unit tests for role detail with permissions
    - Test that `GET /api/v1/roles/{id}` includes `permissions` array
    - Test that role with no permissions returns empty array
    - Test 404 response for non-existent role
    - _Requirements: 15.1, 15.2, 15.3_

- [x] 4. Create CompanyController with GET and PUT endpoints
  - [x] 4.1 Create `backend/app/Http/Requests/UpdateCompanyRequest.php`
    - Extend `BaseFormRequest`
    - Validate `name`: required, string, max 255
    - _Requirements: 13.5, 13.6_

  - [x] 4.2 Create `backend/app/Http/Controllers/CompanyController.php`
    - `show()`: Return current tenant's company data (`id`, `name`, `email_domain`, `subscription_status`, `settings`)
    - `update(UpdateCompanyRequest)`: Update company name, return updated data
    - _Requirements: 13.1, 13.2_

  - [x] 4.3 Register company routes in `backend/routes/api.php`
    - Add `GET /company` → `CompanyController@show` with `havenhr.auth`, `tenant.resolve` middleware
    - Add `PUT /company` → `CompanyController@update` with `havenhr.auth`, `tenant.resolve`, `rbac:tenant.update` middleware
    - _Requirements: 13.3, 13.4_

  - [ ]* 4.4 Write property test for company name round trip
    - **Property 14: Company name update round trip**
    - **Validates: Requirements 13.2**

- [x] 5. Checkpoint — Backend complete
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. Build Dashboard Home page with stat cards, activity feed, quick actions, and stage chart
  - [x] 6.1 Create `frontend/src/components/dashboard/StatCard.tsx`
    - Accept `label`, `value`, `icon`, and `loading` props
    - Render white card with border, icon, label, and value
    - Render skeleton placeholder when `loading` is true
    - _Requirements: 2.1, 2.3, 2.5_

  - [x] 6.2 Create `frontend/src/components/dashboard/ActivityFeed.tsx`
    - Accept `logs` and `loading` props
    - Render each entry with color-coded action badge, human-readable description, and relative timestamp
    - Show "No recent activity." when empty
    - Include "View All" link to `/dashboard/audit-logs`
    - _Requirements: 3.1, 3.3, 3.4, 3.5_

  - [x] 6.3 Create `frontend/src/components/dashboard/StageChart.tsx`
    - Accept `data` and `loading` props
    - Render horizontal bar chart using CSS/Tailwind (no chart library)
    - Show "No application data yet." when empty
    - Show skeleton placeholder when loading
    - _Requirements: 5.3, 5.4, 5.5_

  - [x] 6.4 Rewrite `frontend/src/app/dashboard/page.tsx` as the Dashboard Home
    - Fetch metrics from `GET /api/v1/dashboard/metrics` using apiClient
    - Fetch activity from `GET /api/v1/audit-logs?per_page=10` using apiClient
    - Fetch stage data from `GET /api/v1/dashboard/applications-by-stage` using apiClient
    - Render four StatCard components (Open Jobs, Total Candidates, Applications This Week, Pipeline Conversion Rate)
    - Render ActivityFeed component with fetched audit logs
    - Render Quick Action Buttons: "Create Job" → `/dashboard/jobs/create`, "View Pipeline" → `/dashboard/jobs`, "Invite User" → `/dashboard/users?action=invite`
    - Hide "Create Job" if user lacks `jobs.create` permission; hide "Invite User" if user lacks `users.create` permission
    - Render StageChart component with fetched stage data
    - Show loading skeletons while fetching, error messages on failure
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 3.1, 3.2, 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 5.3_

  - [ ]* 6.5 Write property tests for Dashboard Home utilities
    - **Property 5: Relative timestamp accuracy**
    - **Property 6: Permission-gated quick action visibility**
    - **Validates: Requirements 3.3, 4.5, 4.6**

- [x] 7. Build Role Detail page with grouped permissions
  - [x] 7.1 Create `frontend/src/app/dashboard/roles/[id]/page.tsx`
    - Fetch role data from `GET /api/v1/roles/{id}` using apiClient (includes permissions)
    - Display role name, description, and "System Role" badge for `is_system_default` roles
    - Group permissions by `resource` field into Permission_Group sections
    - Display each permission's name and description within its group
    - Include "Back to Roles" link to `/dashboard/roles`
    - Show "Role not found" error on 404
    - Show loading state while fetching
    - _Requirements: 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8, 6.9, 6.10_

  - [x] 7.2 Update `frontend/src/app/dashboard/roles/page.tsx` to make role cards clickable
    - Wrap each role card in a link to `/dashboard/roles/{id}`
    - Use `router.push` or `<a>` tag for navigation
    - _Requirements: 6.1_

  - [ ]* 7.3 Write property test for permission grouping
    - **Property 8: Permission grouping by resource**
    - **Validates: Requirements 6.5, 6.6**

- [x] 8. Build Invite User Modal and integrate with Users page
  - [x] 8.1 Create `frontend/src/components/dashboard/InviteUserModal.tsx`
    - Accept `isOpen`, `onClose`, `onSuccess` props
    - Render modal with name (required), email (required, email validation), and role dropdown
    - Populate role dropdown from `GET /api/v1/roles`
    - Generate 16-character temporary password using `crypto.getRandomValues()` with uppercase, lowercase, digits, and special characters
    - Submit `POST /api/v1/users` with `{ name, email, password, role_id }`
    - On success: display success message with email and temporary password (copyable)
    - On 409 error: display "A user with this email already exists."
    - On other errors: display API error message
    - Disable submit button and show loading indicator while submitting
    - Include Cancel button to close without submitting
    - _Requirements: 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 7.9, 7.10, 7.11, 7.12_

  - [x] 8.2 Update `frontend/src/app/dashboard/users/page.tsx` to integrate Invite User Modal
    - Add "Invite User" button in page header, visible only when user has `users.create` permission
    - Open InviteUserModal on button click
    - Also open modal when URL has `action=invite` query parameter
    - Refresh user list on successful invite (`onSuccess` callback)
    - _Requirements: 7.1, 7.8_

  - [ ]* 8.3 Write property test for temporary password generation
    - **Property 9: Temporary password generation**
    - **Validates: Requirements 7.6**

- [x] 9. Checkpoint — Dashboard Home, Role Detail, and Invite User complete
  - Ensure all tests pass, ask the user if questions arise.

- [x] 10. Build Audit Log Filter Bar and integrate with Audit Logs page
  - [x] 10.1 Create `frontend/src/components/dashboard/AuditLogFilterBar.tsx`
    - Accept `filters`, `onFilterChange`, `onClear`, `hasActiveFilters` props
    - Render action type dropdown with predefined action types (user.login, user.logout, user.login_failed, user.registered, user.password_reset, role.assigned, role.changed, tenant.created, job.created, job.updated, job.deleted, job.status_changed, application.created, application.stage_changed)
    - Render "From" and "To" date picker inputs (`type="date"`)
    - Render user dropdown populated from `GET /api/v1/users`
    - Render "Clear Filters" button, visually distinct when filters are active
    - _Requirements: 8.1, 9.1, 10.1, 11.4, 11.6_

  - [x] 10.2 Update `frontend/src/app/dashboard/audit-logs/page.tsx` to integrate filter bar
    - Read filter values from URL query parameters (`action`, `from`, `to`, `user_id`, `page`) on page load
    - Apply filters to API request when fetching audit logs
    - Update URL query parameters on filter change without full page reload
    - Reset page to 1 when any filter changes
    - Clear all filters on "Clear Filters" button click
    - Preserve filter state on error
    - _Requirements: 8.2, 9.2, 10.2, 11.1, 11.2, 11.3, 11.5_

  - [ ]* 10.3 Write unit tests for Audit Log Filter Bar
    - Test filter bar renders all controls
    - Test initial values read from URL params
    - Test page resets to 1 on filter change
    - Test Clear Filters removes all params
    - _Requirements: 8.1, 9.1, 10.1, 11.1, 11.3, 11.5_

- [x] 11. Build Company Settings page and update Sidebar
  - [x] 11.1 Create `frontend/src/app/dashboard/settings/page.tsx`
    - Fetch company data from `GET /api/v1/company` using apiClient
    - Display editable company name input and read-only email domain
    - On "Save" click: send `PUT /api/v1/company` with updated name
    - Show success notification on save
    - Show error message on failure, preserve form state
    - Disable "Save" button and show loading indicator while saving
    - Display placeholder sections for "Logo Upload", "Branding Colors", "Notification Preferences" with "Coming Soon" badges
    - _Requirements: 12.3, 12.4, 12.5, 12.6, 12.7, 12.8, 12.9_

  - [x] 11.2 Update `frontend/src/components/dashboard/Sidebar.tsx` to add Settings nav item
    - Add a "Settings" entry to `NAV_ITEMS` with `href: "/dashboard/settings"`, `permission: "tenant.update"`, and a new `icon: "settings"`
    - Add the settings SVG icon to the `NavIcon` component
    - Update the `NavItem` icon type to include `"settings"`
    - _Requirements: 12.1, 12.2_

  - [ ]* 11.3 Write unit tests for Company Settings page
    - Test renders editable name and read-only domain
    - Test success notification on save
    - Test error display on failure
    - Test "Coming Soon" placeholder sections render
    - _Requirements: 12.3, 12.4, 12.5, 12.6, 12.7, 12.9_

- [x] 12. Final checkpoint — All features complete
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design document
- Unit tests validate specific examples and edge cases
- No database migrations are needed — all required models and tables already exist
- Backend follows existing patterns: `BaseFormRequest` for validation, `BelongsToTenant` for tenant scoping, `{ data, meta }` response shape
- Frontend follows existing patterns: `apiClient` for API calls, `useAuth().hasPermission()` for RBAC, URL search params for pagination/filtering
