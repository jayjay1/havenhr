# Requirements Document — Candidate Pipeline (Kanban)

## Introduction

The Candidate Pipeline feature enhances HavenHR's employer hiring experience by replacing the basic pipeline view (dropdown-based stage moves) with an interactive Kanban board. Employers drag and drop candidate cards between pipeline stage columns on desktop, swipe to move on mobile, view rich candidate details in a slide-over panel, perform bulk operations, and search/filter candidates within a job's pipeline. The feature also extends stage customization (renaming, color-coding, inserting stages) and lays groundwork for stage-change notifications.

This spec is primarily frontend-focused, building on the existing backend infrastructure: `pipeline_stages` table, `stage_transitions` audit trail, `PipelineService` with `moveApplication()`, `createDefaultStages()`, `addStage()`, `reorderStages()`, and `removeStage()`. Some backend enhancements are needed for candidate search within a pipeline, bulk move/reject operations, stage renaming, and stage color storage.

## Glossary

- **Kanban_Board**: An interactive board UI on the employer job detail page that displays Pipeline_Stages as vertical columns and candidate applications as draggable cards within those columns.
- **Candidate_Card**: A visual card within a Kanban_Board column representing a single job application, displaying the candidate's name, email, applied date, and a link to their resume.
- **Stage_Column**: A vertical column in the Kanban_Board representing a single Pipeline_Stage, displaying the stage name, candidate count badge, and containing Candidate_Cards.
- **Slide_Over_Panel**: A panel that slides in from the right side of the screen when a Candidate_Card is clicked, displaying full candidate details, resume snapshot, notes, stage history, and quick actions.
- **Bulk_Action**: An operation applied to multiple selected candidate applications simultaneously, such as moving all selected candidates to a specific stage or rejecting them.
- **Pipeline_Search**: A search mechanism that filters Candidate_Cards within a job's Kanban_Board by candidate name or email.
- **Pipeline_Filter**: A filter mechanism that narrows the visible Candidate_Cards by current stage or sorts them by applied date or name.
- **Stage_Color**: A hex color code associated with a Pipeline_Stage, used to visually distinguish stage columns on the Kanban_Board.
- **Drag_Handle**: The interactive area of a Candidate_Card that initiates a drag-and-drop operation on desktop devices.
- **Drop_Zone**: The target area within a Stage_Column where a dragged Candidate_Card can be released to trigger a stage transition.
- **Swipe_Gesture**: A horizontal swipe interaction on a Candidate_Card on touch devices that initiates a stage move action.
- **Bottom_Sheet**: A mobile UI pattern where an action menu slides up from the bottom of the screen, used for stage selection and candidate actions on touch devices.
- **Pipeline_Controller**: The existing backend controller handling pipeline stage management and application stage transitions.
- **Pipeline_Service**: The existing backend service responsible for pipeline stage CRUD and candidate stage transitions.
- **Employer_Application_Controller**: The existing backend controller serving application listing and detail endpoints for employer users.
- **Tenant_User**: An authenticated user belonging to a specific tenant (company), subject to RBAC permissions.
- **Stage_Transition**: An existing audit record capturing when a candidate application moves between pipeline stages.

## Requirements

### Requirement 1: Interactive Kanban Board Display

**User Story:** As a recruiter, I want to see candidates organized in a Kanban board layout by pipeline stage, so that I can visually track where each candidate is in the hiring process.

#### Acceptance Criteria

1. WHEN a Tenant_User with `jobs.view` permission navigates to the job detail page, THE Kanban_Board SHALL display all Pipeline_Stages as horizontal Stage_Columns ordered by sort_order.
2. THE Kanban_Board SHALL display each Stage_Column with the stage name and a badge showing the count of Candidate_Cards in that stage.
3. THE Kanban_Board SHALL display each Candidate_Card with the candidate's name, email address, applied date, and a link to the candidate's resume snapshot.
4. THE Kanban_Board SHALL support horizontal scrolling when the total width of all Stage_Columns exceeds the viewport width.
5. WHILE the Kanban_Board is loading data from the API, THE Kanban_Board SHALL display a loading skeleton with placeholder columns and cards.
6. IF the API request to load pipeline data fails, THEN THE Kanban_Board SHALL display an error message with a retry button.

### Requirement 2: Drag-and-Drop Stage Transitions (Desktop)

