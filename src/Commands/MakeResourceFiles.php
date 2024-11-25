<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Pluralizer;
use Illuminate\Support\Str;

class MakeResourceFiles extends Command
{
    protected $signature = 'make:resource {name}';

    protected $description = 'Create related files for a new resource';

    public $common = 'app/';

    public $apiVersion = 'Api/V1';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $name = $this->argument('name');

        $this->info('Creating resource files...');
        $repositoryPathInterface = $this->common . 'Repositories/' . $this->apiVersion . '/Contract/' . $name;
        $repositoryPath = $this->common . 'Repositories/' . $this->apiVersion . '/Eloquent/' . $name;
        $servicePath = $this->common . 'Services/' . $this->apiVersion . '/' . $name;
        $transformerPath = $this->common . 'Transformers/' . $this->apiVersion . '/' . $name;
        $controllerPath = $this->common . 'Http/Controllers/' . $this->apiVersion . '/' . $name;
        $requestPath = $this->common . 'Http/Requests/' . $this->apiVersion . '/' . $name;

        // $this->createRepositoryInterface($name, $repositoryPathInterface);
        // $this->createRepository($name, $repositoryPath);
        // $this->createService($name, $servicePath);
        // $this->createTransformer($name, $transformerPath);
        $this->createController($name, $controllerPath);
        // $this->generateRequestClassesFromSchema(Pluralizer::plural(strtolower(basename($name))), $name, $requestPath);

