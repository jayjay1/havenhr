# Implementation Plan: Candidate Pipeline (Kanban)

## Overview

This plan implements the interactive Kanban board for the employer hiring pipeline. Backend enhancements come first (migration, service methods, controller endpoints, form requests), followed by frontend type extensions, API functions, state management, and UI components. Each task builds incrementally on the previous, ending with integration wiring and accessibility polish.

## Tasks

- [ ] 1. Database migration and model update for stage color
  - [x] 1.1 Create migration to add `color` column to `pipeline_stages` table
    - Create migration file `add_color_to_pipeline_stages_table`
    - Add `color` column: `VARCHAR(7)`, nullable, default `NULL`, placed after `name`
    - _Requirements: 7.2, 10.4_
  - [x] 1.2 Update `PipelineStage` model to include `color` in `$fillable`
    - Add `'color'` to the `$fillable` array in `backend/app/Models/PipelineStage.php`
    - _Requirements: 7.2_

- [ ] 2. Backend form request classes for new endpoints
  - [x] 2.1 Create `BulkMoveRequest` form request
    - Create `backend/app/Http/Requests/BulkMoveRequest.php` extending `BaseFormRequest`
    - Validate `application_ids` as required array, min 1, max 100, each item UUID
    - Validate `stage_id` as required UUID
    - _Requirements: 10.1_
  - [x] 2.2 Create `BulkRejectRequest` form request
    - Create `backend/app/Http/Requests/BulkRejectRequest.php` extending `BaseFormRequest`
    - Validate `application_ids` as required array, min 1, max 100, each item UUID
    - _Requirements: 10.2_
  - [x] 2.3 Create `UpdatePipelineStageRequest` form request
    - Create `backend/app/Http/Requests/UpdatePipelineStageRequest.php` extending `BaseFormRequest`
    - Validate `name` as optional string, max 255
    - Validate `color` as optional, nullable, regex `^#[0-9a-fA-F]{6}$`
    - _Requirements: 10.3, 10.4, 10.5_

- [ ] 3. PipelineService enhancements
  - [x] 3.1 Implement `bulkMove` method on `PipelineService`
    - Load target stage, verify it exists
    - Load all applications by IDs, verify they belong to the same job posting as the target stage
    - Wrap in a DB transaction: for each application update `pipeline_stage_id`, create `StageTransition`, dispatch `ApplicationStageChanged` event with `notification_eligible: true`
    - Return `['success_count' => ..., 'failed_count' => ..., 'failed_ids' => [...]]`
    - _Requirements: 5.3, 8.1, 8.2, 8.4, 10.1_
  - [ ]* 3.2 Write property test for bulk move (Property 5)
    - **Property 5: Bulk move correctness**
    - Generate random application sets and target stages; verify all moved with transitions, counts unchanged
    - **Validates: Requirements 5.3, 10.1**
  - [x] 3.3 Implement `bulkReject` method on `PipelineService`
    - Load all applications by IDs
    - For each application: find the "Rejected" stage for that application's job posting
    - Wrap in a DB transaction: update `pipeline_stage_id`, create `StageTransition`, dispatch event with `notification_eligible: true`
    - Return `['success_count' => ..., 'failed_count' => ..., 'failed_ids' => [...]]`
    - _Requirements: 5.4, 8.1, 8.2, 8.4, 10.2_
  - [ ]* 3.4 Write property test for bulk reject (Property 6)
    - **Property 6: Bulk reject correctness**
    - Generate random application sets; verify all in Rejected stage with transitions
    - **Validates: Requirements 5.4, 10.2**
  - [x] 3.5 Implement `updateStage` method on `PipelineService`
    - Load stage by ID, verify it belongs to a job posting in the current tenant
    - Update `name` if provided, update `color` if provided (or set to null)
    - Save and return updated `PipelineStage`
    - _Requirements: 7.1, 7.2, 10.3, 10.4_
  - [ ]* 3.6 Write property test for stage update round-trip (Property 11)
    - **Property 11: Stage update round-trip**
    - Generate random names (≤255 chars) and hex colors; verify persistence after update
    - **Validates: Requirements 7.1, 7.2, 10.3, 10.4**
  - [x] 3.7 Update `ApplicationStageChanged` event dispatch to include `notification_eligible` field
    - In `PipelineService::moveApplication()`, add `'notification_eligible' => true` to the event payload
    - Ensure `bulkMove` and `bulkReject` also include this field
    - _Requirements: 8.2, 8.4_

