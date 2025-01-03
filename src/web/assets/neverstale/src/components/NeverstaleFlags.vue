<template>
  <div class="ns-flags-wrapper">
    <header class="ns-flags-header">
      <h2 v-text="'Neverstale'" />
    </header>

    <dl v-if="flagData">
      <div class="ns-flags-data-item">
        <dt v-text="'Content Status'" />
        <dd v-text="contentStatus" />
      </div>

      <div class="ns-flags-data-item">
        <dt v-text="'Date Updated'" />
        <dd v-text="contentUpdatedDate" />
      </div>

      <div
        v-if="flagData.analyzed_at"
        class="ns-flags-data-item"
      >
        <dt v-text="'Last Analyzed'" />
        <dd v-text="formatDate(flagData.analyzed_at)" />
      </div>

      <div
        v-if="flagData.analyzed_at && flagData.expired_at"
        class="ns-flags-data-item"
      >
        <dt v-text="'Content Expired'" />
        <dd v-text="formatDate(flagData.expired_at)" />
      </div>
    </dl>

    <div v-if="isPendingProcessingOrStale">
      <blockquote>
        <p v-text="'This content is currently pending processing by Neverstale, and, as such, some values may be out of date.'" />
      </blockquote>
    </div>

    <div v-if="flagData && flagData.flags.length > 0">
      <!-- TODO: Add i18n -->
      <h3
        class=""
        v-text="`${flagData.flags.length} Content ${flagData.flags.length === 1 ? 'Flag' : 'Flags'}`"
      />

      <ul class="ns-flags-flag-items">
        <li
          v-for="flag in flagData.flags"
          :key="flag.id"
        >
          <details>
            <summary v-text="`${flag.flag} (${formatDate(flag.expired_at)})`" />

            <div>
              <dl>
                <dt v-text="'Expired at:'" />
                <dd v-text="formatDate(flag.expired_at)" />

                <dt v-text="'Reason:'" />
                <dd v-text="flag.reason" />

                <dt v-text="'Snippet:'" />
                <dd v-text="flag.snippet" />
              </dl>

              <hr>

              <div class="flex">
                <form @submit.prevent="handleIgnore(flag.id)">
                  <button
                    type="submit"
                    class="btn"
                    v-text="i18n.IGNORE"
                  />
                </form>

                <form @submit.prevent="handleReschedule(flag.id)">
                  <input
                    v-model="rescheduleDate"
                    type="date"
                  >
                  
                  <button
                    type="submit"
                    class="btn"
                    v-text="i18n.RESCHEDULE"
                  />
                </form>
              </div>
            </div>
          </details>
        </li>
      </ul>
    </div>

    <div v-else>
      <p v-text="props.i18n.NO_FLAGS_FOUND" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { onBeforeMount, ref } from 'vue'

import { FetchApiContentResponse } from '../types/FetchApiContentResponse.ts'

defineOptions({
  name: 'NeverstaleFlags',
})

interface Endpoints {
  IGNORE_FLAG: string
  RESCHEDULE_FLAG: string
  FETCH_API_CONTENT: string
  VIEW_LOCAL_CONTENT: string
}

interface I18nDictionary {
  IGNORE: string
  RESCHEDULE: string
  NO_FLAGS_FOUND: string
}

interface CsrfToken {
  name: string
  value: string
}

const props = defineProps<{
  contentId: string,
  csrfToken: CsrfToken,
  endpoints: Endpoints,
  i18n: I18nDictionary,
  contentStatus: string,
  contentUpdatedDate: string,
  isPendingProcessingOrStale: boolean,
}>()

// TODO: Make this an object so we can have one for each flag
const rescheduleDate = ref('')
const flagData = ref<FetchApiContentResponse | null>(null)

onBeforeMount(async () => {
  const response = await fetch(props.endpoints.FETCH_API_CONTENT, {
    headers: {
      'Accept': 'application/json',
    },
  })

  if (response.ok) {
    const data: { success: boolean, data: FetchApiContentResponse } = await response.json()

    flagData.value = data.success ? data.data : null
  }

  // TODO: Add error handling
})

const formatDate = (date: string): string => {
  return Intl.DateTimeFormat('en-US', {
    dateStyle: 'short',
    timeStyle: 'short',
    timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone,
  })
    .format(new Date(date))
}

const handleIgnore = async (flagId: string): Promise<void> => {
  const confirmed = confirm('Are you sure you want to ignore this flag? There is no undo.')

  if (confirmed) {
    const response = await fetch(props.endpoints.IGNORE_FLAG, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': props.csrfToken.value,
      },
      body: JSON.stringify({
        flagId,
        contentId: props.contentId,
      }),
    })

    if (response.ok) {
      const indexToRemove = flagData.value?.flags.findIndex(flag => flag.id === flagId)

      if (indexToRemove !== undefined && indexToRemove !== -1) {
        flagData.value?.flags.splice(indexToRemove, 1)
      }

      // TODO: Emit an event back to the CMS to update the UI
    }

    // TODO: Add error handling
  }
}

const handleReschedule = async (flagId: string): Promise<void> => {
  if (!rescheduleDate.value) {
    alert('Please select a date to reschedule this flag.')

    return
  }

  const confirmed = confirm('Are you sure you want to reschedule this flag?')

  if (confirmed) {
    const response = await fetch(props.endpoints.RESCHEDULE_FLAG, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': props.csrfToken.value,
      },
      body: JSON.stringify({
        flagId,
        contentId: props.contentId,
        expiredAt: rescheduleDate.value,
      }),
    })

    if (response.ok) {
      rescheduleDate.value = ''

      // TODO: Emit an event back to the CMS to update the UI
    }
  }
}
</script>

<style scoped>
.ns-flags-wrapper {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-inline-end: -1.5rem;
  margin-inline-start: -1.5rem;
  margin-bottom: 1rem;
  padding-bottom: 1rem;
  padding-top: 1rem;
  overflow: visible;
  background-color: #F1F6FB;
  border: 1px solid #e5e7eb;
  border-radius: 5px;
  box-shadow: 0 0 0 1px #cdd8e4, 0 2px 12px rgba(205, 216, 228, .5);

  & > * {
    padding-block: 0;
    padding-inline: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e5e7eb;
  }

  & > *:last-child {
    padding-bottom: 0;
    border-bottom: none;
  }
}

header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

h2 {
  margin: 0 !important;
}

dl {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.ns-flags-data-item {
  display: flex;
  justify-content: space-between;
}

.ns-flags-flag-items {
  display: flex;
  flex-direction: column;
  gap: 1rem;

  & > * {
    padding-bottom: 1rem;
    border-bottom: 1px solid #e5e7eb;
  }

  & > *:last-child {
    border-bottom: none;
  }
}

summary {
  text-transform: capitalize;
}
</style>
