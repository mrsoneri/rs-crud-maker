<?php

namespace RsCrud\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RsCrudMaker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rscrudmaker:create {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate CRUD operations, repository, and service classes pattern for a new resource';

    /**
     * Base app path.
     *
     * @var string
     */
    protected $basePath = 'app/';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Step 1: Get the 'name' argument
        $name = $this->argument('name');
        $formattedName = $this->formatName($name);
        $providerPath = app_path('Providers/RepositoryServiceProvider.php');

        // // Define paths
        $controllerPath = $this->basePath . 'Http/Controllers/' . $formattedName;
        $servicePath = $this->basePath . 'Services/' . $formattedName;
        $repositoryPathInterface = $this->basePath . 'Repositories/Contract/' . $formattedName;
        $repository = $this->basePath . 'Repositories/Eloquent/' . $formattedName;
        $requestPath = $this->basePath . 'Http/Requests/' . $formattedName;

        // // Generate files
        $this->generateController($formattedName, $controllerPath);
        $this->generateService($formattedName, $servicePath);
        $this->generateRepositoryInterface($formattedName, $repositoryPathInterface);
        $this->generateRepository($formattedName, $repository);
        $this->generateRequest($formattedName, $requestPath);
        if (!File::exists($providerPath)) {
            $this->generateRepositoryServiceProvider($providerPath);
        }
    }

    /**
     * Format the input name to match directory conventions.
     */
    protected function formatName($name)
    {
        $segments = explode('/', $name);
        return implode('/', array_map('ucfirst', $segments));
    }

    /**
     * Generate a controller file.
     */
    protected function generateController($name, $path)
    {
        $this->createFileFromStub(
            $name,
            $path,
            'controller.stub',
            'Http/Controllers',
            'Controller.php'
        );
    }

    /**
     * Generate a service file.
     */
    protected function generateService($name, $path)
    {
        $this->createFileFromStub(
            $name,
            $path,
            'service.stub',
            'Services',
            'Service.php'
        );
    }

    protected function generateRepositoryInterface($name, $path)
    {
        $this->createFileFromStub(
            $name,
            $path,
            'repository-interface.stub',
            'Repositories/Contract',
            'RepositoryInterface.php'
        );
    }

    protected function generateRepository($name, $path)
    {
        $this->createFileFromStub(
            $name,
            $path,
            'repository.stub',
            'Repositories/Eloquent',
            'Repository.php'
        );
    }

    protected function generateRulesFromTable($tableName)
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

    protected function mapColumnToValidationRule($details, $tableName)
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

    protected function generateMessagesFromRules($rules)
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



    protected function generateRequest($name, $path)
    {
        $tableName = Str::snake(Str::plural($name));
        $rulesArray = $this->generateRulesFromTable($tableName);
        $messages = $this->generateMessagesFromRules($rulesArray);

        // Convert rules array into a formatted string
        $rules = collect($rulesArray)
            ->map(fn($rule, $field) => "'{$field}' => '{$rule}',")
            ->implode("\n\t\t");

        // Create CreateRequest
        $this->createFileFromStub(
            $name,
            $path,
            'request.stub',
            'Http/Requests',
            'CreateRequest.php',
            [
                'rules' => $rules,
                'messages' => $messages,
                'className' => $name . "CreateRequest"
            ]
        );

        // Create UpdateRequest
        $this->createFileFromStub(
            $name,
            $path,
            'request.stub',
            'Http/Requests',
            'UpdateRequest.php',
            [
                'rules' => $rules,
                'messages' => $messages,
                'className' => $name . "UpdateRequest"
            ]
        );
    }



    /**
     * Create a file from a stub.
     */
    protected function createFileFromStub($name, $path, $stubFile, $defaultNamespace, $fileSuffix, $additionalReplacements = [])
    {
        // Ensure $additionalReplacements is always an array
        $additionalReplacements = $additionalReplacements ?? [];

        // Generate file details
        $className = basename($name); // Extract class name
        $namespace = str_replace('/', '\\', trim($path, '/')); // Convert path to namespace
        $filePath = base_path(trim($path, '/') . '/' . $className . $fileSuffix); // Generate full file path

        $replacements = array_merge([
            'namespace' => ucfirst($namespace),
            'className' => $className,
            'capsName' => ucfirst($className),
            'pluralName' => Str::plural($className),
            'name' => strtolower($className),
            'nameSpaceOfClass' =>  str_replace('/', '\\', trim($name, '/'))
        ], $additionalReplacements);

        // Ensure the directory exists
        File::ensureDirectoryExists(dirname($filePath));

        // Resolve stub file path
        $stubPath = __DIR__ . '/../stubs/' . $stubFile; // Replace with the actual path to the stub file in your package{$stubFile}';
        // Check if stub exists
        if (!File::exists($stubPath)) {
            $this->error("Stub file not found: $stubPath");
            return;
        }

        // Read stub content
        $stubContent = File::get($stubPath);
        // Replace placeholders dynamically
        foreach ($replacements as $placeholder => $value) {
            $search = "{{ {$placeholder} }}";  // Ensure spaces are handled
            $replace = $value;
            $stubContent = str_replace($search, $replace, $stubContent);
        }
        // Write the processed content to the target file
        File::put($filePath, $stubContent);

        // Output success message
        $this->info("File created successfully at: $filePath");
    }
    protected function generateRepositoryServiceProvider($providerPath)
    {
        // Path to the stub file
        $stubPath =  __DIR__ . '/../stubs/' . 'repository-service-provider.stub';

        // Check if the stub exists
        if (!File::exists($stubPath)) {
            $this->error("Stub file not found: $stubPath");
            return;
        }

        // Read the stub content
        $stubContent = File::get($stubPath);

        // Replace placeholders in the stub with appropriate values
        $namespace = 'App\\Providers'; // Namespace for the provider
        $processedContent = str_replace('{{ namespace }}', $namespace, $stubContent);

        // Ensure the Providers directory exists
        File::ensureDirectoryExists(app_path('Providers'));

        // Write the processed content to the target file
        File::put($providerPath, $processedContent);

        $this->info('RepositoryServiceProvider created successfully.');
    }

    /**
     * Ensure a directory exists.
     */
    protected function ensureDirectoryExists($path)
    {
        $fullPath = base_path($path);

        if (!File::exists($fullPath)) {
            File::makeDirectory($fullPath, 0755, true);
            $this->info("Created directory: $fullPath");
        }
    }
}
