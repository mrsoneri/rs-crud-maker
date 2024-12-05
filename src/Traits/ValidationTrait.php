<?php

namespace RsCrud\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait ValidationTrait
{
    public function generateRulesFromTable($tableName)
    {
        if (!Schema::hasTable($tableName)) {
            $this->error("Table '{$tableName}' does not exist.");
            return [];
        }

        $columns = Schema::getColumnListing($tableName);
        $rules = [];

        foreach ($columns as $column) {
            // Skip system fields
            if (in_array($column, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            $columnDetails = DB::select(
                'SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH, COLUMN_DEFAULT 
                 FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$tableName, $column]
            );

            if (empty($columnDetails)) {
                continue;
            }

            $details = $columnDetails[0];
            $rules[$column] = $this->mapColumnToValidationRule($details, $tableName); // Pass $tableName explicitly
        }

        return $rules;
    }

    public function mapColumnToValidationRule($details, $tableName)
    {
        $type = $details->DATA_TYPE;
        $isNullable = $details->IS_NULLABLE === 'YES';
        $maxLength = $details->CHARACTER_MAXIMUM_LENGTH;

        $rule = $isNullable ? 'nullable|' : 'required|';

        switch ($type) {
            case 'varchar':
            case 'char':
            case 'text':
                $rule .= 'string';
                if ($maxLength) {
                    $rule .= "|max:{$maxLength}";
                }
                break;

            case 'int':
            case 'bigint':
            case 'smallint':
            case 'mediumint':
                $rule .= 'integer';
                break;

            case 'decimal':
            case 'float':
            case 'double':
                $rule .= 'numeric';
                break;

            case 'date':
                $rule .= 'date';
                break;

            case 'datetime':
            case 'timestamp':
                $rule .= 'date_format:Y-m-d H:i:s';
                break;

            case 'enum':
                // Fetch enum values using SHOW COLUMNS and the passed table name
                $enumColumn = DB::select("SHOW COLUMNS FROM `{$tableName}` WHERE Field = ?", [$details->COLUMN_NAME])[0];
                $enumValues = str_replace(['enum(', ')', "'"], '', $enumColumn->Type);
                $enumValuesArray = explode(',', $enumValues);

                $rule .= 'in:' . implode(',', $enumValuesArray);
                break;

            case 'json':
                $rule .= 'json';
                break;

            case 'boolean':
            case 'tinyint': // Boolean is often stored as tinyint(1)
                $rule .= 'boolean';
                break;

            default:
                $rule .= 'string';
        }

        return $rule;
    }

    public function generateMessagesFromRules($rules)
    {
        $messageStrings = [];

        foreach ($rules as $field => $ruleString) {
            $ruleParts = explode('|', $ruleString);

            foreach ($ruleParts as $rule) {
                // Extract rule name and parameters
                [$ruleName, $params] = explode(':', $rule . ':', 2);
                $value = str_replace('_', ' ', ucfirst($field));
                // Generate messages for common rule types
                switch ($ruleName) {
                    case 'required':
                        $messageStrings[] = "'{$field}.required' => '" . $value . " is required.'";
                        break;

                    case 'nullable':
                        // Nullable typically doesn't need a message
                        break;

                    case 'string':
                        $messageStrings[] = "'{$field}.string' => '" . $value . " must be a valid string.'";
                        break;

                    case 'max':
                        $params = str_replace(':', '', $ruleName);
                        $messageStrings[] = "'{$field}.max' => '" . $value . " may not be greater than {$params} characters.'";
                        break;

                    case 'integer':
                        $messageStrings[] = "'{$field}.integer' => '" . $value . " must be an integer.'";
                        break;

                    case 'numeric':
                        $messageStrings[] = "'{$field}.numeric' => '" . $value . " must be a valid number.'";
                        break;

                    case 'boolean':
                        $messageStrings[] = "'{$field}.boolean' => '" . $value . " must be true or false.'";
                        break;

                    case 'date':
                        $messageStrings[] = "'{$field}.date' => '" . $value . " must be a valid date.'";
                        break;

                    case 'date_format':
                        $messageStrings[] = "'{$field}.date_format' => '" . $value . " must match the format {$params}.'";
                        break;

                    case 'in':
                        $values = str_replace(',', ', ', $params);
                        $messageStrings[] = "'{$field}.in' => '" . $value . " must be one of the following: {$values}.'";
                        break;

                    case 'json':
                        $messageStrings[] = "'{$field}.json' => '" . $value . " must be a valid JSON string.'";
                        break;

                    case 'exists':
                        $messageStrings[] = "'{$field}.exists' => '" . $value . " must exist in the related table.'";
                        break;

                    default:
                        // Generic fallback for other rules
                        $messageStrings[] = "'{$field}.{$ruleName}' => '" . $value . " validation failed for rule {$ruleName}.'";
                        break;
                }
            }
        }

        // Combine message strings into a single string, separated by newlines
        return implode(",\n\t", $messageStrings);
    }
}
