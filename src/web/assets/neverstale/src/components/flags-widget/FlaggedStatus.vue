<template>
  <span
    :style="{
      backgroundColor: statusColor,
    }"
    :title="title"
    class="ns-rounded-full ns-inline-flex ns-text-white ns-justify-center ns-items-center ns-w-5 ns-h-5">
    <component
      :is="val"
      :count="flagCount"
      class="ns-text-xs ns-text-white" />
  </span>
</template>

<script setup lang="ts">
import type {Component, FunctionalComponent} from 'vue'
import { computed, h, inject } from 'vue'
import { I18nDictionary } from '@/types/I18nDictionary.ts'
import useNeverstaleContent from '@/composables/useNeverstaleContent.ts'
import DisplayStatus from '@/enums/DisplayStatus.ts'
import IconHourglass from '@/components/icons/IconHourglass.vue'
import IconWarning from '@/components/icons/IconWarning.vue'
import IconThumbsUp from '@/components/icons/IconThumbsUp.vue'


const i18n = inject('i18n') as I18nDictionary

const {
  displayStatus,
  flagCount,
  content,
  statusColor,
  isPendingProcessingOrStale,
} = useNeverstaleContent()

type CountComponentProps = {
  count: number
}
const CountComponent: FunctionalComponent<CountComponentProps> =
  (props) =>  h('span', props.count)

const title = computed((): string => {
  if (!isPendingProcessingOrStale && flagCount.value > 0) {
    const flagWord = flagCount.value === 1 ? i18n.FLAG : i18n.FLAGS
    return `${flagCount.value} ${i18n.CONTENT} ${flagWord}`
  }
  if (content.value?.analysis_status) {
    return i18n.statusLabel(content.value?.analysis_status) ?? (i18n.ANALYSIS_STATUS_UNKNOWN as string)
  }
  return i18n.ANALYSIS_STATUS_UNKNOWN as string
})

const val = computed((): Component => {
  switch (displayStatus.value) {
    case DisplayStatus.CLEAN:
      return IconThumbsUp
    case DisplayStatus.PENDING:
      return IconHourglass
    case DisplayStatus.ERROR:
      return IconWarning
    default:
      return CountComponent
  }
})

</script>
