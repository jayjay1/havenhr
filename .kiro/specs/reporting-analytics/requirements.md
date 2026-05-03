# Requirements Document

## Introduction

The Reporting & Analytics feature adds a dedicated reports dashboard to the HavenHR employer portal. It provides time-to-hire metrics, pipeline funnel analytics, candidate source tracking, and CSV export capabilities. All analytics are tenant-scoped and permission-gated. The frontend uses pure CSS/Tailwind bar charts consistent with the existing StageChart pattern. The backend extends the existing DashboardController pattern with new API endpoints.

## Glossary

- **Reports_Dashboard**: The frontend page at `/dashboard/reports` that displays all analytics sections and report controls.
- **Reports_API**: The set of Laravel API endpoints under `/api/v1/reports/` that compute and return analytics data.
- **CSV_Exporter**: The backend service responsible for generating CSV file responses from report data.
- **Date_Range_Filter**: A UI control and corresponding API query parameter pair (`start_date`, `end_date`) that constrains all metrics to a specific time period.
- **Time_To_Hire**: The number of calendar days between a candidate's `applied_at` date and the date the candidate reaches the final pipeline stage (highest `sort_order`) for a given job posting.
- **Stage_Duration**: The number of calendar days a candidate spends in a specific pipeline stage, calculated from stage_transitions records.
- **Pipeline_Funnel**: A visualization showing the count of candidates who reached each pipeline stage and the conversion rate between consecutive stages.
- **Conversion_Rate**: The percentage of candidates who moved from one pipeline stage to the next consecutive stage, calculated as (candidates entering next stage / candidates entering current stage) × 100.
- **Source**: The origin channel of a job application. For MVP, all applications are classified as "direct" since candidates apply through the portal. The data model supports future values such as "referral" and "job_board".
- **Offer_Acceptance_Rate**: The percentage of candidates who reached the final pipeline stage out of all candidates who reached the second-to-last stage.
- **Tenant**: The company entity that scopes all data in the multi-tenant HavenHR system.

## Requirements

### Requirement 1: Reports Sidebar Navigation

**User Story:** As an employer dashboard user with reports permission, I want to see a "Reports" link in the sidebar navigation, so that I can access the analytics dashboard.

#### Acceptance Criteria

1. THE Reports_Dashboard SHALL be accessible at the route `/dashboard/reports`.
2. THE Sidebar SHALL display a "Reports" navigation item with a chart-bar icon between "Audit Logs" and "Settings" in the navigation list.
3. WHEN the current user lacks the `reports.view` permission, THE Sidebar SHALL hide the "Reports" navigation item.
4. WHEN the user navigates to `/dashboard/reports`, THE Sidebar SHALL highlight the "Reports" item as the active page.

### Requirement 2: Reports Dashboard Overview

**User Story:** As an employer, I want to see key hiring metrics at a glance on the reports page, so that I can quickly assess recruitment performance.

#### Acceptance Criteria

1. WHEN the Reports_Dashboard loads, THE Reports_Dashboard SHALL display stat cards for average Time_To_Hire (in days), total hires count, and Offer_Acceptance_Rate (as a percentage).
2. WHEN the Reports_API receives a request for overview metrics, THE Reports_API SHALL compute all metrics scoped to the authenticated user's Tenant.
3. WHEN no applications exist for the selected date range, THE Reports_Dashboard SHALL display zero values for all overview metrics.
4. WHILE the overview metrics are loading, THE Reports_Dashboard SHALL display skeleton placeholders for each stat card.
5. IF the Reports_API returns an error, THEN THE Reports_Dashboard SHALL display an error message in place of the metrics.

### Requirement 3: Date Range Filtering

**User Story:** As an employer, I want to filter all report metrics by a date range, so that I can analyze recruitment performance for specific periods.

#### Acceptance Criteria

1. THE Reports_Dashboard SHALL display a Date_Range_Filter with start date and end date inputs.
2. THE Date_Range_Filter SHALL default to the last 30 days (start date = 30 days ago, end date = today).
3. WHEN the user selects a new date range, THE Reports_Dashboard SHALL re-fetch all report sections with the updated `start_date` and `end_date` parameters.
4. WHEN the Reports_API receives `start_date` and `end_date` query parameters, THE Reports_API SHALL filter all metrics to applications with `applied_at` within the specified range (inclusive).
5. IF the `start_date` is after the `end_date`, THEN THE Reports_API SHALL return a 422 validation error with a descriptive message.
6. WHEN no date range parameters are provided, THE Reports_API SHALL default to the last 30 days.

### Requirement 4: Time-to-Hire Analytics

**User Story:** As an employer, I want to see how long it takes to hire candidates, so that I can identify bottlenecks and improve the hiring process.

#### Acceptance Criteria