**User Story:** As a recruiter using a desktop browser, I want to drag candidate cards between pipeline stage columns, so that I can quickly move candidates through the hiring process.

#### Acceptance Criteria

1. WHEN a Tenant_User with `applications.manage` permission drags a Candidate_Card from one Stage_Column and drops it into a different Stage_Column, THE Kanban_Board SHALL call the existing move application API endpoint with the application ID and target stage ID.
2. WHEN a drag operation begins, THE Kanban_Board SHALL visually indicate the dragged Candidate_Card with a semi-transparent appearance and highlight valid Drop_Zones.
3. WHEN a Candidate_Card is dropped into a valid Drop_Zone, THE Kanban_Board SHALL optimistically update the card's position to the target Stage_Column before the API response returns.
4. IF the move application API call fails after an optimistic update, THEN THE Kanban_Board SHALL revert the Candidate_Card to its original Stage_Column and display an error notification.
5. WHEN a drag-and-drop move succeeds, THE Kanban_Board SHALL update the candidate count badges on both the source and target Stage_Columns.
6. WHILE a Tenant_User does not have `applications.manage` permission, THE Kanban_Board SHALL disable drag-and-drop interactions on all Candidate_Cards.
7. THE Kanban_Board SHALL maintain keyboard accessibility during drag-and-drop operations, allowing stage moves via keyboard shortcuts as an alternative to mouse dragging.

### Requirement 3: Mobile Swipe-to-Move

**User Story:** As a recruiter using a mobile device, I want to swipe candidate cards to move them between stages, so that I can manage the pipeline on the go.

#### Acceptance Criteria

1. WHEN a Tenant_User with `applications.manage` permission performs a horizontal Swipe_Gesture on a Candidate_Card on a touch device, THE Kanban_Board SHALL open a Bottom_Sheet displaying the available target stages.
2. WHEN a Tenant_User selects a target stage from the Bottom_Sheet, THE Kanban_Board SHALL call the move application API endpoint and optimistically update the Candidate_Card's position.
3. IF the Tenant_User cancels the Bottom_Sheet without selecting a stage, THEN THE Kanban_Board SHALL return the Candidate_Card to its original position with no API call.
4. THE Bottom_Sheet SHALL display stage names and their associated Stage_Colors, excluding the candidate's current stage from the list.
5. WHILE a Tenant_User does not have `applications.manage` permission, THE Kanban_Board SHALL disable Swipe_Gestures on Candidate_Cards.

### Requirement 4: Candidate Detail Slide-Over Panel

**User Story:** As a recruiter, I want to click on a candidate card to see their full details in a side panel, so that I can review their qualifications without leaving the pipeline view.

#### Acceptance Criteria

1. WHEN a Tenant_User clicks or taps a Candidate_Card, THE Slide_Over_Panel SHALL open from the right side of the screen displaying the candidate's full details.
2. THE Slide_Over_Panel SHALL display the following sections: candidate name and email, resume snapshot viewer, notes and comments area, stage history timeline, and quick action buttons.
3. THE Slide_Over_Panel SHALL display the stage history timeline showing all Stage_Transitions for the application, ordered chronologically, with the stage name, who moved the candidate, and the timestamp for each transition.
4. THE Slide_Over_Panel SHALL include quick action buttons: move to a specific stage (dropdown), reject (moves to Rejected stage), and shortlist (moves to the next stage in sort_order).
5. WHEN a quick action is performed in the Slide_Over_Panel, THE Kanban_Board SHALL update the Candidate_Card's position and the Stage_Column counts in real time.
6. WHEN the Slide_Over_Panel is open, THE Tenant_User SHALL be able to close it by clicking a close button, pressing the Escape key, or clicking outside the panel.
7. THE Slide_Over_Panel SHALL be accessible via keyboard navigation, with focus trapped within the panel while it is open.
8. WHILE the Slide_Over_Panel is loading candidate details, THE Slide_Over_Panel SHALL display a loading skeleton.
9. WHEN a Tenant_User taps a Candidate_Card on a mobile device, THE Slide_Over_Panel SHALL render as a full-screen overlay instead of a side panel.

### Requirement 5: Bulk Actions

**User Story:** As a recruiter, I want to select multiple candidates and perform actions on them at once, so that I can efficiently manage large applicant pools.

