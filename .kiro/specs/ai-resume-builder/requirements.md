# Requirements Document

## Introduction

The AI Resume Builder extends HavenHR from an employer-only hiring platform into a two-sided marketplace by introducing a candidate-facing experience. Job seekers can register as platform-level Candidates (independent of any Tenant), build AI-powered resumes through a step-by-step wizard, manage multiple resume versions, and apply to jobs posted by any company on the platform. The AI layer leverages OpenAI GPT-4 to generate professional summaries, work experience bullet points, skill suggestions, ATS keyword optimization, and content improvement. Resumes are exportable to PDF and shareable via public links. When a Candidate applies to a job, the resume and profile data flow into the employer's existing applicant tracking and talent pool features.

This specification builds on top of the existing HavenHR multi-tenant foundation (authentication, RBAC, tenant isolation, event-driven architecture) and reuses the same tech stack: Next.js (App Router) with Tailwind CSS on the frontend, Laravel REST API on the backend, PostgreSQL for persistence, and Redis for caching and queues.

## Glossary

- **Candidate**: A job seeker who registers on the HavenHR platform independently of any Tenant. A Candidate is a platform-level user with no Tenant_ID association.
- **Candidate_Profile**: The structured data record containing a Candidate's personal information, work history, education, and skills.
- **Resume**: A formatted document created by a Candidate using the Resume_Builder, containing selected sections of the Candidate_Profile along with AI-enhanced content.
- **Resume_Version**: A point-in-time snapshot of a Resume, stored for history tracking and rollback.
- **Resume_Template**: A predefined visual layout (clean, modern, professional, or creative) applied to a Resume for rendering and PDF export.
- **Resume_Builder**: The step-by-step wizard interface that guides a Candidate through creating or editing a Resume.
- **AI_Service**: The backend service responsible for communicating with the OpenAI API to generate, enhance, and optimize resume content.
- **AI_Job**: A queued background task that processes an AI content generation or enhancement request asynchronously.
- **ATS_Optimizer**: The AI_Service function that analyzes a job description and suggests keywords for a Candidate to include in a Resume to improve Applicant Tracking System compatibility.
- **PDF_Exporter**: The service responsible for rendering a Resume with a selected Resume_Template into a downloadable PDF file.
- **Public_Link**: A shareable URL that allows anyone with the link to view a read-only version of a Resume without authentication.
- **Job_Application**: A record linking a Candidate to a specific job posting within a Tenant, including the Resume used for the application.
- **Talent_Pool**: The collection of Candidates who have applied to jobs within a Tenant, visible to authorized employer users.
- **Auth_Service**: The existing backend authentication service, extended to support Candidate registration and login.
- **Event_Bus**: The existing Redis-backed Laravel queue system used for asynchronous event processing.
- **Input_Validator**: The existing middleware and service layer responsible for validating and sanitizing all incoming request data.
- **Rate_Limiter**: The existing middleware that restricts the number of requests a client can make within a defined time window.

## Requirements

### Requirement 1: Candidate Registration

**User Story:** As a job seeker, I want to create an account on HavenHR without needing a company invitation, so that I can access the resume builder and apply to jobs.

#### Acceptance Criteria

1. WHEN a valid registration request containing name, email, and password is submitted to the Candidate registration endpoint, THE Auth_Service SHALL create a new Candidate record with a unique ID, no Tenant_ID association, and return a confirmation response with authentication tokens.
2. WHEN a Candidate registration request contains an email that is already associated with an existing Candidate account, THE Auth_Service SHALL reject the request and return an error indicating the email is already registered.
3. WHEN a Candidate registration request contains an email that is already associated with a Tenant User account, THE Auth_Service SHALL allow the registration, creating a separate Candidate record, because Candidate accounts and Tenant User accounts are independent identity types.
4. THE Input_Validator SHALL enforce the same password complexity rules for Candidate registration as for Tenant User registration: minimum 12 characters, at least one uppercase letter, one lowercase letter, one digit, and one special character.
5. WHEN a Candidate is successfully registered, THE Event_Bus SHALL publish a "candidate.registered" event containing the Candidate ID and a timestamp.
6. IF a Candidate registration request contains invalid or missing required fields, THEN THE Input_Validator SHALL reject the request and return a 422 response listing each invalid field with a specific validation error message.