1. WHEN the Reports_API receives a request for time-to-hire data, THE Reports_API SHALL calculate the average Time_To_Hire in days across all completed hires within the date range for the Tenant.
2. THE Reports_API SHALL return the average Time_To_Hire broken down by job posting.
3. THE Reports_API SHALL return the average Stage_Duration for each pipeline stage name across all job postings within the date range.
4. THE Reports_API SHALL return monthly average Time_To_Hire values for the last 6 months to support trend visualization.
5. THE Reports_API SHALL return the average Time_To_Hire broken down by department.
6. WHEN the Reports_Dashboard receives time-to-hire data, THE Reports_Dashboard SHALL render a horizontal bar chart showing average Time_To_Hire per job using pure CSS/Tailwind styling.
7. WHEN the Reports_Dashboard receives stage duration data, THE Reports_Dashboard SHALL render a horizontal bar chart showing average days per stage.
8. WHEN the Reports_Dashboard receives trend data, THE Reports_Dashboard SHALL render a vertical bar chart showing monthly average Time_To_Hire for the last 6 months.
9. WHEN no hire data exists for the selected period, THE Reports_Dashboard SHALL display an empty state message in the time-to-hire section.

### Requirement 5: Pipeline Funnel Analytics

**User Story:** As an employer, I want to see how candidates progress through the hiring pipeline, so that I can identify stages where candidates drop off.

#### Acceptance Criteria

1. WHEN the Reports_API receives a request for funnel data, THE Reports_API SHALL return the count of candidates who reached each pipeline stage, ordered by stage `sort_order`.
2. THE Reports_API SHALL return the Conversion_Rate between each pair of consecutive pipeline stages.
3. WHEN a `job_id` query parameter is provided, THE Reports_API SHALL return funnel data scoped to that specific job posting.
4. WHEN no `job_id` query parameter is provided, THE Reports_API SHALL return an overall funnel aggregated across all job postings for the Tenant.
5. WHEN the Reports_Dashboard receives funnel data, THE Reports_Dashboard SHALL render a visual funnel with progressively narrower bars for each stage showing candidate count and Conversion_Rate.
6. THE Reports_Dashboard SHALL provide a job selector dropdown that allows the user to switch between per-job funnel view and the overall funnel view.
7. WHEN no application data exists for the selected job or date range, THE Reports_Dashboard SHALL display an empty state message in the funnel section.

### Requirement 6: Source Tracking

**User Story:** As an employer, I want to see where candidates are coming from, so that I can evaluate recruitment channel effectiveness.

#### Acceptance Criteria

1. WHEN the Reports_API receives a request for source data, THE Reports_API SHALL return the count of applications grouped by Source for the Tenant within the date range.
2. THE Reports_API SHALL classify all current applications as "direct" Source since all candidates apply through the HavenHR portal.
3. WHEN the Reports_Dashboard receives source data, THE Reports_Dashboard SHALL render a horizontal bar chart showing application count per Source.
4. WHEN no application data exists for the selected date range, THE Reports_Dashboard SHALL display an empty state message in the source tracking section.

### Requirement 7: CSV Export

**User Story:** As an employer, I want to export report data as CSV files, so that I can analyze the data in spreadsheet tools or share it with stakeholders.

#### Acceptance Criteria

1. THE Reports_Dashboard SHALL display an export button on each report section (overview, time-to-hire, funnel, source tracking).
2. WHEN the user clicks an export button, THE Reports_Dashboard SHALL send a request to the CSV_Exporter endpoint for that report section with the current date range parameters.
3. WHEN the CSV_Exporter receives a valid export request, THE CSV_Exporter SHALL return a CSV file with appropriate column headers and data rows.
4. THE CSV_Exporter SHALL set the `Content-Type` header to `text/csv` and the `Content-Disposition` header to `attachment` with a descriptive filename including the report type and date range.
5. THE CSV_Exporter SHALL scope all exported data to the authenticated user's Tenant.
6. IF the CSV_Exporter receives an invalid report type, THEN THE CSV_Exporter SHALL return a 422 validation error.
7. WHEN the export request is in progress, THE Reports_Dashboard SHALL disable the export button and display a loading indicator.

### Requirement 8: Reports API Authorization

**User Story:** As a system administrator, I want reports endpoints to be permission-gated, so that only authorized users can access analytics data.

#### Acceptance Criteria

1. THE Reports_API SHALL require the `reports.view` permission for all report data endpoints.
2. THE Reports_API SHALL require the `reports.view` permission for all CSV export endpoints.
3. WHEN an unauthenticated request is made to any Reports_API endpoint, THE Reports_API SHALL return a 401 status code.
4. WHEN an authenticated user without `reports.view` permission requests any Reports_API endpoint, THE Reports_API SHALL return a 403 status code.
