<?php


namespace Postmix\Structure\Mvc;


use Postmix\Application;
use Postmix\Database\Adapter\PDO;
use Postmix\Database\AdapterInterface;
use Postmix\Database\QueryBuilder;
use Postmix\Exception;
use Postmix\Exception\Database\MissingColumnException;
use Postmix\Exception\Database\MissingPrimaryKeyException;
use Postmix\Exception\Model\UnexpectedConditionException;
use Structure\Mvc\Model\Group;

class Model {

	private static $sourceTableName;

	private static $connection;

	const COLUMN_CREATED_AT = 'created_at';

	const COLUMN_UPDATED_AT = 'updated_at';

	const COLUMN_DELETED_AT = 'deleted_at';

	/**
	 * Model constructor.
	 */

	public function __construct(array $data = []) {

		if(!empty($data)) {

			$this->prefillData($data);
		}
	}

	/**
	 * Prefill data with input array
	 *
	 * @param array $data
	 */

	private function prefillData(array $data) {

		/**
		 * Loop input data
		 */

		foreach($data as $field => $value) {

			/**
			 * Set all fields
			 */

			$this->{$field} = $value;
		}
	}

	/**
	 * Fetch all records
	 *
	 * @param array $conditions
	 *
	 * @return Group|false
	 * @throws \Postmix\Exception
	 */

	public static function fetchAll($conditions = []) {

		$connection = self::getConnection();

		/**
		 * Create query builder
		 */

		if(isset($conditions[0]))
			$conditions['conditions'] = $conditions[0];

		$conditions['from'] = self::getTableName();

		$builder = new QueryBuilder($conditions);

		$columns = $connection->getTableColumns(self::getTableName());

		if(isset($columns[self::COLUMN_DELETED_AT])) {

			if(isset($conditions['deleted']) && $conditions['deleted'])
				$builder->andWhere(self::COLUMN_DELETED_AT . ' IS NOT NULL');
			else
				$builder->andWhere(self::COLUMN_DELETED_AT . ' IS NULL');

		}

		/**
		 * Select records
		 */

		$bindData = [];

		if(isset($conditions['bind']))
			$bindData = $conditions['bind'];

		$query = $connection->prepareQuery($builder->getQuery(), $bindData);
		$query->execute();

		$fetchedData = $query->fetchAll();

		/**
		 * Return models if fetched data is not null
		 */

		if(!empty($fetchedData)) {

			$models = [];
			$modelClass = get_called_class();

			foreach($fetchedData as $dataItem) {

				$models[] = new $modelClass($dataItem);
			}

			return new Group($models);

		}

		return false;
	}

	/**
	 * Fetch one record
	 *
	 * @param array $conditions
	 *
	 * @return bool|Model
	 * @throws UnexpectedConditionException
	 * @throws \Postmix\Exception
	 */

	public static function fetchOne($conditions = []) {

		$connection = self::getConnection();

		/**
		 * Create query builder
		 */

		if(isset($conditions[0]))
			$conditions['conditions'] = $conditions[0];

		$conditions['from'] = self::getTableName();

		$builder = new QueryBuilder($conditions);

		$columns = $connection->getTableColumns(self::getTableName());

		if(isset($columns[self::COLUMN_DELETED_AT])) {

			if(isset($conditions['deleted']) && $conditions['deleted'])
				$builder->andWhere(self::COLUMN_DELETED_AT . ' IS NOT NULL');
			else
				$builder->andWhere(self::COLUMN_DELETED_AT . ' IS NULL');

		}

		/**
		 * Limiting is not allowed here
		 */

		if(isset($conditions['limit']))
			throw new UnexpectedConditionException('Limit parameter for condition can\'t be set when fetching one record.');

		$builder->limit(1);

		/**
		 * Select record
		 */

		$bindData = [];

		if(isset($conditions['bind']))
			$bindData = $conditions['bind'];

		$query = $connection->prepareQuery($builder->getQuery(), $bindData);
		$query->execute();

		$fetchedData = $query->fetchAll();

		/**
		 * Return models if fetched data is not null
		 */

		if(!empty($fetchedData)) {

			$modelClass = get_called_class();

			$model = new $modelClass($fetchedData[0]);

			return $model;

		}

		return false;
	}

