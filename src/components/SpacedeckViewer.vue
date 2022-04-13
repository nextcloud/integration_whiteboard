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
import '@nextcloud/dialogs/styles/toast.scss'
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
			sessionToken: null,
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
		this.stopListeningToFrameMessages()
		if (this.sessionToken) {
			this.deleteSession()
		}
		this.saveSpace()
	},

	methods: {
		loadSpace() {
			console.debug(this.loadSpaceUrl)
			// load the file into spacedeck (only if no related space exists)
			axios.get(this.loadSpaceUrl).then((response) => {
				console.debug('response.data', response.data)
				this.spaceId = response.data.space_id
				if (!response.data.use_local_spacedeck) {
					this.sessionToken = response.data.session_token
					// access spacedeck directly in the frame
					this.spaceUrl = response.data.base_url + '/spaces/' + this.spaceId
						+ '?spaceAuth=' + response.data.edit_hash
						+ '&externalToken=' + response.data.session_token
						+ this.nicknameParam
				} else {
					// use the proxy
					this.spaceUrl = this.user
						? generateUrl('/apps/integration_whiteboard/proxy')
						+ '/spaces/' + this.fileid
						+ '?spaceAuth=' + response.data.edit_hash
						+ this.nicknameParam
						+ '&spaceName=' + response.data.space_name
						: generateUrl('/apps/integration_whiteboard/proxy')
						+ '/spaces/' + response.data.space_name
						+ '?spaceAuth=' + response.data.edit_hash
						+ this.nicknameParam
						+ '&token=' + this.sharingToken
						+ '&spaceName=' + response.data.space_name
				}
				// this method only exists when this component is loaded in the Viewer context
				if (this.doneLoading) {
					this.doneLoading()
				}
				this.listenToFrameMessages()
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
		saveSpace() {
			axios.post(this.saveSpaceUrl).then((response) => {
				console.debug('FILE SAVED', response.data)
			}).catch((error) => {
				console.error(error)
			})
		},
		openSidebar() {
			/*
			if (OCA.Files.Sidebar && OCA.Viewer) {
				const filePath = OCA.Viewer.file
				console.debug('openSidebar')
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
				sidebarButtons[0].click()
			}
		},
		listenToFrameMessages() {
			window.addEventListener('message', this.handleFrameMessages, false)
		},
		stopListeningToFrameMessages() {
			window.removeEventListener('message', this.handleFrameMessages)
		},
		handleFrameMessages(event) {
			if (!this.spaceUrl.startsWith(event.origin)) {
				return
			}
			if (['update_artifact', 'create_artifact', 'delete_artifact'].includes(event.data?.action)) {
				this.saveSpace()
			}
		},
		deleteSession() {
			const url = this.user
				? generateUrl('/apps/integration_whiteboard/session/{token}', { token: this.sessionToken })
				: generateUrl('/apps/integration_whiteboard/s/session/{token}', { token: this.sessionToken })

			const params = {
				params: {
					sharingToken: this.user ? undefined : this.sharingToken,
				},
			}

			axios.delete(url, params).then((response) => {
				console.debug('session deleted', response.data)
			}).catch((error) => {
				console.error(error)
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
