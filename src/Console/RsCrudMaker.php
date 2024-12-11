<?php

namespace RsCrud\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RsCrud\Traits\ValidationTrait;
use Illuminate\Support\Facades\Schema;

class RsCrudMaker extends Command
{
    use ValidationTrait;
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
        // $providerPath = app_path('Providers/RepositoryServiceProvider.php');

        // // Define paths
        $controllerPath = $this->basePath . 'Http/Controllers/' . $formattedName;
        $servicePath = $this->basePath . 'Services/' . $formattedName;
        $repositoryPathInterface = $this->basePath . 'Repositories/Contract/' . $formattedName;
        $repository = $this->basePath . 'Repositories/Eloquent/' . $formattedName;
        $requestPath = $this->basePath . 'Http/Requests/' . $formattedName;
        $resourcePath = $this->basePath . 'Http/Resources/' . $formattedName;

        // // // Generate files
        $this->generateController($formattedName, $controllerPath);
        $this->generateService($formattedName, $servicePath);
        $this->generateRepositoryInterface($formattedName, $repositoryPathInterface);
        $this->generateRepository($formattedName, $repository);
        $this->generateRequest($formattedName, $requestPath);
        $this->generateResource($formattedName, $resourcePath);
        $this->pushRoute($formattedName);
        // if (!File::exists($providerPath)) {
        //     $this->generateRepositoryServiceProvider($providerPath);
        // }
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

    public function generateResource($name, $path, $fields = [])
    {
        // If fields are not provided, fetch from the database
        if (empty($fields)) {
            $tableName = Str::snake(Str::plural($name)); // Infer table name from resource name
            $fields = Schema::hasTable($tableName) ? Schema::getColumnListing($tableName) : [];
        }

        // Define resource names and corresponding actions
        $resourceTypes = [
            'Listing' => 'listing',   // For Listing
            'Create' => 'create',     // For Create
            'Show' => 'show',     // For Show
        ];

        // Loop through each resource type (Listing, Create, Show)
        foreach ($resourceTypes as $resourceType => $action) {
            // Generate the resource name dynamically based on the action type
            $resource = $name . $resourceType . 'Resource';

            // Define the target path
            $targetPath = base_path("{$path}/{$resource}.php");

            // Ensure the target directory exists
            if (!File::exists($path)) {
                File::makeDirectory($path, 0755, true);
            }

            // Generate resource content dynamically with the appropriate action type
            $content = $this->generateResourceContent($name, $resource, $fields, $action);

            // Write the content to the target file
            File::put($targetPath, $content);
        }
    }

    private function generateResourceContent($name,$resource, $fields, $action = 'listing')
    {
        // Map the action to a success message
        $messageMap = [
            'listing' => "{$name} retrieved successfully.",
            'create' => "{$name} created successfully.",
            'show' => "{$name} fetched successfully.",
        ];

        // Set the message dynamically based on the action
        $message = $messageMap[$action] ?? "{$resource} operation successful.";

        // Prepare the fields array for the `toArray` method
        $fieldsArray = collect($fields)->map(fn($field) => "'$field' => \$this->$field,")->implode("\n        ");

        // Load the stub file content
        $stubPath = __DIR__ . '/../stubs/' . 'resource.stub';
        $stub = file_get_contents($stubPath);

        // Replace the placeholders with dynamic values
        $content = str_replace(
            ['{{resource}}', '{{fieldsArray}}', '{{message}}','{{namespace}}'],
            [$resource, $fieldsArray, $message, $name],
            $stub
        );

        return $content;
    }
    public function pushRoute($name)
    {
        // Ensure the name is in StudlyCase for the controller and snake_case for the route
        $controllerName = Str::studly($name) . 'Controller';
        $controllerNamespace = "App\\Http\\Controllers\\{$name}\\{$controllerName}";
        $routeName = str::plural(Str::snake($name, '-'));

        // Path to the api.php file
        $apiFilePath = base_path('routes/api.php');

        // Read the content of the api.php file
        $apiFileContent = file_get_contents($apiFilePath);

        // Check if the use statement already exists
        $useStatement = "use {$controllerNamespace};";

        if (!Str::contains($apiFileContent, $useStatement)) {
            // Find the last use statement
            if (preg_match('/^(use\s.+;)/m', $apiFileContent, $matches, PREG_OFFSET_CAPTURE)) {
                $lastUseStatementPosition = $matches[array_key_last($matches)][1];
                $insertPosition = $lastUseStatementPosition + strlen($matches[array_key_last($matches)][0]);

                // Insert the use statement after the last use statement
                $apiFileContent = substr_replace($apiFileContent, PHP_EOL . $useStatement, $insertPosition, 0);
            } else {
                // If no use statements exist, add it at the top
                $apiFileContent = $useStatement . PHP_EOL . $apiFileContent;
            }
        }

        // Define the resource route
        $resourceRoute = "Route::resource('{$routeName}', {$controllerName}::class);";

        // Check if the route already exists
        if (Str::contains($apiFileContent, $resourceRoute)) {
            $this->warn("The route for {$controllerName} already exists in api.php.");
            return;
        }

        // Append the new route to the end of the file
        $apiFileContent .= PHP_EOL . $resourceRoute . PHP_EOL;

        // Save the updated content back to the api.php file
        file_put_contents($apiFilePath, $apiFileContent);

        // Inform the user
        $this->info("Resource route for {$controllerName} has been added to api.php with its namespace.");
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