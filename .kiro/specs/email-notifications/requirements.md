# Requirements Document

## Introduction

The Email Notifications feature adds transactional email capabilities to the HavenHR platform. The system sends automated emails to candidates and users in response to key events: application stage changes, application confirmations, user invitations, and password resets. Candidates can manage their notification preferences to opt in or out of specific email types. The system uses Laravel's built-in Notification framework with Markdown mail templates and supports queued delivery for non-blocking operation.

## Glossary

- **Notification_System**: The Laravel-based email notification infrastructure responsible for constructing, queuing, and dispatching email notifications via configured mail transport.
- **Stage_Change_Notifier**: The component that listens to ApplicationStageChanged events and sends stage change emails to candidates when eligible.
- **Rejection_Notifier**: The component that listens to ApplicationStageChanged events where the target stage is "Rejected" and sends a professional rejection email to the candidate.
- **Application_Confirmation_Notifier**: The component that listens to CandidateApplied events and sends application confirmation emails to candidates.
- **User_Invite_Notifier**: The component that listens to UserRegistered events and sends credential emails to newly created users.
- **Password_Reset_Notifier**: The component that sends password reset emails containing a secure reset link when a user requests a password reset.
- **Preference_Service**: The component responsible for reading and updating candidate notification preferences stored as JSON in the candidates table.
- **Candidate**: A job applicant registered on the platform who has an email address and may have notification preferences.
- **User**: An employer-side platform user belonging to a tenant, who has an email address.
- **Pipeline_Stage**: A named step in a job posting's hiring pipeline (e.g., Applied, Screening, Interview, Offer, Hired, Rejected).
- **Notification_Preferences**: A JSON object stored on the Candidate record that controls which email notification types the candidate receives. Defaults to all enabled.

## Requirements

### Requirement 1: Notification Infrastructure

**User Story:** As a platform operator, I want a centralized email notification infrastructure, so that all transactional emails are sent consistently, queued for non-blocking delivery, and use professional templates.

#### Acceptance Criteria

1. THE Notification_System SHALL use Laravel Notification classes with the mail channel for all transactional emails.
2. THE Notification_System SHALL implement the ShouldQueue interface on all Notification classes so that email delivery is processed asynchronously via the configured queue connection.
3. THE Notification_System SHALL use Laravel Markdown mail templates for all email content.
4. WHEN a notification is dispatched, THE Notification_System SHALL include the company name in the email header.
5. WHEN a notification is dispatched, THE Notification_System SHALL include a consistent footer containing a link to notification preferences.
6. THE Notification_System SHALL render mobile-responsive HTML emails using Laravel's built-in Markdown mail CSS.
7. IF a notification fails to send after 3 attempts, THEN THE Notification_System SHALL log the failure with the notification type, recipient email, and error message.

### Requirement 2: Candidate Stage Change Notification

**User Story:** As a candidate, I want to receive an email when my application moves to a new pipeline stage, so that I stay informed about my application progress.

#### Acceptance Criteria

1. WHEN an ApplicationStageChanged event fires with notification_eligible set to true and the target stage name is not "Rejected", THE Stage_Change_Notifier SHALL send a stage change email to the Candidate associated with the application.
2. WHEN a stage change email is sent, THE Stage_Change_Notifier SHALL include the job posting title, the new stage name, and the company name in the email body.
3. WHILE the Candidate has stage_change_emails disabled in Notification_Preferences, THE Stage_Change_Notifier SHALL skip sending the stage change email.
4. WHEN an ApplicationStageChanged event fires with notification_eligible set to false, THE Stage_Change_Notifier SHALL skip sending the stage change email.

### Requirement 3: Candidate Rejection Notification

**User Story:** As a candidate, I want to receive a professional rejection email when my application is rejected, so that I have closure and can move forward.

#### Acceptance Criteria

