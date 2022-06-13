<?php
namespace CarloNicora\Minimalism\Services\Sqlite;

use CarloNicora\Minimalism\Abstracts\AbstractService;
use CarloNicora\Minimalism\Enums\HttpCode;
use CarloNicora\Minimalism\Exceptions\MinimalismException;
use CarloNicora\Minimalism\Interfaces\Cache\Enums\CacheType;
use CarloNicora\Minimalism\Interfaces\Cache\Interfaces\CacheBuilderInterface;
use CarloNicora\Minimalism\Interfaces\Cache\Interfaces\CacheInterface;
use CarloNicora\Minimalism\Interfaces\Sql\Abstracts\AbstractSqlFactory;
use CarloNicora\Minimalism\Interfaces\Sql\Enums\SqlDatabaseOperationType;
use CarloNicora\Minimalism\Interfaces\Sql\Factories\SqlDataObjectFactory;
use CarloNicora\Minimalism\Interfaces\Sql\Factories\SqlJoinFactory;
use CarloNicora\Minimalism\Interfaces\Sql\Factories\SqlQueryFactory;
use CarloNicora\Minimalism\Interfaces\Sql\Factories\SqlTableFactory;
use CarloNicora\Minimalism\Interfaces\Sql\Interfaces\SqlDataObjectInterface;
use CarloNicora\Minimalism\Interfaces\Sql\Interfaces\SqlInterface;
use CarloNicora\Minimalism\Interfaces\Sql\Interfaces\SqlQueryFactoryInterface;
use CarloNicora\Minimalism\Interfaces\Sql\Interfaces\SqlStatementCommandInterface;
use CarloNicora\Minimalism\Services\Sqlite\Commands\SqliteCommand;
use CarloNicora\Minimalism\Services\Sqlite\Factories\SqliteConnectionFactory;
use CarloNicora\Minimalism\Services\Sqlite\Factories\SqliteJoinFactory;
use CarloNicora\Minimalism\Services\Sqlite\Factories\SqliteQueryFactory;
use CarloNicora\Minimalism\Services\Sqlite\Factories\SqliteTableFactory;
use Exception;
use Throwable;

class Sqlite extends AbstractService implements SqlInterface
{

    /** @var SqliteConnectionFactory  */
    private SqliteConnectionFactory $connectionFactory;

    /**
     * @param string $MINIMALISM_SERVICE_SQLITE
     * @param CacheInterface|null $cache
     */
    public function __construct(
        string $MINIMALISM_SERVICE_SQLITE,
        private ?CacheInterface $cache=null,
    )
    {
        $this->connectionFactory = new SqliteConnectionFactory(
            databaseConfigurations: $MINIMALISM_SERVICE_SQLITE,
        );

        if (!$this->cache?->useCaching()){
            $this->cache = null;
        }
    }

    /**
     * @return void
     */
    public function initialise(
    ): void
    {
        AbstractSqlFactory::setSqlInterface($this);
        SqliteTableFactory::initialise($this->connectionFactory->getConfigurations());
    }

    /**
     * @return void
     */
    public function destroy(
    ): void
    {
        $this->connectionFactory->resetDatabases();
    }

    /**
     * @return string|null
     */
    public static function getBaseInterface(
    ): ?string
    {
        return SqlInterface::class;
    }

