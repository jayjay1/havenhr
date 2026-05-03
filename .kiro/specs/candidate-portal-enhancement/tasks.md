# Implementation Plan: Candidate Portal Enhancement

## Overview

This plan implements the candidate portal enhancements across backend (Laravel) and frontend (Next.js). Backend work comes first: a database migration for three new columns, then service and controller extensions for application detail/filtering and profile fields. Frontend work follows: type extensions, updated navigation, applications dashboard and detail pages, enhanced profile page, and a new settings page. Each task builds incrementally on the previous, ending with integration wiring.

## Tasks

- [x] 1. Database migration and model update
  - [x] 1.1 Create migration to add new columns to `candidates` table
    - Create migration file `add_profile_fields_to_candidates_table`
    - Add `professional_summary` column: `text`, nullable
    - Add `github_url` column: `string(500)`, nullable
    - Add `is_profile_public` column: `boolean`, default `false`
    - _Requirements: 3.6, 3.7, 3.9, 6.4_
  - [x] 1.2 Update `Candidate` model `$fillable` and `$casts`
    - Add `professional_summary`, `github_url`, `is_profile_public` to the `$fillable` array in `backend/app/Models/Candidate.php`
    - Add `is_profile_public` => `boolean` to the `casts()` method
    - _Requirements: 3.6, 3.7, 3.9, 6.4_

- [x] 2. Backend profile service and validation extensions
  - [x] 2.1 Extend `UpdatePersonalInfoRequest` with new validation rules
    - Add `professional_summary`: `sometimes|nullable|string|max:2000`
    - Add `github_url`: `sometimes|nullable|url|max:500`
    - Add `is_profile_public`: `sometimes|boolean`
    - _Requirements: 3.10, 6.5, 6.6, 6.7_
  - [x] 2.2 Extend `CandidateProfileService::getProfile` to include new fields
    - Add `professional_summary`, `github_url`, `is_profile_public` to the returned array
    - _Requirements: 6.8_
  - [x] 2.3 Extend `CandidateProfileService::updatePersonalInfo` to allow new fields
    - Add `professional_summary`, `github_url`, `is_profile_public` to the `$allowedFields` array
    - Include the new fields in the returned response array
    - _Requirements: 3.6, 3.7, 3.9, 6.4_
  - [ ]* 2.4 Write property test for profile field round-trip persistence
    - **Property 8: New profile fields round-trip persistence**
    - Generate random valid `professional_summary` (≤ 2000 chars), valid `github_url`, and boolean `is_profile_public`; save via PUT then fetch via GET; verify values match
    - **Validates: Requirements 3.6, 3.7, 3.9, 6.4, 6.8**
  - [ ]* 2.5 Write property test for invalid GitHub URL rejection
    - **Property 9: Invalid GitHub URL is rejected**
    - Generate random strings that are not valid URL format; submit as `github_url`; verify 422 response
    - **Validates: Requirements 3.10, 6.5**
  - [ ]* 2.6 Write property test for professional summary max length rejection
    - **Property 10: Professional summary exceeding max length is rejected**
    - Generate random strings longer than 2000 characters; submit as `professional_summary`; verify 422 response
    - **Validates: Requirements 6.6**

