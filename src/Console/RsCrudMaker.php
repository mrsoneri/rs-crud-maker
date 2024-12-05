<?php

namespace RsCrud\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RsCrud\Traits\ValidationTrait;
use Illuminate\Support\Facades\Artisan;

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

        // // Generate files
        $this->generateController($formattedName, $controllerPath);
        $this->generateService($formattedName, $servicePath);
        $this->generateRepositoryInterface($formattedName, $repositoryPathInterface);
        $this->generateRepository($formattedName, $repository);
        $this->generateRequest($formattedName, $requestPath);
        $this->generateResource($formattedName, $resourcePath);
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

    public function generateResource($name,$path)
    {
        // Define the dynamic resource name and path
        $resourceName = $name . 'ListingResource';
        $resourceCreateName = $name . 'CreateResource';
        $resourceUpdateName = $name . 'UpdateResource';
        $resourcePath = $path;

        // Automatically create the dynamic ContactListingResource
        Artisan::call('make:resource', [
            'name' => $resourceName,
            '--path' => $resourcePath,
        ]);
        
        // Automatically create the dynamic ContactCreateResource
        Artisan::call('make:resource', [
            'name' => $resourceCreateName,
            '--path' => $resourcePath,
        ]);
        
        // Automatically create the dynamic ContactUpdateResource
        Artisan::call('make:resource', [
            'name' => $resourceUpdateName,
            '--path' => $resourcePath,
        ]);
    }

    // protected function generateRepositoryServiceProvider($providerPath)
    // {
    //     // Path to the stub file
    //     $stubPath =  __DIR__ . '/../stubs/' . 'repository-service-provider.stub';

    //     // Check if the stub exists
    //     if (!File::exists($stubPath)) {
    //         $this->error("Stub file not found: $stubPath");
    //         return;
    //     }

    //     // Read the stub content
    //     $stubContent = File::get($stubPath);

    //     // Replace placeholders in the stub with appropriate values
    //     $namespace = 'App\\Providers'; // Namespace for the provider
    //     $processedContent = str_replace('{{ namespace }}', $namespace, $stubContent);

    //     // Ensure the Providers directory exists
    //     File::ensureDirectoryExists(app_path('Providers'));

    //     // Write the processed content to the target file
    //     File::put($providerPath, $processedContent);

    //     $this->info('RepositoryServiceProvider created successfully.');
    // }

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