#### Acceptance Criteria

1. WHEN a Tenant_User with `applications.manage` permission enables selection mode, THE Kanban_Board SHALL display a checkbox on each Candidate_Card.
2. WHEN one or more Candidate_Cards are selected, THE Kanban_Board SHALL display a bulk action toolbar showing the count of selected candidates and available actions.
3. WHEN a Tenant_User selects "Move to Stage" from the bulk action toolbar and chooses a target stage, THE Pipeline_Controller SHALL move all selected applications to the target stage and create Stage_Transition records for each.
4. WHEN a Tenant_User selects "Reject" from the bulk action toolbar, THE Pipeline_Controller SHALL move all selected applications to the Rejected stage and create Stage_Transition records for each.
5. WHEN a bulk action completes, THE Kanban_Board SHALL update all affected Candidate_Cards' positions and Stage_Column counts.
6. IF any individual move within a bulk action fails, THEN THE Kanban_Board SHALL display a summary indicating which candidates were moved and which failed.
7. WHILE a Tenant_User does not have `applications.manage` permission, THE Kanban_Board SHALL hide the bulk action controls.

### Requirement 6: Pipeline Search and Filtering

**User Story:** As a recruiter, I want to search and filter candidates within a job's pipeline, so that I can quickly find specific applicants.

#### Acceptance Criteria

1. WHEN a Tenant_User enters a search query in the Pipeline_Search input, THE Kanban_Board SHALL filter Candidate_Cards to show only those where the candidate name or email contains the search term (case-insensitive partial match).
2. WHEN a Tenant_User selects a stage from the Pipeline_Filter dropdown, THE Kanban_Board SHALL display only the selected Stage_Column and its Candidate_Cards.
3. WHEN a Tenant_User selects a sort option, THE Kanban_Board SHALL order Candidate_Cards within each Stage_Column by the selected criterion: applied date (newest first, default), applied date (oldest first), or candidate name (alphabetical).
4. WHEN search, filter, and sort are combined, THE Kanban_Board SHALL apply all active criteria simultaneously.
5. WHEN a Pipeline_Search query is active, THE Kanban_Board SHALL display the count of matching candidates and a button to clear the search.
6. THE Pipeline_Search SHALL execute filtering on the client side for datasets already loaded, without additional API calls, for pipelines with up to 200 candidates.
7. WHEN a pipeline has more than 200 candidates, THE Employer_Application_Controller SHALL support server-side search with a `q` query parameter that filters applications by candidate name or email.

### Requirement 7: Stage Customization

**User Story:** As a recruiter, I want to customize pipeline stages with names and colors, so that the pipeline reflects my company's hiring workflow.

#### Acceptance Criteria

1. WHEN a Tenant_User with `pipeline.manage` permission renames a Pipeline_Stage, THE Pipeline_Service SHALL update the stage name and return the updated stage record.
2. WHEN a Tenant_User with `pipeline.manage` permission assigns a Stage_Color to a Pipeline_Stage, THE Pipeline_Service SHALL store the hex color code and return the updated stage record.
3. WHEN a Stage_Color is assigned to a Pipeline_Stage, THE Kanban_Board SHALL render the Stage_Column header with the assigned color as a top border or background accent.
4. WHEN a Tenant_User with `pipeline.manage` permission adds a new Pipeline_Stage, THE Pipeline_Service SHALL insert the stage at the specified sort_order position and shift subsequent stages' sort_order values accordingly.
5. THE Kanban_Board SHALL provide an inline editing interface for stage names, activated by double-clicking or tapping the stage name in the Stage_Column header.
6. IF a Tenant_User without `pipeline.manage` permission views the Kanban_Board, THEN THE Kanban_Board SHALL display stage names and colors as read-only without editing controls.

### Requirement 8: Stage Change Logging and Notification Preparation

**User Story:** As a platform operator, I want all stage changes to be logged and the system prepared for future email notifications, so that we have a complete audit trail and can notify candidates when ready.

#### Acceptance Criteria

