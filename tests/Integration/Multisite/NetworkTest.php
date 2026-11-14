<?php

/**
 * The plugin when it is network active on a real WordPress network.
 *
 * Run by `composer test:integration:multisite`. The Unit suite mocks every branch
 * that depends on the network; these tests use real network options, real sites,
 * a real super admin and the real admin handlers.
 *
 * @see uamswp-block-remover.php
 * @see admin/settings-page.php
 */

declare(strict_types=1);

namespace UamswpBlockRemover\Tests\Integration\Multisite;

use RuntimeException;
use UamswpBlockRemover\Tests\Support\IntegrationTestCase;
use WPDieException;

final class NetworkTest extends IntegrationTestCase
{
    private const OPTION = 'uamswp_block_remover_settings';

    private const SCREEN = 'settings_page_uamswp-block-remover-network';

    protected function setUp(): void
    {
        parent::setUp();

        require_once ABSPATH.'wp-admin/includes/plugin.php';
        require_once ABSPATH.'wp-admin/includes/template.php';
    }

    protected function tearDown(): void
    {
        unset($_GET['action'], $_GET['_wpnonce'], $_GET['updated'], $_GET['reset'], $_POST[self::OPTION], $_POST['uamswp_block_remover_nonce'], $_REQUEST['uamswp_block_remover_nonce'], $_REQUEST['_wp_http_referer']);

        parent::tearDown();
    }

    private function activateNetworkWide(): void
    {
        update_site_option('active_sitewide_plugins', [plugin_basename(UAMSWP_BLOCK_REMOVER_DIR.'uamswp-block-remover.php') => time()]);
    }

    private function actAsSuperAdmin(): void
    {
        $id = self::factory()->user->create(['role' => 'administrator']);
        grant_super_admin($id);
        wp_set_current_user($id);
    }

    private function actAsSiteAdmin(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
    }

    private function captureRedirect(): void
    {
        add_filter('wp_redirect', self::halt(...));
    }

    private static function halt(string $location): string
    {
        throw new RuntimeException($location);
    }

    public function test_the_test_run_is_a_network(): void
    {
        self::assertTrue(is_multisite());
    }

    public function test_the_plugin_is_not_network_active_until_it_is_in_the_sitewide_list(): void
    {
        self::assertFalse(uamswp_block_remover_is_network_active());

        update_site_option('active_sitewide_plugins', ['other/other.php' => time()]);

        self::assertFalse(uamswp_block_remover_is_network_active());

        $this->activateNetworkWide();

        self::assertTrue(uamswp_block_remover_is_network_active());
    }

    public function test_a_network_active_plugin_reads_and_writes_the_network_option(): void
    {
        $this->activateNetworkWide();
        delete_site_option(self::OPTION);

        self::assertSame(
            ['blocked' => uamswp_block_remover_default_blocks(), 'blocked_embeds' => uamswp_block_remover_default_blocked_embeds()],
            uamswp_block_remover_get_settings()
        );

        self::assertTrue(uamswp_block_remover_update_settings(['blocked' => ['core/quote']]));

        self::assertSame(['blocked' => ['core/quote']], get_site_option(self::OPTION));
        self::assertFalse(get_option(self::OPTION));
        self::assertSame('core/quote', uamswp_block_remover_get_settings()['blocked'][0]);
    }

    public function test_the_network_setting_applies_on_every_site(): void
    {
        $this->activateNetworkWide();
        $other = self::factory()->blog->create();
        update_site_option(self::OPTION, ['blocked' => ['core/quote']]);

        switch_to_blog($other);
        $seen = uamswp_block_remover_get_settings()['blocked'];
        restore_current_blog();

        self::assertSame(['core/quote'], $seen);
    }

    public function test_a_site_that_is_not_network_active_keeps_its_own_option(): void
    {
        $other = self::factory()->blog->create();
        update_site_option(self::OPTION, ['blocked' => ['core/quote']]);

        switch_to_blog($other);
        uamswp_block_remover_update_settings(['blocked' => ['core/list']]);
        $siteOption = get_option(self::OPTION);
        restore_current_blog();

        self::assertSame(['blocked' => ['core/list']], $siteOption);
        self::assertSame(['blocked' => ['core/quote']], get_site_option(self::OPTION));
    }

