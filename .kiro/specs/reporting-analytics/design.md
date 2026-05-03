# Design Document: Reporting & Analytics

## Overview

This feature adds a dedicated Reporting & Analytics dashboard to the HavenHR employer portal. It introduces a new `ReportsController` on the backend with endpoints for overview metrics, time-to-hire analytics, pipeline funnel data, source tracking, and CSV export. The frontend adds a new `/dashboard/reports` page with pure CSS/Tailwind charts (no chart library), date range filtering, job selector, and per-section CSV export buttons.

The design follows existing patterns:
- **Backend**: Mirrors `DashboardController` — tenant-scoped queries via `job_postings.tenant_id` joins, JSON responses wrapped in `{ data: ... }`.
- **Frontend**: Reuses `apiClient` for data fetching, `StatCard` for overview metrics, and the `StageChart` horizontal bar pattern for new chart components.
- **Authorization**: Uses the existing `rbac:reports.view` middleware (permission already seeded).

### Key Design Decisions

1. **No chart library** — All charts use pure CSS/Tailwind with percentage-width bars, consistent with the existing `StageChart` component. This keeps the bundle small and avoids a new dependency.
2. **Single controller, multiple endpoints** — One `ReportsController` with methods for each report section, rather than separate controllers. This mirrors how `DashboardController` groups related metrics.
3. **Query-time computation** — All metrics are computed on-the-fly from `job_applications`, `stage_transitions`, and `pipeline_stages` tables. No materialized views or caching layer for MVP. The data volumes per tenant are small enough that indexed queries perform well.
4. **CSV export via dedicated endpoints** — Each report section has a corresponding `/export` endpoint that returns `text/csv` with `Content-Disposition: attachment`. This avoids client-side CSV generation and keeps the export logic server-side where tenant scoping is enforced.

## Architecture

```mermaid
graph TB
    subgraph Frontend ["Next.js Frontend"]
        RP["/dashboard/reports page"]
        DRF["DateRangeFilter component"]
        JS["JobSelector component"]
        SC["StatCard components"]
        TTH["TimeToHireChart component"]
        SD["StageDurationChart component"]
        TT["TrendChart component"]
        FC["FunnelChart component"]
        SRC["SourceChart component"]
        EB["ExportButton components"]
    end

    subgraph Backend ["Laravel Backend"]
        RC["ReportsController"]
        RBAC["rbac:reports.view middleware"]
        CSV["CSV response helpers"]
    end

    subgraph Database ["Database"]
        JA["job_applications"]
        ST["stage_transitions"]
        PS["pipeline_stages"]
        JP["job_postings"]
    end

    RP --> DRF
    RP --> JS
    RP --> SC
    RP --> TTH
    RP --> SD
    RP --> TT
    RP --> FC
    RP --> SRC
    RP --> EB

    DRF -->|date params| RC
    JS -->|job_id param| RC
    SC -->|GET /reports/overview| RC
    TTH -->|GET /reports/time-to-hire| RC
    FC -->|GET /reports/funnel| RC
    SRC -->|GET /reports/sources| RC
    EB -->|GET /reports/export/{type}| RC

    RC --> RBAC
    RC --> CSV
    RC --> JA
    RC --> ST
    RC --> PS
    RC --> JP
```

### Request Flow

1. User navigates to `/dashboard/reports` (sidebar link, gated by `reports.view` permission)
2. Frontend page mounts, reads default date range (last 30 days), fires parallel API requests
3. Each request hits `rbac:reports.view` middleware → `ReportsController` method
4. Controller queries tenant-scoped data, computes metrics, returns JSON (or CSV for export)
5. Frontend renders charts using pure CSS/Tailwind bar components

## Components and Interfaces

### Backend API Endpoints

All endpoints are prefixed with `/api/v1/reports` and require `rbac:reports.view` middleware.

#### GET /reports/overview

