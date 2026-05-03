# Requirements Document

## Introduction

The Candidate Portal Enhancement feature improves the candidate-facing experience in HavenHR by adding an application tracking dashboard, enhanced profile fields, notification preference management UI, improved navigation, and a detailed application view. The existing candidate portal supports authentication, profile management, resume building, job applications, and a public job board. This feature builds on those foundations to give candidates better visibility into their application status and a more complete, navigable portal experience.

## Glossary

- **Candidate_Portal**: The authenticated candidate-facing section of HavenHR, accessible at `/candidate/*`, providing profile management, resume building, job applications, and settings
- **Application_Tracking_Dashboard**: A page displaying all of a candidate's job applications with current status, pipeline stage, and filtering capabilities
- **Application_Detail_View**: A page showing full details for a single job application including job posting info, current stage, stage transition history, and submitted resume snapshot
- **Profile_Completeness_Indicator**: A visual percentage bar showing how complete a candidate's profile is based on filled-in fields
- **Notification_Preferences_UI**: A settings page with toggle switches allowing candidates to manage email notification preferences via the existing API
- **Candidate_Navigation**: The top navigation bar component (`CandidateNav`) providing links to all candidate portal sections with active state indicators and mobile-responsive hamburger menu
- **Pipeline_Stage**: A named step in a job posting's hiring pipeline (e.g., Applied, Screening, Interview) that an application moves through
- **Stage_Transition**: A record of an application moving from one pipeline stage to another, including the date of the move
- **Resume_Snapshot**: A JSON copy of the resume content captured at the time of application submission
- **Candidate_API**: The backend REST API prefixed at `/api/v1/candidate/` using JWT-based `candidate.auth` middleware

## Requirements

### Requirement 1: Application Tracking Dashboard

**User Story:** As a candidate, I want to see all my job applications with their current status on a dashboard, so that I can track where each application stands in the hiring process.

#### Acceptance Criteria

1. WHEN a candidate navigates to the Application Tracking Dashboard, THE Candidate_Portal SHALL display a list of all job applications belonging to the authenticated candidate, ordered by applied date descending.
2. THE Application_Tracking_Dashboard SHALL display for each application: the job title, company name, current pipeline stage name, application status, and applied date.
3. THE Application_Tracking_Dashboard SHALL display a visual pipeline indicator for each application showing the current stage relative to all stages in that job's pipeline.
4. WHEN a candidate selects a status filter, THE Application_Tracking_Dashboard SHALL display only applications matching the selected status value.
5. WHEN a candidate selects a sort option, THE Application_Tracking_Dashboard SHALL reorder the displayed applications by the selected field (applied date or job title) in the selected direction (ascending or descending).
6. WHEN a candidate has zero applications, THE Application_Tracking_Dashboard SHALL display an empty state message with a link to the public job board.
7. WHILE the application list is loading from the Candidate_API, THE Application_Tracking_Dashboard SHALL display a loading indicator.
8. IF the Candidate_API returns an error when fetching applications, THEN THE Application_Tracking_Dashboard SHALL display an error message describing the failure.

### Requirement 2: Application Detail View

**User Story:** As a candidate, I want to click on an application to see its full details, so that I can review the job information, my current stage, the stage transition history, and the resume I submitted.

#### Acceptance Criteria

1. WHEN a candidate clicks an application in the Application Tracking Dashboard, THE Candidate_Portal SHALL navigate to the Application Detail View for that application.
2. THE Application_Detail_View SHALL display the job posting information: title, company name, location, and employment type.
3. THE Application_Detail_View SHALL display the current pipeline stage name with a visual stage indicator showing progress through the pipeline.
4. THE Application_Detail_View SHALL display the applied date formatted in a human-readable format.
5. WHEN the Candidate_API returns stage transition history for an application, THE Application_Detail_View SHALL display a timeline of stage transitions with the stage name and transition date for each entry.
6. THE Application_Detail_View SHALL display the resume snapshot content that was submitted with the application.
7. WHEN a candidate requests a single application detail, THE Candidate_API SHALL return the application record with job posting details, current pipeline stage, all pipeline stages for the job, stage transition history, and resume snapshot.
8. IF the requested application does not belong to the authenticated candidate, THEN THE Candidate_API SHALL return a 404 Not Found response.
9. IF the requested application does not exist, THEN THE Candidate_API SHALL return a 404 Not Found response.
10. WHILE the application detail is loading from the Candidate_API, THE Application_Detail_View SHALL display a loading indicator.

### Requirement 3: Enhanced Candidate Profile

**User Story:** As a candidate, I want to add a professional summary, social links, and see how complete my profile is, so that I can present a more comprehensive professional identity to employers.

#### Acceptance Criteria

