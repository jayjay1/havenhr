# Implementation Plan: AI Resume Builder

## Overview

This plan implements the AI Resume Builder feature for HavenHR, transforming it into a two-sided platform with candidate-facing resume building, AI content generation, PDF export, public sharing, and job application capabilities. Tasks are ordered by dependency: database schema first, then backend services and middleware, then frontend components, and finally integration wiring.

## Tasks

- [x] 1. Database migrations and models for candidate data schema
  - [x] 1.1 Create candidates table migration and Candidate model
    - Create migration `create_candidates_table` with columns: id (UUID PK), name, email (unique), password_hash, phone (nullable), location (nullable), linkedin_url (nullable), portfolio_url (nullable), is_active (default true), email_verified_at (nullable), last_login_at (nullable), timestamps
    - Create `App\Models\Candidate` model with UUID trait, fillable fields, hidden password_hash, and casts
    - _Requirements: 19.1, 19.9_

  - [x] 1.2 Create candidate_refresh_tokens table migration and model
    - Create migration with columns: id (UUID PK), candidate_id (FK to candidates), token_hash (indexed), expires_at, is_revoked (default false), created_at
    - Add composite index on (candidate_id, is_revoked)
    - Create `App\Models\CandidateRefreshToken` model
    - _Requirements: 19.9, 2.1_

  - [x] 1.3 Create candidate_work_histories table migration and model
    - Create migration with columns: id (UUID PK), candidate_id (FK, indexed), job_title, company_name, start_date (date), end_date (date, nullable), description (text), sort_order (integer), timestamps
    - Create `App\Models\CandidateWorkHistory` model with candidate relationship
    - _Requirements: 19.2, 19.9_

  - [x] 1.4 Create candidate_educations table migration and model
    - Create migration with columns: id (UUID PK), candidate_id (FK, indexed), institution_name, degree, field_of_study, start_date (date), end_date (date, nullable), sort_order (integer), timestamps
    - Create `App\Models\CandidateEducation` model with candidate relationship
    - _Requirements: 19.3, 19.9_

  - [x] 1.5 Create candidate_skills table migration and model
    - Create migration with columns: id (UUID PK), candidate_id (FK, indexed), name, category (enum: technical, soft), sort_order (integer), created_at
    - Add unique composite index on (candidate_id, name)
    - Create `App\Models\CandidateSkill` model with candidate relationship
    - _Requirements: 19.4, 19.9_

  - [x] 1.6 Create resumes table migration and Resume model
    - Create migration with columns: id (UUID PK), candidate_id (FK, indexed), title, template_slug, content (JSON), is_complete (default false), public_link_token (nullable, unique), public_link_active (default false), show_contact_on_public (default false), timestamps
    - Create `App\Models\Resume` model with candidate relationship, JSON cast for content
    - _Requirements: 19.5, 19.9_

  - [x] 1.7 Create resume_versions table migration and ResumeVersion model
    - Create migration with columns: id (UUID PK), resume_id (FK, indexed), content (JSON), version_number (integer), change_summary (nullable), created_at
    - Add unique composite index on (resume_id, version_number)
    - Create `App\Models\ResumeVersion` model with resume relationship
    - _Requirements: 19.6, 19.9_

  - [x] 1.8 Create ai_jobs table migration and AIJob model
    - Create migration with columns: id (UUID PK), candidate_id (FK), job_type (enum: summary, bullets, skills, ats_optimize, improve), input_data (JSON), result_data (JSON, nullable), status (enum: pending, processing, completed, failed), error_message (nullable), tokens_used (integer, nullable), processing_duration_ms (integer, nullable), timestamps
    - Add composite indexes on (candidate_id, created_at) and (candidate_id, status)
    - Create `App\Models\AIJob` model with candidate relationship
    - _Requirements: 19.7, 19.9_

  - [x] 1.9 Create job_applications table migration and JobApplication model
    - Create migration with columns: id (UUID PK), candidate_id (FK), job_posting_id (UUID FK), resume_id (FK), resume_snapshot (JSON), status (enum: submitted, reviewed, shortlisted, rejected, default submitted), applied_at, updated_at
    - Add unique composite constraint on (candidate_id, job_posting_id) and index on job_posting_id
    - Create `App\Models\JobApplication` model with candidate and resume relationships
    - _Requirements: 19.8, 19.9_

  - [ ]* 1.10 Write property tests for database schema integrity
    - **Property 2: Duplicate candidate email is rejected** — verify unique constraint on candidates.email
    - **Property 27: Duplicate application rejected** — verify unique composite constraint on (candidate_id, job_posting_id)
    - **Validates: Requirements 1.2, 14.3, 19.1–19.9**

