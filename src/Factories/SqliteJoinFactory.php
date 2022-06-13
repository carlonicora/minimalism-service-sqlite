<?php
namespace CarloNicora\Minimalism\Services\Sqlite\Factories;

use CarloNicora\Minimalism\Exceptions\MinimalismException;
use CarloNicora\Minimalism\Interfaces\Sql\Enums\SqlJoinType;
use CarloNicora\Minimalism\Interfaces\Sql\Interfaces\SqlJoinFactoryInterface;
use UnitEnum;

class SqliteJoinFactory implements SqlJoinFactoryInterface
{
    /**
     * @param UnitEnum $primaryKey
     * @param UnitEnum $foreignKey
     * @param SqlJoinType|null $joinType
     */
    public function __construct(
        private readonly UnitEnum $primaryKey,
        private readonly UnitEnum $foreignKey,
        private readonly ?SqlJoinType $joinType=null,
    )
    {
    }

    /**
     * @return string
     * @throws MinimalismException
     */
    public function getSql(
    ): string
    {
        $primaryTable = SqliteTableFactory::create(get_class($this->primaryKey));
        $foreignTable = SqliteTableFactory::create(get_class($this->foreignKey));


        return ($this->joinType !== null ? $this->joinType->value . ' JOIN' : 'JOIN')
            . ' ' . $foreignTable->getFullName()
            . ' ON ' . $primaryTable->getFieldByName($this->primaryKey->name)->getFullName()
            . '=' . $foreignTable->getFieldByName($this->foreignKey->name)->getFullName();
    }
}