- [ ] 4. PipelineController new endpoints and route registration
  - [x] 4.1 Add `bulkMove` action to `PipelineController`
    - Accept `BulkMoveRequest`, call `PipelineService::bulkMove()`, return JSON with success/failed counts
    - _Requirements: 10.1_
  - [x] 4.2 Add `bulkReject` action to `PipelineController`
    - Accept `BulkRejectRequest`, call `PipelineService::bulkReject()`, return JSON with success/failed counts
    - _Requirements: 10.2_
  - [x] 4.3 Add `updateStage` action to `PipelineController`
    - Accept `UpdatePipelineStageRequest`, call `PipelineService::updateStage()`, return updated stage with `id`, `name`, `color`, `sort_order`
    - _Requirements: 10.3, 10.4, 10.5_
  - [x] 4.4 Update `listStages` to include `color` in the response
    - Modify the `listStages` method in `PipelineController` to return the `color` field for each stage
    - _Requirements: 7.3_
  - [x] 4.5 Register new routes in `backend/routes/api.php`
    - Add `POST /applications/bulk-move` with `rbac:applications.manage`
    - Add `POST /applications/bulk-reject` with `rbac:applications.manage`
    - Add `PATCH /jobs/{jobId}/stages/{stageId}` with `rbac:pipeline.manage`
    - Place bulk routes before the existing `/applications/{appId}/move` route to avoid conflicts
    - _Requirements: 10.1, 10.2, 10.3, 10.4_
  - [ ]* 4.6 Write property test for hex color validation (Property 13)
    - **Property 13: Hex color validation**
    - Generate random strings; verify acceptance only for valid `^#[0-9a-fA-F]{6}$` or null
    - **Validates: Requirements 10.5**

- [ ] 5. EmployerApplicationController search and sort enhancements
  - [x] 5.1 Add `q` query parameter support to `listForJob`
    - When `q` is provided, filter applications by joining with candidates table and matching name or email (case-insensitive `LIKE`)
    - _Requirements: 6.7, 10.6_
  - [x] 5.2 Add `sort` query parameter support to `listForJob`
    - Support `sort=applied_at` (descending, default) and `sort=candidate_name` (alphabetical ascending via join)
    - _Requirements: 10.7_
  - [ ]* 5.3 Write property test for server-side search (Property 9)
    - **Property 9: Server-side search filtering**
    - Generate random candidates and queries; verify API returns only matching results
    - **Validates: Requirements 6.7, 10.6**
  - [ ]* 5.4 Write property test for server-side sort (Property 10)
    - **Property 10: Server-side sort ordering**
    - Generate random candidates; verify ordering by `applied_at` desc or `candidate_name` asc
    - **Validates: Requirements 10.7**

- [x] 6. Checkpoint — Backend complete
  - Ensure all tests pass, ask the user if questions arise.

- [x] 7. Frontend type extensions and API functions
  - [x] 7.1 Extend frontend types in `frontend/src/types/job.ts`
    - Add `PipelineStageDetail` interface extending `PipelineStage` with `color: string | null`
    - Add `BulkActionResult` interface with `success_count`, `failed_count`, `failed_ids`
    - _Requirements: 7.2, 5.3, 5.4_
  - [x] 7.2 Create `frontend/src/lib/pipelineApi.ts` with new API functions
    - `bulkMoveApplications(appIds, stageId)` — POST to `/applications/bulk-move`
    - `bulkRejectApplications(appIds)` — POST to `/applications/bulk-reject`
    - `updatePipelineStage(jobId, stageId, data)` — PATCH to `/jobs/{jobId}/stages/{stageId}`
    - `fetchJobApplicationsWithSearch(jobId, params)` — GET `/jobs/{jobId}/applications` with `q`, `sort`, `page`, `per_page`
    - Follow existing `apiClient` patterns from `frontend/src/lib/jobApi.ts`
    - _Requirements: 5.3, 5.4, 6.7, 7.1, 7.2, 10.1, 10.2, 10.3, 10.4, 10.6, 10.7_