	/**
	 * Save
	 * ----
	 *
	 * Save model state and date
	 *
	 * @return bool
	 * @throws \Postmix\Exception
	 */

	public function save() {

		$connection = self::getConnection();

		$columns = $connection->getTableColumns(self::getTableName());

		$values = [];
		$primary = false;

		foreach($columns as $field => $column) {

			if($column['primary'] != true) {

				if(isset($this->{$field}) && !is_null($this->{$field})) {

					$values[$field] = $this->{$field};

				} else {

					$values[$field] = NULL;
				}

			} else {

				$values[$field] = NULL;
				$primary = $field;
			}
		}

		/**
		 * DateTime columns
		 */

		if(isset($columns[self::COLUMN_UPDATED_AT]))
			$values[self::COLUMN_UPDATED_AT] = date('Y-m-d H:i:s');

		$builder = new QueryBuilder([
			'columns' => $columns,
			'source' => self::getTableName()
		]);

		/**
		 * Save record
		 */

		if($primary != false && isset($this->{$primary})) {

			/**
			 * Update existing record
			 */

			unset($values[$primary]);
			unset($columns[$primary]);

			$builder->columns($columns);
			$builder->statement(QueryBuilder::STATEMENT_UPDATE);
			$builder->where('id = ' . $this->{$primary});

			$query = $connection->prepareQuery($builder->getQuery(), $values);
			if(!$query->execute())
				return false;

		} else {

			if(isset($columns[self::COLUMN_CREATED_AT]))
				$values[self::COLUMN_CREATED_AT] = date('Y-m-d H:i:s');

			/**
			 * Create new if primary field doesn't exist
			 */

			$builder->statement(QueryBuilder::STATEMENT_INSERT);

			$query = $connection->prepareQuery($builder->getQuery(), $values);
			if(!$query->execute())
				return false;

			return $connection->getLastInsertId();
		}

		return true;
	}

	public function delete($permanently = false) {

		$connection = self::getConnection();

		$columns = $connection->getTableColumns(self::getTableName());

		if(!$permanently) {

			if(!isset($columns[self::COLUMN_DELETED_AT]))
				throw new MissingColumnException('Column `' . self::COLUMN_DELETED_AT . '` is missing for impermanent removing records.');

			/**
			 * Set column DELETED_AT to actual DateTime and save it
			 */

			$this->{self::COLUMN_DELETED_AT} = date('Y-m-d H:i:s');

			return $this->save();

		} else {

			foreach($columns as $field => $column) {

				if($column['primary'])
					$primary = $field;

			}

			if($primary != false && isset($this->{$primary})) {

				$builder = new QueryBuilder([
					'source' => self::getTableName(),
					'statement' => QueryBuilder::STATEMENT_DELETE
				]);

				$builder->where('id = ' . $this->{$primary});

				$query = $connection->prepareQuery($builder->getQuery());

				if(!$query->execute())
					return false;

			} else
				throw new MissingPrimaryKeyException('Can\'t delete model when primary key is missing in model.');

		}

		return true;
	}

	/**
	 * Get connection
	 * --------------
	 * Get database connection instance
	 *
	 * @return PDO
	 *
	 * @throws \Postmix\Exception
	 */

	private static function getConnection() {

		if(!isset(self::$connection)) {

			$injector = Application::getStaticInjector();

			/** @var PDO $connection */

			self::$connection = $injector->get('database');

		}

		return self::$connection;
	}

	/**
	 * Get table name
	 * --------------
	 * Get source table name for database quering
	 *
	 * @return string
	 * @throws Exception
	 */

	private static function getTableName() {

		if(get_called_class() == self::class)
			throw new Exception('Can\'t perform operations with base model.');

		return isset(self::$sourceTableName) ? self::$sourceTableName : strtolower(substr(strrchr(get_called_class(), "\\"), 1));
	}

	/**
	 * @return string
	 */

	public function getSourceName() {

		return self::getTableName();
	}

}