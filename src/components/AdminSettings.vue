<template>
	<div id="spacedeck_prefs" class="section">
		<h2>
			<WhiteboardIcon class="icon" />
			{{ t('integration_whiteboard', 'Spacedeck whiteboard integration') }}
		</h2>
		<div id="spacedeck-content">
			<p v-if="state.use_local_spacedeck && !state.spacedeck_data_copied"
				class="settings-hint">
				<AlertCircleIcon :size="20" class="icon" />
				{{ t('integration_whiteboard', 'Spacedeck data couldn\'t be copied to local data directory. Please deploy Spacedeck yourself and don\'t use the integrated Spacedeck server.') }}
			</p>
			<!--p class="settings-hint">
				{{ t('integration_whiteboard', 'If you set up Spacedeck yourself, create a dedicated user in Spacedeck and set an API token in user account settings.') }}
			</p-->
			<div id="toggle-local">
				<CheckboxRadioSwitch
					:checked="state.use_local_spacedeck"
					@update:checked="onCheckboxChanged($event, 'use_local_spacedeck')">
					{{ t('integration_whiteboard', 'Use integrated Spacedeck server') }}
				</CheckboxRadioSwitch>
				<br>
				<div v-if="!state.use_local_spacedeck">
					<p class="settings-hint">
						<InformationOutlineIcon :size="20" class="icon" />
						<a class="external"
							target="_blank"
							href="https://github.com/nextcloud/integration_whiteboard/#deploy-spacedeck">
							{{ t('integration_whiteboard', 'How to deploy Spacedeck for Nextcloud') }}
						</a>
					</p>
					<p class="settings-hint">
						<InformationOutlineIcon :size="20" class="icon" />
						{{ t('integration_whiteboard', 'The "ext_access_control" value of Spacedeck configuration file should be "{spacedeckCheckEndpoint}".', { spacedeckCheckEndpoint }) }}
					</p>
					<p class="settings-hint">
						<InformationOutlineIcon :size="20" class="icon" />
						{{ t('integration_whiteboard', 'Spacedeck base URL is the address where Spacedeck can be contacted, from your webserver point of view.') }}
					</p>
				</div>
			</div>
			<div v-if="!state.use_local_spacedeck">
				<div class="line">
					<label for="spacedeck-baseurl">
						<EarthIcon :size="20" class="icon" />
						{{ t('integration_whiteboard', 'Spacedeck base URL') }}
					</label>
					<input id="spacedeck-baseurl"
						v-model="state.base_url"
						type="text"
						:placeholder="t('integration_whiteboard', 'Your Spacedeck base URL')"
						@input="onInput">
				</div>
				<div class="line">
					<label for="spacedeck-baseurl">
						<LockIcon :size="20" class="icon" />
						{{ t('integration_whiteboard', 'Spacedeck user API token') }}
					</label>
					<input id="spacedeck-baseurl"
						v-model="state.api_token"
						type="password"
						:placeholder="t('integration_whiteboard', 'Your Spacedeck user API token')"
						@input="onInput">
				</div>
			</div>
			<NcButton
				:class="{ loading: checking }"
				@click="checkSpacedeck">
				<template #icon>
					<CogIcon />
				</template>
				{{ t('integration_whiteboard', 'Check Spacedeck config') }}
			</NcButton>
			<label class="check-label">
				{{ checkText }}
			</label>
		</div>
	</div>
</template>

<script>
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import EarthIcon from 'vue-material-design-icons/Earth.vue'
import LockIcon from 'vue-material-design-icons/Lock.vue'
import AlertCircleIcon from 'vue-material-design-icons/AlertCircle.vue'

import WhiteboardIcon from './icons/WhiteboardIcon.vue'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'

import NcButton from '@nextcloud/vue/dist/Components/Button.js'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch.js'

import { delay } from '../utils.js'

const CHECK_NOT_DONE = 0
const CHECK_OK = 1
const CHECK_NO_INTERFACE_ACCESS = 2
const CHECK_NO_API_ACCESS = 3

export default {
	name: 'AdminSettings',

	components: {
		WhiteboardIcon,
		NcButton,
		CheckboxRadioSwitch,
		CogIcon,
		InformationOutlineIcon,
		EarthIcon,
		LockIcon,
		AlertCircleIcon,
	},

	props: [],

	data() {
		return {
			state: loadState('integration_whiteboard', 'admin-config'),
			spacedeckCheckEndpoint: window.location.protocol + '//' + window.location.host + generateUrl('/apps/integration_whiteboard/session/check'),
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
		onCheckboxChanged(newValue, key) {
			this.state[key] = newValue
			this.saveOptions({ [key]: this.state[key] ? '1' : '0' })
		},
		onInput() {
			delay(() => {
				this.saveOptions({
					base_url: this.state.base_url,
					api_token: this.state.api_token,
				})
			}, 2000)()
		},
		saveOptions(values) {
			const req = {
				values,
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
			const url = this.state.use_local_spacedeck
				? generateUrl('/apps/integration_whiteboard/proxy/stylesheets/style.css')
				: generateUrl('/apps/integration_whiteboard/test-get-style')
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
	#spacedeck-content {
		margin-left: 40px;
	}
	h2,
	.line,
	.settings-hint {
		display: flex;
		align-items: center;
		.icon {
			margin-right: 4px;
		}
	}

	h2 .icon {
		margin-right: 8px;
	}

	.line {
		> label {
			width: 300px;
			display: flex;
			align-items: center;
		}
		> input {
			width: 250px;
		}
	}
}
</style>
