/* Bitlence Dev Code Tracker - Team Overview page */
(function () {
    'use strict';

    const cfg = window.bdctConfig || {};
    let teamFrom = '';
    let teamTo   = '';

    function fmtTime( sec ) {
        sec = Math.max( 0, Math.round( +sec ) );
        const h = Math.floor( sec / 3600 );
        const m = Math.floor( ( sec % 3600 ) / 60 );
        const s = sec % 60;
        if ( h > 0 ) return h + 'h ' + m + 'm';
        if ( m > 0 ) return m + 'm ' + s + 's';
        return s + 's';
    }

    function esc( str ) {
        return String( str )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' );
    }

    function hasTeamRange() {
        return !! ( teamFrom || teamTo );
    }

    function showError( tbodyId, cols ) {
        const el = document.getElementById( tbodyId );
        if ( el ) el.innerHTML = '<tr><td colspan="' + cols + '" class="bdct-empty" style="color:#b32d2e">Failed to load data.</td></tr>';
    }

    function loadTeamSummary() {
        if ( ! cfg.ajaxUrl || ! document.getElementById( 'bdct-team-body' ) ) return;

        const fd = new FormData();
        fd.append( 'action', 'bdct_get_team_summary' );
        fd.append( 'nonce',  cfg.nonce );
        fd.append( 'from',   teamFrom );
        fd.append( 'to',     teamTo );

        fetch( cfg.ajaxUrl, { method: 'POST', body: fd } )
            .then( function ( r ) {
                if ( ! r.ok ) throw new Error( 'HTTP ' + r.status );
                return r.json();
            } )
            .then( function ( res ) {
                if ( ! res.success ) throw new Error( 'bad response' );
                renderTeam( res.data );
            } )
            .catch( function () {
                showError( 'bdct-team-body', hasTeamRange() ? 5 : 4 );
            } );
    }

    function renderTeam( rows ) {
        const tbody   = document.getElementById( 'bdct-team-body' );
        const rangeTh = document.getElementById( 'bdct-team-range-th' );
        if ( ! tbody ) return;

        const showRange = hasTeamRange();
        if ( rangeTh ) rangeTh.style.display = showRange ? '' : 'none';
        const cols = showRange ? 5 : 4;

        if ( ! rows || ! rows.length ) {
            tbody.innerHTML = '<tr><td colspan="' + cols + '" class="bdct-empty">No tracked activity yet.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map( function ( r ) {
            let row = '<tr>'
                + '<td>' + esc( r.display_name || 'Unknown' ) + '</td>'
                + '<td>' + fmtTime( r.today_sec ) + '</td>'
                + '<td>' + fmtTime( r.week_sec )  + '</td>'
                + '<td>' + fmtTime( r.all_sec )   + '</td>';
            if ( showRange ) {
                row += '<td>' + fmtTime( r.range_sec || 0 ) + '</td>';
            }
            return row + '</tr>';
        } ).join( '' );
    }

    function updateTeamExportLink() {
        const link = document.getElementById( 'bdct-team-export' );
        if ( ! link || ! link.dataset.baseHref ) return;
        const url = new URL( link.dataset.baseHref, window.location.href );
        if ( teamFrom ) { url.searchParams.set( 'bdct_from', teamFrom ); } else { url.searchParams.delete( 'bdct_from' ); }
        if ( teamTo )   { url.searchParams.set( 'bdct_to',   teamTo );   } else { url.searchParams.delete( 'bdct_to' );   }
        link.href = url.toString();
    }

    function initTeamFilter() {
        const applyBtn = document.getElementById( 'bdct-team-apply' );
        const clearBtn = document.getElementById( 'bdct-team-clear' );
        const fromEl   = document.getElementById( 'bdct-team-from' );
        const toEl     = document.getElementById( 'bdct-team-to' );
        if ( ! applyBtn ) return;

        applyBtn.addEventListener( 'click', function () {
            teamFrom = fromEl.value || '';
            teamTo   = toEl.value   || '';
            updateTeamExportLink();
            loadTeamSummary();
        } );
        clearBtn.addEventListener( 'click', function () {
            fromEl.value = '';
            toEl.value   = '';
            teamFrom = '';
            teamTo   = '';
            updateTeamExportLink();
            loadTeamSummary();
        } );
    }

    function init() {
        initTeamFilter();
        loadTeamSummary();
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

    setInterval( function () {
        if ( document.visibilityState !== 'hidden' ) {
            loadTeamSummary();
        }
    }, 30000 );
}());
