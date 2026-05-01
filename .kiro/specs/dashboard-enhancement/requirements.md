# Requirements Document

## Introduction

This specification covers a major enhancement to the HavenHR employer dashboard. The current dashboard is minimal — a welcome message with no actionable content. This feature adds a metrics-driven home page with activity feed and charts, a role detail page showing grouped permissions, a user invite/create flow, audit log filtering with URL-persisted query params, and a company settings page. All enhancements integrate with the existing Next.js 15 / Laravel 11 stack, follow established RBAC patterns via `useAuth().hasPermission()`, and use the existing `apiClient` for API communication.

## Glossary

- **Dashboard_Home**: The main landing page at `/dashboard` that displays summary metrics, recent activity, quick actions, and an applications-by-stage chart.
- **Stat_Card**: A UI card component on the Dashboard_Home that displays a single summary metric (label, value, optional trend indicator).
- **Activity_Feed**: A list component on the Dashboard_Home that renders the most recent audit log entries with human-readable descriptions.
- **Quick_Action_Button**: A prominent button on the Dashboard_Home that navigates to a frequently used action (Create Job, View Pipeline, Invite User).
- **Stage_Chart**: A bar chart or funnel visualization on the Dashboard_Home showing application counts grouped by pipeline stage.
- **Role_Detail_Page**: A read-only page at `/dashboard/roles/[id]` that displays a role's name, description, and grouped permissions.
- **Permission_Group**: A visual grouping of permissions by category (users, roles, jobs, pipeline, audit_logs, applications) on the Role_Detail_Page.
- **Invite_User_Modal**: A modal dialog on the Users page that collects name, email, and role to create a new user.
- **Temporary_Password**: A system-generated password assigned to a newly invited user, displayed once in a success message.
- **Audit_Log_Filter_Bar**: A UI component on the Audit Logs page containing dropdowns and date pickers for filtering log entries.
- **Company_Settings_Page**: A page at `/dashboard/settings` where Owner and Admin users can view and edit company information.
- **Dashboard_Metrics_API**: A backend endpoint that returns aggregated dashboard statistics (open jobs count, total candidates, weekly applications, pipeline conversion rate).
- **Sidebar**: The left navigation component that renders role-aware navigation items for the dashboard.
- **apiClient**: The frontend HTTP client at `frontend/src/lib/api.ts` used for all API requests with JWT Bearer token authentication.
- **RBAC_Middleware**: The Laravel middleware (`rbac:{permission_name}`) that enforces permission-based access control on API endpoints.

## Requirements

### Requirement 1: Dashboard Metrics API

**User Story:** As an employer, I want to see summary statistics on my dashboard home page, so that I can quickly understand my hiring activity at a glance.

#### Acceptance Criteria

1. WHEN a GET request is made to `/api/v1/dashboard/metrics`, THE Dashboard_Metrics_API SHALL return a JSON response containing `open_jobs_count`, `total_candidates`, `applications_this_week`, and `pipeline_conversion_rate`.
2. THE Dashboard_Metrics_API SHALL compute `open_jobs_count` as the count of job postings with status "published" within the current tenant.
3. THE Dashboard_Metrics_API SHALL compute `total_candidates` as the count of distinct candidates who have applied to any job within the current tenant.
4. THE Dashboard_Metrics_API SHALL compute `applications_this_week` as the count of job applications created within the last 7 calendar days for the current tenant.
5. THE Dashboard_Metrics_API SHALL compute `pipeline_conversion_rate` as the percentage of applications that have moved beyond the first pipeline stage, rounded to one decimal place.
6. THE Dashboard_Metrics_API SHALL require the `havenhr.auth` and `tenant.resolve` middleware for authentication and tenant scoping.
7. IF the authenticated user lacks a valid JWT token, THEN THE Dashboard_Metrics_API SHALL return a 401 Unauthorized response.

### Requirement 2: Dashboard Home Stat Cards

**User Story:** As an employer, I want to see key hiring metrics displayed as cards on my dashboard, so that I can monitor recruitment progress without navigating to other pages.

#### Acceptance Criteria

