# Requirements Document — Job Management

## Introduction

The Job Management feature transforms HavenHR from a candidate-only resume platform into a full two-sided hiring marketplace. Employers create and manage job postings within their tenant, publish them to a public-facing job board, and track candidate applications through configurable pipeline stages. Candidates discover jobs through search and filtering, view detailed job descriptions, and apply using their existing resumes. This spec connects the existing `job_applications` table (currently referencing a placeholder `job_posting_id`) to real, tenant-scoped job posting records, completing the hiring loop between employers and candidates.

## Glossary

- **Job_Posting**: A tenant-scoped record representing a job opening, containing title, description, requirements, benefits, salary range, location, type, department, and status metadata.
- **Job_Board**: The public-facing page that lists all published job postings across all tenants, accessible without authentication.
- **Pipeline_Stage**: A named step in the hiring workflow for a specific job posting (e.g., Applied, Screening, Interview, Offer, Hired, Rejected). Each job has its own ordered set of stages.
- **Stage_Transition**: A record capturing when a candidate application moves from one pipeline stage to another, including who initiated the move and when.
- **Employer_Dashboard**: The tenant-scoped interface where authorized users manage job postings, view applications, and track pipeline progress.
- **Tenant_User**: An authenticated user belonging to a specific tenant (company), subject to RBAC permissions.
- **Candidate**: A platform-level user (no tenant association) who can browse jobs and submit applications.
- **Job_Posting_Service**: The backend service responsible for CRUD operations on job postings, status transitions, and slug generation.
- **Pipeline_Service**: The backend service responsible for managing pipeline stages and candidate stage transitions per job posting.
- **Public_Job_Controller**: The backend controller serving unauthenticated public job listing and detail endpoints.
- **Shareable_URL**: A unique, SEO-friendly URL for a published job posting, composed of a URL-safe slug derived from the job title and a unique identifier.
- **Open_Graph_Metadata**: HTML meta tags (og:title, og:description, og:image, og:url) embedded in public job pages to enable rich previews when shared on social media platforms.
- **Application_Count**: The number of submitted job applications associated with a specific job posting.

## Requirements

### Requirement 1: Create Job Posting

**User Story:** As a recruiter or admin, I want to create a new job posting for my company, so that I can start attracting candidates for open positions.

#### Acceptance Criteria

1. WHEN a Tenant_User with `jobs.create` permission submits a valid job posting payload, THE Job_Posting_Service SHALL create a new Job_Posting record with status `draft`, the authenticated user's `tenant_id`, and a generated UUID primary key.
2. THE Job_Posting_Service SHALL require the following fields: title (max 255 characters), description (max 10000 characters), location (max 255 characters), and employment_type (one of: full-time, part-time, contract, internship).
3. THE Job_Posting_Service SHALL accept the following optional fields: department (max 255 characters), salary_min (non-negative integer), salary_max (non-negative integer), salary_currency (ISO 4217 code, max 3 characters), requirements (max 5000 characters), benefits (max 5000 characters), remote_status (one of: remote, on-site, hybrid).
4. WHEN salary_min and salary_max are both provided, THE Job_Posting_Service SHALL validate that salary_min is less than or equal to salary_max.
5. WHEN a Job_Posting is created, THE Job_Posting_Service SHALL generate a URL-safe slug from the title and a short unique identifier, forming the Shareable_URL path segment.
6. WHEN a Job_Posting is created, THE Pipeline_Service SHALL create the default pipeline stages (Applied, Screening, Interview, Offer, Hired, Rejected) with sequential sort_order values.
7. IF a Tenant_User without `jobs.create` permission attempts to create a job posting, THEN THE Job_Posting_Service SHALL return a 403 Forbidden response.

### Requirement 2: Edit Job Posting

**User Story:** As a recruiter or admin, I want to edit an existing job posting, so that I can update details before or after publishing.

#### Acceptance Criteria

1. WHEN a Tenant_User with `jobs.update` permission submits an update to an existing Job_Posting belonging to the same tenant, THE Job_Posting_Service SHALL update the specified fields and return the updated record.
2. THE Job_Posting_Service SHALL apply the same validation rules for updated fields as defined in Requirement 1 acceptance criteria 2, 3, and 4.
3. IF a Tenant_User attempts to update a Job_Posting belonging to a different tenant, THEN THE Job_Posting_Service SHALL return a 404 Not Found response.
4. IF a Tenant_User attempts to update an archived Job_Posting, THEN THE Job_Posting_Service SHALL return a 422 Unprocessable Entity response with a message indicating archived postings are not editable.
5. WHEN a published Job_Posting's title is updated, THE Job_Posting_Service SHALL regenerate the URL-safe slug.

