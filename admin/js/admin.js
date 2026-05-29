/* global jQuery, PTM */
(function($) {
    'use strict';

    // ── Bracket type toggle
    $('#bracket_type').on('change', function() {
        $('.ptm-losers-row').toggle( $(this).val() === 'double_elim' );
    });

    // ── Handicap toggle
    $('#handicap_enabled').on('change', function() {
        $('.ptm-handicap-section').toggle( $(this).is(':checked') );
    });

    let ruleIndex = $('#handicap-rules-body tr').length;
    $('#ptm-add-rule').on('click', function() {
        const row = `<tr class="ptm-rule-row">
            <td><input type="number" name="handicap_rules[${ruleIndex}][skill_level_higher]" min="1" max="9" class="small-text"></td>
            <td><input type="number" name="handicap_rules[${ruleIndex}][skill_level_lower]"  min="1" max="9" class="small-text"></td>
            <td><input type="number" name="handicap_rules[${ruleIndex}][race_to_higher]"     min="1" max="20" class="small-text"></td>
            <td><input type="number" name="handicap_rules[${ruleIndex}][race_to_lower]"      min="1" max="20" class="small-text"></td>
            <td><button type="button" class="button button-small ptm-remove-rule">&times;</button></td>
        </tr>`;
        $('#handicap-rules-body').append(row);
        ruleIndex++;
    });
    $(document).on('click', '.ptm-remove-rule', function() { $(this).closest('tr').remove(); });

    // ── Payout rules
    let payoutIndex = $('#payout-rules-body tr').length;

    function recalcPctTotal() {
        // Total % paid out = sum of (pct × positions_in_range) for each row
        var total = 0;
        $('#payout-rules-body tr').each(function() {
            var pct  = parseFloat($(this).find('input[name*="[pct]"]').val()) || 0;
            var from = parseInt($(this).find('input[name*="[position_from]"]').val(), 10) || 1;
            var to   = parseInt($(this).find('input[name*="[position_to]"]').val(), 10) || 1;
            var count = Math.max(1, to - from + 1);
            total += pct * count;
        });
        total = Math.round(total * 10) / 10;
        $('#ptm-pct-total').text(total);
        $('#ptm-pct-warn').toggle(total > 0 && Math.abs(total - 100) > 0.1);
    }
    $(document).on('input', '#payout-rules-body input[name*="[pct]"], #payout-rules-body input[name*="[position_from]"], #payout-rules-body input[name*="[position_to]"]', recalcPctTotal);
    recalcPctTotal();

    $('#ptm-add-payout').on('click', function() {
        const row = `<tr class="ptm-payout-row">
            <td><input type="text"   name="payout_rules[${payoutIndex}][position_label]" class="regular-text" placeholder="e.g. 5th-8th Place"></td>
            <td><input type="number" name="payout_rules[${payoutIndex}][position_from]"  min="1" class="small-text"></td>
            <td><input type="number" name="payout_rules[${payoutIndex}][position_to]"    min="1" class="small-text"></td>
            <td><input type="number" name="payout_rules[${payoutIndex}][pct]"           min="0" max="100" step="0.5" class="small-text" value="0"> %</td>
            <td><button type="button" class="button button-small ptm-remove-payout">&times;</button></td>
        </tr>`;
        $('#payout-rules-body').append(row);
        payoutIndex++;
        recalcPctTotal();
    });
    $(document).on('click', '.ptm-remove-payout', function() {
        $(this).closest('tr').remove();
        recalcPctTotal();
    });

    // ── Roster: drag-to-sort seeding
    if ($('#ptm-sortable-roster').length) {
        $('#ptm-sortable-roster').sortable({
            handle: '.ptm-drag-handle', axis: 'y',
            update: function() {
                $('#ptm-sortable-roster tr').each(function(i) { $(this).find('.ptm-seed-num').text(i + 1); });
                $('#ptm-save-seeds').show();
            }
        }).disableSelection();
    }

    $('#ptm-save-seeds').on('click', function() {
        const btn = $(this), tid = $('#ptm-tournament-id').val(), playerIds = [];
        $('#ptm-sortable-roster tr').each(function() { playerIds.push($(this).data('player-id')); });
        btn.prop('disabled', true).text('Saving...');
        $.ajax({
            url: PTM.restUrl + 'tournament/' + tid + '/seeds', method: 'POST',
            beforeSend: xhr => xhr.setRequestHeader('X-WP-Nonce', PTM.restNonce),
            contentType: 'application/json', data: JSON.stringify({ player_ids: playerIds }),
            success: function() { btn.prop('disabled', false).text('Saved ✓'); setTimeout(() => btn.hide(), 2000); },
            error: function() { btn.prop('disabled', false).text('Save Order'); alert('Error saving seed order.'); }
        });
    });

    $('#ptm-randomize').on('click', function() {
        const btn = $(this), tid = $('#ptm-tournament-id').val();
        if (!confirm('Randomize the seeding order?')) return;
        btn.prop('disabled', true).text('Randomizing...');
        $.ajax({
            url: PTM.restUrl + 'tournament/' + tid + '/randomize', method: 'POST',
            beforeSend: xhr => xhr.setRequestHeader('X-WP-Nonce', PTM.restNonce),
            success: function(res) {
                btn.prop('disabled', false).text('🔀 Randomize Seeds');
                const tbody = $('#ptm-sortable-roster').empty();
                res.players.forEach(function(p, i) {
                    tbody.append(`<tr data-player-id="${p.player_id}"><td class="ptm-drag-handle">☰</td><td class="ptm-seed-num">${i+1}</td><td>${escHtml(p.name)}</td></tr>`);
                });
                tbody.sortable('refresh');
                $('#ptm-save-seeds').hide();
            },
            error: function() { btn.prop('disabled', false).text('🔀 Randomize Seeds'); alert('Error randomizing seeds.'); }
        });
    });

    $('#ptm-generate-bracket').on('click', function() {
        const tid = $('#ptm-tournament-id').val();
        if (!confirm('Generate the bracket? This activates the tournament and cannot be undone.')) return;
        $(this).prop('disabled', true).text('Generating...');
        $.ajax({
            url: PTM.restUrl + 'tournament/' + tid + '/generate', method: 'POST',
            beforeSend: xhr => xhr.setRequestHeader('X-WP-Nonce', PTM.restNonce),
            success: function() { window.location.href = window.location.href.replace('action=roster', 'action=bracket'); },
            error: function(xhr) {
                const msg = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Error generating bracket.';
                alert(msg);
                $('#ptm-generate-bracket').prop('disabled', false).text('🏆 Generate Bracket');
            }
        });
    });

    // ── Score entry
    $(document).on('click', '.ptm-score-btn', function() {
        const btn = $(this);
        btn.prop('disabled', true);
        recordUserActivity();
        $.ajax({
            url: PTM.adminUrl, method: 'POST',
            data: { action: 'ptm_admin_score', nonce: PTM.nonce, match_id: btn.data('match'), player_slot: btn.data('slot'), score_action: btn.data('action') },
            success: function(res) {
                if (res.success) { updateMatchUI(res.data.match); if (res.data.completed) handleMatchComplete(res.data.match); }
                else { alert(res.data.message || 'Score update failed.'); }
                btn.prop('disabled', false);
            },
            error: function() { alert('Network error. Please try again.'); btn.prop('disabled', false); }
        });
    });

    function updateMatchUI(match) {
        const $m = $('#ptm-match-' + match.id);
        if (!$m.length) return;
        $m.find('.ptm-match-player').eq(0).find('.ptm-player-score').text(match.player1_score);
        $m.find('.ptm-match-player').eq(1).find('.ptm-player-score').text(match.player2_score);
        $m.removeClass('ptm-match--pending ptm-match--in_progress ptm-match--complete').addClass('ptm-match--' + match.status);
    }

    function handleMatchComplete(match) {
        const $m = $('#ptm-match-' + match.id);
        $m.find('.ptm-score-controls, .ptm-match-scorer-link').remove();
        const $p = $m.find('.ptm-match-player');
        $p.eq(0).toggleClass('ptm-winner', match.winner_id == match.player1_id).toggleClass('ptm-loser', match.winner_id != match.player1_id && !!match.player1_id);
        $p.eq(1).toggleClass('ptm-winner', match.winner_id == match.player2_id).toggleClass('ptm-loser', match.winner_id != match.player2_id && !!match.player2_id);
        $m.append('<div class="ptm-match-complete-badge">✓ Complete</div>');
        // Refresh bracket in-place so next-round player slots update; fall back to reload on error
        const bracketTid = $('#ptm-tournament-id').val();
        if (bracketTid) {
            setTimeout(function() { refreshAdminBracket(bracketTid, true); }, 1200);
        } else {
            setTimeout(() => location.reload(), 1500);
        }
    }

    // ── Bracket tabs
    $(document).on('click', '.ptm-tab', function() {
        const tab = $(this).data('tab');
        $('.ptm-tab').removeClass('active'); $(this).addClass('active');
        $('.ptm-tab-content').hide(); $('#ptm-bracket-' + tab).show();
    });

    $('#ptm-refresh-bracket').on('click', () => location.reload());

    // ── Admin bracket auto-polling ─────────────────────────────────────────────

    var adminLastUpdated  = null;
    var adminLastActivity = Date.now();
    var IDLE_RELOAD_MS    = 60000; // full reload after 60s idle when structural change detected

    function recordUserActivity() {
        adminLastActivity = Date.now();
    }

    $(document).on('click keydown', recordUserActivity);

    function updateLastUpdatedDisplay(ts) {
        if (!ts) return;
        var d = new Date(ts.indexOf('Z') === -1 ? ts + ' UTC' : ts);
        $('#ptm-last-updated').text('Last updated: ' + d.toLocaleTimeString());
    }

    function refreshAdminBracket(tid, forceReloadOnStructural) {
        $.get(PTM.restUrl + 'tournament/' + tid + '/bracket', function(res) {
            if (!res.bracket) return;
            var structural = false;
            var sides = ['winners', 'losers', 'finals'];
            sides.forEach(function(side) {
                var rounds = res.bracket[side];
                if (!rounds) return;
                Object.keys(rounds).forEach(function(roundNum) {
                    rounds[roundNum].forEach(function(match) {
                        if (applyAdminMatchUpdate(match)) structural = true;
                    });
                });
            });
            if (structural && forceReloadOnStructural) {
                location.reload();
            }
        }).fail(function() {
            if (forceReloadOnStructural) location.reload();
        });
    }

    // Returns true if a structural change was found (player slot filled, controls added/removed).
    function applyAdminMatchUpdate(match) {
        var $m = $('#ptm-match-' + match.id);
        if (!$m.length) return false;
        var structural = false;

        // Update scores
        $m.find('.ptm-match-player').eq(0).find('.ptm-player-score').text(match.player1_score);
        $m.find('.ptm-match-player').eq(1).find('.ptm-player-score').text(match.player2_score);

        // Update player names — TBD → real name counts as structural
        var $p1name = $m.find('.ptm-match-player').eq(0).find('.ptm-player-name');
        var $p2name = $m.find('.ptm-match-player').eq(1).find('.ptm-player-name');
        if (match.player1_name && $p1name.find('em.ptm-tbd').length) {
            $p1name.text(match.player1_name);
            structural = true;
        }
        if (match.player2_name && $p2name.find('em.ptm-tbd').length) {
            $p2name.text(match.player2_name);
            structural = true;
        }

        // Update status class
        var prevClass = ($m.attr('class') || '').match(/ptm-match--(\w+)/);
        var prevStatus = prevClass ? prevClass[1] : null;
        $m.removeClass('ptm-match--pending ptm-match--in_progress ptm-match--complete')
          .addClass('ptm-match--' + match.status);

        // Handle newly completed match arriving via external scorer
        if (match.status === 'complete' && prevStatus !== 'complete') {
            $m.find('.ptm-score-controls, .ptm-match-scorer-link').remove();
            var $p = $m.find('.ptm-match-player');
            $p.eq(0).toggleClass('ptm-winner', match.winner_id == match.player1_id)
                    .toggleClass('ptm-loser', match.winner_id != match.player1_id && !!match.player1_id);
            $p.eq(1).toggleClass('ptm-winner', match.winner_id == match.player2_id)
                    .toggleClass('ptm-loser', match.winner_id != match.player2_id && !!match.player2_id);
            if (!$m.find('.ptm-match-complete-badge').length) {
                $m.append('<div class="ptm-match-complete-badge">✓ Complete</div>');
            }
            structural = true;
        }

        return structural;
    }

    function refreshAdminTables(tid) {
        $.get(PTM.restUrl + 'tournament/' + tid + '/tables', function(res) {
            var tables  = res.tables  || [];
            var waiting = res.waiting || 0;

            // Update scores on busy table cards
            tables.forEach(function(t) {
                if (!t.match) return;
                var $card = $('.ptm-table-grid .ptm-table-card').eq(t.table - 1);
                var $score = $card.find('.ptm-table-score');
                if ($score.length) {
                    $score.text(t.match.player1_score + ' – ' + t.match.player2_score);
                }
            });

            // Update waiting badge
            var $badge = $('.ptm-table-dashboard-header .ptm-waiting-badge, .ptm-table-dashboard-header .ptm-waiting-clear');
            if (waiting > 0) {
                $badge.text(waiting + ' match' + (waiting === 1 ? '' : 'es') + ' waiting for a table')
                      .removeClass('ptm-waiting-clear').addClass('ptm-waiting-badge');
            } else {
                $badge.text('✓ All ready matches are assigned')
                      .removeClass('ptm-waiting-badge').addClass('ptm-waiting-clear');
            }
        });
    }

    function checkAdminUpdates(tid) {
        $.get(PTM.restUrl + 'tournament/' + tid + '/updated', function(res) {
            if (!res.last_updated) return;

            updateLastUpdatedDisplay(res.last_updated);

            var changed = adminLastUpdated && res.last_updated !== adminLastUpdated;
            adminLastUpdated = res.last_updated;

            if (changed) {
                var idle = Date.now() - adminLastActivity > IDLE_RELOAD_MS;
                refreshAdminBracket(tid, idle);
                refreshAdminTables(tid);
            }
        });
    }

    const tid = $('#ptm-tournament-id').val();
    if (tid) {
        // Fetch immediately then poll every 10 s
        checkAdminUpdates(tid);
        setInterval(function() { checkAdminUpdates(tid); }, 10000);
    }

    // ── QR modal overlay
    $('body').append(
        '<div id="ptm-qr-modal" style="display:none" role="dialog" aria-modal="true">' +
        '<div id="ptm-qr-modal-backdrop"></div>' +
        '<div id="ptm-qr-modal-box">' +
        '<button id="ptm-qr-modal-close" aria-label="Close">&times;<\/button>' +
        '<div id="ptm-qr-modal-svg"><\/div>' +
        '<p id="ptm-qr-modal-url"><\/p>' +
        '<\/div><\/div>'
    );

    $(document).on('click', '.ptm-qr-toggle', function() {
        var url = $(this).data('url');
        if (!url) return;
        $('#ptm-qr-modal-url').text(url);
        $('#ptm-qr-modal-svg').empty();
        new QRCode(document.getElementById('ptm-qr-modal-svg'), {
            text: url,
            width: 220,
            height: 220,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
        });
        $('#ptm-qr-modal').css('display', 'flex');
    });

    $(document).on('click', '#ptm-qr-modal-close, #ptm-qr-modal-backdrop', function() {
        $('#ptm-qr-modal').hide();
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') $('#ptm-qr-modal').hide();
    });

    // ── Player registry edit
    $(document).on('click', '.ptm-edit-player', function() {
        const btn = $(this);
        $('#edit-player-id').val(btn.data('id')); $('#player-name').val(btn.data('name'));
        $('#player-email').val(btn.data('email')); $('#player-phone').val(btn.data('phone'));
        $('#player-apa-number').val(btn.data('apa-number') || '');
        $('#player-apa-sl').val(btn.data('apa-sl') || '');
        $('#player-fargo-id').val(btn.data('fargo-id') || '');
        $('#player-fargo-rating').val(btn.data('fargo-rating') || '');
        // Load custom meta
        $('#ptm-meta-fields').empty();
        const meta = btn.data('meta') || {};
        Object.entries(meta).forEach(([k, v]) => addMetaRow(k, v));
        $('#ptm-player-form-title').text('Edit Player'); $('#ptm-player-submit').text('Update Player');
        $('#ptm-player-cancel').show();
        $('html, body').animate({ scrollTop: $('#ptm-player-form').offset().top - 50 }, 300);
    });
    $('#ptm-player-cancel').on('click', function() {
        $('#edit-player-id').val(''); $('#player-name, #player-email, #player-phone').val('');
        $('#player-apa-number, #player-apa-sl, #player-fargo-id, #player-fargo-rating').val('');
        $('#ptm-meta-fields').empty();
        $('#ptm-player-form-title').text('Add Player'); $('#ptm-player-submit').text('Add Player'); $(this).hide();
    });

    // ── Custom meta field helpers
    function addMetaRow(key, value) {
        key = key || '';
        value = value || '';
        const row = $('<div class="ptm-meta-row" style="display:flex;gap:4px;margin-bottom:6px">' +
            '<input type="text" name="meta_keys[]" placeholder="Field Name" class="regular-text" style="flex:1" value="' + escHtml(key) + '">' +
            '<input type="text" name="meta_values[]" placeholder="Value" class="regular-text" style="flex:1" value="' + escHtml(value) + '">' +
            '<button type="button" class="button ptm-remove-meta" title="Remove">✕</button>' +
            '</div>');
        $('#ptm-meta-fields').append(row);
    }
    $('#ptm-add-meta-field').on('click', function() { addMetaRow('', ''); });
    $(document).on('click', '.ptm-remove-meta', function() { $(this).closest('.ptm-meta-row').remove(); });

    // ── Player autocomplete
    let searchTimer;
    $('#ptm-player-search').on('input', function() {
        const q = $(this).val().trim();
        clearTimeout(searchTimer);
        if (q.length < 2) { $('#ptm-player-suggestions').hide().empty(); return; }
        searchTimer = setTimeout(function() {
            $.ajax({
                url: PTM.adminUrl, data: { action: 'ptm_player_search', nonce: PTM.nonce, q: q, tournament_id: $('#ptm-tournament-id').val() },
                success: function(res) {
                    if (!res.success) return;
                    const $box = $('#ptm-player-suggestions').empty();
                    if (!res.data.length) { $box.append('<div class="ptm-suggestion-item ptm-suggestion-empty">No players found</div>'); }
                    else {
                        res.data.forEach(p => {
                            const $item = $('<div class="ptm-suggestion-item"></div>').html(escHtml(p.text)).data('id', p.id).data('name', p.name || p.text);
                            $box.append($item);
                        });
                    }
                    $box.show();
                }
            });
        }, 250);
    });
    $(document).on('click', '.ptm-suggestion-item', function() {
        const id = $(this).data('id');
        if (id) { $('#selected-player-id').val(id); $('#new-player-name').val('').prop('disabled', true); }
        $('#ptm-player-search').val($(this).data('name') || $(this).text());
        $('#ptm-player-suggestions').hide().empty();
    });
    $('#ptm-player-search').on('change', function() {
        if (!$(this).val()) { $('#selected-player-id').val(''); $('#new-player-name').prop('disabled', false); }
    });
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.ptm-suggestion-item, #ptm-player-search').length) $('#ptm-player-suggestions').hide();
    });

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Copy URL ──────────────────────────────────────────────────────────────
    $(document).on('click', '#ptm-copy-url', function() {
        const url = $(this).data('url');
        if (!url) return;
        navigator.clipboard.writeText(url).then(() => {
            const btn = $(this);
            const orig = btn.text();
            btn.text('✓ Copied!');
            setTimeout(() => btn.text(orig), 2000);
        }).catch(() => {
            // fallback for older browsers
            const ta = document.createElement('textarea');
            ta.value = url;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            const btn = $(this);
            const orig = btn.text();
            btn.text('✓ Copied!');
            setTimeout(() => btn.text(orig), 2000);
        });
    });

    // ── QR Code modal ─────────────────────────────────────────────────────────
    var _qrInstance = null;

    $(document).on('click', '#ptm-show-qr', function() {
        const url = $(this).data('url');
        if (!url) return;

        $('#ptm-qr-url-display').text(url);
        $('#ptm-qr-canvas').empty();
        _qrInstance = new QRCode(document.getElementById('ptm-qr-canvas'), {
            text: url,
            width: 256,
            height: 256,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
        });

        $('#ptm-qr-modal').fadeIn(150);
        $('#ptm-qr-modal .ptm-qr-modal-close').trigger('focus');
    });

    $(document).on('click', '.ptm-qr-modal-close, .ptm-qr-modal-overlay', function() {
        $('#ptm-qr-modal').fadeOut(150);
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') $('#ptm-qr-modal').fadeOut(150);
    });

    $(document).on('click', '#ptm-qr-download', function() {
        const canvas = document.querySelector('#ptm-qr-canvas canvas');
        if (!canvas) return;
        const link = document.createElement('a');
        link.download = 'tournament-qr.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    });

    // ── Notify Players (match email) ──────────────────────────────────
    $(document).on('click', '.ptm-notify-players', function() {
        const btn     = $(this);
        const matchId = btn.data('match');
        btn.prop('disabled', true).text('Sending...');
        $.ajax({
            url: PTM.adminUrl, method: 'POST',
            data: { action: 'ptm_send_match_email', nonce: PTM.nonce, match_id: matchId },
            success: function(res) {
                btn.prop('disabled', false).text('📧 Notify');
                alert(res.success ? (res.data.message || 'Sent!') : ('Error: ' + (res.data.message || 'Unknown error')));
            },
            error: function() {
                btn.prop('disabled', false).text('📧 Notify');
                alert('Network error. Please try again.');
            }
        });
    });

    // ── Reopen Tournament ─────────────────────────────────────────────────────
    $(document).on('click', '#ptm-reopen-tournament', function() {
        const btn = $(this);
        const tid = btn.data('tournament');
        if (!confirm('Reopen this tournament? This will clear all player finish positions and set the tournament status back to active.')) return;
        btn.prop('disabled', true).text('Reopening...');
        $.ajax({
            url: PTM.adminUrl, method: 'POST',
            data: { action: 'ptm_reopen_tournament', nonce: PTM.nonce, tournament_id: tid },
            success: function(res) {
                if (res.success) {
                    location.reload();
                } else {
                    btn.prop('disabled', false).text('↩ Reopen Tournament');
                    alert('Error: ' + (res.data.message || 'Unknown error'));
                }
            },
            error: function() {
                btn.prop('disabled', false).text('↩ Reopen Tournament');
                alert('Network error.');
            }
        });
    });

    // ── Finalize Results ──────────────────────────────────────────────────────
    $(document).on('click', '#ptm-finalize-results', function() {
        const btn = $(this);
        const tid = btn.data('tournament');
        if (!confirm('Finalize tournament results? This will assign finish positions to all players based on when they were eliminated.')) return;
        btn.prop('disabled', true).text('Finalizing...');
        $.ajax({
            url: PTM.adminUrl, method: 'POST',
            data: { action: 'ptm_finalize_results', nonce: PTM.nonce, tournament_id: tid },
            success: function(res) {
                btn.prop('disabled', false).text('🏆 Finalize Results');
                if (res.success) {
                    if (res.data.debug) {
                        // Show diagnostic info to help troubleshoot
                        var d = res.data.debug;
                        alert('Finalize ran but no results found.\n\n' +
                              'Stats rows: ' + d.stats_rows_total + '\n' +
                              'Positions set: ' + d.positions_set + '\n' +
                              'Final match: ' + JSON.stringify(d.final_match) + '\n' +
                              'DB error: ' + (d.last_db_error || 'none'));
                    } else {
                        alert(res.data.message || 'Results finalized!');
                        location.reload();
                    }
                } else {
                    alert('Error: ' + (res.data.message || 'Unknown error'));
                }
            },
            error: function() {
                btn.prop('disabled', false).text('🏆 Finalize Results');
                alert('Network error.');
            }
        });
    });

    // ── Score correction modal ────────────────────────────────────────────────
    const $scoreModal = $('#ptm-score-modal');

    $(document).on('click', '.ptm-edit-score-btn', function() {
        const btn = $(this);
        $('#ptm-modal-match-id').val(btn.data('match'));
        $('#ptm-modal-p1-label').text(btn.data('p1name'));
        $('#ptm-modal-p2-label').text(btn.data('p2name'));
        $('#ptm-modal-p1-score').val(btn.data('p1'));
        $('#ptm-modal-p2-score').val(btn.data('p2'));
        $scoreModal.show();
        $('#ptm-modal-p1-score').focus();
    });

    $('#ptm-modal-cancel').on('click', function() { $scoreModal.hide(); });
    $scoreModal.on('click', function(e) { if ($(e.target).is($scoreModal)) $scoreModal.hide(); });

    $('#ptm-modal-save').on('click', function() {
        const btn     = $(this);
        const matchId = $('#ptm-modal-match-id').val();
        const p1      = parseInt($('#ptm-modal-p1-score').val(), 10);
        const p2      = parseInt($('#ptm-modal-p2-score').val(), 10);

        if (isNaN(p1) || isNaN(p2) || p1 < 0 || p2 < 0) {
            alert('Please enter valid scores (0 or higher).');
            return;
        }

        btn.prop('disabled', true).text('Saving...');
        $.ajax({
            url: PTM.adminUrl, method: 'POST',
            data: { action: 'ptm_correct_score', nonce: PTM.nonce, match_id: matchId, p1_score: p1, p2_score: p2 },
            success: function(res) {
                btn.prop('disabled', false).text('Save Correction');
                $scoreModal.hide();
                if (res.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (res.data.message || 'Unknown error'));
                }
            },
            error: function() {
                btn.prop('disabled', false).text('Save Correction');
                alert('Network error.');
            }
        });
    });

    // ── Email template merge-tag insert ──────────────────────────────
    $(document).on('click', '.ptm-insert-tag', function(e) {
        e.preventDefault();
        var tag    = $(this).data('tag');
        var edId   = 'notification_body';

        // TinyMCE (visual tab)
        if (typeof window.tinymce !== 'undefined') {
            var ed = window.tinymce.get(edId);
            if (ed && !ed.isHidden()) {
                ed.execCommand('mceInsertContent', false, tag);
                return;
            }
        }

        // Quicktags / plain textarea fallback
        var ta = document.getElementById(edId);
        if (!ta) return;
        var start = ta.selectionStart;
        var end   = ta.selectionEnd;
        ta.value  = ta.value.substring(0, start) + tag + ta.value.substring(end);
        ta.selectionStart = ta.selectionEnd = start + tag.length;
        ta.focus();
    });

})(jQuery);
