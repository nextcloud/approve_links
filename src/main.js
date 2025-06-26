import { createApp } from 'vue'
import ApprovePage from './views/ApprovePage.vue'

document.addEventListener('DOMContentLoaded', (event) => {
	const app = createApp(ApprovePage)
	app.mixin({ methods: { t, n } })
	app.mount('#content')
})
