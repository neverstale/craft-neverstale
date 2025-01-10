<template>
  <div class="ns-flags-wrapper">
    <header class="ns-flags-header">
      <h2 v-text="'Neverstale'" />
    </header>

    <template v-if="flagData">
      <dl>
        <div class="ns-flags-data-item">
          <dt v-text="i18n.CONTENT_STATUS" />
          <dd>
            <span
              class="ns-flags-content-status"
              aria-hidden="true"
            />
            <span v-text="contentStatus" />
          </dd>
        </div>

        <div
          v-if="flagData.analyzed_at"
          class="ns-flags-data-item"
        >
          <dt v-text="i18n.LAST_ANALYZED" />
          <dd v-text="formatDate(flagData.analyzed_at, { showTime: true })" />
        </div>

        <div
          v-if="flagData.analyzed_at && flagData.expired_at"
          class="ns-flags-data-item"
        >
          <dt v-text="i18n.CONTENT_EXPIRED" />
          <dd v-text="formatDate(flagData.expired_at)" />
        </div>
      </dl>

      <div v-if="isPendingProcessingOrStale || isStale">
        <blockquote>
          <svg
            aria-hidden="true"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z"
              clip-rule="evenodd"
            />
          </svg>

          <div>
            <p v-text="i18n.IS_STALE_NOTICE" />

            <button
              type="button"
              class="ns-flags-reload-button"
              @click="handleReloadPage"
              v-text="i18n.RELOAD_PAGE"
            />
          </div>
        </blockquote>
      </div>

      <div
        v-if="flagData.flags.length > 0"
        :style="{ opacity: (isStale || isPendingProcessingOrStale) ? 0.5 : 1 }"
      >
        <h3 v-text="contentFlagsHeadingText" />

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
        <a
          :href="flagData.permalink"
          class="ns-flags-view-link"
          target="_blank"
          rel="noopener noreferrer"
          v-text="i18n.VIEW_IN_NEVERSTALE"
        />
      </footer>
    </template>

    <div v-else>
      <p v-text="props.i18n.NO_FLAGS_FOUND" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeMount, ref } from 'vue'

import FlagItem from '@/components/FlagItem.vue'

import { formatDate } from '@/utils/formatDate'
import { defaultI18nDictionary } from '@/utils/i18n'
import { fetchApiContent } from '@/api/fetchApiContent'

import { CsrfToken } from '@/types/CsrfToken'
import { Endpoints } from '@/types/Endpoints'
import { I18nDictionary } from '@/types/I18nDictionary'
import { FetchApiContentResponse } from '@/types/FetchApiContentResponse'

defineOptions({
  name: 'NeverstaleFlags',
})

const props = withDefaults(
  defineProps<{
    contentId: string
    csrfToken: CsrfToken
    endpoints: Endpoints
    i18n?: I18nDictionary
    contentStatus: string
    contentStatusColor: string
    isPendingProcessingOrStale: boolean
  }>(),
  {
    i18n: () => defaultI18nDictionary,
  },
)

const emit = defineEmits<{
  ignoreFlag: [flagId: string]
  rescheduleFlag: [flagId: string]
}>()

const isStale = ref(false)
const flagData = ref<FetchApiContentResponse | null>(null)

const contentFlagsHeadingText = computed(() => {
  const flagWord = flagData.value?.flags.length === 1 ? props.i18n.FLAG : props.i18n.FLAGS

  return `${flagData.value?.flags.length} ${props.i18n.CONTENT} ${flagWord}`
})

onBeforeMount(async () => {
  const response = await fetchApiContent(props.endpoints.FETCH_API_CONTENT)

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

  isStale.value = true

  emit('ignoreFlag', flagId)
}

const handleRescheduleFlag = (flagId: string): void => {
  isStale.value = true

  emit('rescheduleFlag', flagId)
}

const handleReloadPage = (): void => {
  window.location.reload()
}
</script>

<style>
:root {
  --ns-flags-background-color: #F1F6FB;
  --ns-flags-padding: 1rem;
  --ns-flags-border: 1px solid #e5e7eb;
  --ns-flags-border-radius: 5px;
  --ns-flags-box-shadow: 0 0 0 1px #cdd8e4, 0 2px 12px rgba(205, 216, 228, .5);
  --ns-flags-text-color: #000;
  --ns-flags-primary-button-color: #dc2626;
  --ns-flags-primary-button-text: #ffffff;
  --ns-flags-secondary-button-color: rgba(96, 125, 159, .25);
  --ns-flags-secondary-button-text: rgba(63, 77, 90, 1);
  --ns-flags-button-border-radius: 5px;
  --ns-flags-button-padding: 0.5rem 1rem;
  --ns-flags-date-input-background-color: #ffffff;
  --ns-flags-date-input-border-color: #e5e7eb;
  --ns-flags-date-input-border-radius: 5px;
  --ns-flags-date-input-padding: 0.5rem 1rem;
}
</style>

<style scoped>
.ns-flags-wrapper {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-bottom: 1rem;
  padding: 1rem 0;
  overflow: visible;
  background-color: var(--ns-flags-background-color);
  border: var(--ns-flags-border);
  border-radius: var(--ns-flags-border-radius);
  box-shadow: var(--ns-flags-box-shadow);

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

blockquote {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  padding: 1rem;
  border: 1px solid #b45309;
  border-radius: 5px;
  color: #b45309;
}

blockquote svg {
  flex-shrink: 0;
  width: 1.5rem;
  height: 1.5rem;
  margin-top: 0.2rem;
}

.ns-flags-reload-button {
  padding: var(--ns-flags-button-padding);
  background-color: var(--ns-flags-secondary-button-color);
  color: var(--ns-flags-secondary-button-text);
  border-radius: var(--ns-flags-button-border-radius);
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
  padding: var(--ns-flags-button-padding);
  background-color: var(--ns-flags-primary-button-color);
  color: var(--ns-flags-primary-button-text);
  text-decoration: none;
  border-radius: var(--ns-flags-button-border-radius);
}
</style>
