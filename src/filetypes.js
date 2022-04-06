/**
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license AGPL-3.0-or-later
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

import ApplicationPrototype from './prototype.js'
import { loadState } from '@nextcloud/initial-state'

const APP_NAME = 'integration_whiteboard'
const APP_EXT = 'whiteboard'
const APP_MIME = 'application/spacedeck'

__webpack_nonce__ = btoa(OC.requestToken) 			 // eslint-disable-line
__webpack_public_path__ = OC.linkTo(APP_NAME, 'js/') // eslint-disable-line

OCA.integration_whiteboard = ApplicationPrototype

document.addEventListener('DOMContentLoaded', () => {
	// add the + action to create a file
	OCA.integration_whiteboard.initialize(APP_NAME, APP_EXT, APP_MIME)
	// pdf export does not work with the bundled spacedeck
	const useLocalSpacedeck = loadState(APP_NAME, 'use_local_spacedeck') === '1'
	if (!useLocalSpacedeck) {
		OCA.integration_whiteboard.registerExportFileAction()
	}

	// if there is no viewer, do as the Whiteboard app: register a file action to edit
	if (!OCA.Viewer) {
		OCA.integration_whiteboard.registerMainFileAction()

		// check if we need to open the file directly
		const dir = document.getElementById('dir').value
		const filename = document.getElementById('filename').value
		const mimetype = document.getElementById('mimetype').value
		const sharingToken = document.getElementById('sharingToken') ? document.getElementById('sharingToken').value : null
		if (filename && dir === '' && mimetype === APP_MIME && sharingToken) {
			OCA.integration_whiteboard.setupContainer(filename, 0, dir)
		}
	}
})
