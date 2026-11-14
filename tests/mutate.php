<?php

/**
 * Runs the Unit suite's mutation testing: `composer mutate`.
 *
 * Pest's mutation stream wrapper and Patchwork cannot read a directory together, so
 * `pest --mutate` over the test directory launches mutant children that die before
 * any test and are scored as kills. Naming the test files avoids the directory scan.
 * This launcher names them, and starts Pest through an absolute path, because each
 * mutant child reuses the path Pest was started with as its executable.
 *
 * Arguments after the script name go to Pest, so the CI job's `--min`, `--path` and
 * `--ignore-min-score-on-zero-mutations` reach it. A later `--path` replaces the
 * default one below.
 *
 * @see https://github.com/UAMS-Web/uamswp-block-remover/issues/29
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$tests = glob($root.'/tests/Unit/*Test.php');

if ($tests === false || $tests === []) {
    fwrite(STDERR, 'No test files found under tests/Unit.'.PHP_EOL);

    exit(2);
}

sort($tests);

$command = array_merge(
    [PHP_BINARY, $root.'/vendor/bin/pest'],
    $tests,
    ['--mutate', '--path=admin,uamswp-block-remover.php'],
    array_slice($_SERVER['argv'], 1)
);

$process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $root);

if (! is_resource($process)) {
    fwrite(STDERR, 'Could not start Pest.'.PHP_EOL);

    exit(2);
}

exit(proc_close($process));