1. WHEN an ApplicationStageChanged event fires with notification_eligible set to true and the target stage name is "Rejected", THE Rejection_Notifier SHALL send a rejection email to the Candidate associated with the application.
2. WHEN a rejection email is sent, THE Rejection_Notifier SHALL include the job posting title, the company name, and an encouragement message in the email body.
3. THE Rejection_Notifier SHALL use a separate email template from the stage change notification template.
4. WHILE the Candidate has stage_change_emails disabled in Notification_Preferences, THE Rejection_Notifier SHALL skip sending the rejection email.

### Requirement 4: Application Confirmation Email

**User Story:** As a candidate, I want to receive a confirmation email when I apply to a job, so that I know my application was received.

#### Acceptance Criteria

1. WHEN a CandidateApplied event fires, THE Application_Confirmation_Notifier SHALL send a confirmation email to the Candidate who submitted the application.
2. WHEN a confirmation email is sent, THE Application_Confirmation_Notifier SHALL include the job posting title, the company name, and a confirmation message in the email body.
3. WHILE the Candidate has application_confirmation_emails disabled in Notification_Preferences, THE Application_Confirmation_Notifier SHALL skip sending the confirmation email.

### Requirement 5: User Invite Email

**User Story:** As a platform administrator, I want newly invited users to receive an email with their credentials, so that they can log in to the platform.

#### Acceptance Criteria

1. WHEN a UserRegistered event fires from the user creation flow, THE User_Invite_Notifier SHALL send an invite email to the newly created User.
2. WHEN an invite email is sent, THE User_Invite_Notifier SHALL include the user name, email address, temporary password, and a login URL in the email body.
3. THE User_Invite_Notifier SHALL retrieve the temporary password from the UserRegistered event data payload.

### Requirement 6: Password Reset Email

**User Story:** As a user, I want to receive a password reset email with a secure link when I request a password reset, so that I can regain access to my account.

#### Acceptance Criteria

1. WHEN a user requests a password reset and the email matches an existing User, THE Password_Reset_Notifier SHALL send a password reset email to that User.
2. WHEN a password reset email is sent, THE Password_Reset_Notifier SHALL include a secure reset link containing the raw reset token and the configured frontend URL.
3. THE Password_Reset_Notifier SHALL include the user name and a message indicating the link expires in 60 minutes in the email body.
4. WHEN a user requests a password reset and the email does not match an existing User, THE Password_Reset_Notifier SHALL not send any email to prevent email enumeration.

### Requirement 7: Candidate Notification Preferences

**User Story:** As a candidate, I want to manage my email notification preferences, so that I only receive emails I find useful.

#### Acceptance Criteria

1. THE Preference_Service SHALL store notification preferences as a JSON column named notification_preferences on the candidates table.
2. WHEN a new Candidate is created, THE Preference_Service SHALL default all notification preferences to enabled (stage_change_emails: true, application_confirmation_emails: true).
3. WHEN a Candidate sends a GET request to the notification preferences endpoint, THE Preference_Service SHALL return the current notification preferences for that Candidate.
4. WHEN a Candidate sends a PUT request to the notification preferences endpoint with valid preference values, THE Preference_Service SHALL update the notification preferences for that Candidate.
5. THE Preference_Service SHALL validate that preference values are boolean before persisting changes.
6. IF a Candidate sends a PUT request with invalid preference keys or non-boolean values, THEN THE Preference_Service SHALL return a 422 validation error with descriptive messages.

### Requirement 8: Notification Preferences API

**User Story:** As a frontend developer, I want API endpoints for reading and updating candidate notification preferences, so that I can build a preferences UI.

#### Acceptance Criteria

1. THE Notification_System SHALL expose a GET endpoint at /api/v1/candidate/profile/notification-preferences that returns the authenticated Candidate's notification preferences.
2. THE Notification_System SHALL expose a PUT endpoint at /api/v1/candidate/profile/notification-preferences that accepts a JSON body with preference keys and boolean values.
3. WHEN an unauthenticated request is made to either notification preferences endpoint, THE Notification_System SHALL return a 401 Unauthorized response.
4. WHEN a successful GET request is made, THE Notification_System SHALL return a JSON response containing stage_change_emails and application_confirmation_emails boolean values.
5. WHEN a successful PUT request is made, THE Notification_System SHALL return the updated notification preferences in the response body.
