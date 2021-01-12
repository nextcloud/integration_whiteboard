<template>
	<div class="spacedeck-wrapper">
		<iframe v-if="spaceUrl"
			ref="frame"
			:class="{ 'spacedeck-frame': true, 'frame-outside-viewer': !inOcViewer }"
			frameborder="0"
			:allowFullScreen="true"
			:src="spaceUrl" />
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { getCurrentUser } from '@nextcloud/auth'

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
		inOcViewer: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			spaceId: null,
			spaceUrl: '',
			loop: null,
			user: getCurrentUser(),
		}
	},

	computed: {
		saveSpaceUrl() {
			return this.user
				? generateUrl('/apps/integration_whiteboard/space/' + this.spaceId + '/' + this.fileid)
				: generateUrl('/apps/integration_whiteboard/s/' + this.sharingToken + '/space/' + this.spaceId + '/' + this.fileid)
		},
		loadSpaceUrl() {
			return this.user
				? generateUrl('/apps/integration_whiteboard/space/' + this.fileid)
				: generateUrl('/apps/integration_whiteboard/s/' + this.sharingToken + '/space/' + this.fileid)
		},
		nicknameParam() {
			return (this.user && this.user.displayName)
				? '&nickname=' + encodeURIComponent(this.user.displayName)
				: ''
		},
		sharingToken() {
			const elem = document.getElementById('sharingToken')
			return elem && elem.tagName === 'INPUT'
				? elem.value
				: null
		},
	},

	created() {
		this.loadSpace()
	},

	mounted() {
		this.openSidebar()
	},

	destroyed() {
		console.debug('DESTROYED')
		this.saveSpace()
		this.stopSaveLoop()
	},

	methods: {
		loadSpace() {
			console.debug(this.loadSpaceUrl)
			// load the file into spacedeck (only if no related space exists)
			axios.get(this.loadSpaceUrl).then((response) => {
				// console.debug(response.data)
				this.spaceId = response.data.space_id
				this.spaceUrl = generateUrl('/apps/integration_whiteboard/proxy')
					+ '/spaces/' + response.data.space_id
					+ '?spaceAuth=' + response.data.edit_hash
					+ this.nicknameParam
				// TODO uncomment next line
				// this.startSaveLoop()
				// this method only exists when this component is loaded in the Viewer context
				if (this.doneLoading) {
					this.doneLoading()
				}
				this.$nextTick(() => this.applyFrameStyle())
			}).catch((error) => {
				console.error(error)
				showError(
					t('integration_spacedeck', 'Impossible to load Spacedeck whiteboard')
					+ ' ' + (error.response?.request?.responseText || '')
				)
				if (OCA.Viewer) {
					OCA.Viewer.close()
				}
				this.$emit('close')
			})
		},
		applyFrameStyle() {
			// const style = '.btn-group.vertical > .btn:first-child { display: none !important; }'
			console.debug('FRRRRRRRR')
			console.debug(this.$refs.frame.getElementsByClassName('toolbar-elements'))
			// const doc = this.$refs.frame.contentDocument
			// doc.body.innerHTML = doc.body.innerHTML + style
			// this.$refs.frame.append('style', style)
		},
		startSaveLoop() {
			this.loop = setInterval(() => this.saveSpace(), 30000)
		},
		stopSaveLoop() {
			clearInterval(this.loop)
		},
		saveSpace() {
			if (this.spaceUrl) {
				axios.post(this.saveSpaceUrl).then((response) => {
					console.debug('SAVED')
					console.debug(response.data)
				}).catch((error) => {
					console.error(error)
					this.stopSaveLoop()
					showError(t('integration_spacedeck', 'Error while saving Spacedeck whiteboard'))
				})
			}
		},
		openSidebar() {
			/*
			if (OCA.Files.Sidebar && OCA.Viewer) {
				const filePath = OCA.Viewer.file
				console.debug('FFPFPFPFPFPF')
				console.debug(filePath)
				OCA.Files.Sidebar.open(filePath).then((e) => {
					console.debug('IS open')
					console.debug(e)
				})
			}
			*/
			// kind of a trick...until a better way is found
			const sidebarButtons = document.getElementsByClassName('icon-menu-sidebar-white-forced')
			if (sidebarButtons.length > 0) {
				// sidebarButtons[0].click()
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.file-view {
	width: 100%;
	height: 100%;
}

.spacedeck-wrapper {
	width: 100%;
	height: 100%;
}

.modal-container .spacedeck-wrapper {
	position: absolute;
	top: 50px;
	height: calc(100% - 50px);
}

.spacedeck-frame {
	width: 100%;
	height: 100%;
	max-width: 100%;
	resize: both;
}

.frame-outside-viewer {
	top: 0;
	position: absolute;
}
</style>
