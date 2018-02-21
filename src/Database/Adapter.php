<?php

namespace Postmix\Database;
use Postmix\Database\Adapter\MySQL;
use Postmix\Exception;

/**
 * Class Adapter
 * @package Postmix\Database
 */

abstract class Adapter {

	protected $connection;

	/**
	 * Get database connection
	 *
	 * @return mixed
	 * @throws Exception
	 */

	protected function getConnection() {

		if(!isset($this->connection))
			throw new Exception('Database connection wasn\'t created yet.');

		return $this->connection;
	}

	/**
	 * Adapter names
	 */

	const ADAPTERS = [

		'pdo_mysql' => MySQL::class
	];

}