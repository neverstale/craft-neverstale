import { createApp } from 'vue'

import components from '@/components'
import '@/styles/neverstale.css'
const app = createApp({})

components.forEach((Component) => {
  app.component(Component.name ?? '', Component)
})

app.mount('[data-neverstale-flags]')

if (import.meta.hot) {
  import.meta.hot.accept(() => {
    console.log('HMR')
  })
}
