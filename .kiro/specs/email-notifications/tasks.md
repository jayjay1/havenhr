# Implementation Plan: Email Notifications

## Overview

This plan implements transactional email notifications for the HavenHR platform using Laravel's Notification framework with Markdown mail templates. The implementation proceeds incrementally: database migration and model update first, then notification classes and templates, event listeners, service modifications, preference management (service + controller + routes), and finally EventServiceProvider wiring. Each task builds on the previous so there is no orphaned code.

## Tasks

- [x] 1. Database migration and Candidate model update
  - [x] 1.1 Create migration to add `notification_preferences` JSON column to `candidates` table
    - Create migration file `add_notification_preferences_to_candidates_table`
    - Add `notification_preferences` column: JSON, nullable, placed after `is_active`
    - _Requirements: 7.1_
  - [x] 1.2 Update Candidate model with `notification_preferences` field
    - Add `'notification_preferences'` to `$fillable` array in `backend/app/Models/Candidate.php`
    - Add `'notification_preferences' => 'array'` to the `casts()` method
    - _Requirements: 7.1, 7.2_

- [x] 2. Notification classes and Markdown mail templates
  - [x] 2.1 Create `StageChangeNotification` class and template
    - Create `backend/app/Notifications/StageChangeNotification.php` implementing `ShouldQueue` with `Queueable` trait, `$tries = 3`, constructor accepting `$jobTitle`, `$stageName`, `$companyName`, `via()` returning `['mail']`, `toMail()` using Markdown template `emails.stage-change`
    - Create `backend/resources/views/emails/stage-change.blade.php` with `@component('mail::message')`, displaying candidate name, job title, stage name, company name, and a preferences URL link
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 2.2_
  - [ ]* 2.2 Write property test for stage change email content (Property 2)
    - **Property 2: Stage change email content completeness**
    - For random job titles, stage names, and company names, verify the rendered StageChangeNotification email contains all three values
    - **Validates: Requirements 1.4, 2.2**
  - [x] 2.3 Create `RejectionNotification` class and template
    - Create `backend/app/Notifications/RejectionNotification.php` implementing `ShouldQueue` with `Queueable` trait, `$tries = 3`, constructor accepting `$jobTitle`, `$companyName`, `via()` returning `['mail']`, `toMail()` using Markdown template `emails.rejection`
    - Create `backend/resources/views/emails/rejection.blade.php` with `@component('mail::message')`, displaying candidate name, job title, company name, and an encouragement message
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 3.2, 3.3_
  - [ ]* 2.4 Write property test for rejection email content (Property 3)
    - **Property 3: Rejection email content completeness**
    - For random job titles and company names, verify the rendered RejectionNotification email contains the job title, company name, and an encouragement message
    - **Validates: Requirements 1.4, 3.2**
  - [x] 2.5 Create `ApplicationConfirmationNotification` class and template
    - Create `backend/app/Notifications/ApplicationConfirmationNotification.php` implementing `ShouldQueue` with `Queueable` trait, `$tries = 3`, constructor accepting `$jobTitle`, `$companyName`, `via()` returning `['mail']`, `toMail()` using Markdown template `emails.application-confirmation`
    - Create `backend/resources/views/emails/application-confirmation.blade.php` with `@component('mail::message')`, displaying candidate name, job title, company name, and a confirmation message
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 4.2_
  - [ ]* 2.6 Write property test for application confirmation email content (Property 6)
    - **Property 6: Application confirmation email content and dispatch**
    - For random job titles, company names, and candidate names, verify the rendered ApplicationConfirmationNotification email contains all expected content
    - **Validates: Requirements 4.1, 4.2**
  - [x] 2.7 Create `UserInviteNotification` class and template
    - Create `backend/app/Notifications/UserInviteNotification.php` implementing `ShouldQueue` with `Queueable` trait, `$tries = 3`, constructor accepting `$userName`, `$email`, `$temporaryPassword`, `$loginUrl`, `via()` returning `['mail']`, `toMail()` using Markdown template `emails.user-invite`
    - Create `backend/resources/views/emails/user-invite.blade.php` with `@component('mail::message')`, displaying user name, email, temporary password, and login URL button
    - _Requirements: 1.1, 1.2, 1.3, 5.2_
  - [ ]* 2.8 Write property test for user invite email content (Property 8)
    - **Property 8: User invite email content completeness**
    - For random user names, emails, passwords, and login URLs, verify the rendered UserInviteNotification email contains all credential fields
    - **Validates: Requirements 5.1, 5.2**
  - [x] 2.9 Create `PasswordResetNotification` class and template
    - Create `backend/app/Notifications/PasswordResetNotification.php` implementing `ShouldQueue` with `Queueable` trait, `$tries = 3`, constructor accepting `$resetUrl`, `$userName`, `via()` returning `['mail']`, `toMail()` using Markdown template `emails.password-reset`
    - Create `backend/resources/views/emails/password-reset.blade.php` with `@component('mail::message')`, displaying user name, reset URL button, and 60-minute expiry message
    - _Requirements: 1.1, 1.2, 1.3, 6.2, 6.3_
  - [ ]* 2.10 Write property test for password reset email content (Property 9)
    - **Property 9: Password reset email content completeness**
    - For random user names and reset tokens, verify the rendered PasswordResetNotification email contains the user name, a reset URL incorporating the token, and a 60-minute expiry message
    - **Validates: Requirements 6.2, 6.3**

