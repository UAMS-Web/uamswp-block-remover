# UAMSWP Block Remover

## Tests

```bash
composer install
vendor/bin/pest
```

`composer test:unit` runs the same Pest suite. The supported PHP floor stays 7.4; the suite is developed and run on PHP 8.4.27, pinned with `config.platform.php` in `composer.json`.

The Integration suite (PHPUnit 9.6 with `wp-phpunit`, because WordPress' test suite supports PHPUnit 9 only) has its own Composer setup:

```bash
composer setup:integration
composer test:integration
```

## Credits

Developed and maintained by UAMS Web Services.

Developers:

- Josh Daugherty
- Todd McKee