### Requirement 3: Job Posting Status Transitions

**User Story:** As a recruiter or admin, I want to publish, close, and archive job postings, so that I can control which positions are visible to candidates.

#### Acceptance Criteria

1. THE Job_Posting_Service SHALL enforce the following status transitions: draft to published, published to closed, closed to archived, published to draft (unpublish), and closed to published (reopen).
2. WHEN a Job_Posting transitions to `published` status, THE Job_Posting_Service SHALL set the `published_at` timestamp to the current time if it has not been set previously.
3. WHEN a Job_Posting transitions to `closed` status, THE Job_Posting_Service SHALL set the `closed_at` timestamp to the current time.
4. IF a Tenant_User attempts an invalid status transition (e.g., draft to closed, archived to published), THEN THE Job_Posting_Service SHALL return a 422 Unprocessable Entity response listing the allowed transitions from the current status.
5. WHEN a Tenant_User with `jobs.update` permission requests a status transition, THE Job_Posting_Service SHALL apply the transition and return the updated Job_Posting.
6. IF a Tenant_User without `jobs.update` permission attempts a status transition, THEN THE Job_Posting_Service SHALL return a 403 Forbidden response.

### Requirement 4: Delete Job Posting

**User Story:** As an admin, I want to delete a job posting, so that I can remove postings created in error.

#### Acceptance Criteria

1. WHEN a Tenant_User with `jobs.delete` permission requests deletion of a Job_Posting in `draft` status belonging to the same tenant, THE Job_Posting_Service SHALL soft-delete the record.
2. IF a Tenant_User attempts to delete a Job_Posting that is not in `draft` status, THEN THE Job_Posting_Service SHALL return a 422 Unprocessable Entity response indicating only draft postings may be deleted.
3. IF a Tenant_User without `jobs.delete` permission attempts to delete a Job_Posting, THEN THE Job_Posting_Service SHALL return a 403 Forbidden response.

### Requirement 5: Public Job Board

**User Story:** As a candidate or visitor, I want to browse all published job postings across all companies, so that I can discover open positions.

#### Acceptance Criteria

1. THE Public_Job_Controller SHALL return a paginated list of all Job_Postings with status `published` across all tenants, ordered by `published_at` descending.
2. THE Public_Job_Controller SHALL include the following fields in each list item: id, title, company name, department, location, employment_type, remote_status, salary_min, salary_max, salary_currency, published_at, slug, and Application_Count.
3. THE Public_Job_Controller SHALL accept pagination parameters: page (default 1) and per_page (default 20, max 100).
4. THE Public_Job_Controller SHALL NOT require authentication for the job listing endpoint.
5. THE Public_Job_Controller SHALL NOT expose internal fields such as tenant_id, created_by, or closed_at in public responses.

### Requirement 6: Public Job Detail Page

**User Story:** As a candidate or visitor, I want to view the full details of a job posting, so that I can decide whether to apply.

#### Acceptance Criteria

1. WHEN a request is made for a published Job_Posting by its slug, THE Public_Job_Controller SHALL return the full job detail including: title, company name, company logo URL, department, location, employment_type, remote_status, salary_min, salary_max, salary_currency, description, requirements, benefits, published_at, and Application_Count.
2. IF a request is made for a Job_Posting that is not in `published` status, THEN THE Public_Job_Controller SHALL return a 404 Not Found response.
3. THE Public_Job_Controller SHALL NOT require authentication for the job detail endpoint.
4. THE Public_Job_Controller SHALL include Open_Graph_Metadata (og:title, og:description, og:url, og:type) in the response or page rendering for social media sharing.

### Requirement 7: Job Search and Filtering

**User Story:** As a candidate or visitor, I want to search and filter job postings, so that I can find positions matching my interests and qualifications.

#### Acceptance Criteria