---

### Requirement 2: Candidate Authentication

**User Story:** As a registered Candidate, I want to log in and manage my session securely, so that I can access my resumes and profile.

#### Acceptance Criteria

1. WHEN a Candidate submits valid email and password credentials to the Candidate login endpoint, THE Auth_Service SHALL verify the credentials, generate an Access_Token with a 15-minute expiration containing the Candidate ID and a "candidate" role claim (with no Tenant_ID claim), and a Refresh_Token with a 7-day expiration.
2. WHEN a Candidate submits incorrect credentials, THE Auth_Service SHALL return a generic "Invalid credentials" error without revealing whether the email or password was incorrect.
3. WHEN a Candidate submits a logout request with a valid Access_Token, THE Auth_Service SHALL invalidate the associated Refresh_Token and add the Access_Token to the Redis blocklist until the Access_Token expiration time.
4. THE Auth_Service SHALL support token refresh for Candidate sessions using the same refresh flow as Tenant User sessions, including replay detection and full revocation on reuse of an invalidated Refresh_Token.
5. THE Rate_Limiter SHALL enforce a limit of 5 requests per minute per IP address for Candidate authentication endpoints.

---

### Requirement 3: Candidate Profile Management

**User Story:** As a Candidate, I want to maintain a comprehensive profile with my personal information, work history, education, and skills, so that I can use this data across multiple resumes.

#### Acceptance Criteria

1. WHEN an authenticated Candidate submits a profile update request with personal information (full name, email, phone number, location, LinkedIn URL, portfolio URL), THE Candidate_Profile service SHALL update the Candidate_Profile record and return the updated profile.
2. WHEN an authenticated Candidate submits a work history entry containing job title, company name, start date, end date (nullable for current positions), and description, THE Candidate_Profile service SHALL add the entry to the Candidate_Profile work history collection.
3. WHEN an authenticated Candidate submits an education entry containing institution name, degree, field of study, start date, and end date, THE Candidate_Profile service SHALL add the entry to the Candidate_Profile education collection.
4. WHEN an authenticated Candidate submits a skills update request with a list of skill names, THE Candidate_Profile service SHALL replace the Candidate_Profile skills collection with the provided list.
5. THE Candidate_Profile service SHALL allow a Candidate to update, reorder, and delete individual work history and education entries.
6. WHEN a Candidate requests their profile, THE Candidate_Profile service SHALL return the complete profile including personal information, all work history entries ordered by start date descending, all education entries ordered by start date descending, and all skills.

---

### Requirement 4: Resume Creation Wizard

**User Story:** As a Candidate, I want a guided step-by-step wizard to build my resume, so that I can create a professional resume without needing design or writing expertise.

#### Acceptance Criteria

1. THE Resume_Builder SHALL present the following steps in order: (1) select or create a Resume_Template, (2) personal information, (3) professional summary, (4) work experience, (5) education, (6) skills, (7) review and finalize.
2. WHEN a Candidate begins a new resume, THE Resume_Builder SHALL pre-populate form fields with data from the Candidate_Profile where available.
3. THE Resume_Builder SHALL allow a Candidate to navigate forward and backward between wizard steps without losing entered data.
4. THE Resume_Builder SHALL auto-save the resume draft after each step completion, storing the current state so the Candidate can resume later.
5. WHEN a Candidate completes the final review step, THE Resume_Builder SHALL save the Resume as a completed version and create an initial Resume_Version snapshot.
6. THE Resume_Builder SHALL display a real-time preview panel alongside the editing form that updates as the Candidate modifies content, reflecting the selected Resume_Template layout.
7. THE Frontend SHALL render the Resume_Builder with a mobile-first responsive layout, collapsing the preview panel below the form on viewports narrower than 768px.

---

### Requirement 5: AI Content Generation — Professional Summary

**User Story:** As a Candidate, I want AI to generate a professional summary based on my job title and experience, so that I can present myself effectively without struggling to write about myself.

