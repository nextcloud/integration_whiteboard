<?php
/**
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

use OCA\Spacedeck\AppInfo\Application;
use PHPUnit\Framework\TestCase;
use OCP\IGroupManager;
use OCP\IUserManager;

use OCA\Spacedeck\Service\SpacedeckAPIService;
use OCA\Spacedeck\Service\SpacedeckBundleService;

class LocalTest extends TestCase {

	/** @var SpacedeckAPIService */
	private $spacedeckAPIService;
	/** @var SpacedeckBundleService */
	private $spacedeckBundleService;

	public static function setUpBeforeClass(): void {
		// clear test users
		$userManager = \OC::$server->get(IUserManager::class);
		$user = $userManager->get('test');
		if ($user !== null) {
			$user->delete();
		}
		$user = $userManager->get('test2');
		if ($user !== null) {
			$user->delete();
		}
		$user = $userManager->get('test3');
		if ($user !== null) {
			$user->delete();
		}

		// CREATE DUMMY USERS
		$u1 = $userManager->createUser('test', 'T0T0T0');
		$u2 = $userManager->createUser('test2', 'T0T0T0');
		$u3 = $userManager->createUser('test3', 'T0T0T0');
		$groupManager = \OC::$server->get(IGroupManager::class);
		$groupManager->createGroup('group1test');
		$groupManager->get('group1test')->addUser($u1);
		$groupManager->createGroup('group2test');
		$groupManager->get('group2test')->addUser($u2);
	}

	protected function setUp(): void {
		$this->spacedeckAPIService = \OC::$server->get(SpacedeckAPIService::class);
		$this->spacedeckBundleService = \OC::$server->get(SpacedeckBundleService::class);
	}

	public static function tearDownAfterClass(): void {
		$userManager = \OC::$server->get(IUserManager::class);
		$user = $userManager->get('test');
		$user->delete();
		$user = $userManager->get('test2');
		$user->delete();
		$user = $userManager->get('test3');
		$user->delete();
		$groupManager = \OC::$server->get(IGroupManager::class);
		$groupManager->get('group1test')->delete();
		$groupManager->get('group2test')->delete();
	}

	protected function tearDown(): void {
		// in case there was a failure and something was not deleted
	}

	public function testUtils() {
		$pid = $this->spacedeckBundleService->launchSpacedeck(true);
		$this->assertNotNull($pid);
		$this->assertNotEquals(0, $pid);
		error_log('PID "'.$pid.'"');
		sleep(15);
		$spaces = $this->spacedeckAPIService->getSpaceList(Application::DEFAULT_SPACEDECK_URL, Application::DEFAULT_SPACEDECK_API_KEY, true);
		var_dump($spaces);
		$this->assertArrayNotHasKey('error', $spaces);
		$this->assertCount(1, $spaces);
		$space = $spaces[0];
		$this->assertArrayHasKey('_id');
		$this->assertEquals('685a1a0d-5164-4087-9ee8-09be415a85d7', $space['_id']);
	}
}
