# Implementation Plan: Reporting & Analytics

## Overview

This plan implements the Reporting & Analytics feature in incremental steps: backend report endpoints first, then frontend chart components and page, and finally sidebar integration and export wiring. Backend work uses PHP/Laravel 11; frontend work uses TypeScript/Next.js 15 with React. Each task builds on previous tasks so there is no orphaned code. All report data is computed from existing tables — no migrations needed.

## Tasks

- [x] 1. Create ReportsController with overview endpoint and route registration
  - [ ] 1.1 Create `backend/app/Http/Controllers/ReportsController.php` with `overview()` method
    - Parse `start_date` and `end_date` query parameters with defaults (last 30 days)
    - Validate date format (`Y-m-d`) and that `start_date <= end_date`, return 422 on failure
    - Compute `avg_time_to_hire`: average days between `applied_at` and the `moved_at` of the transition to the final stage (highest `sort_order`) for each job, scoped to tenant and date range; return 0 when no hires
    - Compute `total_hires`: count of applications that reached the final pipeline stage within the date range
    - Compute `offer_acceptance_rate`: percentage of candidates at the final stage out of those who reached the second-to-last stage; return 0 when no data
    - Return `{ data: { avg_time_to_hire, total_hires, offer_acceptance_rate } }`
    - _Requirements: 2.1, 2.2, 2.3, 3.4, 3.5, 3.6_

  - [ ] 1.2 Register report routes in `backend/routes/api.php`
    - Add route group under `/reports` with `havenhr.auth`, `tenant.resolve`, and `rbac:reports.view` middleware
    - Register `GET /reports/overview` → `ReportsController@overview`
    - _Requirements: 8.1, 8.3, 8.4_

- [ ] 2. Add time-to-hire endpoint to ReportsController
  - [ ] 2.1 Add `timeToHire()` method to `ReportsController`
    - Parse and validate `start_date` / `end_date` query parameters (same as overview)
    - Compute `by_job`: average time-to-hire and hire count grouped by job posting (`job_id`, `job_title`, `avg_days`, `hire_count`)
    - Compute `by_department`: average time-to-hire and hire count grouped by `job_postings.department`
    - Compute `by_stage`: average stage duration per pipeline stage name across all job postings within the date range, using consecutive `stage_transitions` records
    - Compute `trend`: monthly average time-to-hire for the last 6 months, grouped by the month of the final-stage transition `moved_at`
    - Return `{ data: { by_job, by_department, by_stage, trend } }`
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

  - [ ] 2.2 Register `GET /reports/time-to-hire` route → `ReportsController@timeToHire`
    - _Requirements: 8.1_

  - [ ]* 2.3 Write property test for time-to-hire average computation
    - **Property 4: Time-to-hire average computation**
    - **Validates: Requirements 4.1**

  - [ ]* 2.4 Write property test for time-to-hire grouping consistency
    - **Property 5: Time-to-hire grouping consistency**
    - **Validates: Requirements 4.2, 4.5**

  - [ ]* 2.5 Write property test for stage duration computation
    - **Property 6: Stage duration computation**
    - **Validates: Requirements 4.3**

  - [ ]* 2.6 Write property test for monthly trend grouping
    - **Property 7: Monthly trend grouping**
    - **Validates: Requirements 4.4**

- [ ] 3. Add funnel and sources endpoints to ReportsController
  - [ ] 3.1 Add `funnel()` method to `ReportsController`
    - Parse and validate `start_date`, `end_date`, and optional `job_id` query parameters
    - If `job_id` provided, validate it belongs to the tenant; return 422 if not found
    - Compute funnel stages ordered by `sort_order`: count distinct candidates who "reached" each stage (current `pipeline_stage_id` with `sort_order >= stage.sort_order` OR `stage_transitions` record with matching `to_stage_id`)
    - Compute conversion rate for each stage N (N > 0) as `(count_N / count_{N-1}) × 100`; first stage has `null` conversion rate
    - When `job_id` is null, aggregate by `pipeline_stages.name` across all job postings
    - Return `{ data: { stages: [{ stage_name, count, conversion_rate }], job_id } }`
    - _Requirements: 5.1, 5.2, 5.3, 5.4_

  - [ ] 3.2 Add `sources()` method to `ReportsController`
    - Parse and validate `start_date` / `end_date` query parameters
    - Return all applications within the date range classified as "direct" source
    - Return `{ data: [{ source, count }] }`
    - _Requirements: 6.1, 6.2_

  - [ ] 3.3 Register `GET /reports/funnel` and `GET /reports/sources` routes
    - _Requirements: 8.1_

  - [ ]* 3.4 Write property test for funnel counts, conversion rates, and job filtering
    - **Property 8: Funnel counts, conversion rates, and job filtering**
    - **Validates: Requirements 5.1, 5.2, 5.3, 5.4**

  - [ ]* 3.5 Write property test for date range filtering
    - **Property 3: Date range filtering**
    - **Validates: Requirements 3.4**

  - [ ]* 3.6 Write property test for tenant-scoped report data
    - **Property 2: Tenant-scoped report data**
    - **Validates: Requirements 2.2, 7.5**