    /**
     * @template InstanceOfType
     * @param SqlQueryFactoryInterface|SqlDataObjectInterface|SqlDataObjectInterface[] $queryFactory
     * @param CacheBuilderInterface|null $cacheBuilder
     * @param class-string<InstanceOfType>|null $responseType
     * @param bool $requireObjectsList
     * @param array $options
     * @return InstanceOfType|array
     * @throws MinimalismException
     * @throws Throwable
     */
    public function create(
        SqlDataObjectInterface|array|SqlQueryFactoryInterface $queryFactory,
        ?CacheBuilderInterface $cacheBuilder = null,
        ?string $responseType = null,
        bool $requireObjectsList = false,
        array $options = [],
    ): SqlDataObjectInterface|array
    {
        $response = $this->execute(
            databaseOperationType: SqlDatabaseOperationType::Create,
            queryFactory: $queryFactory,
            cacheBuilder: $cacheBuilder,
            options: $options,
        );

        if ($responseType !== null){
            if ($requireObjectsList){
                $response = $this->returnObjectArray(
                    recordset: $response,
                    objectType: $responseType,
                );
            } else {
                $response = $this->returnSingleObject(
                    recordset: $response,
                    objectType: $responseType,
                );
            }
        }

        return $response;
    }

    /**
     * @template InstanceOfType
     * @param SqlQueryFactoryInterface $queryFactory
     * @param CacheBuilderInterface|null $cacheBuilder
     * @param class-string<InstanceOfType>|null $responseType
     * @param bool $requireObjectsList
     * @param array $options
     * @return InstanceOfType|array
     * @throws Exception
     */
    public function read(
        SqlQueryFactoryInterface $queryFactory,
        ?CacheBuilderInterface $cacheBuilder = null,
        ?string $responseType = null,
        bool $requireObjectsList = false,
        array $options = [],
    ): SqlDataObjectInterface|array
    {

        $response = null;
        if ($this->cache !== null && $cacheBuilder !== null) {
            $response = $this->cache->readArray($cacheBuilder, CacheType::Data);
        }

        if ($response === null){
            $sqlCommand = new SqliteCommand(
                connectionFactory: $this->connectionFactory,
                factory: $queryFactory,
                options: $options,
            );
            try {
                $response = $sqlCommand->execute(
                    databaseOperationType: SqlDatabaseOperationType::Read,
                    queryFactory: $queryFactory,
                );
            } finally {
                $sqlCommand = null;
            }

            if ($this->cache !== null && $cacheBuilder !== null) {
                $this->cache->saveArray($cacheBuilder, $response, CacheType::Data);
            }
        } elseif ($response !== [] && !array_key_exists(0, $response)){
            $response = [$response];
        }

        if ($responseType !== null){
            if ($requireObjectsList){
                $response = $this->returnObjectArray(
                    recordset: $response,
                    objectType: $responseType,
                );
            } else {
                $response = $this->returnSingleObject(
                    recordset: $response,
                    objectType: $responseType,
                );
            }
        }

        return $response;
    }

    /**
     * @param SqlDataObjectInterface|SqlQueryFactoryInterface|SqlDataObjectInterface[] $queryFactory
     * @param CacheBuilderInterface|null $cacheBuilder
     * @param array $options
     * @return void
     * @throws MinimalismException
     * @throws Throwable
     */
    public function update(
        SqlDataObjectInterface|array|SqlQueryFactoryInterface $queryFactory,
        ?CacheBuilderInterface $cacheBuilder = null,
        array $options = [],
    ): void
    {
        /** @noinspection UnusedFunctionResultInspection */
        $this->execute(
            databaseOperationType: SqlDatabaseOperationType::Update,
            queryFactory: $queryFactory,
            cacheBuilder: $cacheBuilder,
            options: $options,
        );
    }

    /**
     * @param SqlDataObjectInterface|SqlQueryFactoryInterface|array $queryFactory
     * @param CacheBuilderInterface|null $cacheBuilder
     * @param array $options
     * @return void
     * @throws MinimalismException
     * @throws Throwable
     */
    public function delete(
        SqlDataObjectInterface|array|SqlQueryFactoryInterface $queryFactory,
        ?CacheBuilderInterface $cacheBuilder = null,
        array $options = [],
    ): void
    {
        /** @noinspection UnusedFunctionResultInspection */
        $this->execute(
            databaseOperationType: SqlDatabaseOperationType::Delete,
            queryFactory: $queryFactory,
            cacheBuilder: $cacheBuilder,
            options: $options,
        );
    }
    
