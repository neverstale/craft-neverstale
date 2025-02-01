enum AnalysisStatus {
  UNSENT = 'unsent',
  STALE = 'stale',
  PENDING_INITIAL_ANALYSIS = 'pending-initial-analysis',
  PENDING_REANALYSIS = 'pending-reanalysis',
  PENDING_TOKEN_AVAILABILITY = 'pending-token-availability',
  PROCESSING_REANALYSIS = 'processing-reanalysis',
  PROCESSING_INITIAL_ANALYSIS = 'processing-initial-analysis',
  ANALYZED_CLEAN = 'analyzed-clean',
  ANALYZED_FLAGGED = 'analyzed-flagged',
  ANALYZED_ERROR = 'analyzed-error',
  UNKNOWN = 'unknown',
  API_ERROR = 'api-error',
}

export default AnalysisStatus
