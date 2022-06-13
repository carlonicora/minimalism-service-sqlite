<?php
namespace CarloNicora\Minimalism\Services\Sqlite\Enums;

enum SqliteOptions
{
    case DisableForeignKeyCheck;

    /**
     * @return string
     */
    public function on(
    ): string
    {
        return match($this){
            self::DisableForeignKeyCheck => 'PRAGMA foreign_keys = OFF;',
        };
    }

    /**
     * @return string
     */
    public function off(
    ): string
    {
        return match($this){
            self::DisableForeignKeyCheck => 'PRAGMA foreign_keys = ON;',
        };
    }
}