    /**
     * @param string $baseFactory
     * @return mixed
     */
    public function getFactory(
        string $baseFactory,
    ): string
    {
        return match ($baseFactory){
            SqlTableFactory::class => SqliteTableFactory::class,
            SqlQueryFactory::class => SqliteQueryFactory::class,
            SqlJoinFactory::class => SqliteJoinFactory::class,
        };
    }

    /**
     * @param SqlDatabaseOperationType $databaseOperationType
     * @param SqlQueryFactoryInterface|SqlDataObjectInterface|SqlDataObjectInterface[] $queryFactory
     * @param CacheBuilderInterface|null $cacheBuilder
     * @param array $options
     * @return array|null
     * @throws MinimalismException
     * @throws Throwable
     */
    private function execute(
        SqlDatabaseOperationType $databaseOperationType,
        SqlQueryFactoryInterface|SqlDataObjectInterface|array $queryFactory,
        ?CacheBuilderInterface $cacheBuilder,
        array $options=[],
    ): ?array
    {
        $response = null;
        $sqlCommand = null;

        try {
            if (is_array($queryFactory)) {
                $response = [];
                $isFirstDataObjectInterface=true;
                foreach ($queryFactory as $dataObjectInterface) {
                    if ($isFirstDataObjectInterface) {
                        $sqlCommand = new SqliteCommand(
                            connectionFactory: $this->connectionFactory,
                            factory: $dataObjectInterface,
                            options: $options,
                        );
                    }
                    $isFirstDataObjectInterface=false;

                    $singleResponse = $sqlCommand->execute($databaseOperationType, $dataObjectInterface);
                    if ($singleResponse !== null){
                        $response[] = $singleResponse;
                    }
                }
            } else {
                $sqlCommand = new SqliteCommand(
                    connectionFactory: $this->connectionFactory,
                    factory: $queryFactory,
                    options: $options,
                );

                $singleResponse = $sqlCommand->execute($databaseOperationType, $queryFactory);
                if ($singleResponse !== null){
                    $response[] = $singleResponse;
                }
            }

            $sqlCommand?->commit();
        } catch (Exception|Throwable $e) {
            $sqlCommand?->rollback();
            throw $e;
        } finally {
            $sqlCommand = null;
        }

        if ($this->cache !== null && $cacheBuilder !== null) {
            $this->cache->invalidate($cacheBuilder);
        }

        return ($response);
    }

    /**
     * @template InstanceOfType
     * @param array $recordset
     * @param class-string<InstanceOfType> $objectType
     * @return InstanceOfType
     * @throws Exception
     */
    private function returnSingleObject(
        array $recordset,
        string $objectType,
    ): SqlDataObjectInterface
    {
        if ($recordset === [] || $recordset === [[]]){
            throw new MinimalismException(
                status: HttpCode::NotFound,
                message: 'Record Not found',
            );
        }

        if (array_is_list($recordset)){
            $response = SqlDataObjectFactory::createObject(
                objectClass: $objectType,
                data: $recordset[0],
            );
        } else {
            $response = SqlDataObjectFactory::createObject(
                objectClass: $objectType,
                data: $recordset,
            );
        }

        return $response;
    }

    /**
     * @template InstanceOfType
     * @param array $recordset
     * @param class-string<InstanceOfType> $objectType
     * @return InstanceOfType[]
     * @throws Exception
     */
    private function returnObjectArray(
        array $recordset,
        string $objectType,
    ): array
    {
        $response = [];

        if (array_is_list($recordset)) {
            foreach ($recordset ?? [] as $record) {
                $response[] = SqlDataObjectFactory::createObject(
                    objectClass: $objectType,
                    data: $record,
                );
            }
        } else {
            $response[] = SqlDataObjectFactory::createObject(
                objectClass: $objectType,
                data: $recordset[0],
            );
        }

        return $response;
    }
}