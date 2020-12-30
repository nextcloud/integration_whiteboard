<template>
	<div id="spacedeck_prefs" class="section">
		<h2>
			<a class="icon icon-spacedeck" />
			{{ t('integration_spacedeck', 'Spacedeck integration') }}
		</h2>
		<p class="settings-hint">
			{{ t('integration_spacedeck', 'Create a dedicated user in Spacedeck and set an API token in user account settings.') }}
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
			<label for="spacedeck-apitoken">
				<a class="icon icon-category-auth" />
				{{ t('integration_spacedeck', 'API token') }}
			</label>
			<input id="spacedeck-apitoken"
				v-model="state.api_token"
				type="password"
				:readonly="readonly"
				:placeholder="t('integration_spacedeck', 'Your Spacedeck API token')"
				@input="onInput"
				@focus="readonly = false">
		</div>

		<!-- TO DELETE later -->
		<input id="load-file-id"
			v-model="loadFileId"
			type="text"
			:placeholder="t('integration_spacedeck', 'File to load')">
		<button @click="onLoadClick">
			load
		</button>
		<br>

		<input id="save-file-id"
			v-model="saveFileId"
			type="text"
			:placeholder="t('integration_spacedeck', 'File to save in')">
		<input id="save-space-id"
			v-model="saveSpaceId"
			type="text"
			:placeholder="t('integration_spacedeck', 'Space to save')">
		<button @click="onSaveClick">
			save
		</button>
		<!-- UNTIL HERE -->
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
			// /////////////// TO DELETE later
			loadFileId: '',
			saveFileId: '',
			saveSpaceId: '',
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
		// /////////////// TO DELETE later
		onLoadClick() {
			// const url = generateUrl('/apps/integration_spacedeck/space/7145')
			const url = generateUrl('/apps/integration_spacedeck/space/' + this.loadFileId)
			axios.get(url).then((response) => {
				console.debug(response.data)
				console.debug(this.state.base_url + '/spaces/' + response.data.space_id + '?spaceAuth=' + response.data.edit_hash)
			})
		},
		onSaveClick() {
			const spaceId = this.saveSpaceId
			const fileId = this.saveFileId
			const url = generateUrl('/apps/integration_spacedeck/space/' + spaceId + '/' + fileId)
			axios.post(url).then((response) => {
				console.debug(response.data)
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