- [x] 8. KanbanProvider state management
  - [x] 8.1 Create `frontend/src/components/pipeline/KanbanProvider.tsx`
    - Define `KanbanState` and `KanbanAction` types as specified in the design
    - Implement `kanbanReducer` with all action types: `SET_DATA`, `SET_LOADING`, `SET_ERROR`, `MOVE_CARD_OPTIMISTIC`, `MOVE_CARD_CONFIRMED`, `MOVE_CARD_ROLLBACK`, `BULK_MOVE_OPTIMISTIC`, `BULK_MOVE_CONFIRMED`, `BULK_MOVE_PARTIAL_ROLLBACK`, `TOGGLE_SELECT`, `SELECT_ALL_IN_STAGE`, `CLEAR_SELECTION`, `SET_SEARCH`, `SET_STAGE_FILTER`, `SET_SORT`, `OPEN_SLIDE_OVER`, `CLOSE_SLIDE_OVER`, `UPDATE_STAGE`
    - Create React Context and `KanbanProvider` component that wraps children with `useReducer`
    - Export `useKanban` hook for consuming context
    - _Requirements: 2.3, 2.4, 2.5, 5.5, 6.1, 6.2, 6.3, 6.4_
  - [ ]* 8.2 Write property test for optimistic move and rollback (Property 2)
    - **Property 2: Optimistic move and rollback round-trip**
    - Generate random board states; dispatch `MOVE_CARD_OPTIMISTIC` then `MOVE_CARD_ROLLBACK`; assert state equality
    - **Validates: Requirements 2.3, 2.4, 2.5**
  - [ ]* 8.3 Write property test for client-side search filtering (Property 7)
    - **Property 7: Client-side search filtering**
    - Generate random candidate lists and search queries; verify filter returns exactly matching applications
    - **Validates: Requirements 6.1, 6.5**
  - [ ]* 8.4 Write property test for client-side stage filter and sort (Property 8)
    - **Property 8: Client-side stage filter and sort**
    - Generate random candidates, stages, sort options; verify filter and ordering correctness
    - **Validates: Requirements 6.2, 6.3, 6.4**

- [x] 9. KanbanBoard component with drag-and-drop
  - [x] 9.1 Create `frontend/src/components/pipeline/KanbanBoard.tsx`
    - Install `@dnd-kit/core` and `@dnd-kit/sortable` packages
    - Wrap board in `DndContext` with `PointerSensor`, `KeyboardSensor`, and `TouchSensor`
    - Implement `onDragEnd` handler: extract application ID and target stage ID, dispatch `MOVE_CARD_OPTIMISTIC`, call `moveApplication` API, dispatch confirm or rollback
    - Apply client-side search/filter/sort from `KanbanProvider` state before rendering columns
    - Render loading skeleton when `isLoading` is true (placeholder columns and cards)
    - Render error message with retry button when `error` is set
    - Add ARIA live region for announcing drag operations
    - On mobile (< 768px): render as horizontally scrollable snap container with stage navigation dots
    - _Requirements: 1.1, 1.4, 1.5, 1.6, 2.1, 2.2, 2.7, 9.1, 9.2, 11.1_

- [x] 10. StageColumn component
  - [x] 10.1 Create `frontend/src/components/pipeline/StageColumn.tsx`
    - Use `useDroppable` from `@dnd-kit` to register as a drop target
    - Render stage header with name, candidate count badge, and 4px top border using stage color
    - Highlight with blue border when a card is being dragged over (`isOver` prop)
    - Support inline editing of stage name on double-click (when `canCustomize` is true)
    - On inline edit submit: call `updatePipelineStage` API, dispatch `UPDATE_STAGE`
    - Vertical scroll for cards within column (`max-h` with `overflow-y-auto`)
    - Read-only display when user lacks `pipeline.manage` permission
    - _Requirements: 1.2, 7.3, 7.5, 7.6, 11.2_

