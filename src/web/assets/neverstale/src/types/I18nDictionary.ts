import AnalysisStatus  from '@/enums/AnalysisStatus.ts'
export interface I18nDictionary {
  ANALYSIS_STATUS_ANALYZED_CLEAN?: string
  ANALYSIS_STATUS_ANALYZED_ERROR?: string
  ANALYSIS_STATUS_ANALYZED_FLAGGED?: string
  ANALYSIS_STATUS_API_ERROR?: string
  ANALYSIS_STATUS_PENDING_INITIAL_ANALYSIS?: string
  ANALYSIS_STATUS_PENDING_REANALYSIS?: string
  ANALYSIS_STATUS_PENDING_TOKEN_AVAILABILITY?: string
  ANALYSIS_STATUS_PROCESSING_INITIAL_ANALYSIS?: string
  ANALYSIS_STATUS_PROCESSING_REANALYSIS?: string
  ANALYSIS_STATUS_STALE?: string
  ANALYSIS_STATUS_UNKNOWN?: string
  ANALYSIS_STATUS_UNSENT?: string
  CONTENT?: string
  CONTENT_EXPIRED?: string
  CONTENT_STATUS?: string
  DATE?: string
  DATE_IS_REQUIRED?: string
  ERROR_IGNORING_FLAG?: string
  ERROR_RESCHEDULING_FLAG?: string
  EXPIRED_AT?: string
  FLAG?: string
  FLAGS?: string
  IGNORE?: string
  IS_STALE_NOTICE?: string
  LAST_ANALYZED?: string
  NO_FLAGS_FOUND?: string
  PROPOSED_EXPIRATION?: string
  REASON?: string
  RELOAD_PAGE?: string
  REFRESH_DATA?: string
  RESCHEDULE?: string
  SNIPPET?: string
  SUBMIT?: string
  VIEW_IN_NEVERSTALE?: string
  VIEW_LOCAL_DETAILS?: string
  statusLabel(status: AnalysisStatus): string|undefined
}