1. WHEN a search query parameter is provided, THE Public_Job_Controller SHALL filter published Job_Postings where the title, department, or location contains the search term (case-insensitive partial match).
2. WHEN an employment_type filter parameter is provided (one or more of: full-time, part-time, contract, internship), THE Public_Job_Controller SHALL return only Job_Postings matching the specified types.
3. WHEN a remote_status filter parameter is provided (one or more of: remote, on-site, hybrid), THE Public_Job_Controller SHALL return only Job_Postings matching the specified remote statuses.
4. WHEN a sort parameter is provided, THE Public_Job_Controller SHALL sort results by the specified field. THE Public_Job_Controller SHALL support sorting by published_at (default, descending) and title (ascending).
5. THE Public_Job_Controller SHALL support combining search, filter, and sort parameters in a single request.

### Requirement 8: Candidate Application Integration

**User Story:** As a candidate, I want to apply to a specific job posting using my resume, so that employers can review my application.

#### Acceptance Criteria

1. WHEN a Candidate submits an application with a valid job_posting_id and resume_id, THE Job_Posting_Service SHALL verify the Job_Posting exists and has `published` status before creating the application.
2. WHEN a valid application is submitted, THE Job_Posting_Service SHALL create a job_applications record linking the Candidate, Job_Posting, and a frozen resume snapshot, and assign the application to the first Pipeline_Stage (Applied) of that Job_Posting.
3. IF a Candidate attempts to apply to a Job_Posting that is not in `published` status, THEN THE Job_Posting_Service SHALL return a 422 Unprocessable Entity response.
4. IF a Candidate has already applied to the same Job_Posting, THEN THE Job_Posting_Service SHALL return a 409 Conflict response.
5. WHEN a Candidate lists their applications, THE Job_Posting_Service SHALL include the job posting title, company name, current pipeline stage name, and application status for each application.

### Requirement 9: Pipeline Stage Management

**User Story:** As a recruiter, I want to configure pipeline stages for each job posting, so that I can customize the hiring workflow to match my process.

#### Acceptance Criteria

1. WHEN a Job_Posting is created, THE Pipeline_Service SHALL create the default stages: Applied (sort_order 0), Screening (sort_order 1), Interview (sort_order 2), Offer (sort_order 3), Hired (sort_order 4), Rejected (sort_order 5).
2. WHEN a Tenant_User with `pipeline.manage` permission adds a new Pipeline_Stage to a Job_Posting, THE Pipeline_Service SHALL create the stage with the specified name and sort_order.
3. WHEN a Tenant_User with `pipeline.manage` permission reorders Pipeline_Stages, THE Pipeline_Service SHALL update the sort_order values for all affected stages.
4. WHEN a Tenant_User with `pipeline.manage` permission removes a Pipeline_Stage that has no associated applications, THE Pipeline_Service SHALL delete the stage.
5. IF a Tenant_User attempts to remove a Pipeline_Stage that has associated applications, THEN THE Pipeline_Service SHALL return a 422 Unprocessable Entity response indicating the stage has active applications.
6. IF a Tenant_User without `pipeline.manage` permission attempts to modify Pipeline_Stages, THEN THE Pipeline_Service SHALL return a 403 Forbidden response.

### Requirement 10: Candidate Stage Transitions

**User Story:** As a recruiter, I want to move candidates through pipeline stages, so that I can track their progress in the hiring process.

#### Acceptance Criteria

1. WHEN a Tenant_User with `applications.manage` permission moves an application to a different Pipeline_Stage within the same Job_Posting, THE Pipeline_Service SHALL update the application's current stage and create a Stage_Transition record with the previous stage, new stage, Tenant_User who initiated the move, and timestamp.
2. IF a Tenant_User attempts to move an application to a Pipeline_Stage belonging to a different Job_Posting, THEN THE Pipeline_Service SHALL return a 422 Unprocessable Entity response.
3. WHEN a stage transition history is requested for an application, THE Pipeline_Service SHALL return all Stage_Transition records ordered by timestamp ascending.
4. IF a Tenant_User without `applications.manage` permission attempts to move an application, THEN THE Pipeline_Service SHALL return a 403 Forbidden response.

### Requirement 11: Employer Job Dashboard

**User Story:** As a recruiter or hiring manager, I want to see all job postings for my company with key metrics, so that I can manage the hiring pipeline efficiently.

#### Acceptance Criteria