#### Acceptance Criteria

1. WHEN a Candidate provides a target job title and years of experience and requests a professional summary, THE AI_Service SHALL submit an AI_Job to the queue and return a job identifier to the client.
2. WHEN the AI_Job is processed, THE AI_Service SHALL call the OpenAI API with the Candidate's job title, years of experience, and optionally their existing work history to generate a professional summary of 3 to 5 sentences.
3. WHEN the AI_Job completes successfully, THE AI_Service SHALL store the generated summary and make it available for retrieval by the Candidate using the job identifier.
4. THE AI_Service SHALL generate summaries that are written in third person, use active voice, and contain no placeholder text or generic filler phrases.
5. IF the OpenAI API returns an error or times out, THEN THE AI_Service SHALL retry the request up to 2 additional times with exponential backoff, and if all retries fail, mark the AI_Job as failed with a descriptive error message.

---

### Requirement 6: AI Content Generation — Work Experience Bullet Points

**User Story:** As a Candidate, I want AI to suggest impactful bullet points for my work experience, so that I can describe my accomplishments in a way that resonates with hiring managers.

#### Acceptance Criteria

1. WHEN a Candidate provides a job title, company name, and a brief description of responsibilities and requests bullet point suggestions, THE AI_Service SHALL submit an AI_Job to the queue and return a job identifier.
2. WHEN the AI_Job is processed, THE AI_Service SHALL call the OpenAI API to generate 4 to 6 bullet points that follow the "accomplished X by doing Y, resulting in Z" format where applicable.
3. WHEN the AI_Job completes successfully, THE AI_Service SHALL store the generated bullet points and make them available for retrieval by the Candidate using the job identifier.
4. THE AI_Service SHALL generate bullet points that begin with strong action verbs and include quantifiable results where the input data supports quantification.
5. THE Candidate SHALL be able to accept, reject, or edit individual generated bullet points before adding them to the Resume.

---

### Requirement 7: AI Content Generation — Skills Suggestion

**User Story:** As a Candidate, I want AI to suggest relevant skills based on my target job title and industry, so that I include the right skills on my resume.

#### Acceptance Criteria

1. WHEN a Candidate provides a target job title and optionally an industry, THE AI_Service SHALL call the OpenAI API to generate a list of 10 to 15 relevant skills categorized as technical skills and soft skills.
2. WHEN the AI_Service returns skill suggestions, THE Resume_Builder SHALL display the suggestions as selectable items that the Candidate can add to or remove from the Resume skills section.
3. THE AI_Service SHALL not suggest skills that the Candidate has already added to the Resume.
4. IF the OpenAI API returns an error or times out, THEN THE AI_Service SHALL retry the request up to 2 additional times with exponential backoff, and if all retries fail, return a descriptive error to the Candidate.

---

### Requirement 8: AI Content Enhancement — ATS Keyword Optimization

**User Story:** As a Candidate, I want to optimize my resume for Applicant Tracking Systems by analyzing a job description, so that my resume passes automated screening.

#### Acceptance Criteria

1. WHEN a Candidate pastes a job description and requests ATS optimization, THE ATS_Optimizer SHALL submit an AI_Job to the queue and return a job identifier.
2. WHEN the AI_Job is processed, THE ATS_Optimizer SHALL call the OpenAI API to extract key skills, qualifications, and keywords from the job description and compare them against the Candidate's current Resume content.
3. WHEN the AI_Job completes successfully, THE ATS_Optimizer SHALL return a list of missing keywords with suggestions on where to incorporate them in the Resume, and a list of keywords already present in the Resume.
4. THE ATS_Optimizer SHALL categorize suggested keywords into groups: required skills, preferred skills, and industry terminology.
5. THE Resume_Builder SHALL display ATS optimization results in a panel that highlights missing keywords and allows the Candidate to click a keyword to navigate to the relevant Resume section for editing.

---

### Requirement 9: AI Content Enhancement — Content Improvement

**User Story:** As a Candidate, I want AI to improve my existing resume text to make it more impactful and professional, so that I can polish my resume without hiring a professional writer.

