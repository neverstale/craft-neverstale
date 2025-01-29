<template>
  <div
    :class="[
      'ns-flags ns-flex ns-flex-col ns-gap-3 ns-divide-y ns-divide-neutral-200 ns-transition-opacity ns-duration-200',
      {
      'ns-opacity-0': isLoading,
    }]">
    <header :class="[
      'ns-flex ns-flex-nowrap ns-justify-between ns-items-baseline',
    ]">
      <h2 class="ns-mb-0 ns-inline-flex ns-gap-1 ns-relative">
        <span>{{ title }}</span>
        <FlaggedStatus />
      </h2>
      <DropdownMenu
        :items="menuItems"
        btnClasses="ns-btn ns-px-3 ns-py-2.5 ns-bg-neutral-300 ns-rounded-md"
      />
    </header>
    <StaleWarning
      v-if="isPendingProcessingOrStale"
      class="ns-pt-3"
    />

    <template v-if="content">
      <dl v-if="showContentSummary" class="ns-flags ns-flex ns-flex-col ns-gap-3 ns-pt-3">
        <div
          v-if="content.analyzed_at?.date"
          class="ns-flex ns-flex-nowrap ns-gap-x-1.5 ns-justify-between"
        >
          <dt v-text="`${i18n.LAST_ANALYZED}:`" />
          <dd v-text="formatDate(content.analyzed_at.date, { showTime: true })" />
        </div>
        <div
          v-if="!isPendingProcessingOrStale && content.analyzed_at && content.expired_at?.date"
          class="ns-flex ns-flex-nowrap ns-gap-x-1.5 ns-justify-between"
        >
          <dt v-text="`${i18n.PROPOSED_EXPIRATION}:`" />
          <dd v-text="formatDate(content.expired_at.date)" />
        </div>
      </dl>
      <ul
        v-if="content?.flags"
        class="ns-flex ns-flex-col ns-isolate ns-relative ns-divide-y ns-divide-neutral-200"
      >
        <li
          v-for="flag in content?.flags"
          :key="flag.id"
          class=" ns-py-1">
          <FlagItem
            :item="flag"
            @ignore-flag="(flagId: string) => emit('ignoreFlag', flagId)"
            @reschedule-flag="(ev: RescheduleFlagEvent) => emit('rescheduleFlag', ev)"
          />
        </li>
      </ul>
    </template>
  </div>
</template>

<script setup lang="ts">
import { onBeforeMount, provide, computed } from 'vue'

import FlagItem from '@/components/flags-widget/FlagItem.vue'

import { formatDate } from '@/utils/formatDate'
import { defaultI18nDictionary } from '@/utils/i18n'

import { CsrfToken } from '@/types/CsrfToken'
import { Endpoints } from '@/types/Endpoints'
import { MenuItem } from '@/types/MenuItem'
import { I18nDictionary } from '@/types/I18nDictionary'

import { RescheduleFlagEvent } from '@/types/events/RescheduleFlagEvent.ts'
import useNeverstaleContent from '@/composables/useNeverstaleContent.ts'

import DropdownMenu from '@/components/flags-widget/DropdownMenu.vue'
import StaleWarning from '@/components/flags-widget/StaleWarning.vue'
import FlaggedStatus from '@/components/flags-widget/FlaggedStatus.vue'

import IconExternalLink from '@/components/icons/IconExternalLink.vue'
import IconList from '@/components/icons/IconList.vue'
import IconRefresh from "@/components/icons/IconRefresh.vue";

defineOptions({
  name: 'NeverstaleFlags',
})

const props = withDefaults(
  defineProps<{
    contentId: string
    csrfToken: CsrfToken
    endpoints: Endpoints
    i18n?: I18nDictionary
    showContentSummary: boolean
    title: string,
  }>(),
  {
    i18n: () => defaultI18nDictionary,
    showContentSummary: true,
    title: 'Neverstale',
  },
)

const emit = defineEmits<{
  ignoreFlag: [flagId: string]
  rescheduleFlag: [ev: RescheduleFlagEvent]
}>()

const i18n: I18nDictionary = {
  ...defaultI18nDictionary,
  ...props.i18n,
}

const {
  configure,
  content,
  isLoading,
  isPendingProcessingOrStale,
  updateContent,
} = useNeverstaleContent()

provide('i18n', i18n)

onBeforeMount(() => {
  configure(props.endpoints, props.csrfToken.value)
})

const menuItems = computed(() =>  [{
  action: content.value?.permalink,
  label: i18n.VIEW_IN_NEVERSTALE,
  icon: IconExternalLink,
  blank: true,
}, {
  action: props.endpoints.VIEW_LOCAL_CONTENT,
  label: i18n.VIEW_LOCAL_DETAILS,
  icon: IconList,
}, {
  label: i18n.REFRESH_DATA,
  action: () => {
    updateContent()
  },
  icon: IconRefresh,
}] as MenuItem[])
</script>

<style>

:root {
  --ns-color-neutral-100: var(--ns-color-neutral-100, #F4F7FC);
  --ns-color-neutral-200: var(--ns-color-neutral-200, #E6E7EB);
  --ns-color-neutral-300: var(--ns-color-neutral-300, #D9DEE7);
  --ns-color-neutral-700: var(--ns-color-neutral-700, #424D5A);
  --ns-color-status-success: var(--ns-color-status-success, #48A397);
  --ns-color-status-pending: var(--ns-color-status-pending, #E9A32D);
  --ns-color-status-alert: var(--ns-color-status-alert, #CC3E2D);
  --ns-flags-bg-color: var(--ns-flags-bg-color, #fff);
  --ns-flags-text-color: var(--ns-flags-bg-color, var(--ns-color-neutral-700));

  background-color: var(--ns-flags-bg-color);
  color: var(--ns-flags-text-color);
}

</style>