        $this->info('Resource files created successfully.');
    }

    protected function createRepositoryInterface($name, $path)
    {
        $this->ensureDirectoryExists($path);
        $name = basename($name);
        $fullPath = base_path("$path/{$name}RepositoryInterface.php");
        $namespace = str_replace('/', '\\', ucwords($path));
        $capsName = ucfirst($name);
        $name = strtolower($name);
        $interfaceTemplate = "<?php\n\nnamespace {$namespace};\n\ninterface {$capsName}RepositoryInterface\n{\n    /**\n     * get{$capsName}s\n     *\n     * @param mixed \$filters\n     * @param mixed \$searchStr\n     * @param mixed \$sortField\n     * @param mixed \$sortDirection\n     */\n    public function get{$capsName}s(\$filters, \$searchStr, \$sortField, \$sortDirection);\n\n    public function create(\$request);\n\n    public function show(\$id);\n\n    public function update(\$id, \$request);\n\n    public function delete(\$id);\n}\n";
        // $interfaceTemplate = "<?php\n\nnamespace$namespace;\n\ninterface {$capsName}RepositoryInterface\n{\n    public function getAll();\n    public function create(array \$data);\n    public function update(\$id, array \$data);\n    public function find(\$id);\n    public function delete(\$id);\n}\n";
        File::put($fullPath, $interfaceTemplate);
    }

    protected function createRepository($name, $path)
    {
        $this->ensureDirectoryExists($path);
        $name = basename($name);
        $fullPath = base_path("$path/{$name}Repository.php");
        $namespace = str_replace('/', '\\', ucwords($path));
        $capsName = ucfirst($name);
        $name = strtolower($name);
        $repositoryTemplate = "<?php\n\nnamespace {$namespace};\n\nuse App\Models\\{$capsName}\\{$capsName};\nuse App\Repositories\Api\V1\Contract\\{$capsName}\\{$capsName}RepositoryInterface;\nuse Illuminate\Support\Facades\DB;\n\nclass {$capsName}Repository implements {$capsName}RepositoryInterface\n{\n    public function get{$capsName}s(\$filters, \$searchStr, \$sortField, \$sortDirection)\n    {\n        \$query = {$capsName}::query();\n        \$this->applySearch(\$query, \$searchStr);\n        \$query->orderBy(\$sortField, \$sortDirection);\n        return \$query;\n    }\n\n    protected function applySearch(\$query, \$searchStr)\n    {\n        if (\$searchStr !== '') {\n            \$query->where(function (\$query) use (\$searchStr) {\n                \$query->where('{$name}.name', 'like', '%'.\$searchStr.'%');\n            });\n        }\n    }\n\n    public function create(\$request)\n    {\n        return {$capsName}::create(\$request->validated());\n    }\n\n    public function show(\$id)\n    {\n        return {$capsName}::findRecord(\$id);\n    }\n\n    public function update(\$id, \$request)\n    {\n        \${$name} = {$capsName}::findRecord(\$id);\n        return \${$name}->update(\$request->validated());\n    }\n\n    public function delete(\$id)\n    {\n        \${$name} = Client::findRecord(\$id);\n        return \${$name}->delete();\n    }\n}\n";
        File::put($fullPath, $repositoryTemplate);
    }

    protected function createService($name, $path)
    {
        $this->ensureDirectoryExists($path);
        $name = basename($name);
        $fullPath = base_path("$path/{$name}Service.php");
        $namespace = str_replace('/', '\\', ucwords($path));
        $capsName = ucfirst($name);
        $name = strtolower($name);
        $serviceTemplate = "<?php\n\nnamespace $namespace;\n\nuse App\Models\\{$capsName}\\{$name};\nuse App\Repositories\Api\V1\Contract\\{$capsName}\\{$capsName}RepositoryInterface;\nuse Illuminate\Support\Facades\Storage;\n\nclass {$capsName}Service\n{\n    public function __construct(protected {$capsName}RepositoryInterface \${$name}Repository, public {$capsName} \$model) {}\n\n    public function get{$capsName}(\$pageSize, \$page, \${$name}Id, \$searchStr, \$sortField, \$sortDirection)\n    {\n        \$sortDirection = strtolower(\$sortDirection) === 'asc' ? 'asc' : 'desc';\n\n        return \$this->{$name}Repository\n            ->get{$capsName}(\${$name}Id, \$searchStr, \$sortField, \$sortDirection)\n            ->paginate(\$pageSize, ['*'], 'page', \$page);\n    }\n\n    public function create{$capsName}(\$request)\n    {\n        return \$this->{$name}Repository->create(\$request);\n    }\n\n    public function get{$capsName}ById(\$id)\n    {\n        return \$this->model->isRecordExists(\$id) ? \$this->{$name}Repository->show(\$id) : false;\n    }\n\n    public function update{$capsName}(\$id, \$request)\n    {\n        return \$this->model->isRecordExists(\$id) ? \$this->{$name}Repository->update(\$id, \$request) : false;\n    }\n\n    public function delete{$capsName}(\$id)\n    {\n        return \$this->model->isRecordExists(\$id) ? \$this->{$name}Repository->delete(\$id) : false;\n    }\n}\n";
        File::put($fullPath, $serviceTemplate);
    }

    protected function createTransformer($name, $path)
    {
        $this->ensureDirectoryExists($path);
        $name = basename($name);
        $fullPaths = [
            'create' => base_path("$path/{$name}CreateTransformer.php"),
            'listing' => base_path("$path/{$name}ListingTransformer.php"),
            'show' => base_path("$path/{$name}ShowTransformer.php"),
        ];

        $namespace = str_replace('/', '\\', ucwords($path));
        $capsName = ucfirst($name);

        $transformerTemplates = [
            'create' => "<?php\n\nnamespace {$namespace};\n\nuse App\Src\Fractal\TsrAppTransformer;\n\nclass {$capsName}CreateTransformer extends TsrAppTransformer\n{\n    public function transform(\$model)\n    {\n        return [\n            // Define fields for creation\n            'id' => \$model->id,\n            // Add other fields here\n        ];\n    }\n}\n",
            'listing' => "<?php\n\nnamespace {$namespace};\n\nuse App\Src\Fractal\TsrAppTransformer;\n\nclass {$capsName}ListingTransformer extends TsrAppTransformer\n{\n    public function transform(\$model)\n    {\n        return [\n            'id' => \$model->id,\n            // Add listing-specific fields here\n        ];\n    }\n}\n",
            'show' => "<?php\n\nnamespace {$namespace};\n\nuse App\Src\Fractal\TsrAppTransformer;\n\nclass {$capsName}ShowTransformer extends TsrAppTransformer\n{\n    public function transform(\$model)\n    {\n        return [\n            'id' => \$model->id,\n            // Add fields for detailed view\n        ];\n    }\n}\n",
        ];

        foreach ($fullPaths as $type => $fullPath) {
            File::put($fullPath, $transformerTemplates[$type]);
        }
    }

    protected function createController($name, $path)
    {
        $this->ensureDirectoryExists($path);
        $namespacefiles = str_replace('/', '\\', ucfirst($name));
        $name = basename($name);
        $pluralName = Str::plural($name);
        $fullPath = base_path("$path/{$pluralName}Controller.php");
        $namespace = str_replace('/', '\\', ucwords($path));
        $capsName = ucfirst($name);
        $pluralCapsName = Str::plural($capsName);
        $name = strtolower($name);
        $controllerTemplate = "<?php\n\nnamespace {$namespace};\n\nuse App\Http\Controllers\Controller;\nuse App\Http\Requests\Api\V1\\{$namespacefiles}\\{$capsName}CreateRequest;\nuse App\Http\Requests\Api\V1\\{$namespacefiles}\\{$capsName}UpdateRequest;\nuse App\Services\Api\V1\\{$namespacefiles}\\{$capsName}Service;\nuse App\Services\Api\V1\Common\PaginationService;\nuse App\Transformers\Api\V1\\{$namespacefiles}\\{$capsName}CreateTransformer;\nuse App\Transformers\Api\V1\\{$namespacefiles}\\{$capsName}ListingTransformer;\nuse App\Transformers\Api\V1\\{$namespacefiles}\\{$capsName}ShowTransformer;\nuse Illuminate\Http\Request;\nuse Illuminate\Http\Response;\n\nclass {$pluralCapsName}Controller extends Controller\n{\n    public function __construct(protected {$capsName}Service \${$name}Service, protected PaginationService \$paginationService, protected Request \$request) {}\n\n    public function callAction(\$method, \$parameters)\n    {\n        \$filteredData = \$this->filteredData(\$parameters);\n        if (in_array(\$method, ['store', 'update', 'show', 'destroy'])) {\n            foreach (\$filteredData as \$key => \$value) {\n                \$validationResult = \$this->validateId(\$value);\n                if (\$validationResult !== true) {\n                    return \$validationResult;\n                }\n            }\n            if (! \$this->isValidCombinationOfIds(\$method, \$filteredData, \$this->{$name}Service->model) && in_array(\$method, ['update', 'show', 'destroy'])) {\n                return \$this->handleInvalidParams();\n            }\n        }\n        return parent::callAction(\$method, \$parameters);\n    }\n\n    public function index(Request \$request, string \${$name}Id, {$capsName}ListingTransformer \$transformer)\n    {\n        try {\n            \$pageSize = \$request->query('pageSize', config('tsr.v1.pagination.defaultPageSize'));\n            \$page = \$request->query('page', config('tsr.v1.pagination.defaultPage'));\n            \$searchStr = trim(\$request->query('search'));\n            \$sortField = \$request->query('sortField', 'created_at');\n            \$sortDirection = \$request->query('sortDirection', 'desc');\n            \${$name}Data = \$this->{$name}Service->get{$capsName}(\$pageSize, \$page, \${$name}Id, \$searchStr, \$sortField, \$sortDirection);\n            \$results = \$this->paginationService->handle(\${$name}Data, \$transformer);\n            \$data = \$results['data'] ?? [];\n            \$pagination = \$results['pagination'] ?? [];\n            return \$data ? \$this->jsonResponse(true, Response::HTTP_OK, \$data, [], trans_versioned('v1', 'client', '{$name}_data_load_success'), \$pagination) : \$this->jsonResponse(true, Response::HTTP_OK, [], [], trans_versioned('v1', 'client', '{$name}_data_not_available'));\n        } catch (\\Exception \$e) {\n            return \$this->jsonResponse(false, Response::HTTP_INTERNAL_SERVER_ERROR, null, [], trans_versioned('v1', 'common', 'something_went_wrong'));\n        }\n    }\n\n    public function store({$capsName}CreateRequest \$request, {$capsName}CreateTransformer \$transformer)\n    {\n        try {\n            \$data = \$this->{$name}Service->create{$capsName}(\$request);\n            return \$data ? \$this->transform(true, Response::HTTP_CREATED, \$data, \$transformer, [], [], trans_versioned('v1', 'client', '{$name}_create_success')) : \$this->jsonResponse(true, Response::HTTP_OK, [], [], trans_versioned('v1', 'client', '{$name}_create_failure'));\n        } catch (\\Exception \$e) {\n            return \$this->jsonResponse(false, Response::HTTP_INTERNAL_SERVER_ERROR, null, [], trans_versioned('v1', 'common', 'something_went_wrong'));\n        }\n    }\n\n    public function show(string \${$name}Id, string \$id, {$capsName}ShowTransformer \$transformer)\n    {\n        try {\n            \$validationId = \$this->validateId(\$id);\n            if (\$validationId !== true) {\n                return \$validationId;\n            }\n            \$data = \$this->{$name}Service->get{$capsName}ById(\$id);\n            return \$data ? \$this->transform(true, Response::HTTP_OK, \$data, \$transformer, [], [], trans_versioned('v1', 'client', '{$name}_show_success')) : \$this->jsonResponse(true, Response::HTTP_OK, [], [], trans_versioned('v1', 'client', '{$name}_show_failure'));\n        } catch (\\Exception \$e) {\n            return \$this->jsonResponse(false, Response::HTTP_INTERNAL_SERVER_ERROR, null, [], trans_versioned('v1', 'common', 'something_went_wrong'));\n        }\n    }\n\n    public function update({$capsName}UpdateRequest \$request, string \${$name}Id, string \$id)\n    {\n        try {\n            \$validationId = \$this->validateId(\$id);\n            if (\$validationId !== true) {\n                return \$validationId;\n            }\n            \$success = \$this->{$name}Service->update{$capsName}(\$id, \$request);\n            return \$this->jsonResponse(\$success, Response::HTTP_OK, [], [], \$success ? trans_versioned('v1', 'client', '{$name}_update_success') : trans_versioned('v1', 'client', '{$name}_update_failure'));\n        } catch (\\Exception \$e) {\n            return \$this->jsonResponse(false, Response::HTTP_INTERNAL_SERVER_ERROR, null, [], trans_versioned('v1', 'common', 'something_went_wrong'));\n        }\n    }\n\n    public function destroy(string \${$name}Id, string \$id)\n    {\n        try {\n            \$validationId = \$this->validateId(\$id);\n            if (\$validationId !== true) {\n                return \$validationId;\n            }\n            \$success = \$this->{$name}Service->delete{$capsName}(\$id);\n            return \$this->jsonResponse(\$success, Response::HTTP_OK, [], [], \$success ? trans_versioned('v1', 'client', '{$name}_delete_success') : trans_versioned('v1', 'client', '{$name}_delete_failure'));\n        } catch (\\Exception \$e) {\n            return \$this->jsonResponse(false, Response::HTTP_INTERNAL_SERVER_ERROR, null, [], trans_versioned('v1', 'common', 'something_went_wrong'));\n        }\n    }\n}\n";
        File::put($fullPath, $controllerTemplate);
    }

    protected function generateRequestClassesFromSchema($tableName, $modelName, $path)
    {
        // Check if the table exists; if not, set rules to an empty string
        if (! Schema::hasTable($tableName)) {
            $rules = '';  // No rules if table does not exist
        } else {
            $columns = Schema::getColumnListing($tableName);
            $rules = '';

            // Specify columns to exclude
            $excludedColumns = [
                'id',
                'created_by',
                'updated_by',
                'deleted_by',
                'created_at',
                'updated_at',
                'deleted_at',
            ];

            foreach ($columns as $column) {
                // Skip excluded columns
                if (in_array($column, $excludedColumns)) {
                    continue;
                }

                // Get the column type
                $type = Schema::getColumnType($tableName, $column);

                // Check if the column is nullable
                $isNullable = $this->isColumnNullable($tableName, $column);

                // Generate validation rules based on type and nullability
                $rule = "'$column' => '" . $this->getValidationRules($type, $column, $isNullable) . "'";
                $rules .= "\t\t\t$rule,\n";
            }
        }

        // Create the request classes with the generated (or empty) rules
        $this->createRequestClasses($modelName, $rules, $path);
    }

    // Helper to check if a column is nullable
    protected function isColumnNullable($tableName, $columnName)
    {
        // Get the column information
        $columnInfo = DB::select(
            'SELECT COLUMN_NAME, IS_NULLABLE
                                   FROM INFORMATION_SCHEMA.COLUMNS
                                   WHERE TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$tableName, $columnName]
        );

        // Return true if the column is nullable, false otherwise
        return ! empty($columnInfo) && $columnInfo[0]->IS_NULLABLE === 'YES';
    }

    // Helper to set validation rules by type and nullability
    protected function getValidationRules($type, $column, $isNullable)
    {
        // Adjust the base rule according to whether the field is nullable or not
        $nullableString = $isNullable ? 'nullable|' : '';

        switch ($type) {
            case 'string':
                return $nullableString . 'string|max:255'; // Nullable or required string with max length
            case 'integer':
                return $nullableString . 'integer'; // Nullable or required integer
            case 'boolean':
                return $nullableString . 'boolean'; // Nullable or required boolean
                // Add other cases as needed
            default:
                return $nullableString . 'string'; // Default to a nullable string
        }
    }

    protected function createRequestClasses($name, $rules, $path)
    {
        $this->ensureDirectoryExists($path);
        $name = basename($name);
        $createRequestPath = base_path("$path/{$name}CreateRequest.php");
        $updateRequestPath = base_path("$path/{$name}UpdateRequest.php");
        $namespace = str_replace('/', '\\', ucwords($path));
        $capsName = ucfirst($name);

        // Template for CreateRequest class
        $createRequestTemplate = "<?php\n\nnamespace {$namespace};\n\nuse App\Http\Requests\Api\V1\AbstractRequest;\n\n";
        $createRequestTemplate .= "class {$capsName}CreateRequest extends AbstractRequest\n{\n\tpublic function rules()\n\t{\n\t\treturn [\n{$rules}\t\t];\n\t}\n}\n";

        // Template for UpdateRequest class
        $updateRequestTemplate = "<?php\n\nnamespace {$namespace};\n\nuse App\Http\Requests\Api\V1\AbstractRequest;\n\n";
        $updateRequestTemplate .= "class {$capsName}UpdateRequest extends AbstractRequest\n{\n\tpublic function rules()\n\t{\n\t\treturn [\n{$rules}\t\t];\n\t}\n}\n";

        // Write the request files
        try {
            File::put($createRequestPath, $createRequestTemplate);
            File::put($updateRequestPath, $updateRequestTemplate);
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to create request files in path: $path");
        }
    }

    protected function ensureDirectoryExists($path)
    {
        $fullPath = base_path($path);
        $segments = explode('/', $path);
        $currentPath = base_path();

        foreach ($segments as $segment) {
            $currentPath .= '/' . $segment;
            if (! File::exists($currentPath)) {
                File::makeDirectory($currentPath, 0755, true);
                $this->info("Created directory: $currentPath");
            }
        }
    }
}
