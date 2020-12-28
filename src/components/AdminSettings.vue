<template>
	<div id="spacedeck_prefs" class="section">
		<h2>
			<a class="icon icon-spacedeck" />
			{{ t('integration_spacedeck', 'Spacedeck integration') }}
		</h2>
		<p class="settings-hint">
			{{ t('integration_spacedeck', 'Create a dedicated user in Spacedeck and set an API key in user account settings.') }}
		</p>
		<div class="grid-form">
			<label for="spacedeck-baseurl">
				<a class="icon icon-link" />
				{{ t('integration_spacedeck', 'Base URL') }}
			</label>
			<input id="spacedeck-baseurl"
				v-model="state.base_url"
				type="text"
				:placeholder="t('integration_spacedeck', 'Your Spacedeck base URL')"
				@input="onInput">
			<label for="spacedeck-apikey">
				<a class="icon icon-category-auth" />
				{{ t('integration_spacedeck', 'Api KEY') }}
			</label>
			<input id="spacedeck-apikey"
				v-model="state.api_token"
				type="password"
				:readonly="readonly"
				:placeholder="t('integration_spacedeck', 'Your Spacedeck Api KEY')"
				@input="onInput"
				@focus="readonly = false">
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'AdminSettings',

	components: {
	},

	props: [],

	data() {
		return {
			state: loadState('integration_spacedeck', 'admin-config'),
			// to prevent some browsers to fill fields with remembered passwords
			readonly: true,
		}
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
			const url = generateUrl('/apps/integration_spacedeck/admin-config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_spacedeck', 'Spacedeck admin options saved'))
				})
				.catch((error) => {
					showError(
						t('integration_spacedeck', 'Failed to save Spacedeck options')
						+ ': ' + error.response?.request?.responseText
					)
					console.error(error)
				})
				.then(() => {
				})
		},
	},
}
</script>

<style scoped lang="scss">
.grid-form label {
	line-height: 38px;
}

.grid-form input {
	width: 100%;
}

.grid-form {
	max-width: 500px;
	display: grid;
	grid-template: 1fr / 1fr 1fr;
	margin-left: 30px;
}

#spacedeck_prefs .icon {
	display: inline-block;
	width: 32px;
}

#spacedeck_prefs .grid-form .icon {
	margin-bottom: -3px;
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