1. WHEN the Dashboard_Home page loads, THE Dashboard_Home SHALL display four Stat_Card components showing Open Jobs, Total Candidates, Applications This Week, and Pipeline Conversion Rate.
2. WHEN the Dashboard_Home page loads, THE Dashboard_Home SHALL fetch metrics data from the Dashboard_Metrics_API using the apiClient.
3. WHILE the metrics data is loading, THE Dashboard_Home SHALL display a loading skeleton for each Stat_Card.
4. IF the metrics API request fails, THEN THE Dashboard_Home SHALL display an error message within the metrics section.
5. THE Stat_Card component SHALL display a label, a numeric value, and an icon for each metric.

### Requirement 3: Dashboard Recent Activity Feed

**User Story:** As an employer, I want to see recent activity on my dashboard, so that I can stay informed about what is happening in my organization.

#### Acceptance Criteria

1. WHEN the Dashboard_Home page loads, THE Activity_Feed SHALL display the 10 most recent audit log entries for the current tenant.
2. THE Activity_Feed SHALL fetch data from the existing `/api/v1/audit-logs` endpoint with `per_page=10`.
3. THE Activity_Feed SHALL render each entry with a human-readable description, a color-coded action badge, and a relative timestamp (e.g., "2 hours ago").
4. WHEN an Activity_Feed entry is empty (no audit logs exist), THE Activity_Feed SHALL display a message stating "No recent activity."
5. THE Activity_Feed SHALL include a "View All" link that navigates to `/dashboard/audit-logs`.

### Requirement 4: Dashboard Quick Action Buttons

**User Story:** As an employer, I want quick action buttons on my dashboard, so that I can navigate to common tasks with a single click.

#### Acceptance Criteria

1. THE Dashboard_Home SHALL display Quick_Action_Button components for "Create Job", "View Pipeline", and "Invite User".
2. WHEN the "Create Job" Quick_Action_Button is clicked, THE Dashboard_Home SHALL navigate to `/dashboard/jobs/create`.
3. WHEN the "View Pipeline" Quick_Action_Button is clicked, THE Dashboard_Home SHALL navigate to `/dashboard/jobs`.
4. WHEN the "Invite User" Quick_Action_Button is clicked, THE Dashboard_Home SHALL navigate to `/dashboard/users` with a query parameter `action=invite`.
5. WHERE the user lacks the `jobs.create` permission, THE Dashboard_Home SHALL hide the "Create Job" Quick_Action_Button.
6. WHERE the user lacks the `users.create` permission, THE Dashboard_Home SHALL hide the "Invite User" Quick_Action_Button.

### Requirement 5: Dashboard Applications by Stage Chart

**User Story:** As an employer, I want to see a visual breakdown of applications by pipeline stage, so that I can identify bottlenecks in my hiring process.

#### Acceptance Criteria

1. WHEN a GET request is made to `/api/v1/dashboard/applications-by-stage`, THE Dashboard_Metrics_API SHALL return an array of objects, each containing `stage_name` and `count`.
2. THE Dashboard_Metrics_API SHALL order the results by pipeline stage position in ascending order.
3. WHEN the Dashboard_Home page loads, THE Stage_Chart SHALL render a bar chart using the applications-by-stage data.
4. WHILE the chart data is loading, THE Stage_Chart SHALL display a loading skeleton placeholder.
5. IF no application data exists, THEN THE Stage_Chart SHALL display a message stating "No application data yet."

### Requirement 6: Role Detail Page with Grouped Permissions

**User Story:** As an administrator, I want to click on a role and see its permissions grouped by category, so that I can understand what each role is authorized to do.

#### Acceptance Criteria

1. WHEN a role card is clicked on the Roles page, THE Roles page SHALL navigate to `/dashboard/roles/[id]`.
2. WHEN the Role_Detail_Page loads, THE Role_Detail_Page SHALL fetch the role data from `GET /api/v1/roles/{id}` including its permissions.
3. THE backend `GET /api/v1/roles/{id}` endpoint SHALL return the role object with a `permissions` array, where each permission includes `name`, `resource`, `action`, and `description`.
4. THE Role_Detail_Page SHALL display the role name and description at the top of the page.
5. THE Role_Detail_Page SHALL group permissions into Permission_Group sections by the `resource` field (users, roles, jobs, pipeline, audit_logs, applications).
6. THE Role_Detail_Page SHALL display each permission within its group showing the permission name and description.
7. THE Role_Detail_Page SHALL display a "System Role" badge for roles where `is_system_default` is true.
8. THE Role_Detail_Page SHALL include a "Back to Roles" link that navigates to `/dashboard/roles`.
9. IF the role is not found, THEN THE Role_Detail_Page SHALL display a "Role not found" error message.
10. THE Role_Detail_Page SHALL require the `roles.view` permission to access.

