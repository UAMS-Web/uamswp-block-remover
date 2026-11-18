# UAMSWP Block Remover

<!-- cspell:ignore multisite phpunit UAMS UAMSWP -->

Removes chosen blocks and embed providers from the WordPress block editor, starting from a default list and changed on a settings page. It works on a single site, or network-wide on a multisite network with one list for every site.

The [wiki](https://github.com/UAMS-Web/uamswp-block-remover/wiki) covers using and configuring it: a walkthrough, guides, the settings reference and troubleshooting.

## Requirements

- WordPress 6.8 or later
- PHP 8.2 or later

## Installation

1. Copy this repository's download into `wp-content/plugins/uamswp-block-remover/`.
2. Activate it under *Plugins* on a site, or under *Network Admin*, *Plugins* with **Network Activate** for one list across the network.
3. Open *Settings*, *Block Remover* (on a network: *Network Admin*, *Settings*, *Block Remover*).

## Tests

```bash
composer install
vendor/bin/pest
```

`composer test:unit` runs the same Pest suite. The supported PHP floor is 8.2; the suite is developed and run on PHP 8.4.27, pinned with `config.platform.php` in `composer.json`.

The Integration suite (PHPUnit 9.6 with `wp-phpunit`, because WordPress' test suite supports PHPUnit 9 only) has its own Composer setup:

```bash
composer setup:integration
composer test:integration
```

`composer test:integration:multisite` runs the tests that need a WordPress network (network activation, the network settings page and uninstall across sites). Both read the test database from `tests/Integration/wp-tests-config.php`.

## Credits

Developed and maintained by UAMS Web Services.

Developers:

- Josh Daugherty
- Todd McKee

## Code quality

```bash
composer stan            # PHPStan, level 3
composer test:refactor   # Rector, dry run (composer refactor applies it)
composer test:pint       # Pint, check only (composer pint applies it)
```

To make `git blame` skip the Pint reformat commit, run once per clone:

```bash
git config blame.ignoreRevsFile .git-blame-ignore-revs
```
