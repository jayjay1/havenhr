# Requirements Document

## Introduction

This feature adds structured evaluation tools to the HavenHR platform — interview kits with predefined questions per pipeline stage, and scorecards that interviewers fill out after each interview to rate candidates on specific criteria. Interview kits ensure consistent, stage-appropriate questioning across all interviewers, while scorecards capture structured feedback that can be aggregated into a summary view for data-driven hiring decisions. The feature integrates with the existing Interview, PipelineStage, and JobPosting models.

## Glossary

- **Interview_Kit**: A template of questions and focus areas assigned to a specific pipeline stage for a job posting. Each kit defines what interviewers should evaluate during that stage.
- **Interview_Kit_Question**: A single question or focus area within an Interview_Kit, categorized by type and optionally accompanied by a scoring rubric.
- **Question_Category**: A classification for interview questions. Valid values are: technical, behavioral, cultural, experience.
- **Scoring_Rubric**: Optional guidance text attached to a question that describes what constitutes each rating level for that question.
- **Scorecard**: A structured evaluation form that an interviewer submits after conducting an interview, containing individual criteria ratings, notes, and an overall recommendation.
- **Criteria_Rating**: A numeric score from 1 to 5 assigned by an interviewer to a single scorecard criterion (question).
- **Overall_Recommendation**: The interviewer's final hiring recommendation for a candidate. Valid values are: strong_no, no, mixed, yes, strong_yes.
- **Overall_Rating**: A numeric score from 1 to 5 representing the interviewer's general assessment of the candidate.
- **Scorecard_Summary**: An aggregated view of all scorecards submitted for a given candidate's job application, showing average ratings and individual interviewer breakdowns.
- **Employer_User**: An authenticated user belonging to a tenant (company) who manages job postings, pipeline stages, and interviews.
- **Interviewer**: An Employer_User assigned to conduct an interview and submit a scorecard.
- **Default_Kit_Template**: A pre-configured Interview_Kit that serves as a starting point and can be customized per job posting.
- **System**: The HavenHR backend application (Laravel 11) and frontend application (Next.js 15) operating together.
- **Pipeline_Stage**: An existing model representing a step in the hiring pipeline for a job posting (e.g., Phone Screen, Technical Interview, Onsite).

## Requirements

### Requirement 1: Create Interview Kit

**User Story:** As an Employer_User, I want to create an interview kit for a specific pipeline stage of a job posting, so that interviewers have a consistent set of questions and focus areas for that stage.

#### Acceptance Criteria

1. WHEN an Employer_User submits a request to create an Interview_Kit with a name, description, and a list of Interview_Kit_Questions, THE System SHALL create the Interview_Kit and associate it with the specified Pipeline_Stage and job posting.
2. THE System SHALL require each Interview_Kit to have a non-empty name with a maximum length of 255 characters.
3. WHEN an Interview_Kit is created, THE System SHALL store each Interview_Kit_Question with its text, Question_Category, sort order, and optional Scoring_Rubric.
4. THE System SHALL validate that each Interview_Kit_Question has non-empty text and a valid Question_Category (technical, behavioral, cultural, or experience).
5. IF an Employer_User attempts to create an Interview_Kit for a Pipeline_Stage that does not belong to the specified job posting, THEN THE System SHALL return a validation error with a descriptive message.
6. IF an Employer_User attempts to create an Interview_Kit without the required pipeline.manage permission, THEN THE System SHALL return a 403 Forbidden response.

### Requirement 2: Update Interview Kit

**User Story:** As an Employer_User, I want to update an existing interview kit's name, description, and questions, so that I can refine the evaluation criteria as hiring needs evolve.

#### Acceptance Criteria

1. WHEN an Employer_User submits a request to update an Interview_Kit, THE System SHALL update the kit's name, description, and replace the list of Interview_Kit_Questions with the provided set.
2. THE System SHALL preserve the sort order of Interview_Kit_Questions as specified in the update request.
3. IF an Employer_User attempts to update an Interview_Kit that does not exist or belongs to a different tenant, THEN THE System SHALL return a 404 Not Found response.
4. IF an Employer_User attempts to update an Interview_Kit without the required pipeline.manage permission, THEN THE System SHALL return a 403 Forbidden response.

### Requirement 3: Delete Interview Kit

**User Story:** As an Employer_User, I want to delete an interview kit that is no longer needed, so that outdated kits do not clutter the stage configuration.

#### Acceptance Criteria

