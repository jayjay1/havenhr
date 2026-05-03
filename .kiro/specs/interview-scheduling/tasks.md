# Implementation Plan: Interview Scheduling

## Overview

This plan implements interview scheduling for the HavenHR platform. Backend work comes first: migration, model, service, form requests, controller, notifications, artisan command, and route registration. Frontend follows: TypeScript types, API functions, and UI components (schedule modal, interview list, upcoming widget, candidate interviews list). Each task builds incrementally on the previous, ending with integration wiring.

## Tasks

- [ ] 1. Database migration and Interview model
  - [ ] 1.1 Create migration for `interviews` table
    - Create migration file `backend/database/migrations/2025_01_05_000001_create_interviews_table.php`
    - UUID primary key, FKs to `job_applications` and `users` with cascade on delete
    - Columns: `scheduled_at` (datetime), `duration_minutes` (unsigned small int), `location` (varchar 500), `interview_type` (varchar 20), `status` (varchar 20, default "scheduled"), `notes` (text, nullable), `candidate_reminder_sent_at` (timestamp, nullable), `interviewer_reminder_sent_at` (timestamp, nullable), `created_at`, `updated_at`
    - Indexes: `job_application_id`, `interviewer_id`, composite `['status', 'scheduled_at']`
    - _Requirements: 1.1, 1.2, 1.3_
  - [ ] 1.2 Create `Interview` Eloquent model
    - Create `backend/app/Models/Interview.php` using `HasFactory` and `HasUuid` traits
    - Define `$fillable` array with all input columns
    - Define `casts()` for `scheduled_at` (datetime) and `duration_minutes` (integer)
    - Define `jobApplication()` BelongsTo relationship to `JobApplication`
    - Define `interviewer()` BelongsTo relationship to `User`
    - _Requirements: 1.1, 1.2, 1.3, 1.4_
  - [ ]* 1.3 Write property test for interview creation round-trip (Property 1)
    - **Property 1: Interview creation round-trip**
    - Generate random valid interview payloads, create via model, verify all fields match and status defaults to "scheduled"
    - **Validates: Requirements 1.4, 2.1**

- [ ] 2. Form request classes
  - [ ] 2.1 Create `ScheduleInterviewRequest` form request
    - Create `backend/app/Http/Requests/ScheduleInterviewRequest.php` extending `BaseFormRequest`
    - Validate: `job_application_id` (required, uuid), `interviewer_id` (required, uuid), `scheduled_at` (required, date, after:now), `duration_minutes` (required, integer, in:30,45,60,90), `interview_type` (required, string, in:phone,video,in_person), `location` (required, string, max:500), `notes` (sometimes, nullable, string, max:2000)
    - _Requirements: 2.1, 2.5, 2.6, 2.7, 2.8_
  - [ ] 2.2 Create `UpdateInterviewRequest` form request
    - Create `backend/app/Http/Requests/UpdateInterviewRequest.php` extending `BaseFormRequest`
    - Validate: `scheduled_at` (sometimes, date, after:now), `duration_minutes` (sometimes, integer, in:30,45,60,90), `interview_type` (sometimes, string, in:phone,video,in_person), `location` (sometimes, string, max:500), `interviewer_id` (sometimes, uuid), `notes` (sometimes, nullable, string, max:2000), `status` (sometimes, string, in:scheduled,completed,cancelled,no_show)
    - _Requirements: 5.3, 5.5_
  - [ ]* 2.3 Write property test for validation rejects invalid input (Property 3)
    - **Property 3: Validation rejects invalid input**
    - Generate random invalid payloads (bad duration, bad type, past dates, missing fields), verify 422 with correct error fields
    - **Validates: Requirements 2.5, 2.6, 2.7, 5.5**

