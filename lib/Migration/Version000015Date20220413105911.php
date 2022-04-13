<?php

declare(strict_types=1);

namespace OCA\Spacedeck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000015Date20220413105911 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('i_whiteboard_sessions')) {
			$table = $schema->createTable('i_whiteboard_sessions');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('token', Types::STRING, [
				'notnull' => true,
				'length' => 32,
			]);
			$table->addColumn('token_type', Types::INTEGER, [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->addColumn('file_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('owner_uid', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('editor_uid', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('share_token', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('created', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('last_checked', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['token'], 'i_wbd_sessions_token_idx');
		}

		return $schema;
	}

}