- [x] 2. Candidate authentication backend
  - [x] 2.1 Implement CandidateAuthService for registration, login, refresh, and logout
    - Create `App\Services\CandidateAuthService` with register(), login(), refresh(), logout() methods
    - Registration: validate input, check email uniqueness in candidates table, bcrypt hash (cost 12), create candidate record with UUID, generate JWT with `{sub: candidate_id, role: "candidate"}` (no tenant_id), generate opaque refresh token, store hash in candidate_refresh_tokens, dispatch candidate.registered event
    - Login: timing-safe lookup, bcrypt verify, generate JWT (15 min) and refresh token (7 day), dispatch candidate.login event
    - Refresh: token rotation with replay detection (revoke all tokens on reuse), same pattern as existing AuthService
    - Logout: blocklist JTI in Redis, revoke refresh token
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 2.1, 2.2, 2.3, 2.4_

  - [x] 2.2 Create CandidateAuth middleware
    - Create `App\Http\Middleware\CandidateAuth` that extracts JWT from Authorization header, verifies signature and expiration, checks JTI against Redis blocklist, verifies `role` claim equals `"candidate"`, resolves Candidate model from `sub` claim, sets candidate on request via `$request->setUserResolver()`
    - Register middleware alias `candidate.auth` in bootstrap/app.php
    - _Requirements: 2.1_

  - [x] 2.3 Create CandidateAuthController and request validation classes
    - Create `App\Http\Controllers\CandidateAuthController` with register(), login(), refresh(), logout(), me() methods
    - Create form request classes: `CandidateRegisterRequest` (name required max:255, email required RFC 5322 unique:candidates, password with StrongPassword rule), `CandidateLoginRequest`
    - _Requirements: 1.1, 1.4, 1.6, 2.1, 2.2_

  - [x] 2.4 Register candidate auth routes
    - Add candidate auth routes to `routes/api.php` under `v1/candidate/auth` prefix: POST register, POST login, POST refresh, POST logout, GET me
    - Apply rate limiting (5/min/IP) to register and login endpoints
    - Apply `candidate.auth` middleware to logout and me endpoints
    - _Requirements: 2.5_

  - [x] 2.5 Create candidate domain events
    - Create `App\Events\CandidateRegistered`, `App\Events\CandidateLogin`, `App\Events\CandidateApplied` event classes following existing DomainEvent pattern
    - _Requirements: 1.5, 14.4_

  - [ ]* 2.6 Write property tests for candidate registration
    - **Property 1: Registration creates candidate record and returns tokens**
    - **Property 3: Invalid registration input produces field-specific errors**
    - **Property 4: Password complexity validation**
    - **Validates: Requirements 1.1, 1.4, 1.6**

  - [ ]* 2.7 Write property tests for candidate authentication lifecycle
    - **Property 5: Candidate login returns correctly-structured JWT**
    - **Property 6: Candidate logout invalidates both tokens**
    - **Property 7: Candidate token refresh with replay detection**
    - **Validates: Requirements 2.1, 2.3, 2.4**

- [x] 3. Checkpoint — Run migrations and verify auth flow
  - Ensure all migrations run cleanly, candidate registration and login return valid JWTs, and all tests pass. Ask the user if questions arise.

- [x] 4. Candidate profile management backend
  - [x] 4.1 Implement CandidateProfileService
    - Create `App\Services\CandidateProfileService` with methods: getProfile(), updatePersonalInfo(), addWorkHistory(), updateWorkHistory(), deleteWorkHistory(), reorderWorkHistory(), addEducation(), updateEducation(), deleteEducation(), reorderEducation(), replaceSkills()
    - All queries scoped to authenticated candidate's ID
    - Work history and education ordered by start_date DESC by default, respecting sort_order after reorder
    - Skills replacement: delete all existing, insert new list
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_

  - [x] 4.2 Create CandidateProfileController and request validation
    - Create `App\Http\Controllers\CandidateProfileController` with endpoints for all profile CRUD operations
    - Create form request classes for work history, education, skills, and personal info updates
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

  - [x] 4.3 Register candidate profile routes
    - Add profile routes under `v1/candidate/profile` prefix with `candidate.auth` middleware
    - GET profile, PUT profile, POST/PUT/DELETE work-history, PUT work-history/reorder, POST/PUT/DELETE education, PUT education/reorder, PUT skills
    - _Requirements: 3.1–3.6_

  - [ ]* 4.4 Write property tests for profile management
    - **Property 8: Profile personal information round-trip**
    - **Property 9: Profile collection entry persistence**
    - **Property 10: Profile collection ordering**
    - **Property 11: Skills replacement is total**
    - **Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5, 3.6**

