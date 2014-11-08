# Taste

Taste is a PHP class defined in a single php file.

## Getting Started

### Install

Use composer to install this package.

```php
require "vendor/autoload.php";

$taste = new Taste\Taste();

$taste->map('get', '/', 'indexController@index');

# output the right template.
$taste->display('index.tpl');

$taste->mapRoute();
```

## Documentation

Not available.

## License

Released under the MIT License.

http://towry.mit-license.org/