#### Acceptance Criteria

1. WHEN a Candidate selects a block of existing resume text and requests improvement, THE AI_Service SHALL submit an AI_Job to the queue and return a job identifier.
2. WHEN the AI_Job is processed, THE AI_Service SHALL call the OpenAI API to rewrite the text to be more concise, impactful, and professional while preserving the original meaning and factual content.
3. WHEN the AI_Job completes successfully, THE AI_Service SHALL return the improved text alongside the original text so the Candidate can compare them.
4. THE Resume_Builder SHALL display the original and improved text side by side and allow the Candidate to accept the improvement, reject it, or further edit the improved version.
5. THE AI_Service SHALL check the text for grammar errors and inconsistent tone and include corrections in the improved version.

---

### Requirement 10: Resume Templates

**User Story:** As a Candidate, I want to choose from multiple professional resume templates, so that my resume has a polished visual presentation suited to my industry.

#### Acceptance Criteria

1. THE Resume_Builder SHALL offer at least four Resume_Template options: clean, modern, professional, and creative.
2. WHEN a Candidate selects a Resume_Template, THE Resume_Builder SHALL apply the template layout and styling to the real-time preview within 1 second.
3. THE Resume_Builder SHALL allow a Candidate to switch Resume_Templates at any point during the resume creation or editing process without losing content.
4. EACH Resume_Template SHALL render all standard resume sections: personal information header, professional summary, work experience, education, and skills.
5. THE PDF_Exporter SHALL render the Resume using the selected Resume_Template with consistent styling between the on-screen preview and the exported PDF.

---

### Requirement 11: Resume PDF Export

**User Story:** As a Candidate, I want to export my resume as a PDF, so that I can download it and use it outside of HavenHR.

#### Acceptance Criteria

1. WHEN a Candidate requests a PDF export of a completed Resume, THE PDF_Exporter SHALL generate a PDF file using the selected Resume_Template and return a download URL.
2. THE PDF_Exporter SHALL produce a PDF that is formatted for standard US Letter (8.5" × 11") page size with appropriate margins.
3. THE PDF_Exporter SHALL render all Resume content including personal information, professional summary, work experience entries, education entries, and skills in the layout defined by the selected Resume_Template.
4. THE PDF_Exporter SHALL generate the PDF within 10 seconds of the export request.
5. IF the PDF generation fails, THEN THE PDF_Exporter SHALL return a descriptive error message to the Candidate and log the failure for debugging.

---

### Requirement 12: Resume Storage and Version Management

**User Story:** As a Candidate, I want to save multiple resumes and track version history, so that I can maintain different resumes for different job types and revert changes if needed.

#### Acceptance Criteria

1. THE Resume storage service SHALL allow a Candidate to save and retrieve multiple Resumes, each with a distinct title (for example, "Engineering Resume" or "Management Resume").
2. WHEN a Candidate saves changes to a Resume, THE Resume storage service SHALL create a new Resume_Version snapshot containing the full Resume content and a timestamp.
3. WHEN a Candidate requests the version history of a Resume, THE Resume storage service SHALL return a list of all Resume_Version records ordered by creation date descending, including the timestamp and a summary of changes.
4. WHEN a Candidate requests to restore a previous Resume_Version, THE Resume storage service SHALL create a new Resume_Version with the restored content as the current version, preserving the full version history.
5. THE Resume storage service SHALL enforce a maximum of 20 Resumes per Candidate to manage storage costs.
6. THE Resume storage service SHALL enforce a maximum of 50 Resume_Versions per Resume to manage storage costs.

---

### Requirement 13: Resume Public Sharing

**User Story:** As a Candidate, I want to share my resume via a public link, so that recruiters or contacts can view my resume without needing a HavenHR account.

#### Acceptance Criteria

