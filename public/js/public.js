/* global PTM */
(function() {
    'use strict';

    var lastUpdated    = {};
    var pollTimers     = {};
    var autoTimers     = {};

    // ----------------------------------------------------------------
    // Spectator bracket polling
    // ----------------------------------------------------------------
    function initPolling(tournamentId) {
        if (pollTimers[tournamentId]) return;

        pollTimers[tournamentId] = setInterval(function() {
            checkForUpdates(tournamentId);
        }, PTM.pollInterval || 5000);

        // Also check immediately on load
        checkForUpdates(tournamentId);
    }

    function checkForUpdates(tournamentId) {
        fetch(PTM.restUrl + 'tournament/' + tournamentId + '/updated')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.last_updated) return;

                var key = 'tournament_' + tournamentId;

                if (lastUpdated[key] && data.last_updated !== lastUpdated[key]) {
                    // Something changed — re-fetch the full bracket
                    refreshBracket(tournamentId);
                }

                lastUpdated[key] = data.last_updated;

                // Update timestamp display
                var el = document.getElementById('ptm-pub-updated-' + tournamentId);
                if (el) {
                    var d = new Date(data.last_updated + ' UTC');
                    el.textContent = 'Updated ' + d.toLocaleTimeString();
                }
            })
            .catch(function() {
                // Silently fail on network error; will retry next interval
            });
    }

    function refreshBracket(tournamentId) {
        fetch(PTM.restUrl + 'tournament/' + tournamentId + '/bracket')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.bracket) return;
                renderBracket(tournamentId, data.bracket);
            })
            .catch(function() {});
    }

    function renderBracket(tournamentId, bracketData) {
        var container = document.querySelector('.ptm-bracket-public[data-tournament="' + tournamentId + '"]');
        if (!container) return;

        var sides = ['winners', 'losers', 'finals'];

        sides.forEach(function(side) {
            var rounds = bracketData[side];
            if (!rounds) return;

            var sectionEl = container.querySelector('#ptm-pub-' + side);
            if (!sectionEl) return;

            var roundsEl = sectionEl.querySelector('.ptm-bracket-pub-rounds');
            if (!roundsEl) return;

            // Update scores in existing match elements rather than full re-render
            // to avoid disrupting any scroll position or tab state
            Object.keys(rounds).forEach(function(roundNum) {
                rounds[roundNum].forEach(function(match) {
                    updatePublicMatch(container, match);
                });
            });
        });
    }

    function updatePublicMatch(container, match) {
        // Find match elements by scanning for player names
        // Since we don't have match IDs in the public DOM, we key on the round/side/order
        // A future enhancement can add data-match-id attributes to simplify this
        var matchEls = container.querySelectorAll(
            '#ptm-pub-' + match.bracket_side + ' .ptm-pub-match'
        );

        matchEls.forEach(function(el) {
            var names = el.querySelectorAll('.ptm-pub-name');
            if (names.length < 2) return;

            var p1Name = names[0].textContent.trim();
            var p2Name = names[1].textContent.trim();

            if (
                (match.player1_name && p1Name === match.player1_name) ||
                (match.player2_name && p2Name === match.player2_name)
            ) {
                var scores = el.querySelectorAll('.ptm-pub-score');
                if (scores[0]) scores[0].textContent = match.status !== 'pending' ? match.player1_score : '';
                if (scores[1]) scores[1].textContent = match.status !== 'pending' ? match.player2_score : '';

                // Update status class
                el.className = el.className
                    .replace(/ptm-pub-match--\w+/, '')
                    .trim() + ' ptm-pub-match--' + match.status;

                // Winner/loser highlighting
                var players = el.querySelectorAll('.ptm-pub-player');
                if (match.status === 'complete') {
                    if (players[0]) {
                        players[0].classList.toggle('ptm-pub-winner', match.winner_id == match.player1_id);
                        players[0].classList.toggle('ptm-pub-loser',  match.winner_id != match.player1_id && !!match.player1_id);
                    }
                    if (players[1]) {
                        players[1].classList.toggle('ptm-pub-winner', match.winner_id == match.player2_id);
                        players[1].classList.toggle('ptm-pub-loser',  match.winner_id != match.player2_id && !!match.player2_id);
                    }
                    // Remove live dot if present
                    var liveDot = el.querySelector('.ptm-pub-live-dot');
                    if (liveDot) liveDot.remove();
                }

                // Add live dot for in_progress
                if (match.status === 'in_progress' && !el.querySelector('.ptm-pub-live-dot')) {
                    var dot = document.createElement('div');
                    dot.className = 'ptm-pub-live-dot';
                    dot.textContent = '● LIVE';
                    el.appendChild(dot);
                }
            }
        });
    }

    // ----------------------------------------------------------------
    // Table status display
    // ----------------------------------------------------------------
    function fetchTableStatus(tournamentId) {
        fetch(PTM.restUrl + 'tournament/' + tournamentId + '/tables')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                renderTableStatus(tournamentId, data.tables || [], data.waiting || 0);
            })
            .catch(function() {});
    }

    function renderTableStatus(tournamentId, tables, waiting) {
        var grid = document.getElementById('ptm-pub-tables-grid-' + tournamentId);
        if (!grid) return;

        grid.innerHTML = '';

        tables.forEach(function(t) {
            var card = document.createElement('div');
            card.className = 'ptm-pub-table-card' + (t.match ? ' ptm-pub-table-busy' : ' ptm-pub-table-free');

            var numEl = document.createElement('div');
            numEl.className = 'ptm-pub-table-num';
            numEl.textContent = 'Table ' + t.table;
            card.appendChild(numEl);

            if (t.match) {
                var players = document.createElement('div');
                players.className = 'ptm-pub-table-players';
                players.innerHTML = '<span>' + escHtmlPublic(t.match.player1_name) + '</span>' +
                                    '<em>vs</em>' +
                                    '<span>' + escHtmlPublic(t.match.player2_name) + '</span>';
                card.appendChild(players);

                var score = document.createElement('div');
                score.className = 'ptm-pub-table-score';
                score.textContent = t.match.player1_score + ' – ' + t.match.player2_score;
                card.appendChild(score);

                if (t.match.status === 'in_progress') {
                    var live = document.createElement('div');
                    live.className = 'ptm-pub-table-live';
                    live.textContent = '● LIVE';
                    card.appendChild(live);
                }
            } else {
                var freeEl = document.createElement('div');
                freeEl.className = 'ptm-pub-table-free-label';
                freeEl.textContent = 'Free';
                card.appendChild(freeEl);
            }

            grid.appendChild(card);
        });

        if (waiting > 0) {
            var waitEl = document.createElement('div');
            waitEl.className = 'ptm-pub-table-waiting';
            waitEl.textContent = waiting + ' match' + (waiting === 1 ? '' : 'es') + ' waiting for a table';
            grid.appendChild(waitEl);
        }
    }

    function escHtmlPublic(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ----------------------------------------------------------------
    // Auto-update checkbox (30s interval)
    // ----------------------------------------------------------------
    document.addEventListener('change', function(e) {
        if (!e.target.classList.contains('ptm-autoupdate-toggle')) return;
        var tid = e.target.dataset.tournament;
        if (!tid) return;

        if (e.target.checked) {
            fetchTableStatus(tid);
            refreshBracket(tid);
            autoTimers[tid] = setInterval(function() {
                fetchTableStatus(tid);
                refreshBracket(tid);
            }, 30000);
        } else {
            clearInterval(autoTimers[tid]);
            delete autoTimers[tid];
        }
    });

    // ----------------------------------------------------------------
    // Tabs (public bracket)
    // ----------------------------------------------------------------
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('ptm-pub-tab')) return;

        var tab       = e.target.dataset.tab;
        var container = e.target.closest('.ptm-bracket-public');
        if (!container) return;

        container.querySelectorAll('.ptm-pub-tab').forEach(function(t) {
            t.classList.remove('active');
        });
        e.target.classList.add('active');

        container.querySelectorAll('.ptm-pub-tab-content').forEach(function(s) {
            s.style.display = 'none';
        });
        var target = container.querySelector('#ptm-pub-' + tab);
        if (target) target.style.display = '';
    });

    // ----------------------------------------------------------------
    // Bootstrap: start polling for all bracket widgets on page
    // ----------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.ptm-bracket-public').forEach(function(el) {
            var tid = el.dataset.tournament;
            if (tid) {
                var liveBadge = el.querySelector('.ptm-live-badge');
                if (liveBadge) {
                    initPolling(tid);
                    fetchTableStatus(tid);
                } else {
                    checkForUpdates(tid);
                }
            }
        });
    });

})();