- [ ] 3. InterviewService — core CRUD methods
  - [ ] 3.1 Create `InterviewService` with `schedule` method
    - Create `backend/app/Services/InterviewService.php`
    - Implement `schedule(array $data, string $tenantId): Interview` — validate application and interviewer belong to tenant, create interview with status "scheduled", eager-load interviewer, return interview
    - Use tenant scoping pattern: `whereHas('jobApplication', fn($q) => $q->whereHas('jobPosting', fn($q2) => $q2->where('tenant_id', $tenantId)))`
    - _Requirements: 1.4, 1.5, 2.1, 2.3, 2.4_
  - [ ] 3.2 Implement `listForApplication` method
    - Implement `listForApplication(string $applicationId, string $tenantId): Collection` — verify application belongs to tenant, return interviews ordered by `scheduled_at` descending, eager-load interviewer (name, email)
    - _Requirements: 3.1, 3.3, 3.4_
  - [ ] 3.3 Implement `getDetail` method
    - Implement `getDetail(string $interviewId, string $tenantId): ?Interview` — tenant-scoped query, eager-load interviewer, jobApplication.candidate, jobApplication.jobPosting for candidate name and job title
    - _Requirements: 4.1, 4.3_
  - [ ] 3.4 Implement `update` method
    - Implement `update(Interview $interview, array $data): Interview` — update provided fields, if `interviewer_id` provided validate it belongs to tenant, return updated interview with interviewer eager-loaded
    - _Requirements: 5.1, 5.3_
  - [ ] 3.5 Implement `cancel` method
    - Implement `cancel(Interview $interview): Interview` — if already cancelled return error, otherwise set status to "cancelled" and return updated interview
    - _Requirements: 6.1, 6.4_
  - [ ] 3.6 Implement `getUpcoming` method
    - Implement `getUpcoming(string $tenantId, int $limit = 10): Collection` — return interviews with status "scheduled" and `scheduled_at` within next 7 days, ordered ascending, limited to `$limit`, eager-load candidate name and job title
    - _Requirements: 12.1, 12.2, 12.3_
  - [ ] 3.7 Implement `listForCandidate` method
    - Implement `listForCandidate(string $candidateId): Collection` — return all interviews for the candidate's applications, eager-load job title, interviewer name; exclude notes field from response
    - _Requirements: 9.1, 9.3, 9.4_
  - [ ]* 3.8 Write property test for tenant isolation (Property 2)
    - **Property 2: Tenant isolation**
    - Generate interviews across random tenants, query from wrong tenant, verify null/empty results
    - **Validates: Requirements 1.5, 2.3, 2.4, 3.4, 4.3, 5.4, 6.3**
  - [ ]* 3.9 Write property test for list interviews correct set and order (Property 4)
    - **Property 4: List interviews returns correct set in correct order**
    - Generate random applications with random interview counts, list via service, verify count, order (descending), and interviewer info present
    - **Validates: Requirements 3.1, 3.3**
  - [ ]* 3.10 Write property test for interview detail includes all fields (Property 5)
    - **Property 5: Interview detail includes all required fields**
    - Generate random interviews, fetch detail, verify all required fields present (interviewer_name, interviewer_email, candidate_name, job_title, all attributes)
    - **Validates: Requirements 4.1**
  - [ ]* 3.11 Write property test for update applies and persists (Property 6)
    - **Property 6: Update applies and persists changes**
    - Generate random interviews and random valid update subsets, apply update, verify changed fields updated and unchanged fields preserved
    - **Validates: Requirements 5.1, 5.3**
  - [ ]* 3.12 Write property test for cancel sets status (Property 7)
    - **Property 7: Cancel sets status to cancelled**
    - Generate random interviews, cancel them, verify status; cancel again, verify error
    - **Validates: Requirements 6.1, 6.4**