- [x] 3. Checkpoint — Verify notification classes and templates
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Event listeners
  - [x] 4.1 Create `StageChangeNotificationListener`
    - Create `backend/app/Listeners/StageChangeNotificationListener.php` implementing `ShouldQueue` with `$tries = 3`
    - In `handle(ApplicationStageChanged $event)`: check `notification_eligible`, load application with candidate and jobPosting.company, check candidate `stage_change_emails` preference, determine target stage name, dispatch `RejectionNotification` if stage is "Rejected" or `StageChangeNotification` otherwise
    - _Requirements: 2.1, 2.3, 2.4, 3.1, 3.4_
  - [ ]* 4.2 Write property test for correct notification type dispatch (Property 1)
    - **Property 1: Correct notification type dispatched based on stage name**
    - For random stage names (including "Rejected"), random candidates with preferences enabled, verify the correct notification type is dispatched
    - **Validates: Requirements 2.1, 3.1**
  - [ ]* 4.3 Write property test for preference gating on stage change emails (Property 4)
    - **Property 4: Preference gating prevents stage change and rejection emails**
    - For random ApplicationStageChanged events with notification_eligible=true and candidate stage_change_emails=false, verify no notifications are dispatched
    - **Validates: Requirements 2.3, 3.4**
  - [ ]* 4.4 Write property test for notification_eligible=false (Property 5)
    - **Property 5: notification_eligible=false prevents all stage-related emails**
    - For random ApplicationStageChanged events with notification_eligible=false, verify no notifications are dispatched regardless of preferences or stage name
    - **Validates: Requirements 2.4**
  - [x] 4.5 Create `ApplicationConfirmationNotificationListener`
    - Create `backend/app/Listeners/ApplicationConfirmationNotificationListener.php` implementing `ShouldQueue` with `$tries = 3`
    - In `handle(CandidateApplied $event)`: load application with candidate and jobPosting.company, check candidate `application_confirmation_emails` preference, dispatch `ApplicationConfirmationNotification`
    - _Requirements: 4.1, 4.3_
  - [ ]* 4.6 Write property test for application confirmation preference gating (Property 7)
    - **Property 7: Preference gating prevents application confirmation emails**
    - For random CandidateApplied events where candidate has application_confirmation_emails=false, verify no ApplicationConfirmationNotification is dispatched
    - **Validates: Requirements 4.3**
  - [x] 4.7 Create `UserInviteNotificationListener`
    - Create `backend/app/Listeners/UserInviteNotificationListener.php` implementing `ShouldQueue` with `$tries = 3`
    - In `handle(UserRegistered $event)`: load user (withoutGlobalScopes), extract temporary password from event data, return early if no password, dispatch `UserInviteNotification` with user name, email, password, and login URL
    - _Requirements: 5.1, 5.3_

