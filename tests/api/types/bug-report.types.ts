export interface CreateBugReportRequest {
  project: string;
  title: string;
  description: string;
  severity?: string;
  area?: string;
  contact?: string;
  attach_log?: boolean;
  attach_health?: boolean;
}

export interface BugReportAccepted {
  id: string;
  status: string;
  queued: boolean;
  endpoint?: string;
  report?: Record<string, unknown>;
}