- [x] 5. Resume service backend
  - [x] 5.1 Implement ResumeService
    - Create `App\Services\ResumeService` with methods: listResumes(), createResume(), getResume(), updateResume(), deleteResume(), finalizeResume(), listVersions(), restoreVersion(), toggleSharing(), exportPdf()
    - Enforce max 20 resumes per candidate at creation
    - On finalize: set is_complete=true, create initial resume_version
    - On update (auto-save): create resume_version snapshot, enforce max 50 versions
    - Version restore: create new version with restored content (preserve history)
    - Public sharing: generate UUID v4 token on enable, deactivate on disable, regenerate new token on re-enable
    - Pre-populate new resume content from candidate profile data
    - _Requirements: 4.2, 4.4, 4.5, 12.1, 12.2, 12.3, 12.4, 12.5, 12.6, 13.1, 13.3, 13.4_

  - [x] 5.2 Create ResumeController and request validation
    - Create `App\Http\Controllers\ResumeController` with all resume CRUD, version, sharing, and export endpoints
    - Create form request classes for resume creation, update, and sharing toggle
    - _Requirements: 12.1–12.6, 13.1–13.5_

  - [x] 5.3 Register candidate resume routes
    - Add resume routes under `v1/candidate/resumes` prefix with `candidate.auth` middleware
    - GET list, POST create, GET show, PUT update, DELETE destroy, POST finalize, GET versions, POST restore version, POST share, POST export-pdf
    - _Requirements: 12.1–12.6_

  - [ ]* 5.4 Write property tests for resume versioning
    - **Property 20: Resume save creates version snapshot**
    - **Property 21: Multiple resumes per candidate**
    - **Property 22: Version history ordering**
    - **Property 23: Version restore round-trip**
    - **Validates: Requirements 12.1, 12.2, 12.3, 12.4, 4.5**

  - [ ]* 5.5 Write property tests for resume templates and sharing
    - **Property 18: Template switching preserves content**
    - **Property 24: Public sharing toggle generates and invalidates links**
    - **Property 25: Public link hides contact information by default**
    - **Validates: Requirements 10.3, 13.1, 13.3, 13.4, 13.5**

