<!--
  - @copyright Copyright (c) 2024 Julien Veyssier <julien-nc@posteo.net>
  -
  - @author 2024 Julien Veyssier <julien-nc@posteo.net>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -->

<template>
	<div class="approve-link-page">
		<h2>
			{{ t('approve_links', 'Approval') }}
		</h2>
		<div class="description">
			{{ description }}
		</div>
		<div class="buttons">
			<NcButton
				class="button"
				type="error"
				@click="onReject">
				<template #icon>
					<CloseIcon :size="24" />
				</template>
			</NcButton>
			<NcButton
				class="button"
				type="success"
				@click="onApprove">
				<template #icon>
					<CheckIcon :size="24" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import CloseIcon from 'vue-material-design-icons/Close.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'

// import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import { generateOcsUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'

export default {
	name: 'ApprovePage',

	components: {
		// NcLoadingIcon,
		NcButton,
		CheckIcon,
		CloseIcon,
	},

	props: {
	},

	data() {
		return {
			urlParams: new URLSearchParams(window.location.search),
		}
	},

	computed: {
		approveCallbackUri() { return this.urlParams.get('approveCallbackUri') },
		rejectCallbackUri() { return this.urlParams.get('rejectCallbackUri') },
		description() { return this.urlParams.get('description') },
		signature() { return this.urlParams.get('signature') },
	},

	mounted() {
	},

	methods: {
		onApprove() {
			const params = {
				approveCallbackUri: this.approveCallbackUri,
				rejectCallbackUri: this.rejectCallbackUri,
				description: this.description,
				signature: this.signature,
			}
			const url = generateOcsUrl('/apps/approve_links/api/v1/approve')
			axios.post(url, params).then(response => {
				showSuccess(t('approve_links', 'Your approval completed successfully'))
			}).catch(error => {
				console.debug('APPROVE_LINKS approve error', error)
				if (error.response.status === 400) {
					showError(t('approve_links', 'The request to the approve callback URI failed'))
				} else if (error.response.status === 401) {
					showError(t('approve_links', 'Bad signature'))
				}
			})
		},
		onReject() {
			const params = {
				approveCallbackUri: this.approveCallbackUri,
				rejectCallbackUri: this.rejectCallbackUri,
				description: this.description,
				signature: this.signature,
			}
			const url = generateOcsUrl('/apps/approve_links/api/v1/reject')
			axios.post(url, params).then(response => {
				showSuccess(t('approve_links', 'Your rejection completed successfully'))
			}).catch(error => {
				console.debug('APPROVE_LINKS reject error', error.response.status)
				if (error.response.status === 400) {
					showError(t('approve_links', 'The request to the reject callback URI failed'))
				} else if (error.response.status === 401) {
					showError(t('approve_links', 'Bad signature'))
				}
			})
		},
	},
}
</script>

<style scoped lang="scss">
.approve-link-page {
	width: 100%;
	padding: 12px;
}
</style>
