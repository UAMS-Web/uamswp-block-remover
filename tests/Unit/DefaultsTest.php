<?php

/**
 * The three lists the plugin ships: the default blocked blocks, the default blocked
 * embeds and the known embed providers.
 *
 * Each list is pinned in full, in order, so adding, dropping or renaming an entry
 * is a visible change to this file.
 *
 * @see uamswp-block-remover.php
 */

declare(strict_types=1);

use function Brain\Monkey\Functions\when;

test('the default blocked blocks are the pinned list', function (): void {
    expect(uamswp_block_remover_default_blocks())->toBe([
        'core/archives',
        'core/audio',
        'core/avatar',
        'core/calendar',
        'core/classic',
        'core/categories',
        'core/column',
        'core/columns',
        'core/comment-author-name',
        'core/comment-content',
        'core/comment-date',
        'core/comment-edit-link',
        'core/comment-reply-link',
        'core/comment-template',
        'core/comments',
        'core/comments-pagination',
        'core/comments-pagination-next',
        'core/comments-pagination-previous',
        'core/comments-title',
        'core/cover',
        'core/gallery',
        'core/latest-comments',
        'core/latest-posts',
        'core/loginout',
        'core/missing',
        'core/more',
        'core/nextpage',
        'core/post-author',
        'core/post-author-biography',
        'core/post-author-name',
        'core/post-comments',
        'core/post-comments-form',
        'core/post-navigation-link',
        'core/post-template',
        'core/post-terms',
        'core/query',
        'core/query-no-results',
        'core/query-pagination',
        'core/query-pagination-next',
        'core/query-pagination-numbers',
        'core/query-pagination-previous',
        'core/query-title',
        'core/query-total',
        'core/rss',
        'core/search',
        'core/social-links',
        'core/spacer',
        'core/table-of-contents',
        'core/tag-cloud',
        'core/template-part',
        'core/term-description',
        'core/text-columns',
        'core/verse',
        'core/video',
    ]);
});

test('the default blocked blocks hold no duplicate', function (): void {
    $blocks = uamswp_block_remover_default_blocks();

    expect($blocks)->toBe(array_values(array_unique($blocks)));
});

test('the post template entry is the real block name', function (): void {
    $blocks = uamswp_block_remover_default_blocks();

    expect($blocks)->toContain('core/post-template')
        ->and($blocks)->not->toContain('core/core/post-template');
});

test('every default blocked name survives the block name sanitizer', function (): void {
    when('sanitize_text_field')->returnArg();
    $blocks = uamswp_block_remover_default_blocks();

    expect(uamswp_block_remover_sanitize_block_list($blocks))->toBe($blocks);
});

test('no embed variation is blocked by default', function (): void {
    expect(uamswp_block_remover_default_blocked_embeds())->toBe([]);
});

test('the known embed variations are the pinned map of name to label', function (): void {
    expect(uamswp_block_remover_known_embed_variations())->toBe([
        'amazon-kindle' => 'Amazon Kindle',
        'animoto' => 'Animoto',
        'cloudup' => 'Cloudup',
        'crowdsignal' => 'Crowdsignal',
        'dailymotion' => 'Dailymotion',
        'flickr' => 'Flickr',
        'imdb' => 'IMDb',
        'imgur' => 'Imgur',
        'instagram' => 'Instagram',
        'issuu' => 'Issuu',
        'kickstarter' => 'Kickstarter',
        'mixcloud' => 'Mixcloud',
        'pinterest' => 'Pinterest',
        'pocket-casts' => 'Pocket Casts',
        'reddit' => 'Reddit',
        'reverbnation' => 'ReverbNation',
        'screencast' => 'Screencast',
        'scribd' => 'Scribd',
        'slideshare' => 'SlideShare',
        'smugmug' => 'SmugMug',
        'soundcloud' => 'SoundCloud',
        'speaker-deck' => 'Speaker Deck',
        'spotify' => 'Spotify',
        'ted' => 'TED',
        'tiktok' => 'TikTok',
        'tumblr' => 'Tumblr',
        'twitter' => 'X (Twitter)',
        'videopress' => 'VideoPress',
        'vimeo' => 'Vimeo',
        'wolfram-cloud' => 'Wolfram Cloud',
        'wordpress' => 'WordPress',
        'wordpress-tv' => 'WordPress.tv',
        'youtube' => 'YouTube',
    ]);
});