- [ ] 4. Add CSV export endpoint to ReportsController
  - [ ] 4.1 Add `export()` method to `ReportsController`
    - Accept `{type}` path parameter; validate against allowed values: `overview`, `time-to-hire`, `funnel`, `sources`; return 422 for invalid type
    - Parse same query parameters as the corresponding data endpoint (`start_date`, `end_date`, `job_id` for funnel)
    - Reuse the computation logic from the corresponding data method
    - Return `text/csv` response with `Content-Disposition: attachment; filename="havenhr-{type}-{start_date}-to-{end_date}.csv"`
    - Include appropriate column headers as the first CSV row for each report type
    - _Requirements: 7.3, 7.4, 7.5, 7.6_

  - [ ] 4.2 Register `GET /reports/export/{type}` route
    - _Requirements: 8.1, 8.2_

  - [ ]* 4.3 Write property test for CSV output structure
    - **Property 9: CSV output structure**
    - **Validates: Requirements 7.3**

  - [ ]* 4.4 Write property test for permission gating on all report endpoints
    - **Property 10: Permission gating for all report endpoints**
    - **Validates: Requirements 8.1, 8.2**

- [ ] 5. Checkpoint — Backend complete
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 6. Build DateRangeFilter and JobSelector components
  - [ ] 6.1 Create `frontend/src/components/reports/DateRangeFilter.tsx`
    - Accept `startDate`, `endDate`, and `onChange(startDate, endDate)` props
    - Render two `type="date"` inputs with labels "Start Date" and "End Date"
    - Default to last 30 days (start = 30 days ago, end = today)
    - Call `onChange` when either input changes
    - Prevent `start_date > end_date` on the client side
    - _Requirements: 3.1, 3.2, 3.3_

  - [ ] 6.2 Create `frontend/src/components/reports/JobSelector.tsx`
    - Accept `jobId`, `onChange(jobId | null)`, and `jobs` array props
    - Render a `<select>` dropdown with "All Jobs" as the default option and each job posting as an option
    - Call `onChange` with the selected job ID or `null` for "All Jobs"
    - _Requirements: 5.6_

