<?php
namespace CarloNicora\Minimalism\Services\Sqlite\Data;

use CarloNicora\Minimalism\Interfaces\Sql\Attributes\SqlFieldAttribute;
use CarloNicora\Minimalism\Interfaces\Sql\Enums\SqlFieldType;

class SqliteField extends SqlFieldAttribute
{
    /**
     * @return string
     */
    public function getFullName(
    ): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getType(
    ): string
    {
        $response = SQLITE3_TEXT;

        if (($this->fieldType->value & SqlFieldType::Integer->value) > 0){
            $response = SQLITE3_INTEGER;
        } elseif (($this->fieldType->value & SqlFieldType::Double->value) > 0){
            $response = SQLITE3_FLOAT;
        } elseif (($this->fieldType->value & SqlFieldType::Blob->value) > 0){
            $response = SQLITE3_BLOB;
        }

        return $response;
    }
}