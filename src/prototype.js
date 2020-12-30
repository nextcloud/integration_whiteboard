/**
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { imagePath, generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

import Vue from 'vue'
import PrototypeView from './PrototypeView'

/**
* @namespace ApplicationPrototype
*/
export default {
	name: 'ApplicationPrototype',

	// App init
	initialize(APP_NAME, APP_EXT, APP_MIME) {
		this.APP_NAME = APP_NAME
		this.APP_EXT = APP_EXT
		this.APP_MIME = APP_MIME

		this.NewFileMenu.APP_NAME = APP_NAME
		this.NewFileMenu.APP_EXT = APP_EXT
		this.NewFileMenu.APP_MIME = APP_MIME

		this.sessionInfo = {}

		OC.Plugins.register('OCA.Files.NewFileMenu', this.NewFileMenu)
		// this.registerFileActions()
	},

	// create container + handle close button
	setupContainer(filename, context) {
		const self = this

		this.filename = filename
		this.context = context

		const container = document.createElement('div')
		container.id = 'app-content-' + this.APP_NAME

		document.getElementById('app-content').appendChild(container)
		document.body.style.overflowY = 'hidden'
		document.getElementById('app-navigation').classList.add('hidden')

		Vue.prototype.t = window.t
		Vue.prototype.n = window.n
		Vue.prototype.OCA = window.OCA

		this.vm = new Vue({
			data: {
				sessionInfo: {},
			},
			render: h => h(
				PrototypeView,
				{
					props: {
						appName: this.APP_NAME,
						filename,
						context,
						appContent: 'app-content-' + this.APP_NAME,
					},
				}
			),
		})

		this.vm.$mount(container)

		subscribe(this.APP_NAME + '::closeClick', this.CC = () => {
			self.stopEdit()
		})
	},

	// register file handler
	registerFileActions() {
		const self = this

		OCA.Files.fileActions.registerAction({
			name: 'Edit',
			mime: this.APP_MIME,
			permissions: OC.PERMISSION_READ,
			icon() {
				return imagePath('core', 'actions/edit')
			},
			actionHandler(filename, context) {
				self.setupContainer(filename, context)
				self.startEdit(filename, context)
			},
		})

		OCA.Files.fileActions.setDefault(this.APP_MIME, 'Edit')
	},

	// register "New" in file app
	NewFileMenu: {
		attach(menu) {
			// const self = this
			const fileList = menu.fileList
			if (fileList.id !== 'files') {
				return
			}

			menu.addMenuEntry({
				id: this.APP_NAME,
				displayName: t(this.APP_NAME, 'New ' + this.APP_NAME),
				templateName: t(this.APP_NAME, 'New ' + this.APP_NAME + '.' + this.APP_EXT),
				iconClass: 'icon-' + this.APP_NAME,
				fileType: this.APP_MIME,
				actionHandler(fileName) {
					// const dir = fileList.getCurrentDirectory()
					fileList.createFile(fileName)
						.then(() => {
							// console.log('New ' + self.APP_NAME)
						})
				},
			})
		},
	},

	// start editing
	async startEdit(filename, context) {

		console.debug('AAAAA')
		console.debug(this.vm)
		/*
		const self = this

		// get the content and start the editor
		const content = await this.loadContent()
		this.ED = editor
		this.ED.start(self.APP_NAME, content)

		// start the collaboration Engine
		this.CE = collaborationEngine

		this.sessionInfo = await this.CE.start(this.APP_NAME, filename, context)
		this.vm.sessionInfo = this.sessionInfo
		// subscribtion to event bus
		// local changes => send to Engine
		subscribe(this.APP_NAME + '::editorAddStep', this.EDS = (data) => {
			self.CE.sendStep(data)
		})

		// engine sent us changes => forward to editor
		subscribe(this.APP_NAME + '::externalAddStep', this.EAS = (data) => {
			self.ED.applyChange(data)
		})
		*/

		return true
	},

	// stop editing
	stopEdit() {
		// save the content
		this.saveEdit()

		// unsubscribe from bus event
		unsubscribe(this.APP_NAME + '::closeClick', this.CC)
		// unsubscribe(this.APP_NAME + '::saveClick', this.SC)

		// remove app container
		this.vm.$destroy()
	},

	loadContent() {
		console.debug('loadContent')

		// const self = this
		const url = generateUrl('apps/' + this.APP_NAME + '/file/load')

		return axios.get(url, {
			params: {
				path: this.context.dir + '/' + this.filename,
			},
		})
	},

	saveEdit() {
		console.debug('saveEdit')
	},
}