1. WHEN a Candidate enables public sharing for a Resume, THE Resume storage service SHALL generate a unique, unguessable Public_Link URL and associate it with the Resume.
2. WHEN an unauthenticated user accesses a valid Public_Link, THE Frontend SHALL render a read-only view of the Resume using the selected Resume_Template without requiring authentication.
3. WHEN a Candidate disables public sharing for a Resume, THE Resume storage service SHALL deactivate the Public_Link so that subsequent access attempts return a 404 Not Found response.
4. WHEN a Candidate re-enables public sharing for a Resume, THE Resume storage service SHALL generate a new Public_Link URL, invalidating any previously generated link for that Resume.
5. THE Public_Link view SHALL not expose the Candidate's email address or phone number unless the Candidate explicitly opts in to showing contact information on the public view.

---

### Requirement 14: Job Application with Resume

**User Story:** As a Candidate, I want to apply to jobs posted on HavenHR using my saved resume, so that I can pursue opportunities directly on the platform.

#### Acceptance Criteria

1. WHEN a Candidate submits a job application selecting a saved Resume, THE Job_Application service SHALL create a Job_Application record linking the Candidate ID, the job posting ID, the selected Resume ID, and a snapshot of the Resume content at the time of application.
2. THE Job_Application service SHALL store a frozen copy of the Resume content at application time so that subsequent Resume edits do not alter the submitted application.
3. WHEN a Candidate attempts to apply to a job they have already applied to, THE Job_Application service SHALL reject the request and return an error indicating a duplicate application.
4. WHEN a Job_Application is successfully created, THE Event_Bus SHALL publish a "candidate.applied" event containing the Candidate ID, job posting ID, and Tenant_ID of the employer.
5. THE Job_Application service SHALL validate that the referenced job posting exists and is in an active status before creating the application.

---

### Requirement 15: Employer Integration — Candidate Visibility

**User Story:** As an employer, I want to see candidate profiles and resumes when they apply to my jobs, so that I can evaluate applicants effectively.

#### Acceptance Criteria

1. WHEN an authorized Tenant User with the appropriate Permission queries applications for a job posting, THE Job_Application service SHALL return a list of Job_Application records including the Candidate name, application date, and a link to the submitted Resume snapshot.
2. WHEN an authorized Tenant User requests a specific Job_Application, THE Job_Application service SHALL return the Candidate_Profile (name, location, skills, work history summary) and the frozen Resume content submitted with the application.
3. THE Job_Application service SHALL scope all application queries by Tenant_ID so that employers can only see applications for their own job postings.
4. WHEN an authorized Tenant User queries the Talent_Pool, THE Talent_Pool service SHALL return a list of all Candidates who have applied to any job within the Tenant, with de-duplicated Candidate entries.
5. THE RBAC_Middleware SHALL enforce that only Tenant Users with the "applications.view" Permission can access Job_Application and Talent_Pool data.

---

### Requirement 16: AI Service Rate Limiting and Cost Control

**User Story:** As a platform operator, I want to control AI usage costs and prevent abuse, so that the AI features remain sustainable and available to all Candidates.

#### Acceptance Criteria

1. THE Rate_Limiter SHALL enforce a limit of 20 AI content generation requests per hour per Candidate.
2. THE Rate_Limiter SHALL enforce a limit of 100 AI content generation requests per day per Candidate.
3. WHEN a Candidate exceeds the AI rate limit, THE AI_Service SHALL return a 429 Too Many Requests response with a "Retry-After" header indicating when the Candidate can make the next request.
4. THE AI_Service SHALL log every AI_Job with the Candidate ID, request type, token count consumed, and processing duration for cost monitoring.
5. THE AI_Service SHALL enforce a maximum input length of 5000 characters per AI request to control token consumption.

---

### Requirement 17: AI Job Queue Processing

**User Story:** As a platform architect, I want AI requests to be processed asynchronously via a job queue, so that the user interface remains responsive and the system can handle load spikes gracefully.

#### Acceptance Criteria

