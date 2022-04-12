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

use DateTime;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

use OCA\Spacedeck\AppInfo\Application;

class SessionService {
	private const SESSIONS_TABLE_NAME = 'i_whiteboard_sessions';

	/**
	 * @var IDBConnection
	 */
	private $db;
	/**
	 * @var LoggerInterface
	 */
	private $logger;

	public function __construct (IDBConnection   $db,
								 LoggerInterface $logger) {
		$this->db = $db;
		$this->logger = $logger;
	}

	public function getSession(string $token, ?string $ownerUid = null, ?string $editorUid = null, ?int $fileId = null,
							   ?string $shareToken = null, ?int $tokenType = null): ?array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::SESSIONS_TABLE_NAME)
			->where(
				$qb->expr()->eq('token', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR))
			);
		if ($ownerUid !== null) {
			$qb->andWhere(
				$qb->expr()->eq('owner_uid', $qb->createNamedParameter($ownerUid, IQueryBuilder::PARAM_STR))
			);
		}
		if ($editorUid !== null) {
			$qb->andWhere(
				$qb->expr()->eq('editor_uid', $qb->createNamedParameter($editorUid, IQueryBuilder::PARAM_STR))
			);
		}
		if ($fileId !== null) {
			$qb->andWhere(
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
			);
		}
		if ($shareToken !== null) {
			$qb->andWhere(
				$qb->expr()->eq('share_token', $qb->createNamedParameter($shareToken, IQueryBuilder::PARAM_STR))
			);
		}
		if ($tokenType !== null) {
			$qb->andWhere(
				$qb->expr()->eq('token_type', $qb->createNamedParameter($tokenType, IQueryBuilder::PARAM_INT))
			);
		}
		$req = $qb->executeQuery();

		if ($row = $req->fetchOne()) {
			return $this->getSessionFromRow($row);
		}
		$req->closeCursor();
		$qb->resetQueryParts();
		return null;
	}

	public function getSessions(?string $ownerUid = null, ?string $editorUid = null, ?int $fileId = null,
								?string $shareToken = null, ?int $tokenType = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::SESSIONS_TABLE_NAME);
		if ($ownerUid !== null) {
			$qb->andWhere(
				$qb->expr()->eq('owner_uid', $qb->createNamedParameter($ownerUid, IQueryBuilder::PARAM_STR))
			);
		}
		if ($editorUid !== null) {
			$qb->andWhere(
				$qb->expr()->eq('editor_uid', $qb->createNamedParameter($editorUid, IQueryBuilder::PARAM_STR))
			);
		}
		if ($fileId !== null) {
			$qb->andWhere(
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
			);
		}
		if ($shareToken !== null) {
			$qb->andWhere(
				$qb->expr()->eq('share_token', $qb->createNamedParameter($shareToken, IQueryBuilder::PARAM_STR))
			);
		}
		if ($tokenType !== null) {
			$qb->andWhere(
				$qb->expr()->eq('token_type', $qb->createNamedParameter($tokenType, IQueryBuilder::PARAM_INT))
			);
		}
		$req = $qb->executeQuery();

		$sessions = [];
		while ($row = $req->fetch()) {
			$sessions[] = $this->getSessionFromRow($row);
		}
		$req->closeCursor();
		$qb->resetQueryParts();
		return $sessions;
	}

	private function getSessionFromRow(array $row): array {
		return [
			'id' => (int) $row['id'],
			'owner_uid' => $row['owner_uid'],
			'editor_uid' => $row['editor_uid'],
			'file_id' => (int) $row['file_id'],
			'token' => $row['token'],
			'token_type' => (int) $row['token_type'],
			'last_checked' => (int) $row['last_checked'],
		];
	}

	public function createSession(string $ownerUid, ?string $editorUid, int $fileId, ?string $shareToken): ?array {
		// exclusive or between editorUid and shareToken
		if (!(($editorUid === null) xor ($shareToken === null))) {
			return null;
		}

		$qb = $this->db->getQueryBuilder();

		$nowTimestamp = (new DateTime())->getTimestamp();
		$token = $this->generateToken($ownerUid . ((string) $nowTimestamp));

		$values = [
			'token' => $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR),
			'owner_uid' => $qb->createNamedParameter($ownerUid, IQueryBuilder::PARAM_STR),
			'editor_uid' => $qb->createNamedParameter($editorUid, IQueryBuilder::PARAM_STR),
			'file_id' => $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT),
			'share_token' => $qb->createNamedParameter($shareToken, IQueryBuilder::PARAM_STR),
			'token_type' => $qb->createNamedParameter(
				$editorUid === null
					? Application::TOKEN_TYPES['share']
					: Application::TOKEN_TYPES['user'],
				IQueryBuilder::PARAM_INT
			),
			'last_checked' => $qb->createNamedParameter($nowTimestamp, IQueryBuilder::PARAM_INT),
			'created' => $qb->createNamedParameter($nowTimestamp, IQueryBuilder::PARAM_INT),
		];

		$qb->insert(self::SESSIONS_TABLE_NAME)
			->values($values);
		$qb->executeStatement();
		$insertedSessionId = $qb->getLastInsertId();
		$qb->resetQueryParts();

		return [
			'id' => $insertedSessionId,
			'token' => $token,
		];
	}

	private function generateToken(string $baseString) {
		return md5($baseString.rand());
	}

	public function deleteSession(string $token): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete(self::SESSIONS_TABLE_NAME)
			->where(
				$qb->expr()->eq('token', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR))
			);
		$qb->executeStatement();
		$qb->resetQueryParts();
	}
}
