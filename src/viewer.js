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

import SpacedeckViewer from './components/SpacedeckViewer'

document.addEventListener('DOMContentLoaded', () => {
	// register the viewer handler if possible
	if (typeof OCA.Viewer === 'undefined') {
		console.error('Viewer app is not installed')
		return
	}

	OCA.Viewer.registerHandler({
		id: 'spacedeck',
		group: null,
		mimes: [
			'application/spacedeck',
		],
		component: SpacedeckViewer,
	})
})
