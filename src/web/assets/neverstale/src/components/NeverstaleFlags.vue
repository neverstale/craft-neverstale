<script setup lang="ts">
defineOptions({
  name: 'NeverstaleFlags',
})

interface Endpoints {
  GET_CONTENT: string,
  IGNORE_FLAG: string,
  RESCHEDULE_FLAG: string,
}

interface I18nDictionary {
  [key: string]: string,
}

interface CsrfToken {
  name: string,
  value: string,
}

defineProps<{
  csrfToken: CsrfToken,
  customId: string,
  endpoints: Endpoints,
  i18n: I18nDictionary,
}>()

</script>

<template>
  <h1>Neverstale Flags</h1>
  <p>Custom ID: {{ customId }}</p>
  <h2>Endpoints:</h2>
  <ul>
    <li>GET_CONTENT: {{ endpoints.GET_CONTENT }}</li>
    <li>IGNORE_FLAG: {{ endpoints.IGNORE_FLAG }}</li>
    <li>RESCHEDULE_FLAG: {{ endpoints.RESCHEDULE_FLAG }}</li>
  </ul>
  <h2>CSRF Token:</h2>
  <ul>
    <li>Name: {{ csrfToken.name }}</li>
    <li>Value: {{ csrfToken.value }}</li>
  </ul>
  <h2>I18n:</h2>
  <ul>
    <li v-for="(value, key) in i18n" :key="key">{{ key }}: {{ value }}</li>
  </ul>
</template>
