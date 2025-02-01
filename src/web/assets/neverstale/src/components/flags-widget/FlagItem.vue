<template>
  <Disclosure v-slot="{ open }">
    <div class="ns-flex ns-w-full ns-flex-nowrap ns-items-center ns-justify-between ns-gap-x-2 ns-py-3">
      <DisclosureButton class="ns-inline-flex ns-gap-x-2">
        <IconChevron
          :class="[
            'ns-mt-0.5 ns-size-3 ns-text-neutral-600',
            open && 'ns-rotate-90 ns-transform',
          ]"
          aria-hidden="true"
        />
        <h3
          class="ns-m-0"
          v-text="capitalize(item.flag)"
        />
      </DisclosureButton>
      <DropdownMenu
        :items="menuItems"
        dots-classes="ns-text-neutral-400"
        class="ns-justify-self-end  ns-overflow-hidden"
      >
        <template #expand>
          <div
            :class="[
              'ns-relative  ns-rounded-b-md',
              { 'ns-bg-neutral-100 -ns-mb-2': isRescheduleFlyoutOpen,}
            ]"
          >
            <Disclosure v-slot="{ open: innerOpen }">
              <DisclosureButton
                class="ns-flex ns-w-full ns-flex-nowrap ns-items-center ns-justify-between ns-rounded-md ns-p-3 ns-text-sm hover:ns-bg-neutral-100"
                @click="isRescheduleFlyoutOpen = !isRescheduleFlyoutOpen"
              >
                <span v-text="i18n.RESCHEDULE" />
                <IconChevron :class="innerOpen && 'ns-rotate-90 ns-transform '"/>
              </DisclosureButton>
              <div v-show="isRescheduleFlyoutOpen">
                <DisclosurePanel static>
                  <form
                    class="ns-my-2 ns-flex ns-pb-2 ns-flex-col ns-gap-2 ns-px-2"
                    @submit.prevent="handleReschedule"
                  >
                    <div>
                      <label
                        :for="`reschedule-date-${item.id}`"
                        class="ns-sr-only"
                        v-text="`${i18n.RESCHEDULE} ${i18n.DATE}`"
                      />
                      <input
                        :id="`reschedule-date-${item.id}`"
                        v-model="rescheduleDate"
                        class="ns-w-full  ns-rounded-md ns-border ns-border-neutral-300 ns-px-2 ns-py-1"
                        type="date"
                      >
                    </div>
                    <button
                      class="ns-btn ns-w-full ns-rounded-md ns-bg-neutral-300 ns-px-2 ns-py-2"
                      type="submit"
                      v-text="i18n.SUBMIT"
                    />
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
        <dl class="ns-flex ns-flex-col ns-gap-3 ns-pt-1.5 ns-pb-3">
          <div>
            <dt
              class="ns-sr-only"
              v-text="`${i18n.SNIPPET}:`"
            />
            <dd
              class="ns-rounded-md ns-bg-neutral-100 ns-px-3 ns-py-2 ns-italic"
            >
              <blockquote v-text="item.snippet" />
            </dd>
          </div>
          <div>
            <dt
              class="ns-sr-only"
              v-text="`${i18n.REASON}:`"
            />
            <dd v-text="item.reason"/>
          </div>
          <div class="ns-flex ns-flex-nowrap ns-gap-x-1 ns-text-xs">
            <dt
              class="ns-font-bold"
              v-text="`${i18n.PROPOSED_EXPIRATION}:`"
            />
            <dd v-text="formatDate(item.expired_at)"/>
          </div>
        </dl>
      </DisclosurePanel>
    </transition>
  </Disclosure>
</template>

<script setup lang="ts">
import {ref, inject} from 'vue'

import {
  Disclosure,
  DisclosureButton,
  DisclosurePanel,
} from '@headlessui/vue'

import {formatDate} from '@/utils/formatDate'

import {capitalize} from '@/utils/stringHelper'

import {ContentFlag} from '@/types/ContentFlag'
import {I18nDictionary} from '@/types/I18nDictionary'
import {RescheduleFlagEvent} from '@/types/events/RescheduleFlagEvent.ts'
import useNeverstaleContent from '@/composables/useNeverstaleContent.ts'
import DropdownMenu from '@/components/flags-widget/DropdownMenu.vue'
import IconChevron from '@/components/icons/IconChevron.vue'
import IconIgnore from '@/components/icons/IconIgnore.vue'
import {MenuItem} from '@/types/MenuItem.ts'

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
  },
] as MenuItem[]

</script>
