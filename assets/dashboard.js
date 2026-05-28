/* Bitlence Dev Code Tracker — dashboard data + chart rendering */
( function () {
    'use strict';

    const cfg = window.bdctConfig || {};
    let chartInstance = null;
    let serverData    = null;
    let loading       = false;

    /* ── formatters ── */
    function fmtTime( sec ) {
        sec = Math.max( 0, Math.round( +sec ) );
        const h = Math.floor( sec / 3600 );
        const m = Math.floor( ( sec % 3600 ) / 60 );
        const s = sec % 60;
        if ( h > 0 ) return h + 'h ' + m + 'm';
        if ( m > 0 ) return m + 'm ' + s + 's';
        return s + 's';
    }

    // "2026-05-27 14:30:00" → "27-05-2026 14:30"   |   "2026-05-27" → "27-05-2026"
    function fmtDate( raw ) {
        const str = String( raw );
        const p   = str.slice( 0, 10 ).split( '-' );
        const t   = str.length > 10 ? ' ' + str.slice( 11, 16 ) : '';
        return p[ 2 ] + '-' + p[ 1 ] + '-' + p[ 0 ] + t;
    }

    /* ── live session elapsed seconds ── */
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

    /* ── stat cards ── */
    function tickStats() {
        if ( ! serverData ) return;
        const live   = liveSessionSec();
        const streak = serverData.streak || 0;
        setText( 'bdct-today',   fmtTime( serverData.today_sec + live ) );
        setText( 'bdct-week',    fmtTime( serverData.week_sec  + live ) );
        setText( 'bdct-alltime', fmtTime( serverData.all_sec   + live ) );
        setText( 'bdct-streak',  streak + ( streak === 1 ? ' day' : ' days' ) );
    }

    /* ── fetch server data ── */
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

    /* ── chart ── */
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

        if ( ! daily || ! daily.length ) {
            canvas.style.display = 'none';
            const msg = document.getElementById( 'bdct-chart-msg' );
            if ( msg ) { msg.textContent = 'No activity in the last 30 days yet.'; msg.style.display = 'block'; }
            return;
        }

        canvas.style.display = '';
        const msg = document.getElementById( 'bdct-chart-msg' );
        if ( msg ) msg.style.display = 'none';

        const labels = daily.map( function ( d ) {
            const p = d.date.split( '-' );
            return p[ 2 ] + '-' + p[ 1 ];
        } );
        const values = daily.map( function ( d ) { return Math.round( d.seconds / 60 ); } );

        chartInstance = new window.Chart( canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [ {
                    label: 'Minutes',
                    data:  values,
                    backgroundColor: '#2271b1',
                    borderRadius: 3,
                } ],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales:  { y: { beginAtZero: true, ticks: { stepSize: 30 } } },
            },
        } );
    }

    /* ── tables ── */
    function renderRecent( sessions ) {
        const tbody = document.getElementById( 'bdct-recent-body' );
        if ( ! tbody ) return;
        if ( ! sessions || ! sessions.length ) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#888">No completed sessions yet — keep browsing wp-admin and come back!</td></tr>';
            return;
        }
        tbody.innerHTML = sessions.map( function ( s ) {
            return '<tr>'
                + '<td>' + esc( fmtDate( s.started_at ) ) + '</td>'
                + '<td>' + esc( s.admin_page || '—' ) + '</td>'
                + '<td>' + esc( s.post_id > 0 ? s.post_id : '—' ) + '</td>'
                + '<td>' + esc( s.post_type  || '—' ) + '</td>'
                + '<td>' + fmtTime( s.duration_sec ) + '</td>'
                + '</tr>';
        } ).join( '' );
    }

    function renderPerPage( rows ) {
        const tbody = document.getElementById( 'bdct-perpage-body' );
        if ( ! tbody ) return;
        if ( ! rows || ! rows.length ) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#888">No data yet.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map( function ( r ) {
            return '<tr>'
                + '<td>' + esc( r.page_label || r.admin_page || '—' ) + '</td>'
                + '<td>' + esc( r.post_type  || '—' ) + '</td>'
                + '<td>' + esc( r.post_id > 0 ? r.post_id : '—' ) + '</td>'
                + '<td>' + fmtTime( r.total_sec ) + '</td>'
                + '<td>' + esc( r.sessions ) + '</td>'
                + '</tr>';
        } ).join( '' );
    }

    /* ── helpers ── */
    function showError( tbodyId, cols ) {
        const el = document.getElementById( tbodyId );
        if ( el ) el.innerHTML = '<tr><td colspan="' + cols + '" style="text-align:center;color:#b32d2e">Failed to load data.</td></tr>';
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

    /* ── init ── */
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', loadDashboard );
    } else {
        loadDashboard();
    }

    setInterval( tickStats,     1000 );
    setInterval( loadDashboard, 30000 );
} () );
