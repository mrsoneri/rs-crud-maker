# Laravel Make Resource

This package provides a command to generate resource files (repositories, services, transformers, controllers, and requests) for Laravel applications.

## Installation

1. Require the package via Composer:
   ```bash
   composer require your-vendor/laravel-make-resource
   ```

2. Publish the configuration file:
   ```bash
   php artisan vendor:publish --tag=config
   ```

3. Use the command:
   ```bash
   php artisan make:resource ResourceName
   ```
