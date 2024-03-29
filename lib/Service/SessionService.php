<?php
/**
 * Nextcloud - spacedeck
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Spacedeck\Service;

use OC\User\NoUserException;
use OCP\DB\Exception;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

use OCA\Spacedeck\AppInfo\Application;

class SessionService {
	private const SESSION_TIMEOUT_SECONDS = 600;
	/**
	 * @var LoggerInterface
	 */
	private $logger;
	/**
	 * @var SessionStoreService
	 */
	private $sessionStoreService;
	/**
	 * @var FileService
	 */
	private $fileService;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var SpacedeckAPIService
	 */
	private $spacedeckAPIService;

	public function __construct (SessionStoreService $sessionStoreService,
								 FileService $fileService,
								 IConfig $config,
								 SpacedeckAPIService $spacedeckAPIService,
								 LoggerInterface $logger) {
		$this->logger = $logger;
		$this->sessionStoreService = $sessionStoreService;
		$this->fileService = $fileService;
		$this->config = $config;
		$this->spacedeckAPIService = $spacedeckAPIService;
	}

	/**
	 * Check what this session can do with the related file
	 *
	 * @param string $sessionToken
	 * @param string $method
	 * @return int
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function checkSessionPermissions(string $sessionToken, string $method): int {
		$session = $this->sessionStoreService->getSession($sessionToken);
		if ($session !== null) {
			$this->sessionStoreService->touchSession($sessionToken);
			if ($session['token_type'] === Application::TOKEN_TYPES['user']) {
				$perm = $this->fileService->getUserPermissionsOnFile($session['editor_uid'], $session['file_id']);
				if ($method !== 'GET' && $perm === Application::PERMISSIONS['edit']) {
					$this->saveSessionSpaceToFile($session);
				}
				return $perm;
			} else {
				$perm = $this->fileService->getSharePermissionsOnFile($session['share_token'], $session['file_id']);
				if ($method !== 'GET' && $perm === Application::PERMISSIONS['edit']) {
					$this->saveSessionSpaceToFile($session);
				}
				return $perm;
			}
		}
		return Application::PERMISSIONS['none'];
	}

	private function saveSessionSpaceToFile(array $session) {
		try {
			$baseUrl = $this->config->getAppValue(Application::APP_ID, 'base_url', Application::DEFAULT_SPACEDECK_URL);
			$apiToken = $this->config->getAppValue(Application::APP_ID, 'api_token', Application::DEFAULT_SPACEDECK_API_KEY);
			$this->spacedeckAPIService->saveSpaceToFile(
				$baseUrl, $apiToken, null, (string)$session['file_id'], $session['file_id']
			);
		} catch (\Exception | \Throwable $e) {
			$this->logger->error('Error saving space for session', ['exception' => $e]);
		}
	}

	/**
	 * Create a session for a user
	 *
	 * @param string $userId
	 * @param int $fileId
	 * @return array|null
	 * @throws NoUserException
	 * @throws NotPermittedException
	 */
	public function createUserSession(string $userId, int $fileId): ?array {
		$file = $this->fileService->getFileFromId($userId, $fileId);
		if ($file === null) {
			return null;
		}
		return $this->sessionStoreService->createSession($file->getOwner()->getUID(), $fileId, $userId, null);
	}

	/**
	 * Create a session for a share token
	 *
	 * @param string $shareToken
	 * @param int $fileId
	 * @return array|null
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function createShareSession(string $shareToken, int $fileId): ?array {
		$file = $this->fileService->getFileFromShareToken($shareToken, $fileId);
		if ($file === null) {
			return null;
		}
		return $this->sessionStoreService->createSession($file->getOwner()->getUID(), $file->getId(), null, $shareToken);
	}

	/**
	 * Delete a user session
	 *
	 * @param string $userId
	 * @param string $sessionToken
	 * @return bool
	 * @throws Exception
	 */
	public function deleteUserSession(string $userId, string $sessionToken): bool {
		$session = $this->sessionStoreService->getSession(
			$sessionToken, null, $userId, null, null, Application::TOKEN_TYPES['user']
		);
		if ($session !== null) {
			$this->sessionStoreService->deleteSession($sessionToken);
			return true;
		}
		return false;
	}

	/**
	 * Delete a share token session
	 *
	 * @param string $shareToken
	 * @param string $sessionToken
	 * @return bool
	 * @throws Exception
	 */
	public function deleteShareSession(string $shareToken, string $sessionToken): bool {
		$session = $this->sessionStoreService->getSession(
			$sessionToken, null, null, null, $shareToken, Application::TOKEN_TYPES['share']
		);
		if ($session !== null) {
			$this->sessionStoreService->deleteSession($sessionToken);
			return true;
		}
		return false;
	}

	public function cleanupSessions(): array {
		return $this->sessionStoreService->cleanupSessions(self::SESSION_TIMEOUT_SECONDS);
	}
}