    public function test_deleting_when_network_active_removes_the_network_option_only(): void
    {
        $this->activateNetworkWide();
        update_site_option(self::OPTION, ['blocked' => []]);
        update_option(self::OPTION, ['blocked' => ['core/list']]);

        uamswp_block_remover_delete_settings();

        self::assertFalse(get_site_option(self::OPTION));
        self::assertSame(['blocked' => ['core/list']], get_option(self::OPTION));
    }

    public function test_network_wide_activation_seeds_the_network_option_once(): void
    {
        delete_site_option(self::OPTION);

        uamswp_block_remover_activate(true);

        self::assertSame(
            ['blocked' => uamswp_block_remover_default_blocks(), 'blocked_embeds' => uamswp_block_remover_default_blocked_embeds()],
            get_site_option(self::OPTION)
        );

        update_site_option(self::OPTION, ['blocked' => ['core/rss']]);
        uamswp_block_remover_activate(true);

        self::assertSame(['blocked' => ['core/rss']], get_site_option(self::OPTION));
        self::assertFalse(get_option(self::OPTION));
    }

    public function test_activating_one_site_seeds_that_sites_option_only(): void
    {
        delete_site_option(self::OPTION);

        uamswp_block_remover_activate(false);

        self::assertSame(
            ['blocked' => uamswp_block_remover_default_blocks(), 'blocked_embeds' => uamswp_block_remover_default_blocked_embeds()],
            get_option(self::OPTION)
        );
        self::assertFalse(get_site_option(self::OPTION));
    }

    public function test_the_page_hooks_follow_how_the_plugin_is_activated(): void
    {
        $this->activateNetworkWide();
        remove_all_actions('network_admin_menu');
        remove_all_actions('network_admin_notices');
        remove_all_actions('admin_menu');

        uamswp_block_remover_register_hooks();

        self::assertSame(10, has_action('network_admin_menu', 'uamswp_block_remover_add_network_menu'));
        self::assertSame(10, has_action('network_admin_notices', 'uamswp_block_remover_network_notices'));
        self::assertFalse(has_action('admin_menu', 'uamswp_block_remover_add_menu'));
    }

    public function test_the_network_menu_requires_manage_network_options(): void
    {
        global $submenu;

        $this->actAsSuperAdmin();
        uamswp_block_remover_add_network_menu();

        $entry = array_values(array_filter(
            $submenu['settings.php'],
            static fn (array $item): bool => $item[2] === 'uamswp-block-remover'
        ));

        self::assertCount(1, $entry);
        self::assertSame('manage_network_options', $entry[0][1]);
    }

    public function test_a_network_save_stores_the_sanitized_lists_and_redirects(): void
    {
        $this->actAsSuperAdmin();
        $_POST['uamswp_block_remover_nonce'] = $_REQUEST['uamswp_block_remover_nonce'] = wp_create_nonce('uamswp_block_remover_network_save');
        $_POST[self::OPTION] = [
            'blocked' => ['core/quote', 'core/quote', 'Not A Block'],
            'blocked_embeds' => ['youtube', 'not-a-provider'],
        ];
        $this->captureRedirect();

        try {
            uamswp_block_remover_handle_network_save();
            self::fail('The handler should redirect and stop.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame(
                network_admin_url('settings.php?page=uamswp-block-remover&updated=1'),
                $runtimeException->getMessage()
            );
        }

        self::assertSame(
            ['blocked' => ['core/quote'], 'blocked_embeds' => ['youtube']],
            get_site_option(self::OPTION)
        );
    }

    public function test_a_network_save_without_the_nonce_field_does_nothing(): void
    {
        $this->actAsSuperAdmin();
        update_site_option(self::OPTION, ['blocked' => ['core/list']]);
        $_POST[self::OPTION] = ['blocked' => ['core/quote']];

        uamswp_block_remover_handle_network_save();

        self::assertSame(['blocked' => ['core/list']], get_site_option(self::OPTION));
    }

