<?php
namespace CarloNicora\Minimalism\Services\Sqlite\Factories;

use BackedEnum;
use CarloNicora\Minimalism\Enums\HttpCode;
use CarloNicora\Minimalism\Exceptions\MinimalismException;
use CarloNicora\Minimalism\Interfaces\Sql\Data\SqlComparisonObject;
use CarloNicora\Minimalism\Interfaces\Sql\Enums\SqlComparison;
use CarloNicora\Minimalism\Interfaces\Sql\Enums\SqlDatabaseOperationType;
use CarloNicora\Minimalism\Interfaces\Sql\Enums\SqlFieldType;
use CarloNicora\Minimalism\Interfaces\Sql\Interfaces\SqlJoinFactoryInterface;
use CarloNicora\Minimalism\Interfaces\Sql\Interfaces\SqlOrderByInterface;
use CarloNicora\Minimalism\Interfaces\Sql\Interfaces\SqlQueryFactoryInterface;
use CarloNicora\Minimalism\Interfaces\Sql\Interfaces\SqlTableInterface;
use CarloNicora\Minimalism\Services\Sqlite\Data\SqliteField;
use CarloNicora\Minimalism\Services\Sqlite\Data\SqliteTable;
use CarloNicora\Minimalism\Services\Sqlite\Enums\SqliteFieldType;
use UnitEnum;

class SqliteQueryFactory implements SqlQueryFactoryInterface
{
    /** @var SqlDatabaseOperationType  */
    private SqlDatabaseOperationType $databaseOperationType;

    /** @var SqlTableInterface|SqliteTable  */
    private SqlTableInterface|SqliteTable $table;

    /** @var string|null  */
    private ?string $sql=null;

    /** @var array  */
    private array $parameters=[];

    /** @var string  */
    private string $operandAndFields;

    /** @var string  */
    private string $from;

    /** @var SqliteJoinFactory[] */
    private array $join=[];

    /** @var SqlComparisonObject[]  */
    private array $where=[];

    /** @var UnitEnum[] */
    private array $groupBy=[];

    /** @var SqlComparisonObject[] */
    private array $having=[];

    /** @var SqlOrderByInterface[] */
    private array $orderBy=[];

    /** @var int|null  */
    private ?int $start=null;

    /** @var int|null  */
    private ?int $length=null;

    /**
     * @param string $tableClass
     * @param string|null $overrideDatabaseIdentifier
     * @throws MinimalismException
     */
    public function __construct(
        private readonly string $tableClass,
        ?string $overrideDatabaseIdentifier=null,
    )
    {
        $this->table = SqliteTableFactory::create($this->tableClass, $overrideDatabaseIdentifier);
    }

    /**
     * @param string $tableClass
     * @param string|null $overrideDatabaseIdentifier
     * @return SqlQueryFactoryInterface
     * @throws MinimalismException
     */
    public static function create(
        string $tableClass,
        ?string $overrideDatabaseIdentifier=null,
    ): SqlQueryFactoryInterface
    {
        return (new self($tableClass, $overrideDatabaseIdentifier))->selectAll();
    }

    /**
     * @return SqlQueryFactoryInterface
     */
    public function selectAll(
    ): SqlQueryFactoryInterface
    {
        $this->databaseOperationType = SqlDatabaseOperationType::Read;
        $this->operandAndFields = 'SELECT *';
        $this->from = 'FROM ' . $this->table->getFullName();

        return $this;
    }

    /**
     * @param UnitEnum[]|string[] $fields
     * @return SqlQueryFactoryInterface
     * @throws MinimalismException
     */
    public function selectFields(
        array $fields,
    ): SqlQueryFactoryInterface
    {
        $this->databaseOperationType = SqlDatabaseOperationType::Read;
        $this->operandAndFields = 'SELECT ';

        foreach ($fields as $field){
            if (is_string($field)){
                $this->operandAndFields .= $field . ',';
            } else {
                $this->operandAndFields .= self::create(get_class($field))->getTable()->getFieldByName($field->name)->getFullName() . ',';
            }
        }

        $this->operandAndFields = substr($this->operandAndFields, 0, -1);

        $this->from = 'FROM ' . $this->table->getFullName();

        return $this;
    }

