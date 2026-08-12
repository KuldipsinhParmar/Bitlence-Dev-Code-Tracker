/* Bitlence Dev Code Tracker - dashboard data + chart rendering */
(function () {
    'use strict';

    const cfg = window.bdctConfig || {};
    let chartInstance = null;
    let serverData    = null;
    let loading       = false;

    /* formatters */
    function fmtTime( sec ) {
        sec = Math.max( 0, Math.round( +sec ) );
        const h = Math.floor( sec / 3600 );
        const m = Math.floor( ( sec % 3600 ) / 60 );
        const s = sec % 60;
        if ( h > 0 ) return h + 'h ' + m + 'm';
        if ( m > 0 ) return m + 'm ' + s + 's';
        return s + 's';
    }

    // "2026-05-27 14:30:00" -> "27-05-2026 2:30 PM"  |  "2026-05-27" -> "27-05-2026"
    function fmtDate( raw ) {
        const str = String( raw );
        const p   = str.slice( 0, 10 ).split( '-' );
        if ( str.length <= 10 ) return p[ 2 ] + '-' + p[ 1 ] + '-' + p[ 0 ];
        const hh   = parseInt( str.slice( 11, 13 ), 10 );
        const mm   = str.slice( 14, 16 );
        const ampm = hh >= 12 ? 'PM' : 'AM';
        const h12  = hh % 12 || 12;
        return p[ 2 ] + '-' + p[ 1 ] + '-' + p[ 0 ] + ' ' + h12 + ':' + mm + ' ' + ampm;
    }

    /* live session elapsed seconds */
    function liveSessionSec() {
        const tracker = window.bdctTracker;
        if ( ! tracker ) return 0;
        const active = tracker.getActiveSession();
        if ( ! active ) return 0;
        const sec = Math.round(
            ( Date.now() - new Date( active.started_at.replace( ' ', 'T' ) + 'Z' ).getTime() ) / 1000
        );
        const minSec = cfg.minSessionSec || 60;
        return sec >= minSec ? sec : 0;
    }

    /* stat cards */
    function tickStats() {
        if ( ! serverData ) return;
        const live     = liveSessionSec();
        const streak   = serverData.streak || 0;
        const todaySec = serverData.today_sec + live;

        setText( 'bdct-today',   fmtTime( todaySec ) );
        setText( 'bdct-week',    fmtTime( serverData.week_sec  + live ) );
        setText( 'bdct-alltime', fmtTime( serverData.all_sec   + live ) );
        setText( 'bdct-streak',  streak + ( streak === 1 ? ' day' : ' days' ) );

        // Warn when there is an active streak but no activity logged today yet.
        const riskEl = document.getElementById( 'bdct-streak-risk' );
        if ( riskEl ) {
            riskEl.textContent = ( streak > 0 && todaySec === 0 ) ? '(!) at risk today' : '';
        }
    }

    /* fetch server data */
    function loadDashboard() {
        if ( ! cfg.ajaxUrl || loading ) return;
        loading = true;

        const fd = new FormData();
        fd.append( 'action', 'bdct_get_dashboard' );
        fd.append( 'nonce',  cfg.nonce );

        fetch( cfg.ajaxUrl, { method: 'POST', body: fd } )
            .then( function ( r ) {
                if ( ! r.ok ) throw new Error( 'HTTP ' + r.status );
                return r.json();
            } )
            .then( function ( res ) {
                loading = false;
                if ( ! res.success ) return;
                serverData = res.data;
                tickStats();
                renderChart( res.data.daily_30 );
                renderRecent( res.data.recent );
                renderPerPage( res.data.per_page );
            } )
            .catch( function () {
                loading = false;
                showError( 'bdct-recent-body',  5 );
                showError( 'bdct-perpage-body', 5 );
            } );
    }

    /* chart */
    function renderChart( daily ) {
        const canvas = document.getElementById( 'bdct-chart' );
        if ( ! canvas ) return;

        if ( chartInstance ) {
            chartInstance.destroy();
            chartInstance = null;
        }

        if ( ! window.Chart ) {
            canvas.style.display = 'none';
            const msg = document.getElementById( 'bdct-chart-msg' );
            if ( msg ) { msg.textContent = 'Chart.js failed to load.'; msg.style.display = 'block'; }
            return;
        }

        // Only show days that actually have tracked time.
        const active = ( daily || [] ).filter( function ( d ) { return +d.seconds > 0; } );

        if ( ! active.length ) {
            canvas.style.display = 'none';
            const msg = document.getElementById( 'bdct-chart-msg' );
            if ( msg ) { msg.textContent = 'No activity in the last 30 days yet.'; msg.style.display = 'block'; }
            return;
        }

        canvas.style.display = '';
        const msg = document.getElementById( 'bdct-chart-msg' );
        if ( msg ) msg.style.display = 'none';

        const labels = active.map( function ( d ) {
            const p = d.date.split( '-' );
            return p[ 2 ] + '-' + p[ 1 ];
        } );
        const values = active.map( function ( d ) { return +d.seconds; } );

        chartInstance = new window.Chart( canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [ {
                    label: 'Time',
                    data:  values,
                    backgroundColor: '#2271b1',
                    borderRadius: 3,
                } ],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function ( ctx ) {
                                return ' ' + fmtTime( ctx.parsed.y );
                            },
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1800,
                            callback: function ( val ) {
                                var h = Math.floor( val / 3600 );
                                var m = Math.floor( ( val % 3600 ) / 60 );
                                if ( h > 0 ) return m > 0 ? h + 'h ' + m + 'm' : h + 'h';
                                return m + 'm';
                            },
                        },
                    },
                },
            },
        } );
    }

    /* human-readable admin page labels */
    const PAGE_LABELS = {
        'bdct':              'Dev Code Tracker',
        'bdct-sessions':     'Sessions Log',
        'bdct-settings':     'DCT Settings',
        'edit':              'Posts List',
        'post':              'Post Editor',
        'upload':            'Media Library',
        'plugins':           'Plugins',
        'themes':            'Themes',
        'options-general':   'General Settings',
        'options-writing':   'Writing Settings',
        'options-reading':   'Reading Settings',
        'options-permalink': 'Permalinks',
        'users':             'Users',
        'profile':           'Profile',
        'tools':             'Tools',
        'woocommerce':       'WooCommerce',
    };

    const adminBase = ( cfg.ajaxUrl || '' ).replace( /admin-ajax\.php$/, '' );

    function humanLabel( row ) {
        if ( row.post_id > 0 && row.page_label && row.page_label !== 'Unknown' ) {
            return row.page_label;
        }
        return PAGE_LABELS[ row.admin_page ] || row.page_label || row.admin_page
            || ( row.post_id > 0 ? 'Post #' + row.post_id : 'Unknown' );
    }

    function editLink( row, label ) {
        if ( row.post_id > 0 ) {
            return '<a href="' + esc( adminBase + 'post.php?post=' + row.post_id + '&action=edit' ) + '">' + esc( label ) + '</a>';
        }
        return esc( label );
    }

    /* tables */
    function renderRecent( sessions ) {
        const tbody = document.getElementById( 'bdct-recent-body' );
        if ( ! tbody ) return;
        if ( ! sessions || ! sessions.length ) {
            tbody.innerHTML = '<tr><td colspan="5" class="bdct-empty">No completed sessions yet - keep browsing wp-admin and come back!</td></tr>';
            return;
        }
        tbody.innerHTML = sessions.map( function ( s ) {
            return '<tr>'
                + '<td>' + esc( fmtDate( s.started_at ) ) + '</td>'
                + '<td>' + editLink( s, humanLabel( s ) ) + '</td>'
                + '<td>' + esc( s.post_id > 0 ? s.post_id : '-' ) + '</td>'
                + '<td>' + esc( s.post_type  || '-' ) + '</td>'
                + '<td>' + fmtTime( s.duration_sec ) + '</td>'
                + '</tr>';
        } ).join( '' );
    }

    function renderPerPage( rows ) {
        const tbody = document.getElementById( 'bdct-perpage-body' );
        if ( ! tbody ) return;
        if ( ! rows || ! rows.length ) {
            tbody.innerHTML = '<tr><td colspan="5" class="bdct-empty">No data yet.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map( function ( r ) {
            return '<tr>'
                + '<td>' + editLink( r, humanLabel( r ) ) + '</td>'
                + '<td>' + esc( r.post_type  || '-' ) + '</td>'
                + '<td>' + esc( r.post_id > 0 ? r.post_id : '-' ) + '</td>'
                + '<td>' + fmtTime( r.total_sec ) + '</td>'
                + '<td>' + esc( r.sessions ) + '</td>'
                + '</tr>';
        } ).join( '' );
    }

    /* helpers */
    function showError( tbodyId, cols ) {
        const el = document.getElementById( tbodyId );
        if ( el ) el.innerHTML = '<tr><td colspan="' + cols + '" class="bdct-empty" style="color:#b32d2e">Failed to load data.</td></tr>';
    }

    function setText( id, val ) {
        const el = document.getElementById( id );
        if ( el ) el.textContent = val;
    }

    function esc( str ) {
        return String( str )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' );
    }

    /* init */
    function init() {
        loadDashboard();
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

    setInterval( tickStats, 1000 );
    setInterval( function () {
        if ( document.visibilityState !== 'hidden' ) {
            loadDashboard();
        }
    }, 30000 );
}());
