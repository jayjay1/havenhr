/**
 * Standard successful API response wrapper.
 */
export interface ApiResponse<T> {
  data: T;
}

/**
 * Standard API error response format.
 */
export interface ApiError {
  error: {
    code: string;
    message: string;
    details?: Record<string, unknown>;
  };
}

/**
 * Validation error details returned on 422 responses.
 */
export interface ValidationErrorDetails {
  fields: Record<
    string,
    {
      value: string | "[REDACTED]";
      messages: string[];
    }
  >;
}

/**
 * Paginated response wrapper for list endpoints.
 */
export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}
