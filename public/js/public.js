/* global PTM */
(function() {
    'use strict';

    var lastUpdated  = {};
    var pollTimers   = {};

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
                // Only poll for active tournaments
                var liveBadge = el.querySelector('.ptm-live-badge');
                if (liveBadge) {
                    initPolling(tid);
                } else {
                    // Still fetch once to populate last-updated timestamp
                    checkForUpdates(tid);
                }
            }
        });
    });

})();
