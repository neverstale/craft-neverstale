import { createApp } from 'vue'
import components from './components'

const app = createApp({
  async mounted() {
    console.log()
  },
})

components.forEach((Component) => {
  app.component(Component.name ?? '', Component)
})


app.mount('#neverstale-container')

if (import.meta.hot) {
  import.meta.hot.accept(() => {
    console.log('HMR')
  })
}