- [x] 11. CandidateCard component
  - [x] 11.1 Create `frontend/src/components/pipeline/CandidateCard.tsx`
    - Use `useDraggable` from `@dnd-kit` to register as a drag source
    - Display candidate name, email, applied date, and resume link
    - Show selection checkbox when bulk selection mode is active
    - On click (not drag): dispatch `OPEN_SLIDE_OVER`
    - Semi-transparent appearance while being dragged
    - Disable drag when `canManage` is false
    - Keyboard accessible: Enter/Space to open slide-over, Ctrl+Arrow to move between stages
    - Minimum 44×44px touch target on mobile
    - _Requirements: 1.3, 2.1, 2.2, 2.6, 5.1, 9.6, 11.2, 11.3, 11.6_
  - [ ]* 11.2 Write property test for card and column rendering (Property 1)
    - **Property 1: Candidate card and stage column rendering completeness**
    - Generate random stage names, colors, counts, and candidate data; render components; verify all fields present
    - **Validates: Requirements 1.2, 1.3**

- [x] 12. Checkpoint — Core Kanban board functional
  - Ensure all tests pass, ask the user if questions arise.

- [x] 13. StageColorPicker component
  - [x] 13.1 Create `frontend/src/components/pipeline/StageColorPicker.tsx`
    - Render a small popover with a preset palette of 8-10 colors plus a "No color" option
    - On select: call `updatePipelineStage` API with the chosen color, dispatch `UPDATE_STAGE`
    - Only visible to users with `pipeline.manage` permission
    - Integrate into `StageColumn` header
    - _Requirements: 7.2, 7.3, 7.6_
  - [ ]* 13.2 Write property test for color contrast accessibility (Property 14)
    - **Property 14: Color contrast accessibility**
    - Generate random hex colors; compute WCAG contrast ratio; verify ≥ 4.5:1 for selected text color
    - **Validates: Requirements 11.5**

- [x] 14. PipelineSearchBar component
  - [x] 14.1 Create `frontend/src/components/pipeline/PipelineSearchBar.tsx`
    - Search input with 300ms debounce dispatching `SET_SEARCH`
    - Stage filter dropdown dispatching `SET_STAGE_FILTER`
    - Sort selector (Applied: Newest, Applied: Oldest, Name: A-Z) dispatching `SET_SORT`
    - Show match count and "Clear" button when search is active
    - For pipelines with > 200 candidates: trigger server-side API call with `q` parameter via `fetchJobApplicationsWithSearch` instead of client-side filtering
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7_

- [x] 15. BottomSheet component (mobile)
  - [x] 15.1 Create `frontend/src/components/pipeline/BottomSheet.tsx`
    - Slide-up panel from bottom of screen for mobile interactions
    - Display stage names with color indicators
    - Exclude the candidate's current stage from the list
    - Backdrop overlay; tap outside or swipe down to dismiss
    - ARIA: `role="dialog"`, focus trapped while open
    - Used for: swipe-to-move stage selection, bulk action menus, confirmation dialogs
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 9.4_
  - [ ]* 15.2 Write property test for bottom sheet stage exclusion (Property 3)
    - **Property 3: Bottom sheet excludes current stage**
    - Generate random stage lists and current stage; verify exclusion of current stage
    - **Validates: Requirements 3.4**

- [x] 16. Mobile swipe-to-move integration
  - [x] 16.1 Add swipe gesture handling to `CandidateCard` for touch devices
    - Detect horizontal swipe on touch devices
    - On swipe: open `BottomSheet` with available target stages
    - On stage selection from BottomSheet: call move API, dispatch optimistic update
    - On cancel: return card to original position, no API call
    - Disable swipe when user lacks `applications.manage` permission
    - Ensure touch scrolling within columns does not conflict with swipe gestures
    - _Requirements: 3.1, 3.2, 3.3, 3.5, 9.5_

