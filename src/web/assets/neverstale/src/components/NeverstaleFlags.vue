<template>
  <div class="ns-flags-wrapper">
    <header class="ns-flags-header">
      <h2 v-text="'Neverstale'" />
    </header>

    <template v-if="flagData">
      <dl>
        <div class="ns-flags-data-item">
          <dt v-text="'Content Status'" />
          <dd>
            <span
              class="ns-flags-content-status"
              aria-hidden="true"
            />
            <span v-text="contentStatus" />
          </dd>
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
          <dd v-text="formatDate(flagData.analyzed_at, { showTime: true })" />
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

      <div v-if="flagData.flags.length > 0">
        <!-- TODO: Add i18n -->
        <h3
          class=""
          v-text="`${flagData.flags.length} Content ${flagData.flags.length === 1 ? 'Flag' : 'Flags'}`"
        />

        <ul class="ns-flags-flag-items">
          <FlagItem
            v-for="flag in flagData.flags"
            :key="flag.id"
            :content-id="contentId"
            :csrf-token="csrfToken"
            :endpoints="endpoints"
            :flag="flag"
            :i18n="i18n"
            @ignore-flag="handleIgnoreFlag"
            @reschedule-flag="handleRescheduleFlag"
          />
        </ul>
      </div>

      <footer>
        <!-- TODO: Add i18n -->
        <a
          :href="flagData.permalink"
          class="ns-flags-view-link"
          target="_blank"
          rel="noopener noreferrer"
          v-text="'View in Neverstale'"
        />
      </footer>
    </template>

    <div v-else>
      <p v-text="props.i18n.NO_FLAGS_FOUND" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { onBeforeMount, ref } from 'vue'

import FlagItem from './FlagItem.vue'

import { formatDate } from '../utils/formatDate.ts'

import { CsrfToken } from '../types/CsrfToken.ts'
import { Endpoints } from '../types/Endpoints.ts'
import { I18nDictionary } from '../types/I18nDictionary.ts'
import { FetchApiContentResponse } from '../types/FetchApiContentResponse.ts'

defineOptions({
  name: 'NeverstaleFlags',
})

const props = defineProps<{
  contentId: string,
  csrfToken: CsrfToken,
  endpoints: Endpoints,
  i18n: I18nDictionary,
  contentStatus: string,
  contentStatusColor: string,
  contentUpdatedDate: string,
  isPendingProcessingOrStale: boolean,
}>()

const emit = defineEmits<{
  ignoreFlag: [flagId: string],
  rescheduleFlag: [flagId: string],
}>()

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

const handleIgnoreFlag = (flagId: string): void => {
  const indexToRemove = flagData.value?.flags.findIndex(flag => flag.id === flagId)

  if (indexToRemove !== undefined && indexToRemove !== -1) {
    flagData.value?.flags.splice(indexToRemove, 1)
  }

  emit('ignoreFlag', flagId)
}

const handleRescheduleFlag = (flagId: string): void => {
  emit('rescheduleFlag', flagId)
}
</script>

<style scoped>
/*
  Variables needed:

  - Background color
  - Padding
  - Margin (e.g., Craft needs margin-inline-end & margin-inline-start)
  - Border color
  - Border radius
  - Box shadow
  - Text color
  - Primary button color (background and text)
  - Secondary button color (background and text)
  - Button border radius
  - Button padding
  - Date input background color
  - Date input border color
  - Date input border radius
  - Date input padding
*/

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

dd {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.ns-flags-content-status {
  display: inline-block;
  width: 0.75rem;
  height: 0.75rem;
  border-radius: 50%;
  background-color: v-bind(contentStatusColor);
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
    padding-bottom: 0;
    border-bottom: none;
  }
}

.ns-flags-view-link {
  padding: 0.5rem 1rem;
  background-color: red;
  color: white;
  text-decoration: none;
  border-radius: 5px;
}
</style>