### Requirement 7: User Invite/Create Flow

**User Story:** As an administrator, I want to invite new users to my organization, so that I can onboard team members and assign them appropriate roles.

#### Acceptance Criteria

1. WHERE the user has the `users.create` permission, THE Users page SHALL display an "Invite User" button in the page header.
2. WHEN the "Invite User" button is clicked, THE Invite_User_Modal SHALL open.
3. THE Invite_User_Modal SHALL contain input fields for name (required), email (required, validated as email format), and a role selection dropdown.
4. THE Invite_User_Modal SHALL populate the role dropdown by fetching roles from `GET /api/v1/roles`.
5. WHEN the Invite_User_Modal form is submitted with valid data, THE Invite_User_Modal SHALL send a POST request to `/api/v1/users` with name, email, a generated Temporary_Password, and the selected role.
6. THE Invite_User_Modal SHALL generate a Temporary_Password of 16 characters containing uppercase letters, lowercase letters, digits, and special characters.
7. WHEN the user creation succeeds, THE Invite_User_Modal SHALL display a success message containing the new user's email and the Temporary_Password.
8. WHEN the user creation succeeds, THE Users page SHALL refresh the user list to include the newly created user.
9. IF the email already exists within the tenant, THEN THE Invite_User_Modal SHALL display an error message stating "A user with this email already exists."
10. IF the API request fails for another reason, THEN THE Invite_User_Modal SHALL display the error message from the API response.
11. WHILE the form is submitting, THE Invite_User_Modal SHALL disable the submit button and display a loading indicator.
12. THE Invite_User_Modal SHALL include a "Cancel" button that closes the modal without submitting.

### Requirement 8: Audit Log Filtering by Action Type

**User Story:** As an administrator, I want to filter audit logs by action type, so that I can find specific events quickly.

#### Acceptance Criteria

1. THE Audit_Log_Filter_Bar SHALL display a dropdown for selecting an action type with options including: user.login, user.logout, user.login_failed, user.registered, user.password_reset, role.assigned, role.changed, tenant.created, job.created, job.updated, job.deleted, job.status_changed, application.created, application.stage_changed.
2. WHEN an action type is selected, THE Audit Logs page SHALL update the URL query parameter `action` and re-fetch logs filtered by that action.
3. THE backend `/api/v1/audit-logs` endpoint SHALL accept an `action` query parameter and filter results to only logs matching that action type.

### Requirement 9: Audit Log Filtering by Date Range

**User Story:** As an administrator, I want to filter audit logs by date range, so that I can investigate events within a specific time period.

#### Acceptance Criteria

1. THE Audit_Log_Filter_Bar SHALL display "From" and "To" date picker inputs.
2. WHEN date range values are set, THE Audit Logs page SHALL update the URL query parameters `from` and `to` and re-fetch logs filtered by the date range.
3. THE backend `/api/v1/audit-logs` endpoint SHALL accept `from` and `to` query parameters as ISO 8601 date strings and filter results to logs created within that range (inclusive).
4. IF only a `from` date is provided, THEN THE backend SHALL return logs created on or after that date.
5. IF only a `to` date is provided, THEN THE backend SHALL return logs created on or before that date.

### Requirement 10: Audit Log Filtering by User

**User Story:** As an administrator, I want to filter audit logs by user, so that I can review the actions of a specific team member.

#### Acceptance Criteria

1. THE Audit_Log_Filter_Bar SHALL display a user dropdown populated by fetching users from `GET /api/v1/users`.
2. WHEN a user is selected, THE Audit Logs page SHALL update the URL query parameter `user_id` and re-fetch logs filtered by that user.
3. THE backend `/api/v1/audit-logs` endpoint SHALL accept a `user_id` query parameter and filter results to logs where the `user_id` matches.

### Requirement 11: Audit Log Filter Persistence and Reset

**User Story:** As an administrator, I want my audit log filters to persist when I refresh the page and to be able to clear all filters at once, so that I have a consistent and efficient filtering experience.

#### Acceptance Criteria