    /**
     * @return SqlQueryFactoryInterface
     */
    public function delete(
    ): SqlQueryFactoryInterface
    {
        $this->databaseOperationType = SqlDatabaseOperationType::Delete;
        $this->operandAndFields = 'DELETE';
        $this->from = 'FROM ' . $this->table->getFullName();

        return $this;
    }

    /**
     * @return SqlQueryFactoryInterface
     */
    public function update(
    ): SqlQueryFactoryInterface
    {
        $this->databaseOperationType = SqlDatabaseOperationType::Update;
        $this->operandAndFields = 'UPDATE';
        $this->from = $this->table->getFullName();

        return $this;
    }

    /**
     * @return SqlQueryFactoryInterface
     */
    public function insert(
    ): SqlQueryFactoryInterface
    {
        $this->databaseOperationType = SqlDatabaseOperationType::Create;
        $this->operandAndFields = 'INSERT' . ($this->table->isInsertIgnore() ? ' IGNORE' : '') . ' INTO';
        $this->from = $this->table->getFullName();

        return $this;
    }

    /**
     * @param SqlJoinFactoryInterface $join
     * @return SqlQueryFactoryInterface
     */
    public function addJoin(
        SqlJoinFactoryInterface $join
    ): SqlQueryFactoryInterface
    {
        $this->join[] = $join;

        return $this;
    }

    /**
     * @param UnitEnum[] $fields
     * @return SqlQueryFactoryInterface
     */
    public function addGroupByFields(
        array $fields,
    ): SqlQueryFactoryInterface
    {
        $this->groupBy = $fields;

        return $this;
    }

    /**
     * @param SqlOrderByInterface[] $fields
     * @return SqlQueryFactoryInterface
     */
    public function addOrderByFields(
        array $fields,
    ): SqlQueryFactoryInterface
    {
        $this->orderBy = $fields;

        return $this;
    }

    /**
     * @param string $sql
     * @return SqlQueryFactoryInterface
     */
    public function setSql(
        string $sql,
    ): SqlQueryFactoryInterface
    {
        $this->sql = $sql;

        return $this;
    }

    /**
     * @param UnitEnum|string $field
     * @param mixed $value
     * @param SqlComparison|null $comparison
     * @param SqlFieldType|null $stringParameterType
     * @param bool $isHaving
     * @throws MinimalismException
     */
    private function addParam(
        UnitEnum|string $field,
        mixed $value,
        ?SqlComparison $comparison=SqlComparison::Equal,
        ?SqlFieldType $stringParameterType=null,
        bool $isHaving=false,
    ): void
    {
        if ($this->parameters === []){
            $this->parameters[] = '';
        }

        if (is_string($field)){
            $sqlField = $field;
            $this->parameters[0] .= match($stringParameterType) {
                SqlFieldType::Integer => 'i',
                SqlFieldType::Double => 'd',
                SqlFieldType::Blob => 'b',
                SqlFieldType::String => 's',
            };
        } else {
            $sqlField = self::create(get_class($field))->getTable()->getFieldByName($field->name);
            $this->parameters[0] .= $sqlField->getType();
        }
        if ($isHaving) {
            $this->having[] = new SqlComparisonObject(field: $sqlField, comparison: $comparison);
        } else {
            $this->where[] = new SqlComparisonObject(field: $sqlField, comparison: $comparison);
        }
        
        $this->parameters[] = [
            ':' . $sqlField->getName(),
            ($value instanceof BackedEnum) ? $value->value : $value,
        ];
    }

    /**
     * @param UnitEnum|string $field
     * @param mixed $value
     * @param SqlComparison|null $comparison
     * @param SqlFieldType|null $stringParameterType
     * @return SqlQueryFactoryInterface
     * @throws MinimalismException
     */
    public function addParameter(
        UnitEnum|string $field,
        mixed $value,
        ?SqlComparison $comparison=SqlComparison::Equal,
        ?SqlFieldType $stringParameterType=null,
    ): SqlQueryFactoryInterface
    {
        $this->addParam(
            field: $field,
            value: $value,
            comparison: $comparison,
            stringParameterType: $stringParameterType,
        );

        return $this;
    }

