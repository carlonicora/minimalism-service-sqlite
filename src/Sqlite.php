<?php
namespace CarloNicora\Minimalism\Services\Sqlite;

use CarloNicora\Minimalism\Abstracts\AbstractService;
use CarloNicora\Minimalism\Exceptions\MinimalismException;
use CarloNicora\Minimalism\Interfaces\Cache\Interfaces\CacheBuilderInterface;
use CarloNicora\Minimalism\Interfaces\Sql\Interfaces\SqlDataObjectInterface;
use CarloNicora\Minimalism\Interfaces\Sql\Interfaces\SqlInterface;
use CarloNicora\Minimalism\Interfaces\Sql\Interfaces\SqlQueryFactoryInterface;
use Exception;

class Sqlite extends AbstractService implements SqlInterface
{

    public function create(
        SqlDataObjectInterface|array|SqlQueryFactoryInterface $queryFactory,
        ?CacheBuilderInterface $cacheBuilder = null,
        ?string $responseType = null,
        bool $requireObjectsList = false,
        array $options = [],
    ): SqlDataObjectInterface|array
    {
        // TODO: Implement create() method.
    }

    public function read(
        SqlQueryFactoryInterface $queryFactory,
        ?CacheBuilderInterface $cacheBuilder = null,
        ?string $responseType = null,
        bool $requireObjectsList = false,
        array $options = [],
    ): SqlDataObjectInterface|array
    {
        // TODO: Implement read() method.
    }

    public function update(
        SqlDataObjectInterface|array|SqlQueryFactoryInterface $queryFactory,
        ?CacheBuilderInterface $cacheBuilder = null,
        array $options = [],
    ): void
    {
        // TODO: Implement update() method.
    }

    public function delete(
        SqlDataObjectInterface|array|SqlQueryFactoryInterface $queryFactory,
        ?CacheBuilderInterface $cacheBuilder = null,
        array $options = [],
    ): void
    {
        // TODO: Implement delete() method.
    }

    public function getFactory(
        string $baseFactory,
    ): string
    {
        // TODO: Implement getFactory() method.
    }
}