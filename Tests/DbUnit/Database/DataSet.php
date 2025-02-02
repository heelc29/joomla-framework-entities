<?php

/*
 * This file is part of DbUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Joomla\Entity\Tests\DbUnit\Database;

use Joomla\Entity\Tests\DbUnit\DataSet\AbstractDataSet;
use Joomla\Entity\Tests\DbUnit\DataSet\DefaultTableMetadata;
use Joomla\Entity\Tests\DbUnit\DataSet\ITableMetadata;
use Joomla\Entity\Tests\DbUnit\InvalidArgumentException;
use Joomla\Entity\Tests\DbUnit\RuntimeException;

/**
 * Provides access to a database instance as a data set.
 */
class DataSet extends AbstractDataSet
{
    /**
     * An array of ITable objects.
     *
     * @var array
     */
    protected $tables = [];

    /**
     * The database connection this dataset is using.
     *
     * @var Connection
     */
    protected $databaseConnection;

    /**
     * Creates the query necessary to pull all of the data from a table.
     *
     * @param ITableMetadata $tableMetaData
     * @param Connection|null $databaseConnection
     *
     * @return string
     */
    public static function buildTableSelect(ITableMetadata $tableMetaData, ?Connection $databaseConnection = null)
    {
        if ($tableMetaData->getTableName() == '') {
            $e = new RuntimeException('Empty Table Name');
            print $e->getTraceAsString();

            throw $e;
        }

        $columns = $tableMetaData->getColumns();

        if ($databaseConnection) {
            $columns = \array_map([$databaseConnection, 'quoteSchemaObject'], $columns);
        }
        $columnList = \implode(', ', $columns);

        if ($databaseConnection) {
            $tableName = $databaseConnection->quoteSchemaObject($tableMetaData->getTableName());
        } else {
            $tableName = $tableMetaData->getTableName();
        }

        $primaryKeys = $tableMetaData->getPrimaryKeys();

        if ($databaseConnection) {
            $primaryKeys = \array_map([$databaseConnection, 'quoteSchemaObject'], $primaryKeys);
        }

        if (\count($primaryKeys)) {
            $orderBy = 'ORDER BY ' . \implode(' ASC, ', $primaryKeys) . ' ASC';
        } else {
            $orderBy = '';
        }

        return "SELECT {$columnList} FROM {$tableName} {$orderBy}";
    }

    /**
     * Creates a new dataset using the given database connection.
     *
     * @param Connection $databaseConnection
     */
    public function __construct(Connection $databaseConnection)
    {
        $this->databaseConnection = $databaseConnection;
    }

    /**
     * Returns a table object for the given table.
     *
     * @param string $tableName
     *
     * @return Table
     */
    public function getTable($tableName)
    {
        if (!\in_array($tableName, $this->getTableNames())) {
            throw new InvalidArgumentException("$tableName is not a table in the current database.");
        }

        if (empty($this->tables[$tableName])) {
            $this->tables[$tableName] = new Table($this->getTableMetaData($tableName), $this->databaseConnection);
        }

        return $this->tables[$tableName];
    }

    /**
     * Returns a table meta data object for the given table.
     *
     * @param string $tableName
     *
     * @return DefaultTableMetadata
     */
    public function getTableMetaData($tableName)
    {
        return new DefaultTableMetadata($tableName, $this->databaseConnection->getMetaData()->getTableColumns($tableName), $this->databaseConnection->getMetaData()->getTablePrimaryKeys($tableName));
    }

    /**
     * Returns a list of table names for the database
     *
     * @return array
     */
    public function getTableNames()
    {
        return $this->databaseConnection->getMetaData()->getTableNames();
    }

    /**
     * Creates an iterator over the tables in the data set. If $reverse is
     * true a reverse iterator will be returned.
     *
     * @param bool $reverse
     *
     * @return TableIterator
     */
    protected function createIterator($reverse = false)
    {
        return new TableIterator($this->getTableNames(), $this, $reverse);
    }
}
