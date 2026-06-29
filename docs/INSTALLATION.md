# Installation

## Requirements

- PHP 8.2+ with extensions: `dom`, `json`, `libxml`, `zip`
- Symfony 6.4 / 7.x / 8.x (see `composer.json`)
- [PHPWord](https://github.com/PHPOffice/PHPWord) (installed via Composer)

## Composer

```bash
composer require nowo-tech/word-template-bundle:^1.0
```

## Register the bundle

Add to `config/bundles.php` if Flex does not register it:

```php
<?php

return [
    // ...
    Nowo\WordTemplateBundle\WordTemplateBundle::class => ['all' => true],
];
```

## Configuration

Copy the sample file from `vendor/nowo-tech/word-template-bundle/src/Resources/config/nowo_word_template.yaml` to `config/packages/nowo_word_template.yaml` (optional if defaults suit you). See [Configuration](CONFIGURATION.md).
