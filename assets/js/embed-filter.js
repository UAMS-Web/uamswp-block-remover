/**
 * UAMSWP Block Remover — embed-filter.js
 *
 * Filters core/embed variations using the blocks.registerBlockType hook.
 * This runs as the block is being registered, before any dependency graphs
 * are built, so it's safe and avoids the recursion issues of unregisterBlockType.
 *
 * Dependencies: wp-hooks, wp-blocks
 */
( function () {
    'use strict';

    const cfg           = window.uamswpBREmbeds || {};
    const blockedEmbeds = new Set( cfg.blockedEmbeds || [] );

    if ( ! blockedEmbeds.size ) {
        return;
    }

    wp.hooks.addFilter(
        'blocks.registerBlockType',
        'uamswp-block-remover/filter-embed-variations',
        function ( settings, name ) {
            if ( name !== 'core/embed' ) {
                return settings;
            }

            // settings.variations is the array of embed provider objects.
            // Each variation has a { name: 'youtube', ... } shape.
            if ( ! Array.isArray( settings.variations ) ) {
                return settings;
            }

            settings.variations = settings.variations.filter(
                function ( variation ) {
                    return ! blockedEmbeds.has( variation.name );
                }
            );

            return settings;
        }
    );

} )();
