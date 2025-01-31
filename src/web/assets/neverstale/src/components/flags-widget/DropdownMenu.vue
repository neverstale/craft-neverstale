<template>
  <Menu
    v-if="items"
    as="div"
  >
    <MenuButton :class="btnClasses">
      <IconDots
        :class="[
          dotsClasses,
          'ns-size-3',
        ]"
        aria-hidden="true"
      />
      <span class="ns-sr-only">More</span>
    </MenuButton>
    <transition
      enter-active-class="ns-transition ns-duration-100 ns-ease-out"
      enter-from-class="ns-transform ns-scale-95 ns-opacity-0"
      enter-to-class="ns-transform ns-scale-100 ns-opacity-100"
      leave-active-class="ns-transition ns-duration-75 ns-ease-in"
      leave-from-class="ns-transform ns-scale-100 ns-opacity-100"
      leave-to-class="ns-transform ns-scale-95 ns-opacity-0"
    >
      <MenuItems
        class="ns-absolute ns-right-1 ns-z-50 ns-mt-2 ns-w-48 ns-origin-top-right
         ns-divide-y ns-divide-gray-100 ns-rounded-md
         ns-border ns-border-gray-300 ns-bg-white ns-shadow-lg ns-ring-1 ns-ring-black/5 focus:ns-outline-none"
      >
        <div class="ns-p-1 ">
          <UiMenuItem
            v-for="item in items"
            :key="item.label"
          >
            <button
              type="button"
              class="ns-flex ns-w-full ns-flex-nowrap ns-items-center ns-justify-between ns-rounded-md ns-p-2 ns-text-sm ns-text-gray-700 hover:ns-bg-neutral-200"
              @click="doAction(item)"
            >
              <span v-text="item.label" />
              <component
                :is="item.icon"
                class="ns-size-4 ns-text-gray-600"
              />
            </button>
          </UiMenuItem>
          <slot name="expand" />
        </div>
      </MenuItems>
    </transition>
  </Menu>
</template>
<script setup lang="ts">
import { Menu, MenuButton, MenuItems, MenuItem as UiMenuItem } from '@headlessui/vue'
import { MenuItem } from '@/types/MenuItem'
import IconDots from '@/components/icons/IconDots.vue'

withDefaults(
  defineProps<{
    items: MenuItem[]
    btnClasses?: string|string[]
    dotsClasses?: string|string[]
  }>(),{
    btnClasses: 'ns-inline-flex ns-focus-visible:ring-2 ns-focus-visible:ring-white/75 ns-btn ns-px-1',
    dotsClasses: '',
  },
)

const doAction = (item: MenuItem): void => {
  if (typeof item.action === 'function') {
    item.action()
  }
  else if (item.blank) {
    window.open(item.action, '_blank')
  }
  else {
    window.location.href = item.action
  }
}
</script>