- [x] 3. Backend application service extensions for filtering and sorting
  - [x] 3.1 Extend `JobApplicationService::listCandidateApplications` with filtering, sorting, and enriched data
    - Add optional parameters: `?string $status`, `string $sortBy = 'applied_at'`, `string $sortDir = 'desc'`
    - Apply `status` filter via `->where('status', $status)` when provided
    - Apply sorting by `applied_at` (default) or `job_title` (via join on `job_postings`)
    - Eager-load `jobPosting.company`, `pipelineStage`, and `jobPosting.pipelineStages`
    - Enrich each application with `pipeline_stage` (name, color) and `all_stages` array (name, color, sort_order)
    - Include `location` and `employment_type` from the job posting
    - _Requirements: 1.1, 1.2, 1.4, 1.5, 6.2, 6.3_
  - [x] 3.2 Implement `JobApplicationService::getCandidateApplicationDetail` method
    - Accept `candidateId` and `applicationId`
    - Load application with eager-loaded relationships: `jobPosting.company`, `pipelineStage`, `jobPosting.pipelineStages`
    - Load stage transitions via `StageTransition::where('job_application_id', $id)` ordered by `moved_at`
    - Verify `candidate_id` matches; return null if not found or not owned
    - Return full detail payload: job info, current stage, all stages, transitions, resume snapshot
    - _Requirements: 2.7, 2.8, 2.9, 6.1_
  - [ ]* 3.3 Write property test for application list completeness and default ordering
    - **Property 1: Application list returns all candidate applications in default order**
    - Generate random candidate with applications; call index without filters; verify all returned, ordered by `applied_at` descending
    - **Validates: Requirements 1.1**
  - [ ]* 3.4 Write property test for status filter correctness
    - **Property 2: Application list status filter returns only matching applications**
    - Generate random candidate with mixed-status applications; filter by each status; verify only matching returned
    - **Validates: Requirements 1.4, 6.2**
  - [ ]* 3.5 Write property test for sort correctness
    - **Property 3: Application list sorting produces correctly ordered results**
    - Generate random candidate with multiple applications; test all sort_by/sort_dir combinations; verify ordering
    - **Validates: Requirements 1.5, 6.3**
  - [ ]* 3.6 Write property test for list response shape completeness
    - **Property 4: Application list items contain all required fields**
    - Generate random applications; verify each item has non-null `job_title`, `company_name`, `pipeline_stage`, `status`, `applied_at`, and `all_stages` with ≥ 1 entry
    - **Validates: Requirements 1.2, 6.1**

- [x] 4. Backend application controller extensions and route registration
  - [x] 4.1 Extend `CandidateApplicationController::index` to accept query parameters
    - Accept optional `status`, `sort_by`, `sort_dir` query parameters
    - Validate `status` against allowed values (submitted, reviewed, shortlisted, rejected)
    - Validate `sort_by` against allowed values (applied_at, job_title)
    - Validate `sort_dir` against allowed values (asc, desc)
    - Pass parameters to `JobApplicationService::listCandidateApplications`
    - _Requirements: 1.4, 1.5, 6.2, 6.3_
  - [x] 4.2 Add `show` method to `CandidateApplicationController`
    - Route: `GET /api/v1/candidate/applications/{id}`
    - Call `JobApplicationService::getCandidateApplicationDetail`
    - Return 404 if null (not found or not owned)
    - Return full application detail JSON
    - _Requirements: 2.7, 2.8, 2.9, 6.1_
  - [x] 4.3 Register the new `show` route in `backend/routes/api.php`
    - Add `Route::get('/{id}', [CandidateApplicationController::class, 'show'])` inside the candidate applications group
    - _Requirements: 6.1_
  - [ ]* 4.4 Write property test for application detail response completeness
    - **Property 5: Application detail response contains complete data**
    - Generate random owned applications; verify response contains all required fields: job_title, company_name, location, employment_type, pipeline_stage, all_stages, transitions, resume_snapshot, status, applied_at
    - **Validates: Requirements 2.2, 2.5, 2.6, 2.7, 6.1**
  - [ ]* 4.5 Write property test for application detail ownership enforcement
    - **Property 6: Application detail enforces candidate ownership**
    - Generate applications belonging to other candidates; verify 404 response for each
    - **Validates: Requirements 2.8, 2.9**

- [x] 5. Checkpoint — Backend complete
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. Frontend type extensions and API functions
  - [x] 6.1 Extend TypeScript types in `frontend/src/types/candidate.ts`
    - Add `professional_summary`, `github_url`, `is_profile_public` to `CandidateProfile` interface
    - Add `ApplicationListItem` interface with enriched fields: job_title, company_name, location, employment_type, pipeline_stage (name, color), all_stages array, status, applied_at
    - Add `ApplicationDetail` interface with full detail: all of `ApplicationListItem` plus transitions array and resume_snapshot
    - Add `PipelineStageInfo` interface: `{ id: string; name: string; color: string; sort_order: number }`
    - Add `StageTransitionInfo` interface: `{ from_stage: string; to_stage: string; moved_at: string }`
    - _Requirements: 1.2, 2.2, 3.6, 3.7, 3.9, 6.1, 6.8_
  - [x] 6.2 Add API functions for new endpoints in `frontend/src/lib/candidateApi.ts`
    - `fetchApplications(params?: { status?, sort_by?, sort_dir? })` — GET `/candidate/applications` with query params
    - `fetchApplicationDetail(id: string)` — GET `/candidate/applications/{id}`
    - Update existing profile API functions to handle new fields
    - Follow existing `candidateApiClient` patterns
    - _Requirements: 1.1, 1.4, 1.5, 2.7, 6.1, 6.2, 6.3_

