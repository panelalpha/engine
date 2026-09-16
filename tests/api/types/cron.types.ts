export interface CronJob {
  hash: string;
  minute: string;
  hour: string;
  month: string;
  command: string;
  /** OpenAPI response field names */
  day?: string;
  weekday?: string;
  /** Request / legacy field names */
  day_of_month?: string;
  day_of_week?: string;
}

export interface CreateCronJobRequest {
  minute: string;
  hour: string;
  day_of_month: string;
  month: string;
  day_of_week: string;
  command: string;
}
