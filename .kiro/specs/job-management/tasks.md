# Implementation Plan: Job Management

## Overview

This plan implements the Job Management feature for HavenHR, adding tenant-scoped job postings, a public job board, configurable hiring pipelines, and candidate application integration. Tasks are ordered by dependency: database migrations first, then models, services, controllers, routes, and finally frontend pages. The implementation builds on existing patterns (BelongsToTenant, DomainEvent, RBAC middleware, Pest testing).

## Tasks

- [x] 1. Database migrations and schema setup
  - [x] 1.1 Create the `job_postings` table migration
    - Create migration file `create_job_postings_table` with all columns: `id` (UUID PK), `tenant_id` (FK to companies), `created_by` (FK to users), `title`, `slug` (unique), `description`, `location`, `employment_type`, `department`, `salary_min`, `salary_max`, `salary_currency`, `requirements`, `benefits`, `remote_status`, `status` (default draft), `published_at`, `closed_at`, `deleted_at` (soft delete), `created_at`, `updated_at`
    - Add indexes: `tenant_id`, `slug` (unique), composite `(tenant_id, status)`, composite `(status, published_at)`, `deleted_at`
    - _Requirements: 1.1, 1.2, 1.3, 13.1_

  - [x] 1.2 Create the `pipeline_stages` table migration
    - Create migration file `create_pipeline_stages_table` with columns: `id` (UUID PK), `job_posting_id` (FK to job_postings, cascade on delete), `name`, `sort_order`, `created_at`, `updated_at`
    - Add composite index on `(job_posting_id, sort_order)`
    - _Requirements: 9.1, 13.3_

  - [x] 1.3 Create the `stage_transitions` table migration
    - Create migration file `create_stage_transitions_table` with columns: `id` (UUID PK), `job_application_id` (FK to job_applications, cascade on delete), `from_stage_id` (FK to pipeline_stages), `to_stage_id` (FK to pipeline_stages), `moved_by` (FK to users), `moved_at`
    - Add composite index on `(job_application_id, moved_at)`
    - _Requirements: 10.1, 10.3_

  - [x] 1.4 Create migration to add `pipeline_stage_id` to `job_applications` table
    - Add nullable `pipeline_stage_id` UUID column with FK to `pipeline_stages.id`
    - Add FK constraint from `job_posting_id` to `job_postings.id` (currently missing)
    - Add index on `pipeline_stage_id`
    - _Requirements: 8.2, 10.1_

  - [x] 1.5 Add new RBAC permissions for job management
    - Seed permissions: `jobs.create`, `jobs.list`, `jobs.view`, `jobs.update`, `jobs.delete`, `pipeline.manage`, `applications.manage`
    - Assign appropriate permissions to existing roles (admin gets all, recruiter/HR gets job and pipeline permissions)
    - _Requirements: 1.7, 3.6, 4.3, 9.6, 10.4, 11.5_

- [x] 2. Eloquent models
  - [x] 2.1 Create the `JobPosting` model
    - Create `app/Models/JobPosting.php` using `HasUuid`, `HasFactory`, `BelongsToTenant`, and `SoftDeletes` traits
    - Define `$fillable` array with all writable fields, `$casts` for timestamps and JSON
    - Add relationships: `belongsTo(Company::class, 'tenant_id')`, `belongsTo(User::class, 'created_by')`, `hasMany(PipelineStage::class)`, `hasMany(JobApplication::class)`
    - Add `company` relationship alias for public responses (company name, logo_url from settings)
    - _Requirements: 1.1, 13.1, 13.2, 16.1_

  - [x] 2.2 Create the `PipelineStage` model
    - Create `app/Models/PipelineStage.php` using `HasUuid` and `HasFactory` traits
    - Define relationships: `belongsTo(JobPosting::class)`, `hasMany(JobApplication::class, 'pipeline_stage_id')`
    - Scope queries through the parent `JobPosting` for transitive tenant isolation
    - _Requirements: 9.1, 13.3_

  - [x] 2.3 Create the `StageTransition` model
    - Create `app/Models/StageTransition.php` using `HasUuid` trait
    - Define relationships: `belongsTo(JobApplication::class)`, `belongsTo(PipelineStage::class, 'from_stage_id')`, `belongsTo(PipelineStage::class, 'to_stage_id')`, `belongsTo(User::class, 'moved_by')`
    - Set `$timestamps = false` (uses `moved_at` instead)
    - _Requirements: 10.1, 10.3_

  - [x] 2.4 Update the `JobApplication` model to add pipeline stage relationship
    - Add `pipeline_stage_id` to `$fillable`
    - Add `belongsTo(PipelineStage::class, 'pipeline_stage_id')` relationship
    - Add `belongsTo(JobPosting::class)` relationship (currently missing)
    - _Requirements: 8.2, 10.1_

  - [x] 2.5 Add `jobPostings` relationship to the `Company` model
    - Add `hasMany(JobPosting::class, 'tenant_id')` relationship
    - _Requirements: 13.1, 16.1_