- [ ] 7. Build chart components for reports
  - [ ] 7.1 Create `frontend/src/components/reports/TimeToHireChart.tsx`
    - Accept `data` (array of `{ job_title, avg_days, hire_count }`) and `loading` props
    - Render horizontal bar chart following the `StageChart` pattern — bars proportional to max `avg_days`, pure CSS/Tailwind widths
    - Show skeleton placeholder when loading; show empty state message when no data
    - _Requirements: 4.6_

  - [ ] 7.2 Create `frontend/src/components/reports/StageDurationChart.tsx`
    - Accept `data` (array of `{ stage_name, avg_days }`) and `loading` props
    - Render horizontal bar chart showing average days per pipeline stage
    - Show skeleton placeholder when loading; show empty state message when no data
    - _Requirements: 4.7_

  - [ ] 7.3 Create `frontend/src/components/reports/TrendChart.tsx`
    - Accept `data` (array of `{ month, avg_days }`) and `loading` props
    - Render vertical bar chart with bars growing upward from a baseline using CSS `flex-end` alignment
    - Show month labels below each bar, avg_days value above each bar
    - Show skeleton placeholder when loading; show empty state message when no data
    - _Requirements: 4.8_

  - [ ] 7.4 Create `frontend/src/components/reports/FunnelChart.tsx`
    - Accept `data` (array of `{ stage_name, count, conversion_rate }`) and `loading` props
    - Render progressively narrower horizontal bars; bar widths proportional to first stage count
    - Display stage name, candidate count, and conversion rate percentage on each bar
    - Show skeleton placeholder when loading; show empty state message when no data
    - _Requirements: 5.5_

  - [ ] 7.5 Create `frontend/src/components/reports/SourceChart.tsx`
    - Accept `data` (array of `{ source, count }`) and `loading` props
    - Render horizontal bar chart following the `StageChart` pattern
    - Show skeleton placeholder when loading; show empty state message when no data
    - _Requirements: 6.3_

  - [ ] 7.6 Create `frontend/src/components/reports/ExportButton.tsx`
    - Accept `reportType`, `startDate`, `endDate`, and optional `jobId` props
    - On click, use `fetch` directly (not `apiClient`) to request `GET /reports/export/{reportType}` with auth header and query params
    - Handle the binary CSV response: create a `Blob`, generate `URL.createObjectURL`, trigger download via a temporary `<a>` element
    - Show loading state while export is in progress; disable button during download
    - Show error message on failure
    - _Requirements: 7.1, 7.2, 7.7_

- [ ] 8. Build ReportsPage and wire all components together
  - [ ] 8.1 Create `frontend/src/app/dashboard/reports/page.tsx`
    - Manage date range state (default last 30 days) and job selector state
    - On mount and on date range / job change, fetch all report sections in parallel using `apiClient`:
      - `GET /reports/overview?start_date=...&end_date=...`
      - `GET /reports/time-to-hire?start_date=...&end_date=...`
      - `GET /reports/funnel?start_date=...&end_date=...&job_id=...`
      - `GET /reports/sources?start_date=...&end_date=...`
      - `GET /jobs` (for job selector dropdown)
    - Render `DateRangeFilter` at the top of the page
    - Render overview stat cards using the existing `StatCard` component for avg time-to-hire, total hires, and offer acceptance rate
    - Render `TimeToHireChart` with by-job data
    - Render `StageDurationChart` with by-stage data
    - Render `TrendChart` with monthly trend data
    - Render `JobSelector` above the funnel section
    - Render `FunnelChart` with funnel data
    - Render `SourceChart` with source data
    - Place `ExportButton` on each report section (overview, time-to-hire, funnel, sources)
    - Each section independently manages loading and error states
    - Show skeleton placeholders while loading; show inline error messages on API failure
    - _Requirements: 2.1, 2.3, 2.4, 2.5, 3.3, 4.6, 4.7, 4.8, 4.9, 5.5, 5.6, 5.7, 6.3, 6.4, 7.1_

- [ ] 9. Update Sidebar with Reports navigation item
  - [ ] 9.1 Update `frontend/src/components/dashboard/Sidebar.tsx`
    - Add `"reports"` to the `NavItem.icon` union type
    - Add a chart-bar SVG icon case in the `NavIcon` component for `"reports"`
    - Add a "Reports" entry to `NAV_ITEMS` array between "Audit Logs" and "Settings": `{ label: "Reports", href: "/dashboard/reports", permission: "reports.view", icon: "reports" }`
    - _Requirements: 1.1, 1.2, 1.3, 1.4_

  - [ ]* 9.2 Write property test for permission-based navigation filtering
    - **Property 1: Permission-based navigation filtering**
    - **Validates: Requirements 1.3**

- [ ] 10. Final checkpoint — All features complete
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design document
- Unit tests validate specific examples and edge cases
- No database migrations are needed — all metrics are computed from existing `job_applications`, `stage_transitions`, `pipeline_stages`, and `job_postings` tables
- Backend follows existing patterns: tenant scoping via `job_postings.tenant_id` joins, `{ data: ... }` response shape, `rbac:` middleware for permission gating
- Frontend follows existing patterns: `apiClient` for JSON data fetching, `StatCard` for overview metrics, `StageChart` horizontal bar pattern for new chart components, `useAuth().hasPermission()` for RBAC
- CSV export uses `fetch` directly (not `apiClient`) to handle binary response and trigger browser download
