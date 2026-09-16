export interface ApiResponse<T> {
  data: T;
  message?: string;
}

export interface ApiListResponse<T> {
  data: T[];
  meta?: {
    current_page: number;
    per_page: number;
    total: number;
  };
}

export interface ApiErrorResponse {
  message: string;
  errors?: Record<string, string[]>;
}

/** Unparsed response from *Raw API methods. */
export interface RawApiResponse {
  status: number;
  body: unknown;
}
