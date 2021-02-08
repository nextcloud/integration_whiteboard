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
import { showError, showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'

import Vue from 'vue'
import PrototypeView from './PrototypeView'

const FILE_ACTION_EDIT_IDENTIFIER = 'edit-spacedeck'
const FILE_ACTION_EXPORT_IDENTIFIER = 'export-spacedeck'

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
	},

	// create container + handle close button
	setupContainerAction(filename, context) {
		const fileId = parseInt(context.$file[0].getAttribute('data-id'))
		this.setupContainer(filename, fileId, context.dir)
	},

	setupContainer(filename, fileid, dir) {
		const container = document.createElement('div')
		container.id = 'app-content-' + this.APP_NAME

		document.getElementById('app-content').appendChild(container)
		document.body.style.overflowY = 'hidden'
		document.getElementById('app-navigation')?.classList.add('hidden')

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
						fileid,
						dir,
						appContent: 'app-content-' + this.APP_NAME,
					},
				}
			),
		})

		this.vm.$mount(container)

		subscribe(this.APP_NAME + '::closeClick', this.CC = () => {
			this.stopEdit()
		})
	},

	// register file handler
	registerMainFileAction() {
		/*
		// this is done like that in Whiteboard App
		OCA.Files.fileActions.registerAction({
			name: FILE_ACTION_EDIT_IDENTIFIER,
			mime: this.APP_MIME,
			permissions: OC.PERMISSION_UPDATE | OC.PERMISSION_READ,
			icon() {
				return imagePath('core', 'actions/edit')
			},
			actionHandler: (filename, context) => {
				this.setupContainer(filename, context)
			},
		})
		*/

		// this is how Text app does it
		OCA.Files.fileActions.register(
			this.APP_MIME,
			FILE_ACTION_EDIT_IDENTIFIER,
			OC.PERMISSION_UPDATE | OC.PERMISSION_READ,
			imagePath('core', 'actions/edit'),
			(filename, context) => {
				this.setupContainerAction(filename, context)
			},
			t(this.APP_NAME, 'Edit')
		)

		OCA.Files.fileActions.setDefault(this.APP_MIME, FILE_ACTION_EDIT_IDENTIFIER)
	},

	registerExportFileAction() {
		OCA.Files.fileActions.register(
			this.APP_MIME,
			FILE_ACTION_EXPORT_IDENTIFIER,
			OC.PERMISSION_READ,
			imagePath('core', 'filetypes/application-pdf'),
			(filename, context) => {
				this.exportToPdf(filename, context)
			},
			t(this.APP_NAME, 'Export to Pdf')
		)
	},

	// register "New" in Files app
	NewFileMenu: {
		attach(menu) {
			const fileList = menu.fileList
			if (fileList.id !== 'files' && fileList.id !== 'files.public') {
				return
			}

			menu.addMenuEntry({
				id: this.APP_NAME,
				displayName: t(this.APP_NAME, 'New whiteboard'),
				templateName: t(this.APP_NAME, 'whiteboard') + '.' + this.APP_EXT,
				iconClass: 'icon-' + this.APP_NAME,
				fileType: this.APP_MIME,
				actionHandler(fileName) {
					fileList.createFile(fileName).then((status, data) => {
						const fileInfoModel = new OCA.Files.FileInfoModel(data)
						if (typeof OCA.Viewer !== 'undefined') {
							OCA.Files.fileActions.triggerAction('view', fileInfoModel, fileList)
						} else if (typeof OCA.Viewer === 'undefined') {
							// this still has some style issues
							// TODO fix
							// OCA.Files.fileActions.triggerAction(FILE_ACTION_EDIT_IDENTIFIER, fileInfoModel, fileList)
						}
					})
				},
			})
		},
	},

	// stop editing
	stopEdit() {
		// unsubscribe from bus event
		unsubscribe(this.APP_NAME + '::closeClick', this.CC)
		// unsubscribe(this.APP_NAME + '::saveClick', this.SC)

		// remove app container
		this.vm.$destroy()
	},

	exportToPdf(filename, context) {
		const fileId = context.fileInfoModel.id
		const url = generateUrl('/apps/integration_whiteboard/space/' + fileId + '/pdf')
		const req = {
			outputDir: context.dir,
		}
		axios.post(url, req).then((response) => {
			showSuccess(t('integration_spacedeck', 'Whiteboard exported to {name}', { name: response.data.name }))
			const fileList = OCA?.Files?.App?.currentFileList
			fileList?.reload?.() || window.location.reload()
		}).catch((error) => {
			console.error(error)
			showError(
				t('integration_spacedeck', 'Impossible to export {filename} to Pdf', { filename })
				+ ' ' + (error.response?.data?.message || error.response?.request?.responseText)
			)
		})
	},
}
