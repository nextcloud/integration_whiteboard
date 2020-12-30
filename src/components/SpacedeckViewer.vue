<template>
	<div>
		{{ filename }} => {{ fileid }}
		<iframe v-if="spaceUrl"
			class="spacedeck-frame"
			frameborder="0"
			:allowFullScreen="true"
			:src="spaceUrl" />
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'

export default {
	name: 'SpacedeckViewer',

	components: {
	},

	props: {
		filename: {
			type: String,
			required: true,
		},
		fileid: {
			type: Number,
			required: true,
		},
	},

	data() {
		return {
			spaceId: null,
			spaceUrl: '',
			loop: null,
		}
	},

	computed: {
		saveSpaceUrl() {
			return generateUrl('/apps/integration_spacedeck/space/' + this.spaceId + '/' + this.fileid)
		},
	},

	created() {
		// load the file into spacedeck (only if no related space exists)
		const url = generateUrl('/apps/integration_spacedeck/space/' + this.fileid)
		axios.get(url).then((response) => {
			console.debug(response.data)
			this.spaceId = response.data.space_id
			// this.spaceUrl = response.data.base_url + '/spaces/' + response.data.space_id + '?spaceAuth=' + response.data.edit_hash
			this.spaceUrl = 'https://localhost/dev/server21/index.php/apps/files/'
			this.startSaveLoop()
			// this method only exists when this component is loaded in the Viewer context
			if (this.doneLoading) {
				this.doneLoading()
			}
		})
	},

	destroyed() {
		console.debug('DESTROYED')
		this.stopSaveLoop()
	},

	methods: {
		startSaveLoop() {
			this.loop = setInterval(() => this.saveSpace(), 5000)
		},
		stopSaveLoop() {
			clearInterval(this.loop)
		},
		saveSpace() {
			axios.post(this.saveSpaceUrl).then((response) => {
				console.debug('SAVED')
				console.debug(response.data)
			}).catch((error) => {
				console.error(error)
				clearInterval(this.loop)
				showError(t('integration_spacedeck', 'Error while saving Spacedeck whiteboard'))
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.file-view {
	width: 100%;
	height: 100%;
}

.spacedeck-frame {
	width: 100%;
	height: 100%;
	max-width: 100%;
	resize: both;
}
</style>