1. THE Candidate_Portal profile page SHALL display a Profile Completeness Indicator showing the percentage of profile fields that are filled in.
2. THE Profile_Completeness_Indicator SHALL calculate completeness based on the following fields: name, phone, location, professional summary, LinkedIn URL, GitHub URL, portfolio URL, at least one work history entry, at least one education entry, and at least one skill.
3. WHEN a candidate updates any profile field, THE Profile_Completeness_Indicator SHALL recalculate and display the updated percentage.
4. THE Candidate_Portal profile page SHALL include an avatar placeholder section displaying the first letter of the candidate's name in a styled circle.
5. THE Candidate_Portal profile page SHALL include editable fields for GitHub URL and professional summary in the personal information section.
6. WHEN a candidate saves a professional summary, THE Candidate_API SHALL persist the summary value to the candidate record.
7. WHEN a candidate saves a GitHub URL, THE Candidate_API SHALL persist the github_url value to the candidate record.
8. THE Candidate_Portal profile page SHALL include a profile visibility toggle allowing the candidate to set the profile as public or private.
9. WHEN a candidate toggles profile visibility, THE Candidate_API SHALL persist the is_profile_public boolean value to the candidate record.
10. IF a candidate submits a GitHub URL that is not a valid URL format, THEN THE Candidate_API SHALL return a 422 validation error with a descriptive message.

### Requirement 4: Notification Preferences UI

**User Story:** As a candidate, I want to manage my email notification preferences from a settings page, so that I can control which emails I receive about my applications.

#### Acceptance Criteria

1. WHEN a candidate navigates to the settings page, THE Notification_Preferences_UI SHALL fetch the current notification preferences from the Candidate_API endpoint `GET /candidate/profile/notification-preferences`.
2. THE Notification_Preferences_UI SHALL display a toggle switch for stage change emails, reflecting the current `stage_change_emails` preference value.
3. THE Notification_Preferences_UI SHALL display a toggle switch for application confirmation emails, reflecting the current `application_confirmation_emails` preference value.
4. WHEN a candidate toggles a notification preference switch, THE Notification_Preferences_UI SHALL send the updated preferences to the Candidate_API endpoint `PUT /candidate/profile/notification-preferences`.
5. WHEN the Candidate_API confirms the preference update, THE Notification_Preferences_UI SHALL display a success confirmation message.
6. IF the Candidate_API returns an error when updating preferences, THEN THE Notification_Preferences_UI SHALL display an error message and revert the toggle to its previous state.
7. WHILE notification preferences are loading from the Candidate_API, THE Notification_Preferences_UI SHALL display a loading indicator.

### Requirement 5: Improved Candidate Navigation

**User Story:** As a candidate, I want clear, consistent navigation across the candidate portal, so that I can easily access all sections of the portal.

#### Acceptance Criteria

1. THE Candidate_Navigation SHALL include navigation items for: Dashboard, My Applications, Profile, Resumes, Job Board, and Settings.
2. WHEN a candidate is on a page, THE Candidate_Navigation SHALL visually highlight the navigation item corresponding to the current page using an active state indicator.
3. WHILE the viewport width is below the medium breakpoint (768px), THE Candidate_Navigation SHALL collapse navigation items into a hamburger menu.
4. WHEN a candidate activates the hamburger menu button, THE Candidate_Navigation SHALL expand to show all navigation items in a vertical list.
5. WHEN a candidate presses the Escape key while the mobile menu is open, THE Candidate_Navigation SHALL close the mobile menu.
6. THE Candidate_Navigation SHALL include the candidate's name and an avatar placeholder in the user section.
7. THE Candidate_Navigation SHALL include a sign-out button that triggers the candidate logout flow.

### Requirement 6: Backend API Extensions

**User Story:** As a developer, I want the candidate API to support application detail retrieval and enhanced profile fields, so that the frontend can display application details and extended profile information.

#### Acceptance Criteria

1. WHEN a candidate requests `GET /candidate/applications/{id}`, THE Candidate_API SHALL return the application with: id, job_posting_id, resume_id, status, applied_at, job title, company name, location, employment type, current pipeline stage (name and color), all pipeline stages for the job (name, color, sort_order), stage transition history (from_stage name, to_stage name, moved_at), and resume snapshot.
2. WHEN a candidate requests `GET /candidate/applications` with a `status` query parameter, THE Candidate_API SHALL return only applications matching the specified status value.
3. WHEN a candidate requests `GET /candidate/applications` with `sort_by` and `sort_dir` query parameters, THE Candidate_API SHALL return applications sorted by the specified field in the specified direction.
4. THE Candidate_API SHALL accept `professional_summary`, `github_url`, and `is_profile_public` fields in the `PUT /candidate/profile` endpoint.
5. THE Candidate_API SHALL validate that `github_url` is a valid URL format when provided.
6. THE Candidate_API SHALL validate that `professional_summary` does not exceed 2000 characters when provided.
7. THE Candidate_API SHALL validate that `is_profile_public` is a boolean value when provided.
8. THE Candidate_API SHALL include `professional_summary`, `github_url`, and `is_profile_public` in the `GET /candidate/profile` response.