1. WHEN a Tenant_User with `jobs.list` permission requests the job dashboard, THE Job_Posting_Service SHALL return a paginated list of all Job_Postings belonging to the tenant, including: id, title, status, department, location, employment_type, Application_Count, published_at, and created_at.
2. WHEN a status filter parameter is provided (one or more of: draft, published, closed, archived), THE Job_Posting_Service SHALL return only Job_Postings matching the specified statuses.
3. THE Job_Posting_Service SHALL support sorting by created_at (default, descending), title, status, and Application_Count.
4. THE Job_Posting_Service SHALL accept pagination parameters: page (default 1) and per_page (default 20, max 100).
5. IF a Tenant_User without `jobs.list` permission attempts to access the job dashboard, THEN THE Job_Posting_Service SHALL return a 403 Forbidden response.

### Requirement 12: View Job Posting Detail (Employer)

**User Story:** As a recruiter or hiring manager, I want to view the full details of a job posting including applications, so that I can review and manage the position.

#### Acceptance Criteria

1. WHEN a Tenant_User with `jobs.view` permission requests a specific Job_Posting belonging to the same tenant, THE Job_Posting_Service SHALL return the full job detail including all fields, Pipeline_Stages with application counts per stage, and total Application_Count.
2. WHEN a Tenant_User with `applications.view` permission requests applications for a specific Job_Posting, THE Job_Posting_Service SHALL return a paginated list of applications including: candidate name, candidate email, current Pipeline_Stage name, application status, and applied_at.
3. IF a Tenant_User attempts to view a Job_Posting belonging to a different tenant, THEN THE Job_Posting_Service SHALL return a 404 Not Found response.

### Requirement 13: Tenant Scoping and Data Isolation

**User Story:** As a platform operator, I want all job posting data to be strictly isolated per tenant, so that companies cannot access each other's data.

#### Acceptance Criteria

1. THE Job_Posting model SHALL include a `tenant_id` column with a NOT NULL constraint and a foreign key reference to the companies table.
2. THE Job_Posting model SHALL use the existing `BelongsToTenant` trait and `TenantScope` global scope to automatically filter all queries by the authenticated tenant's ID.
3. THE Pipeline_Stage model SHALL be scoped to a Job_Posting, which is itself tenant-scoped, ensuring transitive tenant isolation.
4. WHEN the `TenantResolver` middleware resolves the tenant context, THE Job_Posting_Service SHALL use that context for all database operations.

### Requirement 14: Audit Logging for Job Actions

**User Story:** As an admin, I want all job posting actions to be recorded in the audit log, so that I can track who made changes and when.

#### Acceptance Criteria

1. WHEN a Job_Posting is created, updated, status-transitioned, or deleted, THE Job_Posting_Service SHALL dispatch a domain event containing the tenant_id, user_id, action type, resource_type (`job_posting`), resource_id, previous state, and new state.
2. THE Audit_Logger SHALL record all Job_Posting domain events in the audit_logs table following the existing event payload schema.
3. WHEN a Pipeline_Stage transition occurs for an application, THE Pipeline_Service SHALL dispatch a domain event containing the tenant_id, user_id, application_id, previous stage, and new stage.

### Requirement 15: SEO-Friendly Public Job URLs

**User Story:** As a platform operator, I want job posting URLs to be SEO-friendly, so that job listings rank well in search engines.

#### Acceptance Criteria

1. THE Job_Posting_Service SHALL generate a URL slug from the job title by converting to lowercase, replacing spaces and special characters with hyphens, removing consecutive hyphens, and appending a short unique identifier.
2. WHEN a public job detail page is requested by slug, THE Public_Job_Controller SHALL resolve the Job_Posting by matching the slug.
3. THE public job detail page SHALL include Open_Graph_Metadata tags: og:title (job title — company name), og:description (first 200 characters of description), og:url (canonical URL), and og:type (website).

### Requirement 16: Company Branding on Public Job Pages

**User Story:** As an employer, I want my company branding to appear on public job pages, so that candidates recognize my company.

#### Acceptance Criteria

1. THE Public_Job_Controller SHALL include the company name in all public job listing and detail responses.
2. WHERE a company has a logo_url configured in its settings, THE Public_Job_Controller SHALL include the logo_url in public job detail responses.
3. THE public job detail page SHALL display the company name prominently alongside the job title.
