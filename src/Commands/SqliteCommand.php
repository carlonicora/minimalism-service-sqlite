<?php
namespace CarloNicora\Minimalism\Services\Sqlite\Commands;

use CarloNicora\Minimalism\Exceptions\MinimalismException;
use CarloNicora\Minimalism\Interfaces\Sql\Enums\SqlDatabaseOperationType;
use CarloNicora\Minimalism\Interfaces\Sql\Factories\SqlExceptionFactory;
use CarloNicora\Minimalism\Interfaces\Sql\Interfaces\SqlDataObjectInterface;
use CarloNicora\Minimalism\Interfaces\Sql\Interfaces\SqlQueryFactoryInterface;
use CarloNicora\Minimalism\Services\Sqlite\Enums\SqliteOptions;
use CarloNicora\Minimalism\Services\Sqlite\Factories\SqliteConnectionFactory;
use Exception;
use SQLite3;

class SqliteCommand
{
    /** @var SQLite3  */
    private SQLite3 $connection;

    /**
     * @param SqliteConnectionFactory $connectionFactory
     * @param SqlQueryFactoryInterface|SqlDataObjectInterface $factory
     * @param SqliteOptions[] $options
     * @throws MinimalismException
     */
    public function __construct(
        SqliteConnectionFactory                         $connectionFactory,
        SqlQueryFactoryInterface|SqlDataObjectInterface $factory,
        private readonly array                          $options,
    )
    {
        $this->connection = $connectionFactory->create($factory->getTable());
    }

    /**
     *
     */
    public function rollback(): void
    {
        $this->connection->exec('ROLLBACK;');
    }

    /**
     *
     */
    public function commit(): void
    {
        $this->connection->exec('COMMIT;');
    }

    /**
     * @return int|null
     */
    public function getInsertedId(
    ): ?int
    {
        return $this->connection->lastInsertRowID();
    }

    /**
     * @param SqlDatabaseOperationType $databaseOperationType
     * @param SqlQueryFactoryInterface|SqlDataObjectInterface $queryFactory
     * @param int $retry
     * @return array|null
     * @throws MinimalismException|Exception
     */
    public function execute(
        SqlDatabaseOperationType $databaseOperationType,
        SqlQueryFactoryInterface|SqlDataObjectInterface $queryFactory,
        int $retry=0,
    ): ?array
    {
        $response = null;

        $this->connection->exec('BEGIN;');
        $this->runOptions();

        $interfaces = class_implements($queryFactory);
        if (array_key_exists(SqlQueryFactoryInterface::class, $interfaces)){
            $sqlFactory = $queryFactory;
        } else {
            $sqlFactory = $databaseOperationType->getSqlStatementCommand($queryFactory);
        }
        $sql = $sqlFactory->getSql();
        $parameters = $sqlFactory->getParameters();

        $statement = $this->connection->prepare($sql);

        if ($statement === false) {
            throw SqlExceptionFactory::SqlStatementPreparationFailed->create($sql . '(' . $this->connection->lastErrorMsg() . ')');
        }

        if (!empty($parameters)) {
            $params = str_split($parameters[0]);
            foreach($parameters as $parameterKey => $parameter){
                if ($parameterKey > 0){
                    $statement->bindParam(param: $parameter[0], var: $parameter[1], type: $params[$parameterKey-1]);
                }
            }
        }

        if (!$statement->execute()) {
            if ($retry<10 && $this->connection->lastErrorCode()===5){
                $retry++;
                usleep(100000);
                /** @noinspection UnusedFunctionResultInspection */
                $this->execute($databaseOperationType, $queryFactory, $retry);
            } else {
                throw SqlExceptionFactory::SqlStatementExecutionFailed->create($sql . '(' . $this->connection->lastErrorMsg() . ')');
            }
        }

        if ($databaseOperationType === SqlDatabaseOperationType::Read) {
            $results = $statement->execute();

            $response = [];
            if ($results !== false) {
                while ($record = $results->fetchArray()) {
                    $this->setOriginalValues($record);
                    $response[] = $record;
                }
            }
        } elseif ($databaseOperationType === SqlDatabaseOperationType::Create) {
            $response = $sqlFactory->getInsertedArray();

            if ($sqlFactory->getTable()->getAutoIncrementField() !== null){
                $response[$sqlFactory->getTable()->getAutoIncrementField()->getName()] = $this->getInsertedId();
            }

            $this->setOriginalValues($response);
        }

        if (!$statement->close()) {
            throw SqlExceptionFactory::SqlCloseFailed->create($this->connection->lastErrorMsg());
        }

        $this->runOptions(on: false);

        return $response;
    }

    /**
     * @param $arr
     * @return array
     */
    private function refValues($arr): array
    {
        $refs = [];

        foreach ($arr as $key => $value) {
            /** @noinspection PhpArrayAccessCanBeReplacedWithForeachValueInspection */
            $refs[$key] = &$arr[$key];
        }

        return $refs;
    }

    /**
     * @param array $record
     */
    public function setOriginalValues(array &$record): void
    {
        $originalValues = [];
        foreach($record as $fieldName=>$fieldValue){
            $originalValues[$fieldName] = $fieldValue;
        }
        $record['originalValues'] = $originalValues;
    }

    /**
     * @param bool $on
     * @return void
     */
    public function runOptions(
        bool $on=true,
    ): void
    {
        foreach ($this->options as $option){
            /** @noinspection UnusedFunctionResultInspection */
            $this->connection->query(query: ($on ? $option->on() : $option->off()));
        }
    }
}