- [x] 7. Update CandidateNav component
  - [x] 7.1 Extend `NAV_ITEMS` in `frontend/src/components/candidate/CandidateNav.tsx`
    - Add "Job Board" item with href `/candidate/jobs`
    - Add "Settings" item with href `/candidate/settings`
    - Rename "Applications" label to "My Applications"
    - _Requirements: 5.1, 5.2_
  - [ ]* 7.2 Write property test for navigation active state correctness
    - **Property 11: Navigation active state correctness**
    - Extract `isActive` as a pure exported function; generate random candidate portal paths; verify exactly one nav item is active for each path
    - **Validates: Requirements 5.2**

- [x] 8. Applications dashboard page
  - [x] 8.1 Rewrite `frontend/src/app/candidate/applications/page.tsx` as the applications dashboard
    - Fetch `GET /candidate/applications` with query params on mount
    - Render card list with: job title, company name, pipeline stage indicator (horizontal step bar), status badge, applied date
    - Each card links to `/candidate/applications/[id]`
    - Filter bar: status dropdown (all, submitted, reviewed, shortlisted, rejected)
    - Sort controls: sort by applied date or job title, ascending/descending
    - Empty state: message with link to `/candidate/jobs`
    - Loading state: spinner
    - Error state: alert banner
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8_

- [x] 9. Application detail page
  - [x] 9.1 Create `frontend/src/app/candidate/applications/[id]/page.tsx`
    - Fetch `GET /candidate/applications/{id}` on mount
    - Render job info section: title, company, location, employment type
    - Render visual pipeline stage indicator showing progress through all stages
    - Render applied date in human-readable format
    - Render stage timeline: vertical timeline of transitions with stage name and date
    - Render resume snapshot content from the JSON snapshot
    - Back link to `/candidate/applications`
    - Loading and error states
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.10_

- [x] 10. Checkpoint — Applications feature complete
  - Ensure all tests pass, ask the user if questions arise.

- [x] 11. Enhanced profile page
  - [x] 11.1 Update `frontend/src/app/candidate/profile/page.tsx` with new sections
    - Add avatar placeholder: circle with first letter of candidate's name at top of page
    - Add profile completeness indicator: progress bar with percentage
    - Completeness calculated from 10 fields: name, phone, location, professional_summary, linkedin_url, github_url, portfolio_url, ≥1 work history, ≥1 education, ≥1 skill
    - Add `professional_summary` textarea field in personal info section
    - Add `github_url` URL input field in personal info section
    - Add `is_profile_public` toggle switch for profile visibility
    - Recalculate completeness on any field change
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 3.10_
  - [ ]* 11.2 Write property test for profile completeness calculation
    - **Property 7: Profile completeness calculation**
    - Extract completeness calculation as a pure function; generate random profile states; verify percentage equals `(filled fields / 10) × 100`
    - **Validates: Requirements 3.1, 3.2**

- [x] 12. Settings page with notification preferences
  - [x] 12.1 Create `frontend/src/app/candidate/settings/page.tsx`
    - Fetch `GET /candidate/profile/notification-preferences` on mount
    - Render toggle switch for `stage_change_emails` reflecting current value
    - Render toggle switch for `application_confirmation_emails` reflecting current value
    - On toggle: `PUT /candidate/profile/notification-preferences` with updated values
    - Success toast on save
    - Error alert with toggle revert on failure
    - Loading state while fetching
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7_

- [x] 13. Final checkpoint — All features integrated
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Backend tasks (1–5) should be completed before frontend tasks (6–13)
- Property-based tests use Pest (PHP, backend) and fast-check (TypeScript, frontend) as specified in the design
- The existing `NotificationPreferenceController` already handles GET/PUT for notification preferences — the settings page just needs to consume it
- Profile completeness is calculated client-side from the profile response, no new API endpoint needed
- Checkpoints ensure incremental validation at backend completion, applications feature completion, and final integration