Returns high-level hiring metrics.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| start_date | string (Y-m-d) | No | 30 days ago | Start of date range |
| end_date | string (Y-m-d) | No | today | End of date range |

**Response:**
```json
{
  "data": {
    "avg_time_to_hire": 18.5,
    "total_hires": 12,
    "offer_acceptance_rate": 75.0
  }
}
```

#### GET /reports/time-to-hire

Returns time-to-hire breakdowns by job, department, stage duration, and monthly trend.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| start_date | string (Y-m-d) | No | 30 days ago | Start of date range |
| end_date | string (Y-m-d) | No | today | End of date range |

**Response:**
```json
{
  "data": {
    "by_job": [
      { "job_id": "uuid", "job_title": "Software Engineer", "avg_days": 21.3, "hire_count": 4 }
    ],
    "by_department": [
      { "department": "Engineering", "avg_days": 19.2, "hire_count": 6 }
    ],
    "by_stage": [
      { "stage_name": "Applied", "avg_days": 3.2 },
      { "stage_name": "Screening", "avg_days": 5.1 }
    ],
    "trend": [
      { "month": "2025-01", "avg_days": 20.1 },
      { "month": "2025-02", "avg_days": 18.3 }
    ]
  }
}
```

#### GET /reports/funnel

Returns pipeline funnel data with candidate counts and conversion rates per stage.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| start_date | string (Y-m-d) | No | 30 days ago | Start of date range |
| end_date | string (Y-m-d) | No | today | End of date range |
| job_id | string (UUID) | No | null | Filter to specific job posting |

**Response:**
```json
{
  "data": {
    "stages": [
      { "stage_name": "Applied", "count": 100, "conversion_rate": null },
      { "stage_name": "Screening", "count": 60, "conversion_rate": 60.0 },
      { "stage_name": "Interview", "count": 30, "conversion_rate": 50.0 },
      { "stage_name": "Offer", "count": 12, "conversion_rate": 40.0 }
    ],
    "job_id": null
  }
}
```

**Funnel Calculation Logic:**
- "Reached" a stage means the candidate's current `pipeline_stage_id` has `sort_order >= stage.sort_order`, OR there exists a `stage_transitions` record with `to_stage_id` matching that stage.
- Conversion rate for stage N = (count at stage N / count at stage N-1) × 100. First stage has `null` conversion rate.
- When `job_id` is null, stages are aggregated by `pipeline_stages.name` across all job postings (since each job has its own pipeline stages with the same default names).

#### GET /reports/sources

Returns application counts grouped by source.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| start_date | string (Y-m-d) | No | 30 days ago | Start of date range |
| end_date | string (Y-m-d) | No | today | End of date range |

**Response:**
```json
{
  "data": [
    { "source": "direct", "count": 85 }
  ]
}
```

For MVP, all applications are classified as "direct". The `source` field will be derived as a constant since the `job_applications` table doesn't have a source column yet. This is future-proofed by returning it as a grouped result.

#### GET /reports/export/{type}

Returns CSV file for the specified report type.

**Path Parameters:**
| Parameter | Values | Description |
|-----------|--------|-------------|
| type | overview, time-to-hire, funnel, sources | Report section to export |

**Query Parameters:** Same as the corresponding data endpoint (start_date, end_date, job_id for funnel).

**Response:** `text/csv` with `Content-Disposition: attachment; filename="havenhr-{type}-{start_date}-to-{end_date}.csv"`

### Frontend Components

#### ReportsPage (`frontend/src/app/dashboard/reports/page.tsx`)

Top-level page component. Manages date range state, fetches all report sections in parallel, passes data to child components.

#### DateRangeFilter (`frontend/src/components/reports/DateRangeFilter.tsx`)

Two date inputs (start, end) with default last-30-days. Calls `onChange(startDate, endDate)` when user updates either field.

#### JobSelector (`frontend/src/components/reports/JobSelector.tsx`)