1. WHEN an Employer_User submits a request to delete an Interview_Kit, THE System SHALL remove the Interview_Kit and all associated Interview_Kit_Questions.
2. IF scorecards already reference questions from the deleted Interview_Kit, THEN THE System SHALL retain the scorecard data and only remove the kit template.
3. IF an Employer_User attempts to delete an Interview_Kit that does not exist or belongs to a different tenant, THEN THE System SHALL return a 404 Not Found response.

### Requirement 4: List and View Interview Kits

**User Story:** As an Employer_User, I want to view all interview kits for a job posting's pipeline stages, so that I can review and manage the evaluation structure.

#### Acceptance Criteria

1. WHEN an Employer_User requests the interview kits for a job posting, THE System SHALL return all Interview_Kits grouped by Pipeline_Stage, including each kit's name, description, and question count.
2. WHEN an Employer_User requests a specific Interview_Kit's details, THE System SHALL return the kit's name, description, and the full list of Interview_Kit_Questions with their text, Question_Category, sort order, and Scoring_Rubric.
3. THE System SHALL scope all Interview_Kit queries to the Employer_User's tenant.

### Requirement 5: Default Kit Templates

**User Story:** As an Employer_User, I want default interview kit templates to be available when I create pipeline stages, so that I have a starting point and do not need to build kits from scratch.

#### Acceptance Criteria

1. WHEN a new Pipeline_Stage is created for a job posting and no Interview_Kit exists for that stage, THE System SHALL offer a set of Default_Kit_Templates appropriate to common stage names (e.g., Phone Screen, Technical Interview, Culture Fit).
2. WHEN an Employer_User selects a Default_Kit_Template, THE System SHALL create a copy of the template as a new Interview_Kit linked to the Pipeline_Stage, allowing the Employer_User to customize it independently.
3. THE System SHALL not modify the original Default_Kit_Template when an Employer_User customizes a copied kit.

### Requirement 6: Submit Scorecard

**User Story:** As an Interviewer, I want to submit a scorecard after conducting an interview, so that my structured evaluation is recorded for the hiring team.

#### Acceptance Criteria

1. WHEN an Interviewer submits a scorecard for a completed interview, THE System SHALL create a Scorecard record linked to the Interview, containing individual Criteria_Ratings, notes per criterion, an Overall_Rating (1–5), and an Overall_Recommendation.
2. THE System SHALL validate that each Criteria_Rating is an integer between 1 and 5 inclusive.
3. THE System SHALL validate that the Overall_Rating is an integer between 1 and 5 inclusive.
4. THE System SHALL validate that the Overall_Recommendation is one of: strong_no, no, mixed, yes, strong_yes.
5. IF an Interviewer attempts to submit a scorecard for an interview that is not in "completed" status, THEN THE System SHALL return a validation error indicating the interview must be completed first.
6. IF an Interviewer attempts to submit a second scorecard for the same interview, THEN THE System SHALL return a validation error indicating a scorecard already exists for this interviewer and interview.
7. THE System SHALL store the submitting Interviewer's user ID and the submission timestamp on the Scorecard.

### Requirement 7: Update Scorecard

**User Story:** As an Interviewer, I want to update my previously submitted scorecard, so that I can correct or refine my evaluation before a hiring decision is made.

#### Acceptance Criteria

1. WHEN an Interviewer submits an update to their own Scorecard, THE System SHALL update the Criteria_Ratings, notes, Overall_Rating, and Overall_Recommendation with the new values.
2. IF an Interviewer attempts to update a Scorecard that was submitted by a different Interviewer, THEN THE System SHALL return a 403 Forbidden response.
3. THE System SHALL record the updated_at timestamp when a Scorecard is modified.

### Requirement 8: View Individual Scorecard

**User Story:** As an Employer_User, I want to view a specific scorecard submitted by an interviewer, so that I can review their detailed evaluation of a candidate.

#### Acceptance Criteria

1. WHEN an Employer_User requests a specific Scorecard, THE System SHALL return the Scorecard's Criteria_Ratings with question text, notes per criterion, Overall_Rating, Overall_Recommendation, Interviewer name, and submission timestamp.
2. THE System SHALL scope Scorecard access to the Employer_User's tenant.
3. IF the requested Scorecard does not exist or belongs to a different tenant, THEN THE System SHALL return a 404 Not Found response.

### Requirement 9: Scorecard Summary for Candidate

**User Story:** As an Employer_User, I want to see an aggregated summary of all scorecards for a candidate's application, so that I can make a data-driven hiring decision.

#### Acceptance Criteria

