# RS CRUD Maker

`paresh/rsp-crud-generator` is a Laravel package designed to automatically generate CRUD operations for API resources. It adheres to the Repository and Service design patterns to promote clean, maintainable, and scalable code.

With a single command, this package sets up controllers, requests, resources, services, and repositories, enabling efficient management of your application's architecture.You only need to register my provider, as it automatically binds your repository.

---

## Installation

### Step 1: Install the Package
Run the following command to install the package via Composer:

```bash
composer require paresh/rsp-crud-generator:dev-main
```

### Step 2: Add the Service Provider
Include the service provider in the `providers` array of your `config/app.php` file:

```php
'providers' => [
    // Other service providers...
    RSPCrud\Providers\RepositoryServiceProvider::class,
],
```

### Step 3: Clear Laravel Cache
Ensure all changes are loaded by clearing Laravel's cache:

```bash
php artisan optimize:clear
```

---

## Usage

To generate the CRUD structure for a specific resource, use the following Artisan command:

```bash
php artisan make:rsp-crud {Resource}
```

Replace `{Resource}` with the name of the resource (e.g., `Product`).

### Example
To create a CRUD structure for the `Product` resource, run:

```bash
php artisan make:rsp-crud Product
```

### Generated Folder Structure
Executing the command generates the following folder and file structure:

```
/app
    /Http
        /Controllers
            /ProductController.php
        /Requests
            /ProductRequest.php
        /Resources
            /ProductListingResource.php
            /ProductCreateResource.php
            /ProductShowResource.php
    /Services
        /ProductService.php
    /Repositories
        /Contract
            /ProductRepositoryInterface.php
        /Eloquent
            /ProductRepository.php
```

---

## Magic Behind the Command

### Database Schema Inspection
When you run the command `php artisan make:rsp-crud User`, the package inspects the `users` table in your database, fetching all columns except `id` and timestamp fields like `created_at` and `updated_at`.

### Request Data Generation
The command automatically generates a `{Resource}Request.php` file containing validation rules for `create` and `update` operations. These rules are dynamically created based on the fields in the database table.

#### Example: UserRequest.php
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            // Additional fields dynamically added based on the schema
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'The name field is required.',
            'email.required' => 'The email field is required.',
            'password.required' => 'The password field is required.',
            // Dynamic messages for other fields
        ];
    }
}
```

### API Resource Response Generation
The package generates resource response files that map fields from the database table to API responses:

- **ListingResource**: For resource listings with required fields.
- **CreateResource**: For responses after resource creation.
- **ShowResource**: For detailed resource views.

#### Example: UserShowResource.php
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserShowResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            // Include additional fields from the `users` table
        ];
    }
}
```

---

## File Breakdown

### Controllers
Defines API endpoints for managing CRUD operations.

### Requests
Handles validation for incoming data during create and update operations.

### Resources
Formats data for API responses:
- `ListingResource`: Formats data for resource listings.
- `CreateResource`: Formats data after creation.
- `ShowResource`: Formats data for detailed resource views.

### Services
Encapsulates business logic related to CRUD operations.

### Repositories
Manages data access, ensuring separation of concerns:
- **Contract**: Defines the repository interface for abstraction.
- **Eloquent**: Implements the contract for Eloquent ORM.

---

## Customization
The generated files serve as a starting point. You can extend and modify them to suit your application's unique requirements, such as adding business logic, validation rules, or database relationships.

---

## Features

- **Automated API CRUD Generation**: Creates all necessary files for resource management.
- **Repository and Service Pattern**: Promotes clean and organized application structure.
- **Flexible and Extendable**: Easily adaptable to meet specific project needs.
- **API Focused**: Optimized for RESTful API resource management.

---

## Requirements

- **PHP**: Version 8.0 or higher.
- **Laravel**: Version 9.x or 10.x.

---

## License

This package is open-source and licensed under the [MIT License](https://opensource.org/licenses/MIT).

---

## Author

**Paresh Soneri**  
Email: [soneriparesh435@gmail.com](mailto:soneriparesh435@gmail.com)

---

## Contributing

Contributions are welcome! To contribute:
- Fork the repository.
- Submit a pull request with your changes.

For bug reports or feature requests, use the GitHub Issues section.