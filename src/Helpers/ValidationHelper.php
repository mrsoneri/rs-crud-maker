<?php

namespace MrSoneri\MakeResource\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ValidationHelper
{
    public static function isColumnNullable($tableName, $columnName)
    {
        $columnInfo = DB::select(
            'SELECT COLUMN_NAME, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$tableName, $columnName]
        );

        return !empty($columnInfo) && $columnInfo[0]->IS_NULLABLE === 'YES';
    }

    public static function getValidationRules($type, $isNullable)
    {
        $nullableString = $isNullable ? 'nullable|' : '';

        switch ($type) {
            case 'string':
                return $nullableString . 'string|max:255';
            case 'integer':
                return $nullableString . 'integer';
            case 'boolean':
                return $nullableString . 'boolean';
            default:
                return $nullableString . 'string';
        }
    }
}