- [x] 5. Checkpoint — Verify listeners
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. NotificationPreferenceService and API endpoints
  - [x] 6.1 Create `NotificationPreferenceService`
    - Create `backend/app/Services/NotificationPreferenceService.php`
    - Define `DEFAULT_PREFERENCES` constant with `stage_change_emails => true` and `application_confirmation_emails => true`
    - Define `ALLOWED_KEYS` constant
    - Implement `getPreferences(Candidate $candidate): array` — merges defaults with stored preferences
    - Implement `updatePreferences(Candidate $candidate, array $preferences): array` — validates keys against ALLOWED_KEYS, casts to boolean, saves, returns updated preferences
    - _Requirements: 7.2, 7.3, 7.4, 7.5_
  - [ ]* 6.2 Write property test for preferences round-trip (Property 10)
    - **Property 10: Notification preferences round-trip**
    - For random boolean combinations of preference keys, verify that updating then reading returns the updated values, and keys not included retain previous values
    - **Validates: Requirements 7.3, 7.4**
  - [x] 6.3 Create `UpdateNotificationPreferencesRequest` form request
    - Create `backend/app/Http/Requests/UpdateNotificationPreferencesRequest.php` extending `BaseFormRequest`
    - Validate `stage_change_emails` as `sometimes|boolean` and `application_confirmation_emails` as `sometimes|boolean`
    - _Requirements: 7.5, 7.6_
  - [ ]* 6.4 Write property test for preference validation (Property 11)
    - **Property 11: Notification preferences validation rejects invalid input**
    - For random non-boolean values (strings, integers, arrays, null), verify the validation rejects with 422
    - **Validates: Requirements 7.5, 7.6**
  - [x] 6.5 Create `NotificationPreferenceController`
    - Create `backend/app/Http/Controllers/NotificationPreferenceController.php`
    - Implement `show(Request $request): JsonResponse` — returns `getPreferences()` result wrapped in `{'data': ...}`
    - Implement `update(UpdateNotificationPreferencesRequest $request): JsonResponse` — calls `updatePreferences()` with validated data, returns updated preferences wrapped in `{'data': ...}`
    - _Requirements: 8.1, 8.4, 8.5_
  - [x] 6.6 Register notification preference routes
    - Add `GET /notification-preferences` and `PUT /notification-preferences` routes inside the existing `candidate/profile` middleware group in `backend/routes/api.php`, pointing to `NotificationPreferenceController`
    - _Requirements: 8.1, 8.2, 8.3_
  - [ ]* 6.7 Write unit tests for notification preferences API
    - Test unauthenticated GET returns 401
    - Test unauthenticated PUT returns 401
    - Test authenticated GET returns both preference keys with boolean values
    - Test authenticated PUT updates and returns updated preferences
    - Test default preferences are all enabled for new candidate
    - **Validates: Requirements 8.3, 8.4, 8.5, 7.2**

- [x] 7. Checkpoint — Verify preference service and API
  - Ensure all tests pass, ask the user if questions arise.

- [x] 8. Service modifications and EventServiceProvider wiring
  - [x] 8.1 Modify `AuthService::forgotPassword()` to send `PasswordResetNotification`
    - In `backend/app/Services/AuthService.php`, replace the `Log::info()` call in `forgotPassword()` with `$user->notify(new PasswordResetNotification(...))` using the reset URL built from `config('app.frontend_url')` and the raw token, and the user's name
    - _Requirements: 6.1, 6.4_
  - [x] 8.2 Modify `UserService::create()` to include password in event data
    - In `backend/app/Services/UserService.php`, add `'password' => $data['password']` to the `UserRegistered::dispatch()` data array so the `UserInviteNotificationListener` can include the temporary password in the invite email
    - _Requirements: 5.3_
  - [x] 8.3 Update `EventServiceProvider` with new listener registrations
    - In `backend/app/Providers/EventServiceProvider.php`, add `StageChangeNotificationListener` to `ApplicationStageChanged` listeners, add `ApplicationConfirmationNotificationListener` to `CandidateApplied` listeners (add `CandidateApplied` event entry if not present), add `UserInviteNotificationListener` to `UserRegistered` listeners
    - Import all new listener classes and the `CandidateApplied` event class
    - _Requirements: 2.1, 3.1, 4.1, 5.1_

- [x] 9. Final checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design document
- Unit tests validate specific examples and edge cases
- All notification classes implement `ShouldQueue` for non-blocking delivery
- The design uses PHP (Laravel) throughout — all code examples should use PHP
