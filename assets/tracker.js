/* Bitlence Dev Code Tracker — session tracking */
( function () {
    'use strict';

    const cfg       = window.bdctConfig || {};
    const idleMs    = cfg.idleMs        || 5 * 60 * 1000;
    const minSec    = cfg.minSessionSec || 60;
    const QUEUE_KEY = 'bdct_pending_sessions';

    let activeSession     = null;
    let lastTouchMs       = 0;
    let iframeKeepAliveId = null;

    /* ── localStorage helpers (safe in private-browsing / restricted contexts) ── */
    function lsGet( key ) {
        try { return localStorage.getItem( key ); } catch ( e ) { return null; }
    }
    function lsSet( key, val ) {
        try { localStorage.setItem( key, val ); } catch ( e ) {}
    }
    function lsRemove( key ) {
        try { localStorage.removeItem( key ); } catch ( e ) {}
    }

    /* ── page identity ── */
    function getPageKey() {
        const params  = new URLSearchParams( location.search );
        const postId  = params.get( 'post' );
        const page    = params.get( 'page' );
        const pagenow = window.pagenow || null;
        const typenow = window.typenow || null;

        if ( postId ) {
            return {
                key:        'post_' + postId,
                post_id:    postId,
                post_type:  typenow || pagenow || null,
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

    /* ── session lifecycle ── */
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
            flushQueue( /* useBeacon= */ true );
        }

        activeSession = null;
    }

    /* ── localStorage pending queue ── */
    function enqueue( data ) {
        const q = JSON.parse( lsGet( QUEUE_KEY ) || '[]' );
        q.push( data );
        lsSet( QUEUE_KEY, JSON.stringify( q ) );
    }

    function flushQueue( useBeacon ) {
        if ( ! cfg.ajaxUrl ) return;
        const q = JSON.parse( lsGet( QUEUE_KEY ) || '[]' );
        if ( ! q.length ) return;

        q.forEach( function ( data ) {
            const payload = buildPayload( data );
            let sent = false;
            if ( useBeacon && navigator.sendBeacon ) {
                sent = navigator.sendBeacon( cfg.ajaxUrl, payload );
            }
            if ( ! sent ) {
                // sendBeacon unavailable / returned false — use fetch (best-effort).
                fetch( cfg.ajaxUrl, { method: 'POST', body: payload } ).catch( function () {
                    enqueue( data );
                } );
            }
        } );

        // Clear queue; any failed items were re-enqueued inside the fetch catch above.
        lsRemove( QUEUE_KEY );
    }

    function buildPayload( data ) {
        const fd = new FormData();
        fd.append( 'action', 'bdct_save_session' );
        fd.append( 'nonce',  cfg.nonce );
        Object.keys( data ).forEach( function ( k ) { fd.append( k, data[ k ] ); } );
        return fd;
    }

    /* ── touch ── */
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

    /* ── tick ── */
    function tick() {
        if ( ! activeSession ) return;
        if ( Date.now() - activeSession.last_activity > idleMs ) {
            endSession();
            return;
        }
        updateToolbar();
    }

    /* ── toolbar ── */
    function updateToolbar() {
        const el = document.getElementById( 'bdct-toolbar-time' );
        if ( ! el || ! activeSession ) return;
        const sec = Math.round(
            ( Date.now() - new Date( activeSession.started_at.replace( ' ', 'T' ) + 'Z' ).getTime() ) / 1000
        );
        const m = Math.floor( sec / 60 );
        const s = sec % 60;
        el.textContent = m + ':' + String( s ).padStart( 2, '0' );
    }

    /* ── SPA navigation (WooCommerce Analytics, React/Vue-based admin plugins) ── */
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

    /* ── iframe keep-alive (Elementor, Divi, Beaver Builder, WPBakery, etc.) ── */
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

    /* ── auto-checkpoint every 5 minutes ── */
    setInterval( function () {
        if ( ! activeSession ) return;
        const elapsed = Math.round(
            ( Date.now() - new Date( activeSession.started_at.replace( ' ', 'T' ) + 'Z' ).getTime() ) / 1000
        );
        if ( elapsed >= minSec ) {
            endSession();
            startSession();
        }
    }, 5 * 60 * 1000 );

    /* ── wire up events ── */
    [ 'mousemove', 'keydown', 'click' ].forEach( function ( e ) {
        document.addEventListener( e, touch, { passive: true } );
    } );

    setInterval( tick, 1000 );

    window.addEventListener( 'beforeunload', function () {
        endSession();
    } );

    window.bdctTracker = { getActiveSession: function () { return activeSession; } };

    flushQueue( /* useBeacon= */ false );
    startSession();
} () );
