<?php

/**
 * Thrown by a mocked wp_die() or wp_redirect() so a test can stop where the
 * plugin would have exited the request.
 */

declare(strict_types=1);

final class UamswpTestHalt extends RuntimeException {}
