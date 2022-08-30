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
use OCA\Spacedeck\AppInfo\Application;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\Files\FileInfo;
use OCP\Files\Node;
use OCP\Share\IManager as IShareManager;

class FileService {

	/**
	 * @var IShareManager
	 */
	private $shareManager;
	/**
	 * @var IRootFolder
	 */
	private $root;

	public function __construct (string $appName,
								 IShareManager $shareManager,
								 IRootFolder $root) {
		$this->shareManager = $shareManager;
		$this->root = $root;
	}

	/**
	 * Get a user file from a fileId
	 *
	 * @param ?string $userId
	 * @param int $fileId
	 * @return ?Node the file or null if it does not exist (or is not accessible by this user)
	 * @throws NoUserException
	 * @throws NotPermittedException
	 */
	public function getFileFromId(?string $userId, int $fileId): ?Node {
		if (is_null($userId)) {
			$file = $this->root->getById($fileId);
		} else {
			$userFolder = $this->root->getUserFolder($userId);
			$file = $userFolder->getById($fileId);
		}
		if (is_array($file) && count($file) > 0) {
			return $file[0];
		} elseif (!is_array($file) && $file->getType() === FileInfo::TYPE_FILE) {
			return $file;
		}
		return null;
	}

	/**
	 * Check if user has write access on a file
	 *
	 * @param ?string $userId
	 * @param int $fileId
	 * @return bool true if the user can write the file
	 * @throws NoUserException
	 * @throws NotPermittedException
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function userHasWriteAccess(string $userId, int $fileId): bool {
		$userFolder = $this->root->getUserFolder($userId);
		$file = $userFolder->getById($fileId);
		if (is_array($file)) {
			foreach ($file as $f) {
				if ($f->getType() === FileInfo::TYPE_FILE && ($f->getPermissions() & Constants::PERMISSION_UPDATE) !== 0) {
					return true;
				}
			}
		} elseif (!is_array($file) && $file->getType() === FileInfo::TYPE_FILE) {
			return ($file->getPermissions() & Constants::PERMISSION_UPDATE) !== 0;
		}
		return false;
	}

	/**
	 * Check what a user can do with a file
	 *
	 * @param string $userId
	 * @param int $fileId
	 * @return int
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function getUserPermissionsOnFile(string $userId, int $fileId): int {
		$userFolder = $this->root->getUserFolder($userId);
		$files = $userFolder->getById($fileId);
		if (is_array($files) && count($files) > 0) {
			foreach ($files as $f) {
				if ($f->getType() === FileInfo::TYPE_FILE && ($f->getPermissions() & Constants::PERMISSION_UPDATE) !== 0) {
					return Application::PERMISSIONS['edit'];
				}
			}
			return Application::PERMISSIONS['view'];
		}
		return Application::PERMISSIONS['none'];
	}

	/**
	 * Check if a share token can access a file
	 *
	 * @param string $shareToken
	 * @param int $fileId
	 * @return ?Node the file or null if this token does not exist or can't access this file
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function getFileFromShareToken(string $shareToken, int $fileId): ?Node {
		try {
			$share = $this->shareManager->getShareByToken($shareToken);
			$node = $share->getNode();
			// in single file share, we get 0 as file ID
			if ($node->getType() === FileInfo::TYPE_FILE && ($fileId === 0 || $node->getId() === $fileId)) {
				return $node;
			} elseif ($node->getType() === FileInfo::TYPE_FOLDER) {
				$files = $node->getById($fileId);
				if (is_array($files) && count($files) > 0) {
					return $files[0];
				}
			}
		} catch (ShareNotFound $e) {
			return null;
		}
		return null;
	}

	/**
	 * Check if a token has write access to a file
	 *
	 * @param string $shareToken
	 * @param int $fileId
	 * @return bool true if has write access
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function isFileWriteableWithToken(string $shareToken, int $fileId): bool {
		$file = $this->getFileFromShareToken($shareToken, $fileId);
		if ($file === null) {
			return false;
		}
		try {
			$share = $this->shareManager->getShareByToken($shareToken);
			$perms = $share->getPermissions();
			return (($perms & Constants::PERMISSION_UPDATE) !== 0);
		} catch (ShareNotFound $e) {
			return false;
		}
	}

	/**
	 * Check what a share token can do with a file
	 *
	 * @param string $shareToken
	 * @param int $fileId
	 * @return int
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	public function getSharePermissionsOnFile(string $shareToken, int $fileId): int {
		try {
			$share = $this->shareManager->getShareByToken($shareToken);
			$sharedNode = $share->getNode();
			$accessibleWithToken = false;
			// in single file share, we get 0 as file ID
			if ($sharedNode->getType() === FileInfo::TYPE_FILE && ($fileId === 0 || $sharedNode->getId() === $fileId)) {
				$accessibleWithToken = true;
			} elseif ($sharedNode->getType() === FileInfo::TYPE_FOLDER) {
				$files = $sharedNode->getById($fileId);
				if (is_array($files) && count($files) > 0) {
					$accessibleWithToken = true;
				}
			}
			if ($accessibleWithToken) {
				$perms = $share->getPermissions();
				if (($perms & Constants::PERMISSION_UPDATE) !== 0) {
					return Application::PERMISSIONS['edit'];
				} else {
					return Application::PERMISSIONS['view'];
				}
			}
		} catch (ShareNotFound $e) {
			return Application::PERMISSIONS['none'];
		}
		return Application::PERMISSIONS['none'];
	}
}