- [x] 3. Domain events for audit logging
  - [x] 3.1 Create job posting domain events
    - Create `app/Events/JobPostingCreated.php` extending `DomainEvent` with `event_type = 'job_posting.created'`
    - Create `app/Events/JobPostingUpdated.php` extending `DomainEvent` with `event_type = 'job_posting.updated'`
    - Create `app/Events/JobPostingStatusChanged.php` extending `DomainEvent` with `event_type = 'job_posting.status_changed'`
    - Create `app/Events/JobPostingDeleted.php` extending `DomainEvent` with `event_type = 'job_posting.deleted'`
    - Each event carries `resource_type`, `resource_id`, `previous_state`, and `new_state` in the data payload
    - _Requirements: 14.1, 14.2_

  - [x] 3.2 Create application stage changed domain event
    - Create `app/Events/ApplicationStageChanged.php` extending `DomainEvent` with `event_type = 'application.stage_changed'`
    - Payload includes `application_id`, `from_stage`, `to_stage`
    - _Requirements: 14.3_

  - [x] 3.3 Register new events in `EventServiceProvider`
    - Map all new domain events to the existing `AuditLogListener`
    - _Requirements: 14.2_

- [x] 4. Checkpoint — Run migrations and verify models
  - Ensure all migrations run cleanly, models instantiate correctly, and relationships load. Ask the user if questions arise.

- [x] 5. Job Posting Service (core business logic)
  - [x] 5.1 Create the `JobPostingService` with CRUD and slug generation
    - Create `app/Services/JobPostingService.php`
    - Implement `create(array $data, string $userId): JobPosting` — validate, generate slug (lowercase title → hyphens → append 8-char UUID), create record with status `draft`, call `PipelineService::createDefaultStages()`, dispatch `JobPostingCreated` event
    - Implement `update(string $id, array $data, string $userId): JobPosting` — load tenant-scoped posting, reject if archived (422), apply updates, regenerate slug if title changed on published posting, dispatch `JobPostingUpdated` event
    - Implement `delete(string $id, string $userId): void` — load tenant-scoped posting, reject if not draft (422), soft-delete, dispatch `JobPostingDeleted` event
    - Implement `getDetail(string $id): JobPosting` — load with pipeline stages and application counts
    - Implement `listForTenant(array $filters, array $pagination, array $sort): LengthAwarePaginator` — filter by status, sort by created_at/title/status/application_count, paginate
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 2.1, 2.2, 2.3, 2.4, 2.5, 4.1, 4.2, 11.1, 11.2, 11.3, 11.4, 12.1, 14.1, 15.1_

  - [ ]* 5.2 Write property tests for job posting creation and validation
    - **Property 1: Valid job posting creation produces a draft record with correct tenant**
    - **Property 2: Invalid job posting payloads are rejected**
    - **Validates: Requirements 1.1, 1.2, 1.3, 1.4, 2.2**

  - [ ]* 5.3 Write property test for slug generation
    - **Property 3: Slug generation produces URL-safe, unique slugs**
    - **Validates: Requirements 1.5, 2.5, 15.1**

  - [x] 5.4 Implement status transition logic in `JobPostingService`
    - Implement `transitionStatus(string $id, string $newStatus, string $userId): JobPosting`
    - Define allowed transitions map: `{draft→published, published→draft, published→closed, closed→published, closed→archived}`
    - Set `published_at` on first publish (preserve if already set), set `closed_at` on close
    - Return 422 with allowed transitions for invalid transitions
    - Dispatch `JobPostingStatusChanged` event with previous and new status
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

  - [ ]* 5.5 Write property tests for status transitions
    - **Property 4: Status transition state machine enforcement**
    - **Property 5: Publishing sets published_at only on first publish**
    - **Property 6: Only draft job postings can be deleted**
    - **Validates: Requirements 3.1, 3.2, 3.4, 4.1, 4.2**