    /**
     * @param UnitEnum|string $field
     * @param mixed $value
     * @param SqlComparison|null $comparison
     * @param SqlFieldType|null $stringParameterType
     * @return SqlQueryFactoryInterface
     * @throws MinimalismException
     */
    public function addHavingParameter(
        UnitEnum|string $field,
        mixed $value,
        ?SqlComparison $comparison=SqlComparison::Equal,
        ?SqlFieldType $stringParameterType=null,
    ): SqlQueryFactoryInterface
    {
        $this->addParam(
            field: $field,
            value: $value,
            comparison: $comparison,
            stringParameterType: $stringParameterType,
            isHaving: true,
        );

        return $this;
    }

    /**
     * @param int $start
     * @param int $length
     * @return SqlQueryFactoryInterface
     */
    public function limit(
        int $start,
        int $length,
    ): SqlQueryFactoryInterface
    {
        $this->start = $start;
        $this->length = $length;
        return $this;
    }

    /**
     * @return SqlTableInterface
     */
    public function getTable(
    ): SqlTableInterface
    {
        return $this->table;
    }

    /**
     * @return string
     */
    public function getTableClass(
    ): string
    {
        return $this->tableClass;
    }

    /**
     * @return array
     * @throws MinimalismException
     */
    public function getInsertedArray(
    ): array
    {
        if ($this->databaseOperationType !== SqlDatabaseOperationType::Create) {
            throw new MinimalismException(HttpCode::InternalServerError, 'Get Inserted Array requested for non-creation query');
        }

        $response = [];

        $valueCount = 1;
        foreach ($this->where as $item){
            $response[$item->getField()->getName()] = $this->parameters[$valueCount][1];
            $valueCount++;
        }

        return $response;
    }

    /**
     * @return string
     * @throws MinimalismException
     */
    public function getSql(
    ): string
    {
        if ($this->sql !== null){
            return $this->sql;
        }

        $response = $this->operandAndFields . ' '. $this->from;

        if ($this->databaseOperationType === SqlDatabaseOperationType::Read) {
            foreach ($this->join as $join) {
                $response .= ' ' . $join->getSql();
            }
        }

        if ($this->databaseOperationType === SqlDatabaseOperationType::Create){
            if (empty($this->where)) {
                $response .= ' VALUES ()';
            } else {
                $additionalSql = '';
                $response .= ' (';

                foreach ($this->where as $field){
                    $response .= $field->getField()->getFullName() . ',';
                    $additionalSql .=  ':' . $field->getField()->getFullName() . ',';
                }

                $response = substr($response, 0, -1);
                $additionalSql = substr($additionalSql, 0, -1);

                $response .= ') VALUES (' . $additionalSql . ')';
            }
        } elseif  ($this->databaseOperationType === SqlDatabaseOperationType::Update) {
            $response .= ' SET ';

            $response .= $this->generateWhereStatement(isUpdate: true);
        } else {
            $response .= $this->generateWhereStatement();

            if ($this->databaseOperationType === SqlDatabaseOperationType::Read) {
                $isFirstGroupBy = true;
                foreach ($this->groupBy as $field) {
                    $response .= ' ' . ($isFirstGroupBy ? 'GROUP BY ' : ',') . self::create(get_class($field))->getTable()->getField($field)->getFullName();
                    $isFirstGroupBy = false;
                }

                $response .= $this->generateWhereStatement(true);

                $isFirstOrderBy = true;
                foreach ($this->orderBy as $orderByField) {
                    $response .= ' ' . ($isFirstOrderBy ? 'ORDER BY ' : ',') . $orderByField->getField()->getFullName() . ($orderByField->isDesc() ? ' DESC' : '');
                    $isFirstOrderBy = false;
                }
            }
        }

        if ($this->databaseOperationType === SqlDatabaseOperationType::Read && $this->start !== null && $this->length !== null) {
            $response .= ' LIMIT ' . $this->start . ',' . $this->length;
        }

        $response .= ';';

        return $response;
    }

    /**
     * @return array
     */
    public function getParameters(
    ): array
    {
        if ($this->databaseOperationType === SqlDatabaseOperationType::Update){
            $primaryKeyIds = '';
            $primaryKeys = [];
            $nonKeyIds = '';
            $nonKeys = [];

            $parameterCount = 1;
            foreach ($this->where as $field){
                if ($field->getField()->isPrimaryKey()){
                    $primaryKeys[] = $this->parameters[$parameterCount];
                    $primaryKeyIds .= substr($this->parameters[0], $parameterCount-1, 1);
                } else {
                    $nonKeys[] = $this->parameters[$parameterCount];
                    $nonKeyIds .= substr($this->parameters[0], $parameterCount-1, 1);
                }
                $parameterCount++;
            }

            $response = [$nonKeyIds . $primaryKeyIds, ...$nonKeys, ...$primaryKeys];
        } else {
            $response = $this->parameters;
        }
        return $response;
    }

