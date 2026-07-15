# WordTemplateBundle

[![CI](https://github.com/nowo-tech/WordTemplateBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/WordTemplateBundle/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/word-template-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/word-template-bundle)
[![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/word-template-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/word-template-bundle)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://php.net)
[![Symfony](https://img.shields.io/badge/Symfony-6.4%20%7C%207.4%2B%20%7C%208.0%20%7C%208.1%2B-000000?logo=symfony)](https://symfony.com)
[![GitHub stars](https://img.shields.io/github/stars/nowo-tech/WordTemplateBundle.svg?style=social&label=Star)](https://github.com/nowo-tech/WordTemplateBundle)
[![Coverage](https://img.shields.io/badge/Coverage-~100%25-green)](#tests-and-coverage)

> **Found this useful?** Install from Packagist (`composer require nowo-tech/word-template-bundle`) and consider starring [WordTemplateBundle on GitHub](https://github.com/nowo-tech/WordTemplateBundle).

Symfony bundle that fills **Microsoft Word `.docx` templates** (PHPWord [`TemplateProcessor`](https://phpoffice.github.io/PHPWord/docs/classes/PhpOffice-PhpWord-TemplateProcessor.html)) using a **PHP context array**:

- **Scalars** (strings, numbers, booleans, null) → `setValue` on placeholders such as `${client_name}` or `${client.city}` when you nest arrays (flattened to dot keys).
- **`TableRows`** → `cloneRow` + per-cell `#1`, `#2`, … replacements for repeating table lines.
- **`HtmlContent`** → rich fragments (paragraphs, bold/italic, tables inside HTML, etc.) via PHPWord `Html::addHtml` embedded as a complex block (lists `<ul>`/`<ol>` may require extra numbering setup in PHPWord; prefer plain paragraphs or combine with [HtmlToWordBundle](https://github.com/nowo-tech/HtmlToWordBundle) for full HTML pipelines).
- **`ImageSource`** → `setImageValue` with optional width/height.
- **`listVariables()`** → read a template and list unique placeholder names (respects configured delimiters).

This bundle does **not** execute Word VBA macros; “macros” here means **template placeholders** in the `.docx` compatible with PHPWord.

## Documentation

- [GitLab CI requirements](docs/GITLAB_CI.md)
- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [GitHub Spec Kit](docs/SPEC-KIT.md)

### Additional documentation

- [FrankenPHP / Docker demos](docs/DEMO-FRANKENPHP.md) — `demo/symfony7` and `demo/symfony8` (see [`demo/README.md`](demo/README.md))

## Requirements

- PHP **8.2+**
- Symfony **6.4 / 7.x / 8.x** (as in `composer.json`)
- Extensions: `dom`, `json`, `libxml`, `zip`

## Quick start

```bash
composer require nowo-tech/word-template-bundle:^1.0
```

Register `Nowo\WordTemplateBundle\WordTemplateBundle` if needed, then wire your templates and inject `WordTemplateProcessorInterface`:

```php
use Nowo\WordTemplateBundle\Processor\WordTemplateProcessorInterface;

$doc = $this->wordTemplateProcessor->process(
    '/path/to/template.docx',
    [
        'title' => 'Contract #42',
        'client' => ['name' => 'ACME', 'city' => 'Madrid'],
    ],
);
$bytes = $doc->readContents();
$doc->dispose(); // if the processor used a temp file
```

## Tests and coverage

| Scope | Detail |
|-------|--------|
| **PHPUnit** | `composer test` — unit + integration (minimal kernel in `tests/Fixtures/AppKernel.php`). |
| **Lines** | `composer coverage-check` enforces **≥ 99%** (PCOV). Latest global measurement: **~100%**. |

## Development

```bash
make up
make qa
make release-check
```

### Demos (FrankenPHP)

Demos run with **FrankenPHP** (Caddy + PHP in Docker). **`docker-compose`** defaults to **`APP_ENV=dev`**, so the entrypoint uses **Caddyfile.dev** (no PHP worker), and Twig/PHP changes are visible on refresh. **Worker mode** applies to a production-style setup — see [docs/DEMO-FRANKENPHP.md](docs/DEMO-FRANKENPHP.md).

From the repository root:

```bash
cd demo/symfony7 && cp .env.example .env && make up   # Symfony 7, port 8020
cd demo/symfony8 && cp .env.example .env && make up   # Symfony 8, port 8021
```

Or from `demo/`: `make up` / `make up8` (see [`demo/README.md`](demo/README.md)).

## Versioning

[Semantic Versioning](https://semver.org/).

## License

[MIT License](LICENSE).
