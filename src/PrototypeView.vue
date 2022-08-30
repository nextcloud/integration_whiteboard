<!--
 - @author Julien Veyssier <eneiluj@posteo.net>
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
 - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 - GNU Affero General Public License for more details.
 -
 - You should have received a copy of the GNU Affero General Public License
 - along with this program.  If not, see <http://www.gnu.org/licenses/>.
 -
 -->

<template>
	<NcContent :id="appContent" :app-name="appName">
		<NcButton @click="close">
			<template #icon>
				<CloseIcon />
			</template>
		</NcButton>
		<NcButton v-if="dir" @click="sidebar">
			<template #icon>
				<MenuOpenIcon />
			</template>
		</NcButton>
		<AppContent style="height: 100%;">
			<SpacedeckViewer
				ref="viewer"
				:filename="filename"
				:fileid="fileid"
				:dir="dir"
				:in-oc-viewer="false"
				@close="close" />
		</AppContent>
	</NcContent>
</template>

<script>
import CloseIcon from 'vue-material-design-icons/Close.vue'
import MenuOpenIcon from 'vue-material-design-icons/MenuOpen.vue'

import NcContent from '@nextcloud/vue/dist/Components/Content.js'
import AppContent from '@nextcloud/vue/dist/Components/AppContent.js'
import NcButton from '@nextcloud/vue/dist/Components/Button.js'
import { emit } from '@nextcloud/event-bus'

import SpacedeckViewer from './components/SpacedeckViewer.vue'

export default {
	name: 'PrototypeView',

	components: {
		NcContent,
		AppContent,
		SpacedeckViewer,
		NcButton,
		CloseIcon,
		MenuOpenIcon,
	},

	props: {
		appName: {
			type: String,
			required: true,
		},
		filename: {
			type: String,
			required: true,
		},
		fileid: {
			type: Number,
			required: true,
		},
		dir: {
			type: String,
			required: true,
		},
		appContent: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
	},

	destroyed() {
		document.getElementById('app-content-' + this.appName).remove()
		document.body.style.overflowY = ''
		document.getElementById('app-navigation')?.classList.remove('hidden')
	},

	methods: {
		close() {
			emit(this.appName + '::closeClick')
		},

		sidebar() {
			if (!document.getElementById('app-sidebar')) {
				OCA.Files.Sidebar.open(this.dir + '/' + this.filename)
			} else {
				OCA.Files.Sidebar.close()
			}
		},
	},

}
</script>

<style lang="scss" scoped>
button {
	position: relative;
	float: right;
	top: 0;
	width: 44px;
	height: 44px;
	opacity: 0.5;
	z-index: 9999999;
	border-width: 0;
	&:hover {
		opacity: 1;
	}
}

#app-content-integration_whiteboard {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: calc(100vh - 50px);
	min-height: 0;
	padding-top: 0;
	background-color: white;
	z-index: 1000;
	overflow: hidden;
	display: block;
	// border: solid 12px black;

	.content {
		padding-top: 0;
	}
}
</style>
