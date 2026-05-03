# Requirements Document

## Introduction

The Interview Scheduling feature adds interview management capabilities to the HavenHR platform. Employers can schedule, reschedule, and track interviews for job applicants. Candidates can view their upcoming interviews. The system sends automated email reminders to both interviewers and candidates before scheduled interviews. An upcoming interviews widget on the employer dashboard provides a quick overview of the next 7 days of interviews.

## Glossary

- **Interview_Scheduler**: The backend system responsible for creating, updating, retrieving, and cancelling interview records, and for dispatching reminder notifications.
- **Interview**: A scheduled meeting between an interviewer (User) and a candidate, linked to a specific JobApplication. Has a type (phone, video, in_person), status (scheduled, completed, cancelled, no_show), date/time, duration, location, and optional notes.
- **Interviewer**: A User (employer team member) assigned to conduct an interview.
- **Candidate_Portal**: The candidate-facing frontend that displays interview information to candidates.
- **Employer_Dashboard**: The employer-facing frontend that provides interview management UI and the upcoming interviews widget.
- **Reminder_Service**: The subsystem responsible for sending email reminders before interviews using Laravel Notifications with ShouldQueue.
- **Interview_API**: The set of REST API endpoints for interview CRUD operations.
- **JobApplication**: An existing model representing a candidate's application to a specific job posting.
- **User**: An existing model representing an employer team member within a tenant.

## Requirements

### Requirement 1: Interview Data Model

**User Story:** As a platform developer, I want a structured interview data model, so that interview records are stored consistently and linked to applications and interviewers.

#### Acceptance Criteria

1. THE Interview_Scheduler SHALL store each Interview with the following attributes: id (UUID), job_application_id, interviewer_id, scheduled_at (datetime), duration_minutes (integer), location (string), interview_type (enum: phone, video, in_person), status (enum: scheduled, completed, cancelled, no_show), notes (text), created_at, and updated_at.
2. THE Interview_Scheduler SHALL associate each Interview with exactly one JobApplication via a foreign key relationship.
3. THE Interview_Scheduler SHALL associate each Interview with exactly one User (Interviewer) via a foreign key relationship.
4. WHEN an Interview is created, THE Interview_Scheduler SHALL set the default status to "scheduled".
5. THE Interview_Scheduler SHALL scope all Interview queries to the current tenant by joining through the JobApplication and JobPosting relationships.

### Requirement 2: Schedule an Interview

**User Story:** As an employer, I want to schedule an interview for a candidate, so that I can organize the hiring process.

#### Acceptance Criteria

1. WHEN a valid schedule request is submitted with job_application_id, interviewer_id, scheduled_at, duration_minutes, interview_type, and location, THE Interview_API SHALL create a new Interview record and return the created Interview with HTTP status 201.
2. THE Interview_API SHALL require the "applications.manage" permission to schedule an interview.
3. IF the referenced JobApplication does not belong to the current tenant, THEN THE Interview_API SHALL return an HTTP 404 error with code "NOT_FOUND".
4. IF the referenced Interviewer (User) does not belong to the current tenant, THEN THE Interview_API SHALL return an HTTP 404 error with code "NOT_FOUND".
5. IF any required field (scheduled_at, duration_minutes, interview_type) is missing or invalid, THEN THE Interview_API SHALL return an HTTP 422 error with field-level validation messages.
6. THE Interview_API SHALL accept duration_minutes values of 30, 45, 60, or 90.
7. THE Interview_API SHALL accept interview_type values of "phone", "video", or "in_person".
8. THE Interview_API SHALL accept an optional "notes" text field when scheduling an interview.

### Requirement 3: List Interviews for an Application

**User Story:** As an employer, I want to see all interviews for a specific application, so that I can track the interview history for a candidate.

#### Acceptance Criteria

1. WHEN a list request is made for a specific application, THE Interview_API SHALL return all Interview records associated with that JobApplication, ordered by scheduled_at descending.
2. THE Interview_API SHALL require the "applications.view" permission to list interviews.
3. THE Interview_API SHALL include the interviewer name and email in each Interview response.
4. IF the referenced JobApplication does not belong to the current tenant, THEN THE Interview_API SHALL return an HTTP 404 error with code "NOT_FOUND".

### Requirement 4: Get Interview Detail

**User Story:** As an employer, I want to view the full details of a specific interview, so that I can review all interview information.

#### Acceptance Criteria

1. WHEN a detail request is made for a specific Interview, THE Interview_API SHALL return the full Interview record including interviewer name, interviewer email, candidate name, job title, and all Interview attributes.
2. THE Interview_API SHALL require the "applications.view" permission to view interview details.
3. IF the Interview does not belong to the current tenant, THEN THE Interview_API SHALL return an HTTP 404 error with code "NOT_FOUND".

### Requirement 5: Update an Interview

**User Story:** As an employer, I want to update interview details such as date/time, location, or interviewer, so that I can reschedule or modify interviews as needed.

#### Acceptance Criteria

1. WHEN a valid update request is submitted, THE Interview_API SHALL update the specified Interview fields and return the updated Interview record.
2. THE Interview_API SHALL require the "applications.manage" permission to update an interview.
3. THE Interview_API SHALL allow updating the following fields: scheduled_at, duration_minutes, interview_type, location, interviewer_id, notes, and status.
4. IF the Interview does not belong to the current tenant, THEN THE Interview_API SHALL return an HTTP 404 error with code "NOT_FOUND".
5. IF any provided field value is invalid, THEN THE Interview_API SHALL return an HTTP 422 error with field-level validation messages.

