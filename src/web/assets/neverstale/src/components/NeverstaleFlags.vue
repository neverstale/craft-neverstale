<template>
  <h2>Neverstale Flags (Vue Widget)</h2>
<!--  <p>Custom ID: {{ customId }}</p>-->
<!--  <h2>Endpoints:</h2>-->
<!--  <ul>-->
<!--    <li>FETCH_CONTENT: {{ endpoints.FETCH_CONTENT }}</li>-->
<!--    <li>IGNORE_FLAG: {{ endpoints.IGNORE_FLAG }}</li>-->
<!--    <li>RESCHEDULE_FLAG: {{ endpoints.RESCHEDULE_FLAG }}</li>-->
<!--  </ul>-->
<!--  <h2>CSRF Token:</h2>-->
<!--  <ul>-->
<!--    <li>Name: {{ csrfToken.name }}</li>-->
<!--    <li>Value: {{ csrfToken.value }}</li>-->
<!--  </ul>-->
<!--  <h2>I18n:</h2>-->
<!--  <ul>-->
<!--    <li v-for="(value, key) in i18n" :key="key">{{ key }}: {{ value }}</li>-->
<!--  </ul>-->


</template>

<script setup lang="ts">
import { onBeforeMount } from 'vue'

defineOptions({
  name: 'NeverstaleFlags',
})

interface Endpoints {
  FETCH_API_CONTENT: string
  VIEW_LOCAL_CONTENT: string
  IGNORE_FLAG: string
  RESCHEDULE_FLAG: string
}

interface I18nDictionary {
  [key: string]: string
}

interface CsrfToken {
  name: string
  value: string
}

const props = defineProps<{
  customId: string,
  csrfToken: CsrfToken,
  endpoints: Endpoints,
  i18n: I18nDictionary,
}>()

onBeforeMount(async () => {
  console.log('NeverstaleFlags mounted')

  const response = await fetch(props.endpoints.FETCH_API_CONTENT, {
    headers: {
      'Accept': 'application/json',
    },
  })

  const data = await response.json()

  console.log(data)
})
</script>
