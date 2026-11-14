<?php

/**
 * The single-site settings page inside a real WordPress admin.
 *
 * The Unit suite mocks capability checks, nonces, redirects and register_setting.
 * These tests use a real administrator and a real subscriber, real nonces, the
 * real Settings API sanitize filter and the real menu and script registries.
 *
 * @see admin/settings-page.php
 */

declare(strict_types=1);

namespace UamswpBlockRemover\Tests\Integration;

use RuntimeException;
use UamswpBlockRemover\Tests\Support\IntegrationTestCase;

final class SettingsPageTest extends IntegrationTestCase
{
    private const OPTION = 'uamswp_block_remover_settings';

    private const PAGE_HOOK = 'settings_page_uamswp-block-remover';

    private const HANDLE = 'uamswp-block-remover-admin';

    protected function setUp(): void
    {
        parent::setUp();

        require_once ABSPATH.'wp-admin/includes/plugin.php';
        require_once ABSPATH.'wp-admin/includes/template.php';
    }

    protected function tearDown(): void
    {
        unset($_GET['action'], $_GET['_wpnonce']);
        wp_dequeue_script(self::HANDLE);
        wp_deregister_script(self::HANDLE);

        parent::tearDown();
    }

    private function actAs(string $role): int
    {
        $id = self::factory()->user->create(['role' => $role]);
        wp_set_current_user($id);

        return $id;
    }

    /**
     * Stop where the handler would redirect and exit, and report where it was going.
     */
    private function captureRedirect(): void
    {
        add_filter('wp_redirect', self::halt(...));
    }

    private static function halt(string $location): string
    {
        throw new RuntimeException($location);
    }

    public function test_the_page_hooks_are_the_single_site_ones(): void
    {
        self::assertSame(10, has_action('admin_menu', 'uamswp_block_remover_add_menu'));
        self::assertSame(10, has_action('admin_init', 'uamswp_block_remover_register_settings'));
        self::assertSame(5, has_action('admin_init', 'uamswp_block_remover_handle_reset'));
        self::assertFalse(has_action('network_admin_menu', 'uamswp_block_remover_add_network_menu'));
    }

    public function test_the_menu_item_is_listed_under_settings_for_an_administrator(): void
    {
        global $submenu;

        $this->actAs('administrator');
        uamswp_block_remover_add_menu();

        $entry = array_values(array_filter(
            $submenu['options-general.php'],
            static fn (array $item): bool => $item[2] === 'uamswp-block-remover'
        ));

        self::assertCount(1, $entry);
        self::assertSame('manage_options', $entry[0][1]);
        self::assertSame(
            admin_url('options-general.php?page=uamswp-block-remover'),
            menu_page_url('uamswp-block-remover', false)
        );
    }

    public function test_the_page_is_not_reachable_for_a_user_without_manage_options(): void
    {
        $this->actAs('subscriber');
        uamswp_block_remover_add_menu();

        self::assertFalse(user_can(wp_get_current_user(), 'manage_options'));
        self::assertFalse(current_user_can('manage_options'));

        ob_start();
        uamswp_block_remover_render_page();

        self::assertSame('', ob_get_clean());
    }

    public function test_a_save_through_the_registered_setting_stores_the_sanitized_lists(): void
    {
        uamswp_block_remover_register_settings();

        update_option(self::OPTION, [
            'blocked' => ['core/quote', 'core/quote', 'Not A Block', 'acme/gallery-2', '../etc'],
            'blocked_embeds' => ['youtube', 'youtube', 'not-a-provider'],
            'extra' => 'dropped',
        ]);

        self::assertSame(
            ['blocked' => ['core/quote', 'acme/gallery-2'], 'blocked_embeds' => ['youtube']],
            get_option(self::OPTION)
        );
    }

    public function test_a_save_with_non_array_lists_stores_empty_lists(): void
    {
        uamswp_block_remover_register_settings();

        update_option(self::OPTION, ['blocked' => 'core/quote', 'blocked_embeds' => 'youtube']);

        self::assertSame(['blocked' => [], 'blocked_embeds' => []], get_option(self::OPTION));
    }

    public function test_the_setting_is_registered_with_the_defaults_and_the_group(): void
    {
        uamswp_block_remover_register_settings();

        // The group is part of a registered setting but not of the stubbed return type.
        $registered = $GLOBALS['wp_registered_settings'];

        self::assertArrayHasKey(self::OPTION, $registered);
        self::assertSame('uamswp_block_remover_group', $registered[self::OPTION]['group']);
        self::assertSame('array', $registered[self::OPTION]['type']);
        self::assertSame(
            ['blocked' => uamswp_block_remover_default_blocks(), 'blocked_embeds' => uamswp_block_remover_default_blocked_embeds()],
            $registered[self::OPTION]['default']
        );
    }

