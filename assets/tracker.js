/* Bitlence Dev Code Tracker - session tracking */
(function () {
    'use strict';

    const cfg           = window.bdctConfig || {};
    cfg.todaySec        = parseInt( cfg.todaySec, 10 )  || 0;
    const idleMs        = parseInt( cfg.idleMs, 10 )    || 5 * 60 * 1000;
    const minSec        = parseInt( cfg.minSessionSec, 10 ) || 60;
    const QUEUE_KEY     = 'bdct_pending_sessions';
    const CHECKPOINT_MS = 30 * 60 * 1000;

    let activeSession     = null;
    let lastTouchMs       = 0;
    let iframeKeepAliveId = null;

    /* localStorage helpers (safe in private-browsing / restricted contexts) */
    function lsGet( key ) {
        try { return localStorage.getItem( key ); } catch ( e ) { return null; }
    }
    function lsSet( key, val ) {
        try { localStorage.setItem( key, val ); } catch ( e ) {}
    }
    function lsRemove( key ) {
        try { localStorage.removeItem( key ); } catch ( e ) {}
    }

    /* page identity */
    function getPageKey() {
        const params  = new URLSearchParams( location.search );
        // Admin: ?post=ID  |  Frontend builders: cfg.postId passed via wp_localize_script.
        const postId  = params.get( 'post' ) || cfg.postId || null;
        const page    = params.get( 'page' );
        const pagenow = window.pagenow || null;
        const typenow = window.typenow || null;

        if ( postId ) {
            return {
                key:        'post_' + postId,
                post_id:    String( postId ),
                post_type:  typenow || pagenow || cfg.postType || null,
                admin_page: pagenow || null,
            };
        }

        return {
            key:        location.pathname + location.search,
            post_id:    null,
            post_type:  null,
            admin_page: page || pagenow || null,
        };
    }

    /* session lifecycle */
    function startSession() {
        const page    = getPageKey();
        activeSession = Object.assign( {}, page, {
            started_at:    new Date().toISOString().slice( 0, 19 ).replace( 'T', ' ' ),
            last_activity: Date.now(),
        } );
    }

    function endSession() {
        if ( ! activeSession ) return;

        const endStr = new Date().toISOString().slice( 0, 19 ).replace( 'T', ' ' );
        const durSec = Math.round(
            ( Date.now() - new Date( activeSession.started_at.replace( ' ', 'T' ) + 'Z' ).getTime() ) / 1000
        );

        if ( durSec >= minSec ) {
            enqueue( {
                post_id:      activeSession.post_id   || '',
                post_type:    activeSession.post_type  || '',
                admin_page:   activeSession.admin_page || '',
                started_at:   activeSession.started_at,
                ended_at:     endStr,
                duration_sec: String( durSec ),
            } );
            flushQueue( true );
            cfg.todaySec = ( cfg.todaySec || 0 ) + durSec;
        }

        activeSession = null;
    }

    /* localStorage pending queue */
    function enqueue( data ) {
        const q = JSON.parse( lsGet( QUEUE_KEY ) || '[]' );
        q.push( data );
        lsSet( QUEUE_KEY, JSON.stringify( q ) );
    }

    function flushQueue( useBeacon ) {
        if ( ! cfg.ajaxUrl ) return;
        const q = JSON.parse( lsGet( QUEUE_KEY ) || '[]' );
        if ( ! q.length ) return;

        // Clear upfront to prevent duplicate sends on concurrent tabs.
        // Failed fetch items are re-enqueued in the catch handler.
        lsRemove( QUEUE_KEY );

        q.forEach( function ( data ) {
            const payload = buildPayload( data );
            if ( useBeacon && navigator.sendBeacon ) {
                const sent = navigator.sendBeacon( cfg.ajaxUrl, payload );
                if ( sent ) return; // browser accepted the beacon
                // beacon rejected - fall back to fetch
            }
            fetch( cfg.ajaxUrl, { method: 'POST', body: payload } ).catch( function () {
                enqueue( data ); // re-enqueue only on confirmed network failure
            } );
        } );
    }

    function buildPayload( data ) {
        const fd = new FormData();
        fd.append( 'action', 'bdct_save_session' );
        fd.append( 'nonce',  cfg.nonce );
        Object.keys( data ).forEach( function ( k ) { fd.append( k, data[ k ] ); } );
        return fd;
    }

    /* touch */
    function touch() {
        if ( ! activeSession ) {
            startSession();
            return;
        }

        if ( activeSession.key !== getPageKey().key ) {
            endSession();
            startSession();
            return;
        }

        const now = Date.now();
        if ( now - lastTouchMs < 1000 ) return;
        lastTouchMs = now;

        activeSession.last_activity = now;
        updateToolbar();
    }

    /* tick */
    function tick() {
        if ( ! activeSession ) return;
        if ( Date.now() - activeSession.last_activity > idleMs ) {
            endSession();
            return;
        }
        updateToolbar();
    }

    /* Hook activity events inside editor iframes (Gutenberg editor-canvas, etc.)
       blob: iframes are same-origin so contentDocument is accessible, but window.blur
       never fires for them — we must attach listeners directly to their document. */
    function hookEditorIframes() {
        document.querySelectorAll( 'iframe' ).forEach( function ( f ) {
            try {
                var doc = f.contentDocument;
                if ( ! doc || ! doc.body ) return;
                if ( f._bdctHookedDoc === doc ) return;
                [ 'mousemove', 'keydown', 'click' ].forEach( function ( e ) {
                    doc.addEventListener( e, touch, { passive: true } );
                } );
                f._bdctHookedDoc = doc;
            } catch ( e ) { /* cross-origin — skip */ }
        } );
    }

    setInterval( hookEditorIframes, 2000 );

    /* toolbar */
    function fmtHM( sec ) {
        var h = Math.floor( sec / 3600 );
        var m = Math.floor( ( sec % 3600 ) / 60 );
        var s = sec % 60;
        if ( h > 0 ) return h + 'h ' + m + 'm';
        if ( m > 0 ) return m + 'm ' + s + 's';
        return s + 's';
    }

    function fmtMS( sec ) {
        var m = Math.floor( sec / 60 );
        var s = sec % 60;
        return m + ':' + String( s ).padStart( 2, '0' );
    }

    function updateToolbar() {
        if ( ! activeSession ) return;

        const liveSec  = Math.round(
            ( Date.now() - new Date( activeSession.started_at.replace( ' ', 'T' ) + 'Z' ).getTime() ) / 1000
        );
        const totalSec = ( cfg.todaySec || 0 ) + liveSec;
        const display  = fmtHM( totalSec ) + ' | ' + fmtMS( liveSec );

        // Admin bar span (may not exist in Elementor — admin bar is hidden there).
        let el = document.getElementById( 'bdct-toolbar-time' );
        if ( ! el ) {
            const link = document.querySelector( '#wp-admin-bar-bdct-status .ab-item' );
            if ( link ) {
                el = document.createElement( 'span' );
                el.id = 'bdct-toolbar-time';
                link.appendChild( el );
            }
        }
        if ( el ) {
            el.textContent = ': ' + display;
        }

        // Floating badge — shown whenever the WP admin bar is hidden.
        // Covers Elementor, Bricks, Breakdance, and any other builder that hides #wpadminbar.
        const adminBar = document.getElementById( 'wpadminbar' );
        const barHidden = ! adminBar || window.getComputedStyle( adminBar ).display === 'none';
        if ( barHidden ) {
            let badge = document.getElementById( 'bdct-el-badge' );
            if ( ! badge ) {
                badge = document.createElement( 'div' );
                badge.id = 'bdct-el-badge';
                badge.style.cssText = 'position:fixed;bottom:20px;right:20px;'
                    + 'background:#2271b1;color:#fff;padding:5px 12px;'
                    + 'border-radius:4px;font:12px/1.4 monospace;z-index:99999;'
                    + 'box-shadow:0 2px 6px rgba(0,0,0,.25);pointer-events:none;';
                document.body.appendChild( badge );
            }
            badge.textContent = display;
        }
    }

    /* SPA navigation (WooCommerce Analytics, React/Vue-based admin plugins) */
    function onUrlChange() {
        if ( activeSession && activeSession.key !== getPageKey().key ) {
            endSession();
            startSession();
        } else if ( ! activeSession ) {
            startSession();
        }
    }

    ( function patchHistory() {
        const _push    = history.pushState.bind( history );
        const _replace = history.replaceState.bind( history );
        history.pushState = function () {
            _push.apply( history, arguments );
            onUrlChange();
        };
        history.replaceState = function () {
            _replace.apply( history, arguments );
            onUrlChange();
        };
    } () );

    window.addEventListener( 'popstate',   onUrlChange );
    window.addEventListener( 'hashchange', onUrlChange );

    /* iframe keep-alive (Elementor, Divi, Beaver Builder, WPBakery, etc.) */
    function startIframeKeepAlive() {
        if ( iframeKeepAliveId ) return;
        iframeKeepAliveId = setInterval( function () {
            if ( ! activeSession || document.visibilityState === 'hidden' ) {
                stopIframeKeepAlive();
                return;
            }
            activeSession.last_activity = Date.now();
            updateToolbar();
        }, 10000 );
    }

    function stopIframeKeepAlive() {
        if ( iframeKeepAliveId ) {
            clearInterval( iframeKeepAliveId );
            iframeKeepAliveId = null;
        }
    }

    window.addEventListener( 'blur', function () {
        setTimeout( function () {
            if (
                document.activeElement &&
                document.activeElement.tagName === 'IFRAME' &&
                document.visibilityState !== 'hidden'
            ) {
                startIframeKeepAlive();
            }
        }, 0 );
    } );

    window.addEventListener( 'focus', function () {
        stopIframeKeepAlive();
        touch();
    } );

    document.addEventListener( 'visibilitychange', function () {
        if ( document.visibilityState === 'hidden' ) {
            stopIframeKeepAlive();
        }
    } );

    /* auto-checkpoint every 5 minutes */
    setInterval( function () {
        if ( ! activeSession ) return;
        const elapsed = Math.round(
            ( Date.now() - new Date( activeSession.started_at.replace( ' ', 'T' ) + 'Z' ).getTime() ) / 1000
        );
        if ( elapsed >= minSec ) {
            endSession();
            startSession();
        }
    }, CHECKPOINT_MS );

    /* wire up events */
    [ 'mousemove', 'keydown', 'click' ].forEach( function ( e ) {
        document.addEventListener( e, touch, { passive: true } );
    } );

    setInterval( tick, 1000 );

    window.addEventListener( 'beforeunload', function () {
        endSession();
    } );

    window.bdctTracker = { getActiveSession: function () { return activeSession; } };

    flushQueue( false );
    updateToolbar();
}());