1. WHEN an Employer_User requests the Scorecard_Summary for a job application, THE System SHALL return the total number of scorecards submitted, the average Overall_Rating across all scorecards, and the distribution of Overall_Recommendations.
2. THE System SHALL include per-criterion average ratings across all scorecards in the Scorecard_Summary.
3. THE System SHALL include each individual Interviewer's Overall_Rating, Overall_Recommendation, and submission timestamp in the Scorecard_Summary.
4. IF no scorecards have been submitted for the job application, THEN THE System SHALL return an empty summary with zero counts and no averages.

### Requirement 10: Interview Kit Management UI

**User Story:** As an Employer_User, I want a user interface to create, edit, and manage interview kits for each pipeline stage, so that I can configure evaluation criteria without technical knowledge.

#### Acceptance Criteria

1. WHEN an Employer_User navigates to the interview kit management section for a job posting, THE System SHALL display all Pipeline_Stages with their associated Interview_Kits.
2. THE System SHALL provide a form to create a new Interview_Kit with fields for name, description, and a dynamic list of Interview_Kit_Questions.
3. THE System SHALL allow the Employer_User to add, remove, and reorder Interview_Kit_Questions within the form using drag-and-drop or up/down controls.
4. THE System SHALL allow the Employer_User to select a Question_Category for each question from a dropdown with options: technical, behavioral, cultural, experience.
5. WHEN an Employer_User saves an Interview_Kit, THE System SHALL validate all fields client-side before submitting to the API and display inline validation errors for invalid fields.
6. THE System SHALL provide an option to select from Default_Kit_Templates when creating a new Interview_Kit for a stage.

### Requirement 11: Scorecard Submission UI

**User Story:** As an Interviewer, I want a user interface to fill out and submit a scorecard after an interview, so that I can provide structured feedback efficiently.

#### Acceptance Criteria

1. WHEN an Interviewer opens the scorecard submission form for a completed interview, THE System SHALL display each criterion from the associated Interview_Kit with a rating input (1–5) and a notes text field.
2. IF no Interview_Kit is associated with the interview's pipeline stage, THEN THE System SHALL display a general scorecard form with only the Overall_Rating and Overall_Recommendation fields and a free-text notes area.
3. THE System SHALL provide a visual rating selector (star rating or labeled buttons: 1–5) for each criterion and the Overall_Rating.
4. THE System SHALL provide a recommendation selector with options labeled: Strong No, No, Mixed, Yes, Strong Yes.
5. WHEN the Interviewer submits the scorecard, THE System SHALL display a success confirmation and return to the interview detail or candidate view.
6. IF the scorecard submission fails validation, THEN THE System SHALL display specific error messages next to the invalid fields without clearing the form.

### Requirement 12: Scorecard Summary Display in Candidate Panel

**User Story:** As an Employer_User, I want to see the scorecard summary within the candidate's slide-over panel, so that I can quickly assess interviewer feedback without navigating away.

#### Acceptance Criteria

1. WHEN an Employer_User opens the SlideOverPanel for a candidate application, THE System SHALL display a Scorecard Summary section showing the average Overall_Rating and the number of scorecards submitted.
2. THE System SHALL display each Interviewer's name, Overall_Rating, and Overall_Recommendation in a side-by-side comparison within the Scorecard Summary section.
3. WHEN an Employer_User clicks on an individual Interviewer's summary entry, THE System SHALL expand or navigate to show the full Scorecard details including per-criterion ratings and notes.
4. IF no scorecards have been submitted for the application, THEN THE System SHALL display a message indicating no evaluations are available.

### Requirement 13: Panel Interview Support

**User Story:** As an Employer_User, I want multiple interviewers to submit independent scorecards for the same candidate, so that panel interview feedback is captured from each evaluator.

#### Acceptance Criteria

1. THE System SHALL allow multiple Interviewers to submit separate Scorecards for the same job application, each linked to their respective Interview record.
2. THE System SHALL prevent a single Interviewer from submitting more than one Scorecard per Interview record.
3. WHEN calculating the Scorecard_Summary, THE System SHALL include all Scorecards from all Interviewers across all interviews for the same job application.

### Requirement 14: Scorecard Access Control

**User Story:** As an Employer_User, I want scorecard access to be controlled by permissions, so that only authorized team members can view and submit evaluations.

#### Acceptance Criteria

1. THE System SHALL require the applications.view permission to view Scorecards and Scorecard_Summaries.
2. THE System SHALL require the applications.manage permission to submit and update Scorecards.
3. THE System SHALL allow an Interviewer to view and update only their own Scorecard regardless of broader permissions.
4. THE System SHALL scope all Scorecard operations to the authenticated user's tenant.