- [ ] 4. InterviewController and route registration
  - [ ] 4.1 Create `InterviewController` with `store` action
    - Create `backend/app/Http/Controllers/InterviewController.php`
    - Implement `store(ScheduleInterviewRequest $request)` — call `InterviewService::schedule()`, return interview data with HTTP 201
    - Format response with interviewer_name and interviewer_email
    - _Requirements: 2.1, 2.2_
  - [ ] 4.2 Implement `listForApplication` action
    - Implement `listForApplication(string $appId)` — call `InterviewService::listForApplication()`, return interview list with interviewer info
    - _Requirements: 3.1, 3.2, 3.3_
  - [ ] 4.3 Implement `show` action
    - Implement `show(string $id)` — call `InterviewService::getDetail()`, return 404 if not found, otherwise return full detail with candidate_name and job_title
    - _Requirements: 4.1, 4.2, 4.3_
  - [ ] 4.4 Implement `update` action
    - Implement `update(UpdateInterviewRequest $request, string $id)` — tenant-scope lookup, call `InterviewService::update()`, return updated interview
    - _Requirements: 5.1, 5.2, 5.4_
  - [ ] 4.5 Implement `cancel` action
    - Implement `cancel(string $id)` — tenant-scope lookup, call `InterviewService::cancel()`, return 422 if already cancelled, otherwise return updated interview
    - _Requirements: 6.1, 6.2, 6.3, 6.4_
  - [ ] 4.6 Implement `upcoming` action
    - Implement `upcoming()` — call `InterviewService::getUpcoming()`, return list with candidate_name, job_title, scheduled_at, duration_minutes, interview_type, location
    - _Requirements: 12.1, 12.2, 12.3, 12.4_
  - [ ] 4.7 Implement `candidateInterviews` action
    - Implement `candidateInterviews()` — get authenticated candidate ID, call `InterviewService::listForCandidate()`, return list without notes field
    - _Requirements: 9.1, 9.2, 9.3, 9.4_
  - [ ] 4.8 Register all interview routes in `backend/routes/api.php`
    - Add employer routes inside `havenhr.auth` + `tenant.resolve` middleware group: POST `/interviews` (rbac:applications.manage), GET `/applications/{appId}/interviews` (rbac:applications.view), GET `/interviews/{id}` (rbac:applications.view), PUT `/interviews/{id}` (rbac:applications.manage), PATCH `/interviews/{id}/cancel` (rbac:applications.manage), GET `/dashboard/upcoming-interviews`
    - Add candidate route inside `candidate.auth` middleware group: GET `/candidate/interviews`
    - Place interview routes to avoid conflicts with existing application routes
    - _Requirements: 2.2, 3.2, 4.2, 5.2, 6.2, 9.2, 12.4_

- [ ] 5. Checkpoint — Backend CRUD complete
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 6. Notification classes and email templates
  - [ ] 6.1 Create `InterviewReminderCandidate` notification
    - Create `backend/app/Notifications/InterviewReminderCandidate.php` implementing `ShouldQueue`
    - Use `Queueable` trait, set `$tries = 3`
    - Constructor accepts: `jobTitle`, `scheduledAt` (Carbon), `interviewType`, `location`
    - `via()` returns `['mail']`
    - `toMail()` returns MailMessage with subject "Interview Reminder — {jobTitle}", uses markdown template `emails.interview-reminder-candidate` with candidateName, jobTitle, scheduledAt (formatted), interviewType, location
    - _Requirements: 10.1, 10.3, 10.4_
  - [ ] 6.2 Create `InterviewReminderInterviewer` notification
    - Create `backend/app/Notifications/InterviewReminderInterviewer.php` implementing `ShouldQueue`
    - Use `Queueable` trait, set `$tries = 3`
    - Constructor accepts: `candidateName`, `scheduledAt` (Carbon), `interviewType`, `location`
    - `via()` returns `['mail']`
    - `toMail()` returns MailMessage with subject "Interview in 1 Hour — {candidateName}", uses markdown template `emails.interview-reminder-interviewer` with interviewerName, candidateName, scheduledAt (formatted), interviewType, location
    - _Requirements: 10.2, 10.3, 10.4_
  - [ ] 6.3 Create Blade markdown email templates
    - Create `backend/resources/views/emails/interview-reminder-candidate.blade.php` — greeting, interview details (date/time, type, location, job title), preparation reminder
    - Create `backend/resources/views/emails/interview-reminder-interviewer.blade.php` — greeting, interview details (date/time, type, location, candidate name), upcoming interview notice
    - _Requirements: 10.3_

