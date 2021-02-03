<template>
	<div id="spacedeck_prefs" class="section">
		<h2>
			<a class="icon icon-spacedeck" />
			{{ t('integration_whiteboard', 'Spacedeck whiteboard integration') }}
		</h2>
		<!--p class="settings-hint">
			<span class="icon icon-details" />
			{{ t('integration_whiteboard', 'Leave those values empty to use the default included Spacedeck server.') }}
		</p>
		<p class="settings-hint">
			{{ t('integration_whiteboard', 'If you set up Spacedeck yourself, create a dedicated user in Spacedeck and set an API token in user account settings.') }}
		</p-->
		<div class="grid-form">
			<!--label for="spacedeck-baseurl">
				<a class="icon icon-link" />
				{{ t('integration_whiteboard', 'Base URL') }}
			</label>
			<input id="spacedeck-baseurl"
				v-model="state.base_url"
				type="text"
				:placeholder="t('integration_whiteboard', 'Your Spacedeck base URL')"
				@input="onInput">
			<label for="spacedeck-apitoken">
				<a class="icon icon-category-auth" />
				{{ t('integration_whiteboard', 'API token') }}
			</label>
			<input id="spacedeck-apitoken"
				v-model="state.api_token"
				type="password"
				:readonly="readonly"
				:placeholder="t('integration_whiteboard', 'Your Spacedeck API token')"
				@input="onInput"
				@focus="readonly = false"-->
			<button
				:class="{ 'icon-loading-small': checking }"
				@click="checkSpacedeck">
				{{ t('integration_whiteboard', 'Check Spacedeck config') }}
			</button>
			<label class="check-label">
				{{ checkText }}
			</label>
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'

import { delay } from '../utils'

const CHECK_NOT_DONE = 0
const CHECK_OK = 1
const CHECK_NO_INTERFACE_ACCESS = 2
const CHECK_NO_API_ACCESS = 3

export default {
	name: 'AdminSettings',

	components: {
	},

	props: [],

	data() {
		return {
			state: loadState('integration_whiteboard', 'admin-config'),
			// to prevent some browsers to fill fields with remembered passwords
			readonly: true,
			checking: false,
			checkState: CHECK_NOT_DONE,
			checkMessage: '',
		}
	},

	computed: {
		checkText() {
			if (this.checkState === CHECK_NOT_DONE) {
				return ''
			} else if (this.checkState === CHECK_OK) {
				return t('integration_whiteboard', 'Everything is fine!')
			} else if (this.checkState === CHECK_NO_INTERFACE_ACCESS) {
				return t('integration_whiteboard', 'Spacedeck interface is not accessible.') + ' ' + this.checkMessage
			} else if (this.checkState === CHECK_NO_API_ACCESS) {
				return t('integration_whiteboard', 'Spacedeck API is not accessible.') + ' ' + this.checkMessage
			}
			return ''
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onInput() {
			delay(() => {
				this.saveOptions()
			}, 2000)()
		},
		saveOptions() {
			const req = {
				values: {
					base_url: this.state.base_url,
					api_token: this.state.api_token,
				},
			}
			const url = generateUrl('/apps/integration_whiteboard/admin-config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_whiteboard', 'Spacedeck admin options saved'))
				}).catch((error) => {
					showError(
						t('integration_whiteboard', 'Failed to save Spacedeck options')
						+ ': ' + error.response?.request?.responseText
					)
					console.error(error)
				}).then(() => {
				})
		},
		checkSpacedeck() {
			this.checking = true
			this.checkState = CHECK_NOT_DONE
			this.checkSpacedeckApi()
				.then((response) => {
					showSuccess(t('integration_whiteboard', 'Spacedeck API is accessible!'))
					this.checkSpacedeckInterface()
						.then((response) => {
							showSuccess(t('integration_whiteboard', 'Spacedeck interface is accessible!'))
							this.checkState = CHECK_OK
						}).catch((error) => {
							showError(
								t('integration_whiteboard', 'Failed to contact Spacedeck interface')
								+ ': ' + (error.response?.data || error.response?.request?.responseText)
							)
							this.checkState = CHECK_NO_INTERFACE_ACCESS
							console.error(error)
							this.checkMessage = error.response?.data || error.response?.request?.responseText
						}).then(() => {
							this.checking = false
						})
				}).catch((error) => {
					showError(
						t('integration_whiteboard', 'Failed to contact Spacedeck API')
						+ ': ' + (error.response?.data || error.response?.request?.responseText)
					)
					console.error(error)
					this.checkState = CHECK_NO_API_ACCESS
					this.checkMessage = error.response?.data || error.response?.request?.responseText
					this.checking = false
				})
		},
		checkSpacedeckInterface() {
			const url = generateUrl('/apps/integration_whiteboard/proxy/stylesheets/style.css')
			return axios.get(url)
		},
		checkSpacedeckApi() {
			const url = generateUrl('/apps/integration_whiteboard/spaces')
			return axios.get(url)
		},
	},
}
</script>

<style scoped lang="scss">
#spacedeck_prefs {
	.grid-form {
		max-width: 500px;
		display: grid;
		grid-template: 1fr / 1fr 1fr;
		margin-left: 30px;

		.icon {
			margin-bottom: -3px;
		}
		input {
			width: 100%;
		}
		label {
			line-height: 38px;
		}
		button {
			height: 34px;
		}
		.check-label {
			padding-left: 5px;
		}
	}

	.icon {
		display: inline-block;
		width: 32px;
	}
}

.icon-spacedeck {
	background-image: url(./../../img/app-dark.svg);
	background-size: 23px 23px;
	height: 23px;
	margin-bottom: -4px;
}

body.theme--dark .icon-spacedeck {
	background-image: url(./../../img/app.svg);
}

</style>