    public function test_a_network_save_with_a_bad_nonce_is_refused(): void
    {
        $this->actAsSuperAdmin();
        update_site_option(self::OPTION, ['blocked' => ['core/list']]);
        $_POST['uamswp_block_remover_nonce'] = $_REQUEST['uamswp_block_remover_nonce'] = 'bad';
        $_POST[self::OPTION] = ['blocked' => ['core/quote']];

        $this->expectException(WPDieException::class);

        try {
            uamswp_block_remover_handle_network_save();
        } finally {
            self::assertSame(['blocked' => ['core/list']], get_site_option(self::OPTION));
        }
    }

    public function test_a_network_save_by_a_site_administrator_is_refused(): void
    {
        $this->actAsSiteAdmin();
        update_site_option(self::OPTION, ['blocked' => ['core/list']]);
        $_POST['uamswp_block_remover_nonce'] = $_REQUEST['uamswp_block_remover_nonce'] = wp_create_nonce('uamswp_block_remover_network_save');
        $_POST[self::OPTION] = ['blocked' => ['core/quote']];

        $this->expectException(WPDieException::class);

        try {
            uamswp_block_remover_handle_network_save();
        } finally {
            self::assertSame(['blocked' => ['core/list']], get_site_option(self::OPTION));
        }
    }

    public function test_a_network_reset_restores_the_default_blocks_and_redirects(): void
    {
        $this->actAsSuperAdmin();
        update_site_option(self::OPTION, ['blocked' => ['core/quote']]);
        $_GET['action'] = 'br_reset';
        $_GET['_wpnonce'] = wp_create_nonce('br_reset_network');
        $this->captureRedirect();

        try {
            uamswp_block_remover_handle_network_reset();
            self::fail('The handler should redirect and stop.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame(
                network_admin_url('settings.php?page=uamswp-block-remover&reset=1'),
                $runtimeException->getMessage()
            );
        }

        self::assertSame(['blocked' => uamswp_block_remover_default_blocks()], get_site_option(self::OPTION));
    }

    public function test_a_network_reset_ignores_another_action(): void
    {
        $this->actAsSuperAdmin();
        update_site_option(self::OPTION, ['blocked' => ['core/quote']]);
        $_GET['action'] = 'something_else';

        uamswp_block_remover_handle_network_reset();

        self::assertSame(['blocked' => ['core/quote']], get_site_option(self::OPTION));
    }

    public function test_a_network_reset_with_a_bad_nonce_is_refused(): void
    {
        $this->actAsSuperAdmin();
        update_site_option(self::OPTION, ['blocked' => ['core/quote']]);
        $_GET['action'] = 'br_reset';
        $_GET['_wpnonce'] = 'bad';

        $this->expectException(WPDieException::class);

        try {
            uamswp_block_remover_handle_network_reset();
        } finally {
            self::assertSame(['blocked' => ['core/quote']], get_site_option(self::OPTION));
        }
    }

    public function test_a_network_reset_by_a_site_administrator_is_refused(): void
    {
        $this->actAsSiteAdmin();
        update_site_option(self::OPTION, ['blocked' => ['core/quote']]);
        $_GET['action'] = 'br_reset';
        $_GET['_wpnonce'] = wp_create_nonce('br_reset_network');

        $this->expectException(WPDieException::class);

        try {
            uamswp_block_remover_handle_network_reset();
        } finally {
            self::assertSame(['blocked' => ['core/quote']], get_site_option(self::OPTION));
        }
    }

    public function test_the_notices_show_only_on_the_network_screen(): void
    {
        $_GET['updated'] = '1';
        $_GET['reset'] = '1';

        set_current_screen('dashboard');
        ob_start();
        uamswp_block_remover_network_notices();

        self::assertSame('', ob_get_clean());

        set_current_screen(self::SCREEN);
        ob_start();
        uamswp_block_remover_network_notices();
        $html = (string) ob_get_clean();

        self::assertStringContainsString('Network block list saved.', $html);
        self::assertStringContainsString('Network block list reset to defaults.', $html);
    }