1. THE Audit Logs page SHALL read filter values from URL query parameters (`action`, `from`, `to`, `user_id`, `page`) on page load and apply them to the filter controls and API request.
2. WHEN any filter value changes, THE Audit Logs page SHALL update the URL query parameters without a full page reload.
3. WHEN any filter value changes, THE Audit Logs page SHALL reset the page parameter to 1.
4. THE Audit_Log_Filter_Bar SHALL display a "Clear Filters" button.
5. WHEN the "Clear Filters" button is clicked, THE Audit Logs page SHALL remove all filter query parameters from the URL and re-fetch unfiltered logs.
6. WHILE filters are active, THE "Clear Filters" button SHALL be visually distinct to indicate active filters.

### Requirement 12: Company Settings Page

**User Story:** As a company owner or administrator, I want a settings page where I can view and edit company information, so that I can keep my organization's details up to date.

#### Acceptance Criteria

1. THE Sidebar SHALL display a "Settings" navigation item with the `tenant.update` permission requirement.
2. WHEN the "Settings" navigation item is clicked, THE Sidebar SHALL navigate to `/dashboard/settings`.
3. WHEN the Company_Settings_Page loads, THE Company_Settings_Page SHALL fetch company data from `GET /api/v1/company`.
4. THE Company_Settings_Page SHALL display the company name in an editable text input and the email domain as read-only text.
5. WHEN the company name is edited and the "Save" button is clicked, THE Company_Settings_Page SHALL send a PUT request to `/api/v1/company` with the updated name.
6. WHEN the save request succeeds, THE Company_Settings_Page SHALL display a success notification.
7. IF the save request fails, THEN THE Company_Settings_Page SHALL display the error message from the API response.
8. WHILE the save request is in progress, THE Company_Settings_Page SHALL disable the "Save" button and display a loading indicator.
9. THE Company_Settings_Page SHALL display placeholder sections labeled "Logo Upload", "Branding Colors", and "Notification Preferences" with "Coming Soon" badges.

### Requirement 13: Company Settings Backend API

**User Story:** As a company owner or administrator, I want API endpoints for reading and updating company settings, so that the settings page can retrieve and persist company data.

#### Acceptance Criteria

1. WHEN a GET request is made to `/api/v1/company`, THE backend SHALL return the current tenant's company data including `id`, `name`, `email_domain`, `subscription_status`, and `settings`.
2. WHEN a PUT request is made to `/api/v1/company` with a `name` field, THE backend SHALL update the company name and return the updated company data.
3. THE `/api/v1/company` GET endpoint SHALL require the `havenhr.auth` and `tenant.resolve` middleware.
4. THE `/api/v1/company` PUT endpoint SHALL require the `havenhr.auth`, `tenant.resolve` middleware and the `rbac:tenant.update` permission.
5. IF the `name` field is missing or empty in a PUT request, THEN THE backend SHALL return a 422 Validation Error response.
6. IF the `name` field exceeds 255 characters, THEN THE backend SHALL return a 422 Validation Error response.

### Requirement 14: Audit Log Backend Filter Enhancements

**User Story:** As a backend service, I want the audit logs endpoint to support filtering by date range and user, so that the frontend can provide comprehensive filtering capabilities.

#### Acceptance Criteria

1. WHEN the `from` query parameter is provided to `GET /api/v1/audit-logs`, THE AuditLogController SHALL filter results to logs with `created_at` greater than or equal to the start of that date.
2. WHEN the `to` query parameter is provided to `GET /api/v1/audit-logs`, THE AuditLogController SHALL filter results to logs with `created_at` less than or equal to the end of that date.
3. WHEN the `user_id` query parameter is provided to `GET /api/v1/audit-logs`, THE AuditLogController SHALL filter results to logs where `user_id` matches the provided value.
4. THE AuditLogController SHALL validate that `from` and `to` parameters are valid date strings when provided.
5. IF an invalid date format is provided for `from` or `to`, THEN THE AuditLogController SHALL ignore the invalid parameter and proceed without that filter.
6. THE AuditLogController SHALL support combining all filter parameters (`action`, `from`, `to`, `user_id`) simultaneously.

### Requirement 15: Role Detail Backend Enhancement

**User Story:** As a backend service, I want the role detail endpoint to include permissions data, so that the frontend can display grouped permissions on the role detail page.

#### Acceptance Criteria

1. WHEN a GET request is made to `/api/v1/roles/{id}`, THE RoleController SHALL return the role object with a `permissions` array eager-loaded.
2. THE `permissions` array SHALL contain objects with `id`, `name`, `resource`, `action`, and `description` fields for each permission assigned to the role.
3. IF the role has no permissions assigned, THEN THE RoleController SHALL return an empty `permissions` array.