Dropdown that lists all job postings for the tenant. Includes an "All Jobs" option. Calls `onChange(jobId | null)`.

#### TimeToHireChart (`frontend/src/components/reports/TimeToHireChart.tsx`)

Horizontal bar chart showing average time-to-hire per job posting. Follows the `StageChart` pattern — bars proportional to max value, pure CSS widths.

#### StageDurationChart (`frontend/src/components/reports/StageDurationChart.tsx`)

Horizontal bar chart showing average days per pipeline stage.

#### TrendChart (`frontend/src/components/reports/TrendChart.tsx`)

Vertical bar chart showing monthly average time-to-hire for the last 6 months. Bars grow upward from a baseline using CSS `flex-end` alignment.

#### FunnelChart (`frontend/src/components/reports/FunnelChart.tsx`)

Visual funnel with progressively narrower horizontal bars. Each bar shows stage name, candidate count, and conversion rate percentage. Bar widths are proportional to the first stage count.

#### SourceChart (`frontend/src/components/reports/SourceChart.tsx`)

Horizontal bar chart showing application count per source. Same pattern as `StageChart`.

#### ExportButton (`frontend/src/components/reports/ExportButton.tsx`)

Reusable button that triggers CSV download. Accepts `reportType`, `startDate`, `endDate`, and optional `jobId`. Uses `fetch` directly (not `apiClient`) to handle the binary CSV response and trigger a browser download via `URL.createObjectURL`.

### Sidebar Update

Add a "Reports" `NavItem` to the `NAV_ITEMS` array in `Sidebar.tsx`:

```typescript
{
  label: "Reports",
  href: "/dashboard/reports",
  permission: "reports.view",
  icon: "reports",  // new icon type
}
```

This requires adding `"reports"` to the `NavItem.icon` union type and a corresponding `NavIcon` case with a chart-bar SVG icon. The item is inserted between "Audit Logs" and "Settings".

## Data Models

### Existing Tables Used (No Schema Changes)

The feature computes all metrics from existing tables. No new tables or columns are needed for MVP.

#### job_applications
| Column | Type | Usage |
|--------|------|-------|
| id | UUID | Primary key |
| candidate_id | UUID | FK to candidates |
| job_posting_id | UUID | FK to job_postings (tenant scoping) |
| pipeline_stage_id | UUID | FK to pipeline_stages (current stage) |
| status | string | Application status |
| applied_at | datetime | Date range filtering, time-to-hire start |

#### stage_transitions
| Column | Type | Usage |
|--------|------|-------|
| id | UUID | Primary key |
| job_application_id | UUID | FK to job_applications |
| from_stage_id | UUID | FK to pipeline_stages |
| to_stage_id | UUID | FK to pipeline_stages |
| moved_at | datetime | Stage duration calculation |
| moved_by | UUID | FK to users |

#### pipeline_stages
| Column | Type | Usage |
|--------|------|-------|
| id | UUID | Primary key |
| job_posting_id | UUID | FK to job_postings |
| name | string | Stage label for grouping |
| sort_order | integer | Stage ordering, funnel sequence |
| color | string | Stage color (unused in reports) |

#### job_postings
| Column | Type | Usage |
|--------|------|-------|
| id | UUID | Primary key |
| tenant_id | UUID | Tenant scoping |
| title | string | Display in by-job breakdowns |
| department | string | Department grouping |
| status | string | Job status |

### Key Query Patterns

**Time-to-Hire Calculation:**
A "hire" is defined as an application whose current `pipeline_stage_id` points to the stage with the highest `sort_order` for that job posting. Time-to-hire = days between `applied_at` and the `moved_at` of the `stage_transitions` record where `to_stage_id` is the final stage.

**Stage Duration Calculation:**
For each application, stage duration is computed from consecutive `stage_transitions` records. Duration in stage S = `moved_at` of transition OUT of S minus `moved_at` of transition INTO S (or `applied_at` for the first stage).