- [x] 17. SlideOverPanel component
  - [x] 17.1 Create `frontend/src/components/pipeline/SlideOverPanel.tsx`
    - Right-side panel on desktop (viewport ≥ 768px), full-screen overlay on mobile (< 768px)
    - Fetch application detail and transition history from API on open
    - Display sections: candidate name/email, resume snapshot viewer, notes area, stage history timeline, quick action buttons
    - Stage history timeline: all transitions ordered by `moved_at` ascending, showing stage name, who moved, timestamp
    - Quick actions: "Move to…" dropdown, "Reject" button, "Shortlist" (next stage) button
    - Quick actions dispatch moves through KanbanProvider, updating the board in real time
    - Focus trap: Tab cycles within panel while open
    - Close via: close button (×), Escape key, click outside (desktop only)
    - Mobile: full-screen overlay with back button
    - Loading skeleton while fetching data
    - ARIA: `role="dialog"`, `aria-modal="true"`, `aria-labelledby` pointing to candidate name heading
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8, 4.9, 9.3, 11.4_
  - [ ]* 17.2 Write property test for slide-over content and timeline ordering (Property 4)
    - **Property 4: Slide-over panel content and timeline ordering**
    - Generate random application details and transitions; verify all sections present and timeline ordered by `moved_at` ascending
    - **Validates: Requirements 4.2, 4.3**

- [x] 18. BulkActionToolbar component
  - [x] 18.1 Create `frontend/src/components/pipeline/BulkActionToolbar.tsx`
    - Floating toolbar that appears when one or more candidates are selected
    - Show selected count, "Move to Stage" dropdown, "Reject All" button, "Clear Selection" button
    - On "Move to Stage": dispatch `BULK_MOVE_OPTIMISTIC`, call `bulkMoveApplications` API, handle confirm or partial rollback
    - On "Reject All": dispatch `BULK_MOVE_OPTIMISTIC` (to Rejected), call `bulkRejectApplications` API, handle confirm or partial rollback
    - Display summary toast on partial failure: "Moved {success_count} candidates. {failed_count} failed."
    - On mobile: render as a fixed bottom bar
    - Hidden when user lacks `applications.manage` permission
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7_

- [x] 19. Checkpoint — All components built
  - Ensure all tests pass, ask the user if questions arise.

- [x] 20. Wire Kanban board into the job detail page
  - [x] 20.1 Integrate KanbanProvider and KanbanBoard into `frontend/src/app/dashboard/jobs/[id]/page.tsx`
    - Replace the existing static pipeline columns and dropdown-based move UI with the new `KanbanProvider` + `KanbanBoard`
    - Pass job ID, user permissions (`applications.manage`, `pipeline.manage`), and initial data to the provider
    - Fetch pipeline stages (with colors) and applications on mount
    - Determine search strategy based on total candidate count (client-side ≤ 200, server-side > 200)
    - Ensure the existing job detail summary section and job description section remain unchanged
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 2.1, 6.6_

- [x] 21. Accessibility polish
  - [x] 21.1 Add ARIA live regions and keyboard navigation to KanbanBoard
    - ARIA live region announces stage changes, card movements, and bulk action results
    - Tab to move between Stage_Columns, Arrow keys between cards within a column, Enter/Space to open slide-over
    - Ctrl+Arrow Right/Left to move focused card to next/previous stage
    - Visible focus indicators on all interactive elements meeting WCAG 2.1 AA
    - Ensure color contrast ratio of at least 4.5:1 for text against Stage_Color backgrounds (use white or dark text based on luminance)
    - _Requirements: 11.1, 11.2, 11.3, 11.5, 11.6_

- [x] 22. Final checkpoint — All features integrated and accessible
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Backend tasks (1–6) should be completed before frontend tasks (7–22)
- The existing `PipelineService.moveApplication()` already creates `StageTransition` records and dispatches events — the new bulk methods follow the same pattern
- Property-based tests use `fast-check` (frontend/Vitest) and Pest (backend) as specified in the design
- The `@dnd-kit` library handles keyboard and touch sensors natively, reducing custom accessibility code
- Client-side search applies for ≤ 200 candidates; server-side search kicks in above that threshold