1. WHEN an AI content generation request is received, THE AI_Service SHALL create an AI_Job record with a "pending" status, dispatch the job to the Redis-backed queue, and return the AI_Job identifier to the client.
2. THE Frontend SHALL poll the AI_Job status endpoint at 2-second intervals until the AI_Job status changes to "completed" or "failed".
3. WHEN an AI_Job is picked up by a queue worker, THE AI_Service SHALL update the AI_Job status to "processing".
4. WHEN an AI_Job completes successfully, THE AI_Service SHALL update the AI_Job status to "completed" and store the generated content in the AI_Job result field.
5. IF an AI_Job fails after all retry attempts, THEN THE AI_Service SHALL update the AI_Job status to "failed", store the error message, and move the job to the failed-jobs queue.
6. THE AI_Service SHALL process AI_Jobs with a maximum execution timeout of 30 seconds per job.

---

### Requirement 18: Candidate-Facing Frontend Pages

**User Story:** As a Candidate, I want a fast, simple, and visually appealing interface for managing my profile and resumes, so that the experience feels like a modern consumer application.

#### Acceptance Criteria

1. THE Frontend SHALL provide the following Candidate-facing pages: registration, login, dashboard (resume list), profile editor, resume builder wizard, resume preview, and public resume view.
2. THE Frontend SHALL render all Candidate-facing pages with a mobile-first responsive layout, adapting to viewport widths from 320px to 2560px.
3. THE Frontend SHALL meet WCAG 2.1 Level AA accessibility standards for all Candidate-facing pages, including proper form labels, keyboard navigation, focus management, and sufficient color contrast ratios.
4. THE Frontend SHALL display loading indicators during all asynchronous operations including AI content generation, PDF export, and data saves.
5. THE Frontend SHALL display inline validation errors for each form field when the Input_Validator returns validation errors.
6. THE Frontend SHALL use a distinct visual identity (color scheme, navigation layout) for Candidate-facing pages to differentiate them from the employer dashboard while maintaining brand consistency.

---

### Requirement 19: Candidate Data Schema

**User Story:** As a developer, I want a well-structured database schema for candidate data, so that the resume builder and job application features have a consistent and scalable data model.

#### Acceptance Criteria

1. THE Database Schema SHALL include a "candidates" table with columns for id (UUID primary key), name, email (unique), password_hash, phone, location, linkedin_url, portfolio_url, is_active, email_verified_at, last_login_at, created_at, and updated_at.
2. THE Database Schema SHALL include a "candidate_work_histories" table with columns for id (UUID primary key), candidate_id (foreign key to candidates), job_title, company_name, start_date, end_date (nullable), description, sort_order, created_at, and updated_at.
3. THE Database Schema SHALL include a "candidate_educations" table with columns for id (UUID primary key), candidate_id (foreign key to candidates), institution_name, degree, field_of_study, start_date, end_date (nullable), sort_order, created_at, and updated_at.
4. THE Database Schema SHALL include a "candidate_skills" table with columns for id (UUID primary key), candidate_id (foreign key to candidates), name, category (technical or soft), sort_order, created_at.
5. THE Database Schema SHALL include a "resumes" table with columns for id (UUID primary key), candidate_id (foreign key to candidates), title, template_slug, content (JSON), is_complete, public_link_token (nullable, unique), public_link_active (boolean, default false), show_contact_on_public (boolean, default false), created_at, and updated_at.
6. THE Database Schema SHALL include a "resume_versions" table with columns for id (UUID primary key), resume_id (foreign key to resumes), content (JSON), version_number, change_summary (nullable), created_at.
7. THE Database Schema SHALL include an "ai_jobs" table with columns for id (UUID primary key), candidate_id (foreign key to candidates), job_type (enum: summary, bullets, skills, ats_optimize, improve), input_data (JSON), result_data (JSON, nullable), status (enum: pending, processing, completed, failed), error_message (nullable), tokens_used (integer, nullable), processing_duration_ms (integer, nullable), created_at, and updated_at.
8. THE Database Schema SHALL include a "job_applications" table with columns for id (UUID primary key), candidate_id (foreign key to candidates), job_posting_id (UUID foreign key), resume_id (foreign key to resumes), resume_snapshot (JSON), status (enum: submitted, reviewed, shortlisted, rejected), applied_at, and updated_at, with a unique composite constraint on (candidate_id, job_posting_id).
9. THE Database Schema SHALL use UUID primary keys for all Candidate-related tables to maintain consistency with the existing platform schema.
