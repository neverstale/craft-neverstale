import { ContentFlag } from '@/types/ContentFlag.ts'
import AnalysisStatus from "@/enums/AnalysisStatus.ts";

interface DateObject {
  date: string
  timezone_type: number
  timezone: string
}

export interface FetchApiContentResponse {
  analysis_status: AnalysisStatus
  analyzed_at: DateObject
  custom_id: string
  expired_at: DateObject
  flags: ContentFlag[]
  id: string
  permalink: string
}