    /**
     * @param bool $isHaving
     * @param bool $isUpdate
     * @return string
     * @throws MinimalismException
     */
    private function generateWhereStatement(
        bool $isHaving=false,
        bool $isUpdate=false,
    ): string
    {
        $response = '';
        $additionalSql = '';

        if ($isHaving){
            $clauses = $this->having;
            $initialStatement = 'HAVING';
        } else {
            $clauses = $this->where;
            $initialStatement = 'WHERE';
        }

        $isFirstWhere = true;
        $parameterCount=0;
        foreach ($clauses as $field) {
            if (is_string($field->getField())){
                $fieldName = $field->getField();

                if ($isUpdate){
                    throw new MinimalismException(HttpCode::InternalServerError, 'Incorrect UPDATE string parameter');
                }
            } else {
                $fieldName = $field->getField()->getFullName();
            }

            $parameterCount++;

            if ($isUpdate){
                if ($field->getField()->isPrimaryKey()) {
                    $additionalSql .= ' ' . ($isFirstWhere ? $initialStatement : 'AND') . ' ' . $field->getField()->getFullName();
                    $isFirstWhere = false;
                } else {
                    $response .= $field->getField()->getFullName();
                }
            } else {
                $response .= ' ' . ($isFirstWhere ? $initialStatement : 'AND') . ' ' . $fieldName;
                $isFirstWhere = false;
            }

            $remove = true;

            $temporaryResponse = '';
            if ($this->parameters[$parameterCount] === null && !$isUpdate){
                if ($field->getComparison() === SqlComparison::Equal) {
                    $temporaryResponse .= ' IS NULL';
                } else {
                    $temporaryResponse .= ' IS NOT NULL';
                }
            } else {
                switch ($field->getComparison()) {
                    case SqlComparison::In:
                        if (is_array($this->parameters[$parameterCount])) {
                            $temporaryResponse .= ' IN (' . implode(separator: ',', array: $this->parameters[$parameterCount]) . ')';
                            break;
                        }
                        $temporaryResponse .= ' IN (' . $this->parameters[$parameterCount] . ')';
                        break;
                    case SqlComparison::NotIn:
                        if (is_array($this->parameters[$parameterCount])) {
                            $temporaryResponse .= ' NOT IN (' . implode(separator: ',', array: $this->parameters[$parameterCount]) . ')';
                            break;
                        }
                        $temporaryResponse .= ' NOT IN (' . $this->parameters[$parameterCount] . ')';
                        break;
                    case SqlComparison::Like:
                        $temporaryResponse .= ' LIKE \'%' . $this->parameters[$parameterCount] . '%\'';
                        break;
                    case SqlComparison::LikeLeft:
                        $temporaryResponse .= ' LIKE \'%' . $this->parameters[$parameterCount] . '\'';
                        break;
                    case SqlComparison::LikeRight:
                        $temporaryResponse .= ' LIKE \'' . $this->parameters[$parameterCount] . '%\'';
                        break;
                    default:
                        $remove = false;
                        $temporaryResponse .= $field->getComparison()->value . ':' . $fieldName;
                        break;
                }
            }

            if ($isUpdate){
                if ($field->getField()->isPrimaryKey()) {
                    $additionalSql .= $temporaryResponse;
                } else {
                    $response .= $temporaryResponse . ',';
                }
            } else {
                $response .= $temporaryResponse;
            }

            if ($remove){
                array_splice($this->parameters, $parameterCount, 1);
                $this->parameters[0] = substr($this->parameters[0],0,$parameterCount-1).substr($this->parameters[0],$parameterCount,strlen($this->parameters[0])-($parameterCount-1));

                if ($this->parameters === ['']) {
                    $this->parameters = [];
                }
                $parameterCount--;
            }
        }

        if ($isUpdate) {
            $response = substr($response, 0, -1);
            $response .= $additionalSql;
        }

        return $response;
    }
}