**Funnel "Reached" Logic:**
A candidate "reached" stage S if:
1. Their current `pipeline_stage_id` has `sort_order >= S.sort_order`, OR
2. A `stage_transitions` record exists with `to_stage_id = S.id`

This ensures candidates who have moved past a stage are still counted as having reached it.


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Permission-based navigation filtering

*For any* set of navigation items and any permission function, `filterNavItems` shall return only items whose required permission is `null` or returns `true` from the permission function. Items requiring a permission the user lacks shall never appear in the result.

**Validates: Requirements 1.3**

### Property 2: Tenant-scoped report data

*For any* tenant with applications and any other tenant's applications in the database, all report endpoints (overview, time-to-hire, funnel, sources, and CSV export) shall return metrics computed exclusively from the authenticated tenant's data. No data from other tenants shall influence the results.

**Validates: Requirements 2.2, 7.5**

### Property 3: Date range filtering

*For any* date range (start_date, end_date) where start_date ≤ end_date, and any set of applications with various `applied_at` dates, all report endpoints shall include only applications whose `applied_at` falls within the inclusive range [start_date, end_date]. Applications outside this range shall not contribute to any metric.

**Validates: Requirements 3.4**

### Property 4: Time-to-hire average computation

*For any* set of completed hires (applications that reached the final pipeline stage), the reported average time-to-hire shall equal the arithmetic mean of the individual time-to-hire values (days between `applied_at` and the `moved_at` timestamp of the transition to the final stage). When no hires exist, the average shall be 0.

**Validates: Requirements 4.1**

### Property 5: Time-to-hire grouping consistency

*For any* set of completed hires grouped by job posting or by department, the weighted average of the group averages (weighted by hire count per group) shall equal the overall average time-to-hire. Every hire shall appear in exactly one group, and no hire shall be omitted.

**Validates: Requirements 4.2, 4.5**

### Property 6: Stage duration computation

*For any* application with a sequence of stage transitions, the computed stage duration for stage S shall equal the elapsed time between the transition INTO stage S and the transition OUT of stage S (or the current time if the application is still in stage S). For the initial stage, the entry time is `applied_at`.

**Validates: Requirements 4.3**

### Property 7: Monthly trend grouping

*For any* set of completed hires spanning multiple months, the monthly trend shall group hires by the month of their completion (final stage transition `moved_at`), and each month's average shall be the arithmetic mean of that month's individual time-to-hire values. Every hire shall appear in exactly one month bucket.

**Validates: Requirements 4.4**

### Property 8: Funnel counts, conversion rates, and job filtering

*For any* set of applications and stage transitions, the funnel shall report stages in `sort_order` ascending. The count for each stage shall equal the number of distinct candidates who reached that stage. The conversion rate for stage N (N > 0) shall equal `(count_N / count_{N-1}) × 100`. When a `job_id` filter is provided, only that job's applications shall be counted; when omitted, all jobs shall be included. Funnel counts shall be monotonically non-increasing (each stage count ≤ previous stage count).

**Validates: Requirements 5.1, 5.2, 5.3, 5.4**

### Property 9: CSV output structure

*For any* report data set, the CSV export shall produce output where the first row contains column headers matching the report type's schema, and subsequent rows correspond one-to-one with the data items. Parsing the CSV back into structured data shall yield values equivalent to the JSON endpoint's response.

**Validates: Requirements 7.3**

### Property 10: Permission gating for all report endpoints

*For any* authenticated user without the `reports.view` permission, all report endpoints (data and export) shall return a 403 status code. No report data shall be included in the response body.

**Validates: Requirements 8.1, 8.2**

## Error Handling

### Backend Error Handling