- [ ] 7. SendInterviewReminders command and reminder logic
  - [ ] 7.1 Implement `sendDueReminders` method on `InterviewService`
    - Candidate reminders: query interviews where `scheduled_at` between now+23h45m and now+24h15m, status "scheduled", `candidate_reminder_sent_at` is null; check candidate notification_preferences for `interview_reminders`; send `InterviewReminderCandidate` notification; set `candidate_reminder_sent_at`
    - Interviewer reminders: query interviews where `scheduled_at` between now+45m and now+1h15m, status "scheduled", `interviewer_reminder_sent_at` is null; send `InterviewReminderInterviewer` notification; set `interviewer_reminder_sent_at`
    - Set `reminder_sent_at` timestamp before dispatching to prevent duplicates
    - _Requirements: 10.1, 10.2, 10.4, 10.5, 10.6_
  - [ ] 7.2 Create `SendInterviewReminders` artisan command
    - Create `backend/app/Console/Commands/SendInterviewReminders.php`
    - Signature: `interviews:send-reminders`
    - `handle()` calls `InterviewService::sendDueReminders()`, returns `Command::SUCCESS`
    - _Requirements: 10.1, 10.2_
  - [ ] 7.3 Register command in Laravel scheduler
    - Add `Schedule::command('interviews:send-reminders')->everyFifteenMinutes()` to `routes/console.php` or `bootstrap/app.php`
    - _Requirements: 10.1, 10.2_
  - [ ]* 7.4 Write property test for reminder timing correctness (Property 9)
    - **Property 9: Reminder timing correctness**
    - Generate random interviews at various times, freeze time, run `sendDueReminders`, verify correct interviews get reminders and `sent_at` is set
    - **Validates: Requirements 10.1, 10.2**
  - [ ]* 7.5 Write property test for reminder skipping (Property 10)
    - **Property 10: Reminder skipping for cancelled and opted-out**
    - Generate cancelled interviews and opted-out candidates within reminder windows, run `sendDueReminders`, verify no reminders sent
    - **Validates: Requirements 10.5, 10.6**
  - [ ]* 7.6 Write property test for candidate API scoped without notes (Property 8)
    - **Property 8: Candidate API returns scoped data without notes**
    - Generate random candidates with random applications/interviews, fetch via candidate endpoint, verify scoping and notes exclusion
    - **Validates: Requirements 9.1, 9.3, 9.4**
  - [ ]* 7.7 Write property test for upcoming API filtering (Property 11)
    - **Property 11: Upcoming interviews API filtering, ordering, and limiting**
    - Generate random interviews across statuses/dates/tenants, query upcoming endpoint, verify filtering (status scheduled, next 7 days), ascending order, max 10 limit
    - **Validates: Requirements 12.1, 12.2, 12.3**

- [ ] 8. Checkpoint — Backend fully complete
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 9. Frontend TypeScript types and API functions
  - [ ] 9.1 Create interview TypeScript types
    - Create `frontend/src/types/interview.ts`
    - Define types: `InterviewType`, `InterviewStatus`, `Interview`, `InterviewListItem`, `InterviewDetail`, `UpcomingInterview`, `CandidateInterview`, `ScheduleInterviewPayload`, `UpdateInterviewPayload` as specified in the design
    - _Requirements: 1.1, 7.1, 7.3, 8.1, 11.1_
  - [ ] 9.2 Create `interviewApi.ts` API functions
    - Create `frontend/src/lib/interviewApi.ts` using existing `apiClient` pattern from `frontend/src/lib/api.ts`
    - Implement: `scheduleInterview(payload)` → POST `/interviews`, `listInterviewsForApplication(appId)` → GET `/applications/{appId}/interviews`, `getInterviewDetail(id)` → GET `/interviews/{id}`, `updateInterview(id, payload)` → PUT `/interviews/{id}`, `cancelInterview(id)` → PATCH `/interviews/{id}/cancel`, `fetchUpcomingInterviews()` → GET `/dashboard/upcoming-interviews`
    - _Requirements: 2.1, 3.1, 4.1, 5.1, 6.1, 12.1_
  - [ ] 9.3 Add candidate interview API function to `candidateApi.ts`
    - Add `fetchCandidateInterviews()` → GET `/candidate/interviews` using existing `candidateApiClient` pattern in `frontend/src/lib/candidateApi.ts`
    - _Requirements: 9.1_

