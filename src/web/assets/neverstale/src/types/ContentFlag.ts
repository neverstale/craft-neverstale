export interface ContentFlag {
  expired_at: string
  flag: string
  id: string
  ignored_at: string | null
  last_analyzed_at: string
  reason: string
  snippet: string
}
