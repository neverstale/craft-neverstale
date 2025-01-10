<template>
  <li>
    <details>
      <summary v-text="`${flag.flag} (${formatDate(flag.expired_at)})`" />

      <div class="flag-item-info-container">
        <dl>
          <div>
            <dt v-text="`${i18n.EXPIRED_AT}:`" />
            <dd v-text="formatDate(flag.expired_at)" />
          </div>

          <div>
            <dt v-text="`${i18n.REASON}:`" />
            <dd v-text="flag.reason" />
          </div>

          <div>
            <dt v-text="`${i18n.SNIPPET}:`" />
            <dd>
              <code v-text="flag.snippet" />
            </dd>
          </div>
        </dl>

        <hr>

        <div class="flex">
          <form @submit.prevent="handleIgnore">
            <button
              type="submit"
              v-text="i18n.IGNORE"
            />
          </form>

          <form
            class="ns-flags-reschedule-form"
            @submit.prevent="handleReschedule"
          >
            <div>
              <label
                :for="`reschedule-date-${flag.id}`"
                v-text="`${i18n.RESCHEDULE} ${i18n.DATE}`"
              />
              <input
                :id="`reschedule-date-${flag.id}`"
                v-model="rescheduleDate"
                type="date"
              >
            </div>

            <button
              type="submit"
              v-text="i18n.RESCHEDULE"
            />
          </form>
        </div>
      </div>
    </details>
  </li>
</template>

<script setup lang="ts">
import { ref } from 'vue'

import { ignoreFlag } from '@/api/ignoreFlag'
import { formatDate } from '@/utils/formatDate'
import { rescheduleFlag } from '@/api/rescheduleFlag'

import { CsrfToken } from '@/types/CsrfToken'
import { Endpoints } from '@/types/Endpoints'
import { ContentFlag } from '@/types/ContentFlag'
import { I18nDictionary } from '@/types/I18nDictionary'

defineOptions({
  name: 'FlagItem',
})

const props = defineProps<{
  contentId: string
  flag: ContentFlag
  csrfToken: CsrfToken
  endpoints: Endpoints
  i18n: I18nDictionary
}>()

const emit = defineEmits<{
  ignoreFlag: [flagId: string]
  rescheduleFlag: [flagId: string]
}>()

const rescheduleDate = ref('')

const handleIgnore = async (): Promise<void> => {
  const confirmed = confirm('Are you sure you want to ignore this flag? There is no undo.')

  if (confirmed) {
    const response = await ignoreFlag({
      flagId: props.flag.id,
      contentId: props.contentId,
      csrfToken: props.csrfToken.value,
      endpoint: props.endpoints.IGNORE_FLAG,
    })

    if (response.ok) {
      emit('ignoreFlag', props.flag.id)
    }

    // TODO: Add error handling
  }
}

const handleReschedule = async (): Promise<void> => {
  if (!rescheduleDate.value) {
    alert('Please select a date to reschedule this flag.')

    return
  }

  const confirmed = confirm('Are you sure you want to reschedule this flag?')

  if (confirmed) {
    const response = await rescheduleFlag({
      flagId: props.flag.id,
      contentId: props.contentId,
      expiredAt: rescheduleDate.value,
      csrfToken: props.csrfToken.value,
      endpoint: props.endpoints.RESCHEDULE_FLAG,
    })

    if (response.ok) {
      rescheduleDate.value = ''

      emit('rescheduleFlag', props.flag.id)
    }

    // TODO: Add error handling
  }
}
</script>

<style scoped>
summary {
  text-transform: capitalize;
  font-weight: 500;
}

.flag-item-info-container {
  margin-top: 1rem;
}

dl {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

dt {
  font-size: 0.75rem;
  text-transform: uppercase;
  color: #606d7b;
}

button {
  padding: var(--ns-flags-button-padding);
  background-color: var(--ns-flags-secondary-button-color);
  color: var(--ns-flags-secondary-button-text);
  border-radius: var(--ns-flags-button-border-radius);
}

form {
  display: flex;
  gap: 1rem;
}

.ns-flags-reschedule-form {
  label {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border-width: 0;
  }
}

input[type="date"] {
  padding: var(--ns-flags-date-input-padding);
  background-color: var(--ns-flags-date-input-background-color);
  border: 1px solid var(--ns-flags-date-input-border-color);
  border-radius: var(--ns-flags-date-input-border-radius);
}
</style>