- [ ] 10. ScheduleInterviewModal component
  - [ ] 10.1 Create `ScheduleInterviewModal` component
    - Create `frontend/src/components/interviews/ScheduleInterviewModal.tsx`
    - Props: `applicationId`, `onClose`, `onScheduled`
    - Form fields: date/time picker (`datetime-local`), duration select (30/45/60/90), interviewer dropdown (fetched from `/users` endpoint), interview type radio group (phone/video/in-person), location text input, notes textarea
    - On submit: call `scheduleInterview()`, show success toast, close modal, trigger `onScheduled` callback
    - Display server-side 422 validation errors per field
    - Focus trap, Escape to close, ARIA `role="dialog"`, `aria-modal="true"`, `aria-labelledby`
    - _Requirements: 7.1, 7.2_

- [ ] 11. InterviewList component
  - [ ] 11.1 Create `InterviewList` component
    - Create `frontend/src/components/interviews/InterviewList.tsx`
    - Props: `applicationId`, `canManage`
    - Fetch interviews via `listInterviewsForApplication(applicationId)`
    - Display each interview as a card: interviewer name, date/time, duration, type badge, status badge, location
    - Status actions (when `canManage` is true): "Mark Completed", "Cancel", "Mark No-Show" buttons calling `updateInterview` with new status
    - Cancel action shows confirmation dialog before proceeding
    - Loading skeleton while fetching, empty state "No interviews scheduled"
    - _Requirements: 7.3, 7.4, 7.5_

- [ ] 12. UpcomingInterviewsWidget component
  - [ ] 12.1 Create `UpcomingInterviewsWidget` component
    - Create `frontend/src/components/dashboard/UpcomingInterviewsWidget.tsx`
    - Fetch data via `fetchUpcomingInterviews()`
    - Display up to 10 interviews: candidate name, job title, date/time, interview type badge
    - Empty state: "No upcoming interviews this week"
    - Loading skeleton while fetching
    - Compact card layout matching existing dashboard widget style
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_

- [ ] 13. CandidateInterviewsList component
  - [ ] 13.1 Create `CandidateInterviewsList` component
    - Create `frontend/src/components/candidate/CandidateInterviewsList.tsx`
    - Fetch data via `fetchCandidateInterviews()` from `candidateApi`
    - Display: date/time, interview type, location, interviewer name, job title
    - Ordered by `scheduled_at` ascending (nearest first)
    - Read-only — no create/update/cancel actions
    - Empty state: "No upcoming interviews"
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

- [ ] 14. Checkpoint — All components built
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 15. Integration wiring
  - [ ] 15.1 Add "Schedule Interview" action and InterviewList to SlideOverPanel
    - In the existing `SlideOverPanel` component, add a "Schedule Interview" button that opens `ScheduleInterviewModal`
    - Add `InterviewList` section below existing application detail content
    - Pass `applicationId` and user permission (`applications.manage`) to both components
    - Refresh interview list after scheduling
    - _Requirements: 7.1, 7.3_
  - [ ] 15.2 Add UpcomingInterviewsWidget to employer dashboard
    - In the existing dashboard page, add `UpcomingInterviewsWidget` alongside existing dashboard widgets
    - _Requirements: 11.1_
  - [ ] 15.3 Add CandidateInterviewsList to candidate applications dashboard
    - In the existing candidate applications page, add `CandidateInterviewsList` section showing upcoming interviews
    - _Requirements: 8.1_

- [ ] 16. Final checkpoint — All features integrated
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Backend tasks (1–8) should be completed before frontend tasks (9–16)
- The Interview model scopes to tenant via `job_applications → job_postings.tenant_id` — no direct `tenant_id` column
- Property-based tests use Pest (backend PHP) and fast-check (frontend TypeScript) as specified in the design
- Reminder `sent_at` timestamps are set before dispatching notifications to ensure at-most-once delivery
- The `InterviewController` handles both employer and candidate endpoints, differentiated by middleware groups
