# Installation

## Requirements

- PHP 8.2+ with extensions: `dom`, `json`, `libxml`, `zip`
- Symfony 7.x / 8.x (see `composer.json`)
- [PHPWord](https://github.com/PHPOffice/PHPWord) (installed via Composer)

## Composer

```bash
composer require nowo-tech/word-template-bundle:^1.1
```

## Register the bundle

### With Symfony Flex

If you use Symfony Flex, the recipe in this repository (`.symfony/recipe/nowo-tech/word-template-bundle/1.0/`) registers the bundle and copies `config/packages/nowo_word_template.yaml` (timeout defaults; prod tightens timeout). Until the recipe is published in [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib), you can point Flex at this stub or register the bundle and config manually as below.

### Manual registration

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
