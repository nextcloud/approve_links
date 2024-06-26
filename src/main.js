import Vue from 'vue'
import ApprovePage from './views/ApprovePage.vue'

Vue.mixin({ methods: { t, n } })

document.addEventListener('DOMContentLoaded', (event) => {
	const View = Vue.extend(ApprovePage)
	new View().$mount('#content')
})