### Requirement 6: Cancel an Interview

**User Story:** As an employer, I want to cancel a scheduled interview, so that I can remove interviews that are no longer needed.

#### Acceptance Criteria

1. WHEN a cancel request is made for a specific Interview, THE Interview_API SHALL set the Interview status to "cancelled" and return the updated Interview record.
2. THE Interview_API SHALL require the "applications.manage" permission to cancel an interview.
3. IF the Interview does not belong to the current tenant, THEN THE Interview_API SHALL return an HTTP 404 error with code "NOT_FOUND".
4. IF the Interview status is already "cancelled", THEN THE Interview_API SHALL return an HTTP 422 error with message "Interview is already cancelled".

### Requirement 7: Employer Interview Management UI

**User Story:** As an employer, I want a user interface to schedule and manage interviews, so that I can efficiently coordinate the interview process.

#### Acceptance Criteria

1. THE Employer_Dashboard SHALL display a "Schedule Interview" action within the candidate detail panel (SlideOverPanel) for each application.
2. WHEN the "Schedule Interview" action is triggered, THE Employer_Dashboard SHALL display a form with fields for date/time picker, duration (30, 45, 60, 90 minutes), interviewer dropdown (populated from tenant team members), interview type (phone, video, in-person), location, and notes.
3. THE Employer_Dashboard SHALL display a list of all interviews on the application detail view, showing interviewer name, date/time, duration, type, status, and location.
4. THE Employer_Dashboard SHALL provide actions to mark an interview as completed, cancelled, or no-show from the interview list.
5. THE Employer_Dashboard SHALL display a confirmation dialog before cancelling an interview.

### Requirement 8: Candidate Interview View

**User Story:** As a candidate, I want to see my upcoming interviews, so that I can prepare and attend them on time.

#### Acceptance Criteria

1. THE Candidate_Portal SHALL display a list of upcoming interviews (status "scheduled" and scheduled_at in the future) on the candidate applications dashboard.
2. THE Candidate_Portal SHALL display the following details for each interview: date/time, interview type, location, and interviewer name.
3. THE Candidate_Portal SHALL display the associated job title for each interview.
4. THE Candidate_Portal SHALL order upcoming interviews by scheduled_at ascending (nearest first).
5. THE Candidate_Portal SHALL provide a read-only view with no ability to create, update, or cancel interviews.

### Requirement 9: Candidate Interview API

**User Story:** As a candidate, I want an API endpoint to retrieve my interview information, so that the candidate portal can display my interviews.

#### Acceptance Criteria

1. WHEN a candidate requests their interviews, THE Interview_API SHALL return all Interview records associated with the authenticated candidate's JobApplications.
2. THE Interview_API SHALL require the "candidate.auth" middleware to access candidate interview endpoints.
3. THE Interview_API SHALL include the job title, interview type, location, interviewer name, scheduled_at, and duration_minutes in each response item.
4. THE Interview_API SHALL exclude the notes field from candidate-facing responses.

### Requirement 10: Interview Reminder Notifications

**User Story:** As a platform user, I want automated email reminders before interviews, so that participants are prepared and attend on time.

#### Acceptance Criteria

1. THE Reminder_Service SHALL send an email reminder to the Candidate 24 hours before the scheduled interview time.
2. THE Reminder_Service SHALL send an email reminder to the Interviewer 1 hour before the scheduled interview time.
3. THE Reminder_Service SHALL include the interview date/time, type, location, and candidate name (for interviewer reminders) or job title (for candidate reminders) in the reminder email.
4. THE Reminder_Service SHALL use the existing Laravel Notifications system with the ShouldQueue interface for asynchronous delivery.
5. IF the Interview status is "cancelled" at the time the reminder is dispatched, THEN THE Reminder_Service SHALL skip sending the reminder.
6. WHILE a candidate has notification preferences with "interview_reminders" set to false, THE Reminder_Service SHALL skip sending interview reminders to that Candidate.

### Requirement 11: Upcoming Interviews Dashboard Widget

**User Story:** As an employer, I want to see upcoming interviews on my dashboard, so that I can quickly review the interview schedule for the week.

#### Acceptance Criteria

1. THE Employer_Dashboard SHALL display an "Upcoming Interviews" widget on the dashboard home page showing interviews scheduled within the next 7 days.
2. THE Employer_Dashboard SHALL display the candidate name, job title, date/time, and interview type for each upcoming interview in the widget.
3. THE Employer_Dashboard SHALL order upcoming interviews by scheduled_at ascending (nearest first).
4. THE Employer_Dashboard SHALL limit the widget to a maximum of 10 interviews.
5. WHEN no interviews are scheduled within the next 7 days, THE Employer_Dashboard SHALL display a message indicating no upcoming interviews.

### Requirement 12: Upcoming Interviews API

**User Story:** As a platform developer, I want an API endpoint for upcoming interviews, so that the dashboard widget can retrieve the data.

#### Acceptance Criteria

1. WHEN a request is made to the upcoming interviews endpoint, THE Interview_API SHALL return Interview records with status "scheduled" and scheduled_at within the next 7 days, scoped to the current tenant.
2. THE Interview_API SHALL include candidate name, job title, scheduled_at, duration_minutes, interview_type, and location in each response item.
3. THE Interview_API SHALL order results by scheduled_at ascending and limit to 10 records.
4. THE Interview_API SHALL require the "havenhr.auth" middleware and tenant scope to access the upcoming interviews endpoint.