- [x] 6. AI service backend
  - [x] 6.1 Implement AIService and ProcessAIJob queue job
    - Create `App\Services\AIService` with methods: createJob(), getJob(), checkRateLimit()
    - Implement rate limiting: 20/hour and 100/day per candidate, queried via (candidate_id, created_at) index
    - Enforce max 5000 character input length
    - Create `App\Jobs\ProcessAIJob` dispatched to Redis queue
    - Worker: update status to processing, build prompt by job type, call OpenAI API (GPT-4, temperature 0.7), store result on success, retry up to 2 times with exponential backoff (2s, 8s) on failure, mark failed after all retries, record tokens_used and processing_duration_ms
    - Max execution timeout: 30 seconds per job
    - _Requirements: 5.1, 5.2, 5.5, 6.1, 6.2, 7.1, 8.1, 8.2, 9.1, 9.2, 16.1, 16.2, 16.4, 16.5, 17.1, 17.3, 17.4, 17.5, 17.6_

  - [x] 6.2 Create AIController and request validation
    - Create `App\Http\Controllers\AIController` with endpoints: summary, bullets, skills, atsOptimize, improve, getJob
    - Create form request classes for each AI job type with appropriate validation
    - Return 202 Accepted with job_id for creation endpoints
    - Return 429 with Retry-After header when rate limited
    - _Requirements: 5.1, 6.1, 7.1, 8.1, 9.1, 16.3, 17.1_

  - [x] 6.3 Create OpenAI integration service
    - Create `App\Services\OpenAIService` as a wrapper around the OpenAI API client
    - Implement prompt templates for each AI job type (summary, bullets, skills, ats_optimize, improve)
    - Configure GPT-4 model, temperature 0.7, and response parsing for each type
    - Support mocking in tests via interface/contract
    - _Requirements: 5.2, 5.4, 6.2, 6.4, 7.1, 8.2, 9.2, 9.5_

  - [x] 6.4 Register AI routes
    - Add AI routes under `v1/candidate/ai` prefix with `candidate.auth` middleware
    - POST summary, POST bullets, POST skills, POST ats-optimize, POST improve, GET jobs/{id}
    - _Requirements: 5.1, 6.1, 7.1, 8.1, 9.1, 17.1_

  - [ ]* 6.5 Write property tests for AI job lifecycle
    - **Property 14: AI job creation returns pending job for all types**
    - **Property 15: AI job result retrieval**
    - **Property 16: AI retry on failure**
    - **Property 17: Skills suggestion excludes existing skills**
    - **Property 34: AI job status transitions**
    - **Validates: Requirements 5.1, 5.3, 5.5, 6.1, 6.3, 7.3, 7.4, 8.1, 8.3, 9.1, 9.3, 17.1, 17.3, 17.4, 17.5**

  - [ ]* 6.6 Write property tests for AI rate limiting
    - **Property 32: AI rate limit returns 429 with Retry-After**
    - **Property 33: AI job records contain all monitoring fields**
    - **Validates: Requirements 16.1, 16.2, 16.3, 16.4**

- [x] 7. PDF export service backend
  - [x] 7.1 Implement PDFExportService with DomPDF
    - Create `App\Services\PDFExportService` that loads resume content and template slug, renders Blade template to HTML, converts to PDF via DomPDF (US Letter 8.5"×11"), stores PDF in file storage, returns signed download URL
    - Timeout: 10 seconds max
    - Log failures with full context (resume ID, template, error stack trace)
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_

  - [x] 7.2 Create resume Blade templates for PDF rendering
    - Create Blade views at `resources/views/resume-templates/` for each template: clean.blade.php, modern.blade.php, professional.blade.php, creative.blade.php
    - Each template renders all sections (personal_info, summary, work_experience, education, skills) with inline CSS for PDF compatibility
    - _Requirements: 10.1, 10.4, 11.3_

  - [ ]* 7.3 Write property test for template rendering
    - **Property 19: All templates render all sections**
    - **Validates: Requirements 10.4**

- [x] 8. Checkpoint — Verify backend services with tests
  - Ensure all backend services work end-to-end: candidate auth, profile CRUD, resume CRUD with versioning, AI job creation and polling, PDF export. Run all tests. Ask the user if questions arise.

- [x] 9. Public resume and job application backend
  - [x] 9.1 Implement PublicResumeController
    - Create `App\Http\Controllers\PublicResumeController` with show() method
    - Look up resume by public_link_token where public_link_active=true, return 404 if not found
    - Exclude email and phone from response unless show_contact_on_public=true
    - No auth required
    - _Requirements: 13.2, 13.5_

  - [x] 9.2 Implement JobApplicationService
    - Create `App\Services\JobApplicationService` with methods: apply(), listCandidateApplications()
    - Apply: validate job posting exists and is active, check duplicate (candidate_id + job_posting_id), snapshot resume content as JSON, create job_applications record, dispatch candidate.applied event
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5_

  - [x] 9.3 Create JobApplicationController for candidate-side
    - Create `App\Http\Controllers\CandidateApplicationController` with apply() and index() methods
    - _Requirements: 14.1, 14.3_

  - [x] 9.4 Implement employer-side application and talent pool endpoints
    - Create `App\Http\Controllers\EmployerApplicationController` with listForJob(), show(), talentPool() methods
    - All queries scoped by tenant_id via TenantResolver middleware
    - Talent pool returns de-duplicated candidate list across all job postings for the tenant
    - _Requirements: 15.1, 15.2, 15.3, 15.4_

  - [x] 9.5 Register public, candidate application, and employer application routes
    - Add public route: GET `v1/public/resumes/{token}` (no auth)
    - Add candidate application routes under `v1/candidate/applications` with `candidate.auth` middleware
    - Add employer routes: GET `v1/jobs/{jobId}/applications`, GET `v1/applications/{id}`, GET `v1/talent-pool` with `havenhr.auth`, `tenant.resolve`, and `rbac:applications.view` middleware
    - _Requirements: 13.2, 14.1, 15.1, 15.3, 15.5_

  - [x] 9.6 Add applications.view permission to PermissionSeeder
    - Add `applications.view` permission to the existing PermissionSeeder
    - _Requirements: 15.5_

  - [ ]* 9.7 Write property tests for job applications
    - **Property 26: Application resume snapshot is frozen**
    - **Property 27: Duplicate application rejected**
    - **Validates: Requirements 14.1, 14.2, 14.3**

  - [ ]* 9.8 Write property tests for employer integration
    - **Property 28: Employer application queries scoped by tenant**
    - **Property 29: Employer sees candidate profile and frozen resume**
    - **Property 30: Talent pool de-duplication**
    - **Property 31: RBAC enforces applications.view permission**
    - **Validates: Requirements 15.1, 15.2, 15.3, 15.4, 15.5**