- [x] 6. Pipeline Service
  - [x] 6.1 Create the `PipelineService`
    - Create `app/Services/PipelineService.php`
    - Implement `createDefaultStages(string $jobPostingId): void` — create Applied (0), Screening (1), Interview (2), Offer (3), Hired (4), Rejected (5)
    - Implement `addStage(string $jobPostingId, string $name, int $sortOrder): PipelineStage`
    - Implement `reorderStages(string $jobPostingId, array $stageOrder): void` — update sort_order values
    - Implement `removeStage(string $stageId): void` — check for associated applications (422 if any), delete, reorder remaining
    - _Requirements: 1.6, 9.1, 9.2, 9.3, 9.4, 9.5_

  - [x] 6.2 Implement stage transition logic in `PipelineService`
    - Implement `moveApplication(string $applicationId, string $targetStageId, string $userId): StageTransition` — verify target stage belongs to same job posting (422 if not), update `pipeline_stage_id`, create `stage_transitions` record, dispatch `ApplicationStageChanged` event
    - Implement `getTransitionHistory(string $applicationId): Collection` — return stage transitions ordered by `moved_at` ascending
    - _Requirements: 10.1, 10.2, 10.3, 14.3_

  - [ ]* 6.3 Write property tests for pipeline and stage transitions
    - **Property 12: Stage transitions create audit trail and update current stage**
    - **Validates: Requirements 10.1, 10.2, 10.3**

- [x] 7. Update Job Application Service for pipeline integration
  - [x] 7.1 Update `JobApplicationService::apply()` to integrate with job postings and pipeline
    - Add job posting existence and `published` status verification (return 422 if not published)
    - After creating the application, look up the first pipeline stage (sort_order 0) for the job posting and set `pipeline_stage_id`
    - Update duplicate check to return 409 with proper error code
    - _Requirements: 8.1, 8.2, 8.3, 8.4_

  - [x] 7.2 Update `JobApplicationService::listCandidateApplications()` to include job and pipeline context
    - Eager-load `jobPosting.company` and `pipelineStage` relationships
    - Return job posting title, company name, current pipeline stage name, and application status
    - _Requirements: 8.5_

  - [ ]* 7.3 Write property tests for application creation and listing
    - **Property 10: Application creation requires published status and assigns to Applied stage**
    - **Property 11: Candidate application list includes job and pipeline context**
    - **Validates: Requirements 8.1, 8.2, 8.3, 8.5**

- [x] 8. Checkpoint — Run all backend tests
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 9. Form requests (validation)
  - [x] 9.1 Create `CreateJobPostingRequest` form request
    - Validate required fields: title (max:255), description (max:10000), location (max:255), employment_type (in:full-time,part-time,contract,internship)
    - Validate optional fields: department (max:255), salary_min (integer, min:0), salary_max (integer, min:0, gte:salary_min when both present), salary_currency (max:3), requirements (max:5000), benefits (max:5000), remote_status (in:remote,on-site,hybrid)
    - _Requirements: 1.2, 1.3, 1.4_

  - [x] 9.2 Create `UpdateJobPostingRequest` form request
    - Same validation rules as create but all fields optional (partial update)
    - Salary cross-validation when both salary_min and salary_max are present
    - _Requirements: 2.1, 2.2_

  - [x] 9.3 Create `TransitionJobPostingStatusRequest` form request
    - Validate `status` field: required, in:draft,published,closed,archived
    - _Requirements: 3.1, 3.5_

  - [x] 9.4 Create `AddPipelineStageRequest` form request
    - Validate `name` (required, max:255) and `sort_order` (required, integer, min:0)
    - _Requirements: 9.2_

  - [x] 9.5 Create `MoveApplicationRequest` form request
    - Validate `stage_id` (required, uuid)
    - _Requirements: 10.1_

  - [x] 9.6 Create `ReorderPipelineStagesRequest` form request
    - Validate `stages` array of `{id, sort_order}` objects
    - _Requirements: 9.3_

