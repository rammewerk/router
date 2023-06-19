Rammewerk Router
======================

A simple and fast environment variable handler for projects.

This package is a different approach to handle environment variables in your project:

* Parses and automatically caches .env file
* Will NOT add variables to $_ENV or $_SERVER - which might lead to exposing values if you are not careful with your debugging.
* No other dependencies - small size.
* Will automatically convert values to types like boolean, integer, null and even array (read more below)
* Support closure to validate environment variables
* Includes caching for even faster loading.
* Support for multiple files

**Important: There are some limitations to the .env file format. See below.**

Getting Started
---------------

```php
use Rammewerk\component\environment\src\Environment;

$env = new Environment();

// Load from environment variable file
$env->load( ROOT_DIR . '.env');

// Get value from environment
$debug_mode = $env->get( 'DEBUG_MODE' );
```