| Scenario | HTTP Status | Error Code | Message |
|----------|-------------|------------|---------|
| Unauthenticated request | 401 | UNAUTHENTICATED | "Authentication required." |
| Missing `reports.view` permission | 403 | FORBIDDEN | "You do not have permission to access this resource." |
| `start_date` after `end_date` | 422 | VALIDATION_ERROR | "The start date must be before or equal to the end date." |
| Invalid date format | 422 | VALIDATION_ERROR | "The start date must be a valid date in Y-m-d format." |
| Invalid export type | 422 | VALIDATION_ERROR | "Invalid report type. Must be one of: overview, time-to-hire, funnel, sources." |
| Invalid `job_id` (not found or wrong tenant) | 422 | VALIDATION_ERROR | "The selected job posting was not found." |
| Database query failure | 500 | INTERNAL_ERROR | "An unexpected error occurred." (logged server-side) |

### Frontend Error Handling

- Each report section independently manages its own loading/error state (same pattern as `DashboardPage`).
- API errors are caught via `ApiRequestError` and displayed as inline error messages within the affected section.
- Other sections continue to render normally if one section fails.
- Export errors show a toast-style notification or inline error near the export button.
- Network errors (fetch failures) display a generic "Failed to load data" message.

### Date Validation

- Frontend: HTML5 date inputs provide basic format validation. Additional client-side check prevents submitting `start_date > end_date`.
- Backend: Laravel validation rules enforce `date_format:Y-m-d` and `before_or_equal:end_date` / `after_or_equal:start_date`.

## Testing Strategy

### Property-Based Tests (Backend - PHP with PHPUnit + custom generators)

Property-based testing is appropriate for this feature because the core logic involves data aggregation, filtering, and mathematical computations over varying input sets. The input space (different combinations of applications, transitions, dates, tenants) is large and edge cases emerge from input variation.

**Library**: Use PHPUnit with custom data generators (Laravel factories + randomized test data). Each property test runs a minimum of **100 iterations** with randomized inputs.

**Properties to implement:**
- Property 1: `filterNavItems` permission filtering (frontend — use `fast-check` with TypeScript)
- Property 2: Tenant scoping across all endpoints
- Property 3: Date range filtering
- Property 4: Time-to-hire average computation
- Property 5: Grouping consistency (weighted averages)
- Property 6: Stage duration computation
- Property 7: Monthly trend grouping
- Property 8: Funnel counts, conversion rates, and filtering
- Property 9: CSV output structure (round-trip)
- Property 10: Permission gating

**Tag format**: `Feature: reporting-analytics, Property {N}: {title}`

### Unit Tests (Example-Based)

**Backend:**
- `ReportsController@overview` returns correct JSON structure
- `ReportsController@overview` defaults to last 30 days
- `ReportsController@overview` returns zeros for empty data
- `ReportsController@timeToHire` returns all breakdown sections
- `ReportsController@funnel` returns stages in sort_order
- `ReportsController@sources` returns "direct" for all applications
- `ReportsController@export` returns correct Content-Type and Content-Disposition headers
- `ReportsController@export` returns 422 for invalid type
- Date validation rejects `start_date > end_date` with 422
- Unauthenticated requests return 401

**Frontend:**
- `DateRangeFilter` renders with default last-30-days values
- `DateRangeFilter` calls onChange when dates are updated
- `JobSelector` renders job options and "All Jobs" default
- `TimeToHireChart` renders bars proportional to max value
- `FunnelChart` renders progressively narrower bars
- `TrendChart` renders vertical bars for each month
- `SourceChart` renders horizontal bars
- `ExportButton` triggers download and shows loading state
- `ReportsPage` displays skeleton placeholders while loading
- `ReportsPage` displays error messages on API failure
- Sidebar shows Reports item for users with `reports.view`
- Sidebar hides Reports item for users without `reports.view`

### Integration Tests

- Full request lifecycle: authenticated user with `reports.view` → endpoint → correct JSON response
- Permission denied: authenticated user without `reports.view` → 403
- CSV export: verify downloadable CSV file with correct data
- Date range edge cases: same start and end date, very wide range
- Funnel with `job_id` filter vs. overall aggregation
