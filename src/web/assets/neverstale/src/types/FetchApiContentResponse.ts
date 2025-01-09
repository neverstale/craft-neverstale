import { ContentFlag } from '@/types/ContentFlag.ts'

export interface FetchApiContentResponse {
  analysis_status: string
  analyzed_at: string
  custom_id: string
  expired_at: string
  flags: ContentFlag[]
  id: string
  permalink: string
}
