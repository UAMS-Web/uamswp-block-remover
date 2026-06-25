/**
 * UAMSWP Block Remover — admin-settings.js
 *
 * Fetches the full registered block list from /wp/v2/block-types,
 * groups blocks by namespace, and renders an interactive checkbox grid.
 * Pre-checks any blocks already in the saved blocked list.
 */
( function () {
    'use strict';

    const cfg         = window.uamswpBRAdmin || {};
    const restUrl     = cfg.restUrl     || '';
    const restNonce   = cfg.restNonce   || '';
    const savedBlocked = new Set( cfg.savedBlocked || [] );
    const optionName  = cfg.optionName  || 'uamswp_block_remover_settings';
    const i18n        = cfg.i18n        || {};

    // Fetch ALL pages of block types (WP paginates at 100 by default).
    async function fetchAllBlocks() {
        let page    = 1;
        let blocks  = [];
        let hasMore = true;

        while ( hasMore ) {
            const url  = `${ restUrl }?per_page=100&page=${ page }`;
            const resp = await fetch( url, {
                headers: {
                    'X-WP-Nonce': restNonce,
                },
            } );

            if ( ! resp.ok ) {
                throw new Error( `REST API returned ${ resp.status }` );
            }

            const data      = await resp.json();
            const totalPages = parseInt( resp.headers.get( 'X-WP-TotalPages' ) || '1', 10 );

            blocks  = blocks.concat( data );
            hasMore = page < totalPages;
            page++;
        }

        return blocks;
    }

    // Group blocks by their namespace (the part before the slash).
    function groupByNamespace( blocks ) {
        const groups = {};

        blocks.forEach( function ( block ) {
            const ns = block.name.split( '/' )[ 0 ];
            if ( ! groups[ ns ] ) {
                groups[ ns ] = [];
            }
            groups[ ns ].push( block );
        } );

        // Sort each group alphabetically by block name.
        Object.keys( groups ).forEach( function ( ns ) {
            groups[ ns ].sort( ( a, b ) => a.name.localeCompare( b.name ) );
        } );

        // Return as sorted array of { namespace, blocks } — core first, then alpha.
        return Object.keys( groups )
            .sort( ( a, b ) => {
                if ( a === 'core' ) return -1;
                if ( b === 'core' ) return  1;
                return a.localeCompare( b );
            } )
            .map( ns => ( { namespace: ns, blocks: groups[ ns ] } ) );
    }

    // Build the DOM for the block list.
    function renderBlockList( container, groups ) {
        container.innerHTML = '';

        groups.forEach( function ( group ) {
            const ns      = group.namespace;
            const section = document.createElement( 'div' );
            section.className  = 'br-namespace';
            section.dataset.ns = ns;

            const checkedInGroup = group.blocks.filter( b => savedBlocked.has( b.name ) ).length;

            // Namespace header (collapsible).
            const header = document.createElement( 'div' );
            header.className = 'br-namespace-header';
            header.innerHTML = `
                <span class="br-chevron">▾</span>
                <span class="br-ns-name">${ escHtml( ns ) }</span>
                <span class="br-ns-badge">
                    <span class="br-ns-checked">${ checkedInGroup }</span> / ${ group.blocks.length }
                </span>
            `;
            header.addEventListener( 'click', function () {
                section.classList.toggle( 'is-collapsed' );
            } );

            // Block grid.
            const grid = document.createElement( 'div' );
            grid.className = 'br-blocks';

            group.blocks.forEach( function ( block ) {
                const isChecked = savedBlocked.has( block.name );
                const label     = document.createElement( 'label' );
                label.className = 'br-block-label';
                label.dataset.name  = block.name;
                label.dataset.title = ( block.title || '' ).toLowerCase();

                label.innerHTML = `
                    <input
                        type="checkbox"
                        name="${ escHtml( optionName ) }[blocked][]"
                        value="${ escHtml( block.name ) }"
                        ${ isChecked ? 'checked' : '' }
                    >
                    <span class="br-block-info">
                        <span class="br-block-title">${ escHtml( block.title || block.name ) }</span>
                        <span class="br-block-slug">${ escHtml( block.name ) }</span>
                    </span>
                `;

                label.querySelector( 'input' ).addEventListener( 'change', function () {
                    updateBadges();
                } );

                grid.appendChild( label );
            } );

            section.appendChild( header );
            section.appendChild( grid );
            container.appendChild( section );
        } );

        updateBadges();
    }

    // Update the top-level badge and per-namespace badges.
    function updateBadges() {
        const allCheckboxes = document.querySelectorAll( '#br-block-list input[type="checkbox"]' );
        const totalChecked  = [ ...allCheckboxes ].filter( cb => cb.checked ).length;

        const badge = document.getElementById( 'br-badge' );
        if ( badge ) {
            badge.textContent = `${ totalChecked } ${ i18n.blockedCount || 'blocked' }`;
        }

        // Per-namespace counts.
        document.querySelectorAll( '.br-namespace' ).forEach( function ( section ) {
            const checked = section.querySelectorAll( 'input[type="checkbox"]:checked' ).length;
            const counter = section.querySelector( '.br-ns-checked' );
            if ( counter ) counter.textContent = checked;
        } );

        // Embed badge.
        const embedCheckboxes = document.querySelectorAll( '.br-embed-cb' );
        const embedChecked    = [ ...embedCheckboxes ].filter( cb => cb.checked ).length;
        const embedBadge      = document.getElementById( 'br-embed-badge' );
        if ( embedBadge ) {
            embedBadge.textContent = `${ embedChecked } blocked`;
        }
    }

    // Filter labels by search term (searches name + title).
    function applyFilter( term ) {
        const lower = term.toLowerCase().trim();

        document.querySelectorAll( '.br-namespace' ).forEach( function ( section ) {
            let visibleInSection = 0;

            section.querySelectorAll( '.br-block-label' ).forEach( function ( label ) {
                const matches = ! lower ||
                    label.dataset.name.includes( lower ) ||
                    label.dataset.title.includes( lower );

                label.classList.toggle( 'br-hidden', ! matches );
                if ( matches ) visibleInSection++;
            } );

            // Hide the entire namespace section if nothing matches.
            section.classList.toggle( 'br-hidden', visibleInSection === 0 );
        } );
    }

    // Minimal HTML escaper.
    function escHtml( str ) {
        return String( str )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' );
    }

    // Boot.
    document.addEventListener( 'DOMContentLoaded', async function () {
        const container = document.getElementById( 'br-block-list' );
        if ( ! container ) return;

        try {
            const blocks = await fetchAllBlocks();
            const groups = groupByNamespace( blocks );
            renderBlockList( container, groups );
        } catch ( err ) {
            container.innerHTML = `<div class="br-error">${ i18n.error || 'Could not load blocks.' } (${ escHtml( err.message ) })</div>`;
            console.error( '[UAMSWP Block Remover]', err );
            return;
        }

        // Wire embed checkboxes to badge updates.
        document.querySelectorAll( '.br-embed-cb' ).forEach( function ( cb ) {
            cb.addEventListener( 'change', updateBadges );
        } );

        // Search / filter.
        const searchInput = document.getElementById( 'br-search' );
        if ( searchInput ) {
            searchInput.addEventListener( 'input', function () {
                applyFilter( this.value );
            } );
        }

        // Select / deselect all (respects current filter).
        document.getElementById( 'br-select-all' )?.addEventListener( 'click', function () {
            document.querySelectorAll( '.br-block-label:not(.br-hidden) input[type="checkbox"]' )
                .forEach( cb => { cb.checked = true; } );
            updateBadges();
        } );

        document.getElementById( 'br-deselect-all' )?.addEventListener( 'click', function () {
            document.querySelectorAll( '.br-block-label:not(.br-hidden) input[type="checkbox"]' )
                .forEach( cb => { cb.checked = false; } );
            updateBadges();
        } );
    } );

} )();
