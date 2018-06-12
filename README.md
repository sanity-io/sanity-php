# sanity-php

PHP library for the [Sanity API](https://sanity.io/)

[![Packagist](https://img.shields.io/packagist/v/sanity/sanity-php.svg?style=flat-square)](https://packagist.org/packages/sanity/sanity-php)[![Travis](https://img.shields.io/travis/sanity-io/sanity-php.svg?style=flat-square)](https://travis-ci.org/sanity-io/sanity-php)

## Requirements

sanity-php requires PHP >= 5.6, with the `json` module installed.

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

### Instantiating a new client

```php
use Sanity\Client as SanityClient;

$client = new SanityClient([
  'projectId' => 'your-project-id',
  'dataset' => 'your-dataset-name',
  'useCdn' => true, // Whether or not to use the API CDN. Default is false.
]);
```

### Using an authorization token

```php
$client = new SanityClient([
  'projectId' => 'your-project-id',
  'dataset' => 'your-dataset-name',
  'useCdn' => false,
  // Note that you cannot combine a token with the `useCdn` option set to true,
  // as authenticated requests cannot be cached
  'token' => 'sanity-auth-token',
]);
```

### Fetch a single document by ID

```php
$document = $client->getDocument('someDocumentId');
```

### Performing queries

```php
$results = $client->fetch(
  '*[_type == $type][0...3]', // Query
  ['type' => 'product'] // Params (optional)
);

foreach ($product in $results) {
  echo $product['title'] . '\n';
}
```

See the [query documentation](https://www.sanity.io/docs/front-ends/query-cheat-sheet) for more information on how to write queries.

### Creating documents

```php
$doc = [
  '_type' => 'bike',
  'name'  => 'Bengler Tandem Extraordinaire',
  'seats' => 2,
];

$newDocument = $client->create($doc);
echo 'Bike was created, document ID is ' . $newDocument['_id'];
```

This creates a new document with the given properties. It must contain a `_type` attribute, and _may_ contain a `_id` attribute. If an ID is specified and a document with that ID already exist, the mutation will fail. If an ID is not specified, it will be auto-generated and is included in the returned document.

### Creating a document (if it does not exist)

As noted above, if you include an `_id` property when calling `create()` and a document with this ID already exists, it will fail. If you instead want to ignore the create operation if it exists, you can use `createIfNotExists()`. It takes the same arguments as `create()`, the only difference being that it *requires* an `_id` attribute.

```php
$doc = [
  '_id'   => 'my-document-id',
  '_type' => 'bike',
  'name'  => 'Amazing bike',
  'seats' => 3,
];

$newDocument = $client->createIfNotExists($doc);
```

### Replacing a document

If you don't care whether or not a document exists already and just want to replace it, you can use the `createOrReplace()` method.

```php
$doc = [
  '_id'   => 'my-document-id',
  '_type' => 'bike',
  'name'  => 'Amazing bike',
  'seats' => 3,
];

$newDocument = $client->createOrReplace($doc);
```

### Patch/update a document

```php
use Sanity\Exception\BaseException;

try {
  $updatedBike = $client
    ->patch('bike-123') // Document ID to patch
    ->set(['inStock' => false]) // Shallow merge
    ->inc(['numSold' => 1]) // Increment field by count
    ->commit(); // Perform the patch and return the modified document
} catch (BaseException $error) {
  echo 'Oh no, the update failed: ';
  var_dump($error);
}
```

Todo: Document all patch operations

### Delete a document

```php
use Sanity\Exception\BaseException;

try {
  $client->delete('bike-123');
} catch (BaseException $error) {
  echo 'Delete failed: ';
  var_dump($error);
}
```

### Multiple mutations in a transaction

```php
$namePatch = $client->patch('bike-310')->set(['name' => 'A Bike To Go']);

try {
  $client->transaction()
    ->create(['name' => 'Bengler Tandem Extraordinaire', 'seats' => 2])
    ->delete('bike-123')
    ->patch($namePatch)
    ->commit();

  echo 'A whole lot of stuff just happened!';
} catch (BaseException $error) {
  echo 'Transaction failed:';
  var_dump($error);
}
```

### Clientless patches & transactions

```php
use Sanity\Patch;
use Sanity\Transaction;

// Patches:
$patch = new Patch('<documentId>');
$patch->inc(['count' => 1])->unset(['visits']);
$client->mutate($patch);

// Transactions:
$transaction = new Transaction();
$transaction
  ->create(['_id' => '123', 'name' => 'FooBike'])
  ->delete('someDocId');

$client->mutate($transaction);
```

An important note on this approach is that you cannot call `commit()` on transactions or patches instantiated this way, instead you have to pass them to `client.mutate()`.

### Get client configuration

```php
$config = $client->config();
echo $config['dataset'];
```

### Set client configuration

```php
$client->config(['dataset' => 'newDataset']);
```

The new configuration will be merged with the existing, so you only need to pass the options you want to modify.

### Rendering block content

When you use the block editor in Sanity, it produces a structured array structure that you can use to render the content on any platform you might want. In PHP, a common output format is HTML. To make the transformation from the array structure to HTML simpler, we include a helper class for this within the library.

If your content only contains the basic, built-in block types, you can get rendered HTML like this:

```php
use Sanity\BlockContent;

$document = $client->getDocument('some-doc');
$article = $document['article']; // The field that contains your block content

$html = BlockContent::toHtml($article, [
    'projectId'    => 'abc123',
    'dataset'      => 'bikeshop',
    'imageOptions' => ['w' => 320, 'h' => 240]
]);
```

If you have some custom types, or would like to customize the rendering, you may pass an associative array of serializers:

```php
$html = BlockContent::toHtml($article, [
  'serializers' => [
    'listItem' => function ($item, $parent, $htmlBuilder) {
      return '<li class="my-list-item">' . implode('\n', $item['children']) . '</li>';
    },
    'geopoint' => function ($item) {
      $attrs = $item['attributes']
      $url = 'https://www.google.com/maps/embed/v1/place?key=someApiKey&center='
      $url .= $attrs['lat'] . ',' . $attrs['lng'];
      return '<iframe class="geomap" src="' . $url . '" allowfullscreen></iframe>'
    },
    'pet' => function ($item, $parent, $htmlBuilder) {
      return '<p class="pet">' . $htmlBuilder->escape($item['attributes']['name']) . '</p>';
    }
  ]
]);
```

## Contributing

`sanity-php` follows the [PSR-2 Coding Style Guide](http://www.php-fig.org/psr/psr-2/). Contributions are welcome, but must conform to this standard.

## License

MIT-licensed. See LICENSE
