import { I18nDictionary } from '@/types/I18nDictionary'
import AnalysisStatus from "@/enums/AnalysisStatus.ts";

export const defaultI18nDictionary: I18nDictionary = {
  ANALYSIS_STATUS_ANALYZED_CLEAN: 'Clean',
  ANALYSIS_STATUS_ANALYZED_ERROR: 'Analysis error',
  ANALYSIS_STATUS_ANALYZED_FLAGGED: 'Flagged',
  ANALYSIS_STATUS_API_ERROR: 'API Error',
  ANALYSIS_STATUS_PENDING_INITIAL_ANALYSIS: 'Pending Initial Analysis',
  ANALYSIS_STATUS_PENDING_REANALYSIS: 'Pending Reanalysis',
  ANALYSIS_STATUS_PENDING_TOKEN_AVAILABILITY: 'Pending token availability',
  ANALYSIS_STATUS_PROCESSING_INITIAL_ANALYSIS: 'Processing',
  ANALYSIS_STATUS_PROCESSING_REANALYSIS: 'Processing',
  ANALYSIS_STATUS_STALE: 'Stale',
  ANALYSIS_STATUS_UNKNOWN: 'Unknown',
  ANALYSIS_STATUS_UNSENT: 'Unsent',
  CONTENT: 'Content',
  CONTENT_EXPIRED: 'Content Expired',
  CONTENT_STATUS: 'Content Status',
  DATE: 'Date',
  DATE_IS_REQUIRED: 'Date is required',
  EXPIRED_AT: 'Expired at',
  ERROR_IGNORING_FLAG: 'Error ignoring flag',
  ERROR_RESCHEDULING_FLAG: 'Error rescheduling flag',
  FLAG: 'Flag',
  FLAGS: 'Flags',
  IGNORE: 'Ignore',
  IS_STALE_NOTICE: 'This content is pending analysis by Neverstale, and, as such, some values may be out of date.',
  LAST_ANALYZED: 'Last analyzed',
  PROPOSED_EXPIRATION: 'Proposed expiration',
  NO_FLAGS_FOUND: 'No flags found.',
  REASON: 'Reason',
  REFRESH_DATA: 'Refresh data',
  RESCHEDULE: 'Reschedule',
  SNIPPET: 'Snippet',
  SUBMIT: 'Submit',
  VIEW_IN_NEVERSTALE: 'View in Neverstale',
  VIEW_LOCAL_DETAILS: 'View Local Details',
  statusLabel(status: AnalysisStatus): string|undefined {
    switch (status) {
      case AnalysisStatus.UNSENT:
        return this.ANALYSIS_STATUS_ANALYZED_CLEAN
      case AnalysisStatus.STALE:
        return this.ANALYSIS_STATUS_STALE
      case AnalysisStatus.PENDING_INITIAL_ANALYSIS:
        return this.ANALYSIS_STATUS_PENDING_INITIAL_ANALYSIS
      case AnalysisStatus.PENDING_REANALYSIS:
        return this.ANALYSIS_STATUS_PENDING_REANALYSIS
      case AnalysisStatus.PENDING_TOKEN_AVAILABILITY:
        return this.ANALYSIS_STATUS_PENDING_TOKEN_AVAILABILITY
      case AnalysisStatus.PROCESSING_REANALYSIS:
        return this.ANALYSIS_STATUS_PROCESSING_REANALYSIS
      case AnalysisStatus.PROCESSING_INITIAL_ANALYSIS:
        return this.ANALYSIS_STATUS_PROCESSING_INITIAL_ANALYSIS
      case AnalysisStatus.API_ERROR:
        return this.ANALYSIS_STATUS_API_ERROR
      case AnalysisStatus.ANALYZED_ERROR:
        return this.ANALYSIS_STATUS_ANALYZED_ERROR
      case AnalysisStatus.ANALYZED_CLEAN:
        return this.ANALYSIS_STATUS_ANALYZED_CLEAN
      case AnalysisStatus.ANALYZED_FLAGGED:
        return this.ANALYSIS_STATUS_ANALYZED_FLAGGED
      default:
        return this.ANALYSIS_STATUS_UNKNOWN
    }
  }
}
