# sanity-php

PHP library for the [Sanity API](https://sanity.io/)

**Work in progress**. Only the most basic query operations are supported. Mutation support coming soon.

## Requirements

sanity-php requires PHP >= 5.5, with the `json` module installed.

## Composer

You can install the library via [Composer](http://getcomposer.org/). Run the following command:

```bash
composer require sanity/sanity-php
```

To use the library, use Composer's [autoload](https://getcomposer.org/doc/00-intro.md#autoloading):

```php
require_once 'vendor/autoload.php';
```

## Usage

```php
<?php
$client = new \Sanity\Client([
  'projectId' => 'your-project-id',
  'dataset' => 'your-dataset-name',
]);

$results = $client->fetch(
  '*[is $type][0...3]', // Query
  ['type' => 'product'] // Params
);

foreach ($product in $results) {
  echo $product['title'] . '\n';
}
```

## License

MIT-licensed. See LICENSE
