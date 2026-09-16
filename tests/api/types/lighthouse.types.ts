export interface GenerateLighthouseReportRequest {
  url: string;
  desktop_preset?: boolean;
  no_local_resolve?: boolean;
}

export type LighthouseReport = Record<string, unknown>;