    public function test_reset_restores_the_default_blocks_for_an_administrator_and_redirects_back(): void
    {
        $this->actAs('administrator');
        update_option(self::OPTION, ['blocked' => ['core/quote']]);
        $_GET['action'] = 'br_reset';
        $_GET['_wpnonce'] = wp_create_nonce('br_reset_site');
        $this->captureRedirect();

        try {
            uamswp_block_remover_handle_reset();
            self::fail('The handler should redirect and stop.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame(
                admin_url('options-general.php?page=uamswp-block-remover&reset=1'),
                $runtimeException->getMessage()
            );
        }

        self::assertSame(['blocked' => uamswp_block_remover_default_blocks()], get_option(self::OPTION));
    }

    public function test_reset_refuses_a_bad_nonce(): void
    {
        $this->actAs('administrator');
        update_option(self::OPTION, ['blocked' => ['core/quote']]);
        $_GET['action'] = 'br_reset';
        $_GET['_wpnonce'] = 'not-a-nonce';

        uamswp_block_remover_handle_reset();

        self::assertSame(['blocked' => ['core/quote']], get_option(self::OPTION));
    }

    public function test_reset_refuses_a_nonce_made_for_another_action(): void
    {
        $this->actAs('administrator');
        update_option(self::OPTION, ['blocked' => ['core/quote']]);
        $_GET['action'] = 'br_reset';
        $_GET['_wpnonce'] = wp_create_nonce('some_other_action');

        uamswp_block_remover_handle_reset();

        self::assertSame(['blocked' => ['core/quote']], get_option(self::OPTION));
    }

    public function test_reset_refuses_a_user_without_the_capability_even_with_a_valid_nonce(): void
    {
        $this->actAs('subscriber');
        update_option(self::OPTION, ['blocked' => ['core/quote']]);
        $_GET['action'] = 'br_reset';
        $_GET['_wpnonce'] = wp_create_nonce('br_reset_site');

        uamswp_block_remover_handle_reset();

        self::assertSame(['blocked' => ['core/quote']], get_option(self::OPTION));
    }

    public function test_reset_ignores_a_request_for_another_action(): void
    {
        $this->actAs('administrator');
        update_option(self::OPTION, ['blocked' => ['core/quote']]);
        $_GET['action'] = 'something_else';
        $_GET['_wpnonce'] = wp_create_nonce('br_reset_site');

        uamswp_block_remover_handle_reset();

        self::assertSame(['blocked' => ['core/quote']], get_option(self::OPTION));
    }

    public function test_the_rendered_page_posts_to_the_settings_api_and_shows_the_saved_embeds(): void
    {
        $this->actAs('administrator');
        update_option(self::OPTION, ['blocked' => ['core/quote'], 'blocked_embeds' => ['youtube']]);

        ob_start();
        uamswp_block_remover_render_page();
        $html = (string) ob_get_clean();

        self::assertStringContainsString('action="options.php"', $html);
        self::assertStringContainsString("name='option_page' value='uamswp_block_remover_group'", $html);
        self::assertStringContainsString('name="uamswp_block_remover_settings[blocked_embeds][]"', $html);
        self::assertMatchesRegularExpression('/value="youtube"\s+checked=\'checked\'/', $html);
        self::assertDoesNotMatchRegularExpression('/value="vimeo"\s+checked=/', $html);
        self::assertStringContainsString('1 blocked', $html);
        self::assertStringContainsString('action=br_reset', $html);
        self::assertStringContainsString('_wpnonce=', $html);
    }

    public function test_the_reset_link_on_the_page_carries_a_nonce_the_handler_accepts(): void
    {
        $this->actAs('administrator');

        ob_start();
        uamswp_block_remover_render_page();
        $html = (string) ob_get_clean();

        self::assertSame(1, preg_match('/br_reset(?:&#038;|&amp;|&)_wpnonce=([a-z0-9]+)/', $html, $nonce));
        self::assertNotFalse(wp_verify_nonce($nonce[1], 'br_reset_site'));
    }

    public function test_the_admin_script_is_enqueued_with_the_saved_state_on_the_settings_page_only(): void
    {
        update_option(self::OPTION, ['blocked' => ['core/quote', 'core/list']]);

        uamswp_block_remover_admin_assets('index.php');

        self::assertFalse(wp_script_is(self::HANDLE, 'enqueued'));

        uamswp_block_remover_admin_assets(self::PAGE_HOOK);

        self::assertTrue(wp_script_is(self::HANDLE, 'enqueued'));

        $data = (string) wp_scripts()->registered[self::HANDLE]->extra['data'];

        self::assertStringContainsString('"savedBlocked":["core/quote","core/list"]', $data);
        self::assertStringContainsString('"optionName":"uamswp_block_remover_settings"', $data);
        self::assertStringContainsString('"restUrl":"'.rest_url('wp/v2/block-types').'"', $data);
        // wp_localize_script() casts top-level scalars to strings, so a false flag reaches the script as "".
        self::assertStringContainsString('"isNetwork":""', $data);
    }
}