- [ ] 10. Controllers and routes
  - [x] 10.1 Create `JobPostingController` for tenant-scoped CRUD and status transitions
    - Create `app/Http/Controllers/JobPostingController.php`
    - Implement `index()` — list tenant job postings with filters, sorting, pagination via `JobPostingService::listForTenant()`
    - Implement `store()` — create job posting via `JobPostingService::create()`, return 201
    - Implement `show()` — get job detail with pipeline stages via `JobPostingService::getDetail()`, return 200
    - Implement `update()` — update job posting via `JobPostingService::update()`, return 200
    - Implement `destroy()` — delete draft job posting via `JobPostingService::delete()`, return 204
    - Implement `transitionStatus()` — transition status via `JobPostingService::transitionStatus()`, return 200
    - _Requirements: 1.1, 2.1, 3.5, 4.1, 11.1, 12.1_

  - [x] 10.2 Create `PipelineController` for stage management and application transitions
    - Create `app/Http/Controllers/PipelineController.php`
    - Implement `listStages()` — list pipeline stages for a job posting
    - Implement `addStage()` — add a new pipeline stage
    - Implement `reorderStages()` — reorder pipeline stages
    - Implement `removeStage()` — remove a pipeline stage (422 if has applications)
    - Implement `moveApplication()` — move application to a different stage
    - Implement `transitionHistory()` — get stage transition history for an application
    - _Requirements: 9.2, 9.3, 9.4, 9.5, 10.1, 10.2, 10.3_

  - [x] 10.3 Create `PublicJobController` for unauthenticated job board endpoints
    - Create `app/Http/Controllers/PublicJobController.php`
    - Implement `index()` — list published jobs across all tenants with search (q param, case-insensitive on title/department/location), filters (employment_type, remote_status), sorting (published_at desc default, title asc), pagination
    - Implement `show()` — get job detail by slug, return 404 if not published, include OG metadata, company name, logo_url, application count
    - Exclude internal fields: tenant_id, created_by, closed_at, deleted_at
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 6.1, 6.2, 6.3, 6.4, 7.1, 7.2, 7.3, 7.4, 7.5, 15.2, 15.3, 16.1, 16.2_

  - [x] 10.4 Update `EmployerApplicationController` to support pipeline-aware application listing
    - Update `listForJob()` to include current pipeline stage name in response
    - Update `show()` to include pipeline stage and transition history
    - _Requirements: 12.2_

  - [x] 10.5 Register all new routes in `routes/api.php`
    - Add tenant-scoped job posting routes under `havenhr.auth`, `tenant.resolve` middleware with RBAC
    - Add pipeline stage routes nested under jobs
    - Add application move and transition history routes
    - Add public job board routes (no auth): `GET /api/v1/public/jobs`, `GET /api/v1/public/jobs/{slug}`
    - Update candidate application controller to handle 422 for non-published jobs
    - _Requirements: 5.4, 6.3, 1.7, 3.6, 4.3, 9.6, 10.4, 11.5_

  - [ ]* 10.6 Write property tests for public job board and tenant isolation
    - **Property 7: Public job board returns only published postings with correct fields**
    - **Property 8: Public job detail returns correct data with OG metadata, 404 for non-published**
    - **Property 9: Search and filter results satisfy all applied constraints**
    - **Property 13: Tenant scoping isolates job postings per tenant**
    - **Validates: Requirements 5.1, 5.2, 5.5, 6.1, 6.2, 6.4, 7.1, 7.2, 7.3, 7.4, 7.5, 13.2, 2.3, 12.3**

  - [ ]* 10.7 Write property tests for audit events, branding, and employer dashboard
    - **Property 14: Job posting actions dispatch domain events with correct payload**
    - **Property 15: Company branding appears in public responses**
    - **Property 16: Employer dashboard returns tenant-scoped postings with filters**
    - **Property 17: Employer job detail includes pipeline stages with per-stage counts**
    - **Validates: Requirements 14.1, 14.3, 16.1, 16.2, 11.1, 11.2, 11.3, 12.1**

- [x] 11. Checkpoint — Run full backend test suite
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 12. Frontend API client and types
  - [x] 12.1 Create job management TypeScript types and API client functions
    - Create `frontend/src/lib/jobApi.ts` with types: `JobPosting`, `JobPostingListItem`, `PublicJobListItem`, `PublicJobDetail`, `PipelineStage`, `StageTransition`, `JobApplication`
    - Implement API functions: `fetchPublicJobs()`, `fetchPublicJobBySlug()`, `fetchTenantJobs()`, `createJobPosting()`, `updateJobPosting()`, `deleteJobPosting()`, `transitionJobStatus()`, `fetchJobDetail()`, `fetchPipelineStages()`, `addPipelineStage()`, `reorderPipelineStages()`, `removePipelineStage()`, `moveApplication()`, `fetchTransitionHistory()`, `fetchJobApplications()`
    - Follow existing patterns in `api.ts` and `candidateApi.ts`
    - _Requirements: 1.1, 2.1, 3.1, 5.1, 6.1, 7.1, 8.1, 9.2, 10.1, 11.1, 12.1_

