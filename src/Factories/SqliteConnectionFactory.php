<?php
namespace CarloNicora\Minimalism\Services\Sqlite\Factories;

use CarloNicora\Minimalism\Exceptions\MinimalismException;
use CarloNicora\Minimalism\Interfaces\Sql\Factories\SqlExceptionFactory;
use CarloNicora\Minimalism\Interfaces\Sql\Interfaces\SqlTableInterface;
use Exception;
use SQLite3;
use Throwable;

class SqliteConnectionFactory
{
    /** @var SQLite3[] */
    private array $databases = [];

    /** @var array */
    private array $databaseConnectionStrings = [];

    /**
     * @param string $databaseConfigurations
     */
    public function __construct(
        string $databaseConfigurations,
    )
    {
        if (!empty($databaseConfigurations)) {
            $databaseNames = explode(',', $databaseConfigurations);
            foreach ($databaseNames ?? [] as $databaseName) {
                if (!array_key_exists($databaseName, $this->databaseConnectionStrings)) {
                    $this->databaseConnectionStrings[$databaseName] = $_ENV[trim($databaseName)];
                }
            }
        }
    }

    /**
     *
     */
    public function __destruct(
    )
    {
        $this->resetDatabases();
    }

    /**
     * @return array
     */
    public function getConfigurations(
    ): array
    {
        return $this->databaseConnectionStrings;
    }

    /**
     * @param SqlTableInterface $table
     * @return SQLite3
     * @throws MinimalismException
     */
    public function create(
        SqlTableInterface $table,
    ): SQLite3
    {
        if (!array_key_exists($table->getDatabaseIdentifier(), $this->databaseConnectionStrings)){
            throw SqlExceptionFactory::DatabaseConnectionStringMissing->create($table->getDatabaseIdentifier());
        }

        $response = new SQLite3($this->databaseConnectionStrings[$table->getDatabaseIdentifier()]);

        if ($response->lastErrorCode()) {
            throw SqlExceptionFactory::ErrorConnectingToTheDatabase->create($this->databaseConnectionStrings[$table->getDatabaseIdentifier()]);
        }

        $this->databases[$table->getDatabaseIdentifier()] = $response;

        return $response;
    }

    /**
     *
     */
    public function resetDatabases(
    ) : void
    {
        /**
         * @var string $databaseKey
         * @var SQLite3 $connection
         */
        foreach ($this->databases as $connection){
            try {
                $connection?->close();
            } catch (Exception|Throwable) {
            }
        }

        $this->databases = [];
    }
}