- [x] 10. Checkpoint — Full backend verification
  - Ensure all backend endpoints work: public resume access, job applications, employer queries, tenant scoping, RBAC enforcement. Run full test suite. Ask the user if questions arise.

- [x] 11. Frontend candidate authentication
  - [x] 11.1 Create CandidateAuthContext provider and API client
    - Create `CandidateAuthContext` with candidate state, login(), register(), logout(), refresh() methods
    - Store candidate JWT in memory, refresh token in httpOnly cookie or secure storage
    - Create candidate API client with automatic token refresh and Authorization header injection
    - Separate from existing tenant AuthContext
    - _Requirements: 2.1, 2.3, 2.4_

  - [x] 11.2 Create candidate registration page
    - Create `/candidate/register` page with form: name, email, password, confirm password
    - Inline validation errors from API 422 responses displayed next to each field
    - On success: redirect to candidate dashboard
    - Mobile-first responsive layout
    - _Requirements: 1.1, 1.6, 18.1, 18.2, 18.5_

  - [x] 11.3 Create candidate login page
    - Create `/candidate/login` page with email and password fields
    - Display generic error on invalid credentials
    - On success: redirect to candidate dashboard
    - Mobile-first responsive layout
    - _Requirements: 2.1, 2.2, 18.1, 18.2_

  - [x] 11.4 Create candidate layout with distinct visual identity
    - Create candidate-specific layout with teal/green color scheme, top navigation bar (no sidebar), candidate-specific links (Dashboard, Profile, Resumes)
    - Shared Tailwind design tokens for brand consistency (typography, spacing, border radius)
    - _Requirements: 18.6_

- [x] 12. Frontend candidate profile editor
  - [x] 12.1 Create candidate dashboard page
    - Create `/candidate/dashboard` page showing list of resumes with titles, template, last updated, completion status
    - "Create New Resume" button
    - Quick links to profile editor
    - _Requirements: 18.1_

  - [x] 12.2 Create profile editor page
    - Create `/candidate/profile` page with sections: personal info form, work history list with add/edit/delete/reorder, education list with add/edit/delete/reorder, skills management with add/remove
    - Auto-save or explicit save per section
    - Loading indicators during save operations
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 18.1, 18.4_

  - [ ]* 12.3 Write property test for resume pre-population
    - **Property 12: Resume pre-population from profile** (fast-check)
    - **Validates: Requirements 4.2**