- [ ] 13. Public Job Board frontend pages
  - [x] 13.1 Create the public Job Board listing page at `/jobs`
    - Create `frontend/src/app/jobs/page.tsx`
    - Implement search bar with real-time query parameter updates
    - Implement filter controls: employment type checkboxes, remote status checkboxes
    - Implement sort dropdown: newest first (default), title A-Z
    - Implement paginated card grid showing job title, company name, location, type, salary range, posted date, application count
    - Mobile-responsive layout with collapsible filters
    - No authentication required
    - _Requirements: 5.1, 5.2, 5.3, 7.1, 7.2, 7.3, 7.4, 7.5, 16.1_

  - [x] 13.2 Create the public Job Detail page at `/jobs/[slug]`
    - Create `frontend/src/app/jobs/[slug]/page.tsx`
    - Display full job description with company branding (name, logo)
    - Show salary range, location, employment type, remote status, application count
    - Add "Apply Now" button that links to candidate application flow (prompt login if unauthenticated)
    - Include Open Graph meta tags via Next.js `generateMetadata()` for social sharing
    - No authentication required
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 15.2, 15.3, 16.1, 16.2, 16.3_

- [ ] 14. Employer Dashboard frontend pages
  - [x] 14.1 Create the Employer Job Dashboard page at `/dashboard/jobs`
    - Create `frontend/src/app/dashboard/jobs/page.tsx`
    - Implement table view with columns: title, status (badge), department, location, type, application count, published date, actions
    - Implement status filter tabs: All, Draft, Published, Closed, Archived
    - Implement sorting by date, title, application count
    - Add quick action buttons: Publish, Close, Edit, Delete (draft only)
    - Paginated with page/per_page controls
    - Protected by `jobs.list` permission
    - _Requirements: 11.1, 11.2, 11.3, 11.4_

  - [x] 14.2 Create the Job Posting form page (create/edit) at `/dashboard/jobs/new` and `/dashboard/jobs/[id]/edit`
    - Create `frontend/src/app/dashboard/jobs/new/page.tsx` and `frontend/src/app/dashboard/jobs/[id]/edit/page.tsx`
    - Implement form with all fields: title, description (rich text area), location, employment_type (select), department, salary_min, salary_max, salary_currency, requirements, benefits, remote_status (select)
    - Client-side validation matching backend rules (required fields, max lengths, salary_min ≤ salary_max)
    - Submit to create or update API endpoint
    - Protected by `jobs.create` / `jobs.update` permission
    - _Requirements: 1.2, 1.3, 1.4, 2.1, 2.2_

  - [x] 14.3 Create the Employer Job Detail + Pipeline page at `/dashboard/jobs/[id]`
    - Create `frontend/src/app/dashboard/jobs/[id]/page.tsx`
    - Display job details summary at top (all fields, status badge, dates)
    - Implement Kanban-style pipeline board showing candidates in each stage with per-stage counts
    - Implement drag-and-drop to move candidates between stages (triggers move API call)
    - Click candidate card to view application detail and resume snapshot
    - Show status transition buttons based on current status (Publish, Close, Reopen, Archive, Unpublish)
    - Protected by `jobs.view` permission
    - _Requirements: 12.1, 12.2, 10.1, 9.1, 3.1_

- [ ] 15. Update Candidate Application flow
  - [x] 15.1 Update candidate application pages to show job posting context
    - Update `frontend/src/app/candidate/applications/page.tsx` to display job posting title, company name, current pipeline stage name, and application status for each application
    - Add link from application list to public job detail page
    - _Requirements: 8.5_

- [x] 16. Final checkpoint — Full integration verification
  - Ensure all backend and frontend tests pass, all routes are registered, migrations run cleanly, and the feature is fully wired together. Ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation after each major phase
- Property tests validate universal correctness properties from the design document
- Unit tests validate specific examples and edge cases
- The implementation uses existing patterns: `BelongsToTenant` trait, `DomainEvent` base class, `HasUuid` trait, RBAC middleware, Pest testing framework
- The `job_applications` table already exists with a `job_posting_id` column — the migration adds the FK constraint and `pipeline_stage_id` column
