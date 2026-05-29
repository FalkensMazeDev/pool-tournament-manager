/* global PTM */
(function() {
    'use strict';

    var lastUpdated    = {};
    var pollTimers     = {};

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
                var changed = lastUpdated[key] && data.last_updated !== lastUpdated[key];

                lastUpdated[key] = data.last_updated;

                if (changed) {
                    refreshBracket(tournamentId);
                }

                // Always refresh table status on every poll
                fetchTableStatus(tournamentId);

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

            Object.keys(rounds).forEach(function(roundNum) {
                rounds[roundNum].forEach(function(match) {
                    updatePublicMatch(container, match);
                });
            });
        });
    }

    function updatePublicMatch(container, match) {
        var el = container.querySelector('.ptm-pub-match[data-match-id="' + match.id + '"]');
        if (!el) return;

        var names = el.querySelectorAll('.ptm-pub-name');
        var scores = el.querySelectorAll('.ptm-pub-score');

        // Update player names (handles TBD → real name when player advances)
        if (names[0]) {
            if (match.player1_name) {
                names[0].innerHTML = escHtmlPublic(match.player1_name);
            } else {
                names[0].innerHTML = '<em>TBD</em>';
            }
        }
        if (names[1]) {
            if (match.player2_name) {
                names[1].innerHTML = escHtmlPublic(match.player2_name);
            } else {
                names[1].innerHTML = '<em>TBD</em>';
            }
        }

        // Update scores
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

    // ----------------------------------------------------------------
    // Table status display
    // ----------------------------------------------------------------
    function fetchTableStatus(tournamentId) {
        fetch(PTM.restUrl + 'tournament/' + tournamentId + '/tables')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                renderTableStatus(tournamentId, data.tables || [], data.waiting || 0);
            })
            .catch(function() {
                // Clear loading state on error so it doesn't stay stuck
                var grid = document.getElementById('ptm-pub-tables-grid-' + tournamentId);
                if (grid && grid.querySelector('.ptm-pub-tables-loading')) {
                    grid.innerHTML = '';
                }
            });
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
                } else {
                    checkForUpdates(tid);
                }
            }
        });
    });

})();
