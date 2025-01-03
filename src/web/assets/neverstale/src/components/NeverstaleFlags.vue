<template>
  <h2>
    Neverstale Flags (Vue Widget)
  </h2>

  <div v-if="flagData && flagData.flags.length > 0">
    <!-- TODO: Add i18n -->
    <p
      class="readable"
      v-text="`${flagData.flags.length} content ${flagData.flags.length === 1 ? 'flag' : 'flags'} found`"
    />

    <ol>
      <li
        v-for="(flag, index) in flagData.flags"
        :key="flag.id"
      >
        <h3 v-text="`${flag.flag} (${flag.formatted_expired_at})`" />
        <div>
          <dl>
            <dt v-text="'Expired at:'" />
            <dd v-text="flag.formatted_expired_at" />

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

            <button
              type="button"
              class="btn"
              v-text="i18n.RESCHEDULE"
            />
          </div>
        </div>

        <hr v-if="index !== flagData.flags.length - 1">
      </li>
    </ol>
  </div>

  <div v-else>
    <p v-text="props.i18n.NO_FLAGS_FOUND" />
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
  customId: string,
  contentId: string,
  csrfToken: CsrfToken,
  endpoints: Endpoints,
  i18n: I18nDictionary,
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
      flagData.value?.flags.filter(flag => flag.id !== flagId)
    }

    // TODO: Add error handling
  }
}
</script>
