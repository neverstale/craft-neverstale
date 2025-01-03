import { ContentFlag } from './ContentFlag.ts'

export interface FetchApiContentResponse {
  // TODO: Make this an enum
  analysis_status: string
  analyzed_at: string
  custom_id: string
  expired_at: string
  flags: ContentFlag[]
  id: string
  permalink: string
}