1. WHEN a candidate application is moved to a new Pipeline_Stage (via drag-and-drop, swipe, quick action, or bulk action), THE Pipeline_Service SHALL create a Stage_Transition record with the from_stage_id, to_stage_id, moved_by user ID, and moved_at timestamp.
2. WHEN a candidate application is moved to a new Pipeline_Stage, THE Pipeline_Service SHALL dispatch an `application.stage_changed` domain event containing the tenant_id, user_id, application_id, from_stage, and to_stage.
3. THE Stage_Transition records SHALL be queryable by application ID, ordered by moved_at ascending, to reconstruct the full stage history timeline.
4. THE `application.stage_changed` domain event payload SHALL include a `notification_eligible` boolean field set to `true`, preparing for future email notification integration.

### Requirement 9: Mobile-First Pipeline Layout

**User Story:** As a recruiter using a mobile device, I want the pipeline to be fully usable on small screens, so that I can manage candidates from anywhere.

#### Acceptance Criteria

1. WHILE the viewport width is below 768 pixels, THE Kanban_Board SHALL display Stage_Columns as horizontally swipeable cards, showing one full column at a time with partial visibility of adjacent columns.
2. WHILE the viewport width is below 768 pixels, THE Kanban_Board SHALL display a stage navigation indicator (dots or tabs) showing the current stage position and total stage count.
3. WHEN a Tenant_User taps a Candidate_Card on a mobile device, THE Slide_Over_Panel SHALL render as a full-screen overlay with a back button for navigation.
4. WHEN a Tenant_User performs actions on mobile, THE Kanban_Board SHALL use Bottom_Sheets for stage selection, bulk action menus, and confirmation dialogs instead of dropdown menus.
5. THE Kanban_Board SHALL support touch scrolling within Stage_Columns without conflicting with Swipe_Gestures on Candidate_Cards.
6. THE Kanban_Board SHALL render all interactive elements with a minimum touch target size of 44x44 pixels on mobile devices.

### Requirement 10: Backend Enhancements for Pipeline Operations

**User Story:** As a recruiter, I want the backend to support bulk operations and search within a pipeline, so that the enhanced frontend features work reliably.

#### Acceptance Criteria

1. WHEN a Tenant_User with `applications.manage` permission submits a bulk move request with an array of application IDs and a target stage ID, THE Pipeline_Controller SHALL move all specified applications to the target stage and return the count of successful and failed moves.
2. WHEN a Tenant_User with `applications.manage` permission submits a bulk reject request with an array of application IDs, THE Pipeline_Controller SHALL move all specified applications to the Rejected stage of their respective Job_Postings.
3. WHEN a Tenant_User with `pipeline.manage` permission submits a stage rename request with a stage ID and new name, THE Pipeline_Controller SHALL update the stage name and return the updated stage record.
4. WHEN a Tenant_User with `pipeline.manage` permission submits a stage color update request with a stage ID and hex color code, THE Pipeline_Controller SHALL update the stage color and return the updated stage record.
5. THE Pipeline_Controller SHALL validate that the hex color code matches the pattern `^#[0-9a-fA-F]{6}$`.
6. WHEN the `q` query parameter is provided on the job applications listing endpoint, THE Employer_Application_Controller SHALL filter applications where the candidate name or email contains the search term (case-insensitive partial match).
7. WHEN the `sort` query parameter is provided on the job applications listing endpoint, THE Employer_Application_Controller SHALL support sorting by `applied_at` (default, descending) and `candidate_name` (alphabetical ascending).

### Requirement 11: Accessibility Compliance

**User Story:** As a recruiter with accessibility needs, I want the Kanban board to be fully accessible, so that I can use it with assistive technologies.

#### Acceptance Criteria

1. THE Kanban_Board SHALL implement ARIA live regions to announce stage changes, card movements, and bulk action results to screen readers.
2. THE Kanban_Board SHALL support keyboard-only navigation: Tab to move between Stage_Columns, Arrow keys to move between Candidate_Cards within a column, and Enter or Space to open the Slide_Over_Panel.
3. THE Kanban_Board SHALL provide keyboard shortcuts for moving a focused Candidate_Card to the next or previous stage (Ctrl+Arrow Right/Left or equivalent).
4. THE Slide_Over_Panel SHALL trap focus within the panel while open and return focus to the triggering Candidate_Card when closed.
5. THE Kanban_Board SHALL maintain a color contrast ratio of at least 4.5:1 for all text elements, including text rendered against Stage_Color backgrounds.
6. THE Kanban_Board SHALL provide visible focus indicators on all interactive elements that meet WCAG 2.1 AA requirements.