- [ ] 13. Frontend resume builder wizard
  - [x] 13.1 Create resume builder wizard component
    - Create `/candidate/resumes/new` and `/candidate/resumes/[id]/edit` pages with multi-step wizard
    - Steps in order: (1) Template selection, (2) Personal info, (3) Professional summary, (4) Work experience, (5) Education, (6) Skills, (7) Review and finalize
    - Forward/backward navigation between steps without data loss
    - Progress indicator showing current step
    - Auto-save after each step completion via PUT /candidate/resumes/{id}
    - _Requirements: 4.1, 4.3, 4.4, 4.5_

  - [x] 13.2 Create real-time resume preview panel
    - Side-by-side preview on desktop (≥768px), below form on mobile (<768px)
    - Preview updates as candidate modifies content, reflecting selected template layout
    - Template switching without content loss
    - _Requirements: 4.6, 4.7, 10.2, 10.3_

  - [x] 13.3 Integrate AI content generation into wizard steps
    - Add "Generate with AI" buttons to summary, work experience, and skills steps
    - Create `useAIJob` hook: accepts job ID, polls GET /candidate/ai/jobs/{id} every 2s, returns { status, result, error, isLoading }, stops on completed/failed
    - Display loading spinner during pending/processing
    - Show AI results inline with accept/reject/edit controls for summaries and bullet points
    - Display skill suggestions as selectable items
    - _Requirements: 5.1, 5.3, 6.1, 6.3, 6.5, 7.1, 7.2, 17.2_

  - [x] 13.4 Create ATS optimization panel
    - Add ATS optimization section: paste job description, submit for analysis
    - Display missing keywords, present keywords, categorized suggestions (required skills, preferred skills, industry terminology)
    - Click keyword to navigate to relevant resume section
    - _Requirements: 8.1, 8.3, 8.4, 8.5_

  - [x] 13.5 Create content improvement UI
    - Add "Improve" button for text blocks in summary and work experience steps
    - Display original and improved text side by side
    - Accept, reject, or further edit improved version
    - _Requirements: 9.1, 9.3, 9.4_

  - [ ]* 13.6 Write property test for wizard navigation
    - **Property 13: Wizard navigation preserves data** (fast-check)
    - **Validates: Requirements 4.3**

- [ ] 14. Frontend resume preview, export, and sharing
  - [x] 14.1 Create resume preview page
    - Create `/candidate/resumes/[id]` page showing full resume rendered with selected template
    - Action buttons: Edit, Export PDF, Share, Delete
    - _Requirements: 18.1_

  - [x] 14.2 Implement PDF export flow
    - "Export PDF" button triggers POST /candidate/resumes/{id}/export-pdf
    - Show loading indicator during generation
    - On success: trigger browser download from returned URL
    - On failure: display error message
    - _Requirements: 11.1, 11.4, 11.5, 18.4_

  - [x] 14.3 Implement public sharing toggle and public resume view
    - Share toggle UI: enable/disable sharing, copy link button, option to show/hide contact info on public view
    - Create `/r/[token]` public page: read-only resume view with selected template, no auth required
    - Respect show_contact_on_public setting
    - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5_

  - [x] 14.4 Create resume version history UI
    - Version history panel on resume preview/edit page
    - List versions with timestamps and change summaries, ordered by date descending
    - "Restore" button per version
    - _Requirements: 12.3, 12.4_

- [x] 15. Frontend job applications
  - [x] 15.1 Create candidate job application flow
    - "Apply" button on job listings that opens resume selection modal
    - Submit application via POST /candidate/applications
    - Display success confirmation or error (duplicate application, inactive job)
    - List candidate's applications on dashboard with status
    - _Requirements: 14.1, 14.3, 14.5_

  - [ ]* 15.2 Write property test for inline validation errors
    - **Property 35: Frontend inline validation error display** (fast-check)
    - **Validates: Requirements 18.5**

- [ ] 16. Frontend accessibility and responsive polish
  - [x] 16.1 Ensure WCAG 2.1 Level AA compliance across all candidate pages
    - Proper form labels and aria attributes on all form fields
    - Keyboard navigation and focus management for wizard steps, modals, and interactive elements
    - Sufficient color contrast ratios (4.5:1 for normal text, 3:1 for large text)
    - Screen reader announcements for loading states and AI generation status
    - _Requirements: 18.3_

  - [x] 16.2 Verify mobile-first responsive layout across all candidate pages
    - Test layouts from 320px to 2560px viewport widths
    - Ensure wizard preview collapses below form on viewports < 768px
    - Verify touch targets are at least 44×44px on mobile
    - _Requirements: 4.7, 18.2_

- [x] 17. Final checkpoint — Full integration verification
  - Ensure all tests pass (backend property tests, unit tests, frontend property tests). Verify end-to-end flows: candidate registration → profile → resume wizard → AI generation → PDF export → public sharing → job application. Ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation at key milestones
- Property tests validate universal correctness properties from the design document (35 properties total)
- Backend uses PHP/Laravel with Pest + Faker for property-based tests
- Frontend uses TypeScript/Next.js with fast-check for property-based tests
- All candidate tables use UUID primary keys and have no tenant_id column
- The OpenAI integration should be behind an interface/contract for easy mocking in tests
