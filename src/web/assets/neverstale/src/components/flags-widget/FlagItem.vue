<template>
  <Disclosure v-slot="{ open }">
    <div class="ns-w-full ns-flex ns-flex-nowrap ns-gap-x-2
      ns-justify-between ns-items-center ns-py-2">
      <DisclosureButton class="ns-inline-flex ns-gap-x-2">
        <IconChevron :class="[
          'ns-w-3 ns-h-3 ns-mt-0.5 ns-text-gray-600',
          open && 'ns-rotate-90 ns-transform',
        ]" aria-hidden="true"/>
        <h3 class="ns-m-0">{{ capitalize(item.flag) }}</h3>
      </DisclosureButton>
      <DropdownMenu
        :items="menuItems"
        dots-classes="ns-text-neutral-400"
        class="ns-justify-self-end  ns-overflow-hidden">
        <template v-slot:expand>
          <div :class="[
            'ns-relative ns-pt-1 -ns-mx-1 ns-px-1 -ns-mb-1 ns-pb-1 ns-rounded-b-md',
            { 'ns-bg-neutral-200': isRescheduleFlyoutOpen,}
            ]">
            <Disclosure v-slot="{ open }">
              <DisclosureButton
                @click="isRescheduleFlyoutOpen = !isRescheduleFlyoutOpen"
                class="ns-w-full ns-flex ns-flex-nowrap ns-items-center ns-justify-between ns-p-2 ns-text-sm ns-hover:bg-gray-100 ns-rounded-md">
                <span>{{ i18n.RESCHEDULE }}</span>
                <IconChevron :class="open && 'ns-rotate-90 ns-transform '" />
              </DisclosureButton>
              <div v-show="isRescheduleFlyoutOpen">
                <DisclosurePanel static>
                  <form
                    @submit.prevent="handleReschedule"
                    class="ns-flex ns-flex-col ns-gap-2 ns-px-2 ns-my-2"
                  >
                    <div>
                      <label
                        :for="`reschedule-date-${item.id}`"
                        class="ns-sr-only"
                        v-text="`${i18n.RESCHEDULE} ${i18n.DATE}`"
                      />
                      <input
                        :id="`reschedule-date-${item.id}`"
                        class="ns-w-full  ns-border ns-border-gray-300 ns-px-2 ns-py-1 ns-rounded-md"
                        v-model="rescheduleDate"
                        type="date"
                      >
                    </div>
                    <button
                      class="ns-w-full ns-btn ns-bg-neutral-300 ns-px-2 ns-py-1 ns-rounded-md"
                      type="submit">
                      {{ i18n.SUBMIT }}
                    </button>
                  </form>
                </DisclosurePanel>
              </div>
            </Disclosure>
          </div>
        </template>
      </DropdownMenu>
    </div>
    <transition
      enter-active-class="ns-transition ns-duration-150 ns-ease-out"
      enter-from-class="ns-transform ns-max-h-0"
      enter-to-class="ns-transform ns-max-h-[9999em]"
      leave-active-class="ns-transition ns-duration-150 ns-ease-out"
      leave-from-class="ns-transform ns-max-h-[9999em]"
      leave-to-class="ns-transform ns-max-h-0"
      >
        <DisclosurePanel>
          <dl class="ns-flex ns-flex-col ns-gap-3 ns-py-1.5">
            <div>
              <dt class="ns-sr-only" v-text="`${i18n.SNIPPET}:`" />
              <dd
                class="ns-bg-neutral-200 ns-rounded-md ns-px-3 ns-py-2 ns-font-italic"
              >
                <blockquote>{{ item.snippet }}</blockquote>
              </dd>
            </div>
            <div>
              <dt class="ns-sr-only" v-text="`${i18n.REASON}:`" />
              <dd v-text="item.reason" />
            </div>
            <div class="ns-flex ns-flex-nowrap ns-gap-x-1 ns-text-xs">
              <dt class="ns-font-bold" v-text="`${i18n.PROPOSED_EXPIRATION}:`" />
              <dd v-text="formatDate(item.expired_at)" />
            </div>
          </dl>
        </DisclosurePanel>
    </transition>
  </Disclosure>
</template>

<script setup lang="ts">
import { ref, inject } from 'vue'

import {
  Disclosure,
  DisclosureButton,
  DisclosurePanel,
} from '@headlessui/vue'

import { formatDate } from '@/utils/formatDate'

import { capitalize } from '@/utils/stringHelper'

import { ContentFlag } from '@/types/ContentFlag'
import { I18nDictionary } from '@/types/I18nDictionary'
import { RescheduleFlagEvent } from '@/types/events/RescheduleFlagEvent.ts'
import useNeverstaleContent from '@/composables/useNeverstaleContent.ts'
import DropdownMenu from '@/components/flags-widget/DropdownMenu.vue'
import IconChevron from '@/components/icons/IconChevron.vue'
import IconIgnore from "@/components/icons/IconIgnore.vue";
import {MenuItem} from "@/types/MenuItem.ts";

const i18n = inject('i18n') as I18nDictionary

defineOptions({
  name: 'FlagItem',
})

const {
  ignoreFlag,
  rescheduleFlag,
} = useNeverstaleContent()

const props = defineProps<{
  item: ContentFlag
}>()

const isRescheduleFlyoutOpen = ref(false)

const emit = defineEmits<{
  ignoreFlag: [flagId: string]
  rescheduleFlag: [ev: RescheduleFlagEvent]
}>()

const rescheduleDate = ref<string>('')

const handleIgnore = async (): Promise<void> => {
  if (await ignoreFlag(props.item.id)) {
    emit('ignoreFlag', props.item.id)
  }
}

const handleReschedule = async (): Promise<void> => {
  if (!rescheduleDate.value) {
    alert(i18n.DATE_IS_REQUIRED)
    return
  }
  if (await rescheduleFlag(props.item.id, new Date(rescheduleDate.value))) {
    emit('rescheduleFlag', {
      flagId: props.item.id,
      rescheduleDate: new Date(rescheduleDate.value),
    })
    isRescheduleFlyoutOpen.value = false
  }
}
const menuItems = [
  {
    label: i18n.IGNORE,
    action: handleIgnore,
    icon: IconIgnore,
  }
] as MenuItem[]

</script>
