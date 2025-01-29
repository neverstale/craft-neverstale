import { reactive, computed } from 'vue'

import { fetchApiContent } from '@/api/fetchApiContent.ts'

import AnalysisStatus from '@/enums/AnalysisStatus.ts'
import DisplayStatus from '@/enums/DisplayStatus.ts'

import { ContentFlag } from '@/types/ContentFlag.ts'
import { FetchApiContentResponse } from '@/types/FetchApiContentResponse.ts'
import { Endpoints } from '@/types/Endpoints.ts'

import { rescheduleFlag as doRescheduleFlag } from '@/api/rescheduleFlag.ts'
import { ignoreFlag as doIgnoreFlag } from '@/api/ignoreFlag.ts'


interface State {
  content?: FetchApiContentResponse
  csrfToken: string
  endpoints: Endpoints
  isStale: boolean
  isLoading: boolean
}

const state: State = reactive<State>({} as State);

const delay = (ms:number) => new Promise(res => setTimeout(res, ms))

export async function updateContent() {
  state.isLoading = true
  await delay(200)


  const response = await fetchApiContent(state.endpoints.FETCH_API_CONTENT)

  if (response.ok) {
    const payload: { success: boolean, data: FetchApiContentResponse } = await response.json()

    state.content = payload.success ? payload.data : undefined
  } else {
    console.error('Error fetching content')
  }
  state.isLoading = false
  state.isStale = false
}

const displayStatus = computed(() => {
  if (state.isStale) {
    return DisplayStatus.PENDING
  }
  switch (state.content?.analysis_status) {
    case AnalysisStatus.UNSENT:
    case AnalysisStatus.STALE:
    case AnalysisStatus.PENDING_INITIAL_ANALYSIS:
    case AnalysisStatus.PENDING_REANALYSIS:
    case AnalysisStatus.PENDING_TOKEN_AVAILABILITY:
    case AnalysisStatus.PROCESSING_REANALYSIS:
    case AnalysisStatus.PROCESSING_INITIAL_ANALYSIS:
      return DisplayStatus.PENDING
    case AnalysisStatus.API_ERROR:
    case AnalysisStatus.ANALYZED_ERROR:
      return DisplayStatus.ERROR
    case AnalysisStatus.ANALYZED_CLEAN:
      return DisplayStatus.CLEAN
    case AnalysisStatus.ANALYZED_FLAGGED:
      return DisplayStatus.FLAGGED
    default:
      return DisplayStatus.UNKNOWN
  }
})


export async function ignoreFlag(flagId: string): Promise<boolean> {
  const confirmed = confirm('Are you sure you want to ignore this flag? There is no undo.')

  if (!confirmed || !state.content?.flags) {
    return false
  }

  const response = await doIgnoreFlag({
    flagId: flagId,
    customId: state.content.custom_id,
    csrfToken: state.csrfToken,
    endpoint: state.endpoints.IGNORE_FLAG,
  })

  if (response.ok) {
    state.isStale = true

    state.content.flags = state.content.flags.filter(
      (flag: ContentFlag) => flag.id !== flagId
    )
    return true
  }
  console.error(`Error rescheduling flag ${flagId}`)

  return false
}

export async function rescheduleFlag(flagId: string, newDate?: Date): Promise<boolean> {
  if (!newDate) {
    alert('Please select a date to reschedule this flag.')
    return false
  }

  const confirmed = confirm('Are you sure you want to reschedule this flag?')

  if (!confirmed || !state.content || !state.content?.flags) {
    return false
  }

  const flag = state.content
    .flags.find((flag: ContentFlag) => flag.id === flagId)

  if (!flag) {
    return false
  }

  const response = await doRescheduleFlag({
    flagId: flag.id,
    customId: state.content.custom_id,
    expiredAt: newDate.toISOString(),
    csrfToken: state.csrfToken,
    endpoint: state.endpoints.RESCHEDULE_FLAG,
  })

  flag.expired_at = newDate.toISOString()

  if (response.ok) {
    state.isStale = true
    return true
  }
  console.error(`Error rescheduling flag ${flagId}`)
  return false
}

export const statusColor = computed(() => {
  switch (displayStatus.value) {
    case DisplayStatus.CLEAN:
      return 'var(--ns-color-status-success, #48A397)'
    case DisplayStatus.PENDING:
      return 'var(--ns-color-status-pending, #E9A32D)'
    case DisplayStatus.FLAGGED:
    case DisplayStatus.ERROR:
      return 'var(--ns-color-status-alert, #CC3E2D)'
    default:
      return 'var(--ns-color-status-unknown, #DDDDDD)'
  }
})

export function configure(endpoints: Endpoints, csrfToken: string) {
  state.csrfToken = csrfToken
  state.endpoints = endpoints
  state.isStale = false
  state.isLoading = false

  updateContent()
}

export default function useNeverstaleContent() {
  return {
    configure,
    displayStatus,
    ignoreFlag,
    isLoading: computed(() =>state.isLoading),
    rescheduleFlag,
    updateContent,
    statusColor,
    content: computed(() => state.content),
    flagCount: computed(() => state.content?.flags.length || 0),
    isPendingProcessingOrStale: computed(() => state.isStale || displayStatus.value === DisplayStatus.PENDING),
  }
}