    public function test_only_the_notice_for_the_parameter_present_is_shown(): void
    {
        set_current_screen(self::SCREEN);
        $_GET['reset'] = '1';

        ob_start();
        uamswp_block_remover_network_notices();
        $html = (string) ob_get_clean();

        self::assertStringNotContainsString('Network block list saved.', $html);
        self::assertStringContainsString('Network block list reset to defaults.', $html);
    }

    public function test_the_network_page_posts_to_the_network_admin_with_a_nonce_and_a_reset_link(): void
    {
        $this->actAsSuperAdmin();
        $this->activateNetworkWide();
        update_site_option(self::OPTION, ['blocked' => [], 'blocked_embeds' => ['youtube']]);

        ob_start();
        uamswp_block_remover_render_network_page();
        $html = (string) ob_get_clean();

        self::assertStringContainsString('action="'.esc_url(network_admin_url('settings.php?page=uamswp-block-remover')).'"', $html);
        self::assertStringContainsString('name="uamswp_block_remover_nonce"', $html);
        self::assertStringContainsString('Save Network Settings', $html);
        self::assertMatchesRegularExpression('/value="youtube"\s+checked=\'checked\'/', $html);
        self::assertSame(1, preg_match('/br_reset(?:&#038;|&amp;|&)_wpnonce=([a-z0-9]+)/', $html, $nonce));
        self::assertNotFalse(wp_verify_nonce($nonce[1], 'br_reset_network'));
    }

    public function test_the_network_page_renders_nothing_for_a_site_administrator(): void
    {
        $this->actAsSiteAdmin();

        ob_start();
        uamswp_block_remover_render_network_page();

        self::assertSame('', ob_get_clean());
    }

    public function test_the_admin_script_on_the_network_screen_uses_the_main_sites_rest_url(): void
    {
        $this->activateNetworkWide();
        $other = self::factory()->blog->create();
        update_site_option(self::OPTION, ['blocked' => ['core/quote']]);

        switch_to_blog($other);
        uamswp_block_remover_admin_assets('settings_page_uamswp-block-remover-network');
        restore_current_blog();

        $data = (string) wp_scripts()->registered['uamswp-block-remover-admin']->extra['data'];

        switch_to_blog(get_main_site_id());
        $mainRest = rest_url('wp/v2/block-types');
        restore_current_blog();

        self::assertStringContainsString('"restUrl":"'.$mainRest.'"', $data);
        self::assertStringContainsString('"isNetwork":"1"', $data);
        self::assertStringContainsString('"savedBlocked":["core/quote"]', $data);
    }

    public function test_uninstalling_deletes_the_network_option_and_the_option_on_every_site(): void
    {
        $first = self::factory()->blog->create();
        $second = self::factory()->blog->create();
        update_site_option(self::OPTION, ['blocked' => []]);
        update_option(self::OPTION, ['blocked' => ['core/list']]);

        foreach ([$first, $second] as $site) {
            switch_to_blog($site);
            update_option(self::OPTION, ['blocked' => ['core/quote']]);
            restore_current_blog();
        }

        uamswp_block_remover_uninstall();

        self::assertFalse(get_site_option(self::OPTION));
        self::assertFalse(get_option(self::OPTION));

        foreach ([$first, $second] as $site) {
            switch_to_blog($site);
            self::assertFalse(get_option(self::OPTION), "site {$site} still holds the option");
            restore_current_blog();
        }
    }

    public function test_the_block_filter_uses_the_network_blocked_list(): void
    {
        $this->activateNetworkWide();
        update_site_option(self::OPTION, ['blocked' => ['core/quote']]);

        $allowed = apply_filters('allowed_block_types_all', ['core/paragraph', 'core/quote'], new \WP_Block_Editor_Context);

        self::assertSame(['core/paragraph'], $allowed);
    }
}
