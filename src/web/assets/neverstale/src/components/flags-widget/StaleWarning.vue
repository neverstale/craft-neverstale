<template>
  <blockquote class="ns-stale-warning">
    <span v-text="i18n.IS_STALE_NOTICE" />&nbsp;
    <a
      href="#"
      @click.prevent="updateContent"
      v-text="i18n.REFRESH_DATA"
      class="ns-underline hover:ns-decoration-dashed ns-underline-offset-2"
    />
  </blockquote>
</template>

<script setup lang="ts">
import { inject } from 'vue'
import { updateContent }  from '@/composables/useNeverstaleContent.ts'
import { I18nDictionary } from '@/types/I18nDictionary'

const i18n = inject('i18n') as I18nDictionary

const handleRefresh = () => {
  updateContent()
}

</script>

<style>
.ns-stale-warning {
  --link-color: var(--ns-color-neutral-700)
}

</style>
