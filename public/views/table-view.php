<?php
defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo esc_html( sprintf( __( 'Table %d — %s', 'ptm-tournaments' ), $table_number, $tournament->name ) ); ?></title>
    <link rel="stylesheet" href="<?php echo PTM_PLUGIN_URL; ?>public/css/scorer.css">
    <script src="<?php echo PTM_PLUGIN_URL; ?>admin/js/qrcode.min.js"></script>
    <style>
        .ptm-table-status { text-align: center; padding: 48px 24px; color: #94a3b8; }
        .ptm-table-status h2 { font-size: 22px; color: #cbd5e1; margin: 0 0 8px; }
        .ptm-table-status p  { margin: 0; font-size: 15px; }
        .ptm-table-spinner   { display: inline-block; width: 32px; height: 32px; border: 3px solid #334155; border-top-color: #38bdf8; border-radius: 50%; animation: ptm-spin 0.8s linear infinite; margin-bottom: 16px; }
        @keyframes ptm-spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body class="ptm-scorer-body">
<div class="ptm-scorer">

    <header class="ptm-scorer-header">
        <div class="ptm-scorer-table-num"><?php printf( __( 'Table %d', 'ptm-tournaments' ), $table_number ); ?></div>
        <div class="ptm-scorer-tournament"><?php echo esc_html( $tournament->name ); ?></div>
        <div class="ptm-scorer-meta" id="ptm-match-meta">&nbsp;</div>
    </header>

    <div id="ptm-table-body">
        <div class="ptm-table-status">
            <div class="ptm-table-spinner"></div>
            <h2><?php _e( 'Loading…', 'ptm-tournaments' ); ?></h2>
        </div>
    </div>

</div>

<script>
(function() {
    var tournamentId = <?php echo (int) $tournament_id; ?>;
    var tableNumber  = <?php echo (int) $table_number; ?>;
    var restUrl      = <?php echo json_encode( rest_url( 'gdc/v1/' ) ); ?>;
    var currentToken = null;
    var busy         = false;
    var POLL_MS      = 4000;

    function escHtml(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }

    function fetchTable() {
        fetch(restUrl + 'tournament/' + tournamentId + '/table/' + tableNumber)
            .then(function(r) { return r.json(); })
            .then(function(data) { render(data); })
            .catch(function() {})
            .then(function() { setTimeout(fetchTable, POLL_MS); });
    }

    function render(data) {
        var match = data.match;
        var body  = document.getElementById('ptm-table-body');
        var meta  = document.getElementById('ptm-match-meta');

        if (!match) {
            currentToken = null;
            busy = false;
            meta.textContent = ' ';
            body.innerHTML =
                '<div class="ptm-table-status">' +
                '<h2>' + <?php echo json_encode( __( 'Table is free', 'ptm-tournaments' ) ); ?> + '<\/h2>' +
                '<p>' + <?php echo json_encode( __( 'Waiting for next match to be assigned…', 'ptm-tournaments' ) ); ?> + '<\/p>' +
                '<\/div>';
            return;
        }

        if (match.status === 'complete') {
            // Match just finished — show briefly then clear token so next poll re-renders
            currentToken = null;
        }

        if (match.token !== currentToken) {
            currentToken = match.token;
            renderMatchUI(match, data.game_type);
        } else {
            updateScores(match);
        }
    }

    function renderMatchUI(match, gameType) {
        var meta = document.getElementById('ptm-match-meta');
        var sideLabel = match.bracket_side === 'losers' ? <?php echo json_encode( __( 'Losers Bracket', 'ptm-tournaments' ) ); ?> :
                        match.bracket_side === 'finals' ? <?php echo json_encode( __( 'Grand Finals', 'ptm-tournaments' ) ); ?> :
                        <?php echo json_encode( __( 'Winners Bracket', 'ptm-tournaments' ) ); ?>;
        meta.textContent = (gameType ? gameType.toUpperCase() + ' · ' : '') + sideLabel + ' · Round ' + match.round;

        var rt1 = match.race_to_player1;
        var rt2 = match.race_to_player2;
        var raceLabel = rt1 !== rt2
            ? <?php echo json_encode( __( 'Race to %1$d / Race to %2$d', 'ptm-tournaments' ) ); ?>.replace('%1$d', rt1).replace('%2$d', rt2)
            : <?php echo json_encode( __( 'Race to %d', 'ptm-tournaments' ) ); ?>.replace('%d', rt1);

        var p1pct = Math.min(100, Math.round(match.player1_score / rt1 * 100));
        var p2pct = Math.min(100, Math.round(match.player2_score / rt2 * 100));

        document.getElementById('ptm-table-body').innerHTML =
            '<div class="ptm-scorer-race">' + escHtml(raceLabel) + '<\/div>' +
            '<div class="ptm-scorer-players" id="ptm-scorer-players">' +
            '<div class="ptm-scorer-player" id="ptm-sp-1">' +
            '<div class="ptm-scorer-name">' + escHtml(match.player1_name || 'Player 1') + '<\/div>' +
            '<div class="ptm-scorer-score" id="ptm-score-1">' + match.player1_score + '<\/div>' +
            '<div class="ptm-scorer-progress"><div class="ptm-scorer-progress-bar" id="ptm-progress-1" style="width:' + p1pct + '%"><\/div><\/div>' +
            '<div class="ptm-scorer-buttons">' +
            '<button type="button" class="ptm-scorer-minus" data-slot="1" aria-label="Remove game">−<\/button>' +
            '<button type="button" class="ptm-scorer-plus" data-slot="1" aria-label="Add game">+<\/button>' +
            '<\/div><\/div>' +
            '<div class="ptm-scorer-divider">VS<\/div>' +
            '<div class="ptm-scorer-player" id="ptm-sp-2">' +
            '<div class="ptm-scorer-name">' + escHtml(match.player2_name || 'Player 2') + '<\/div>' +
            '<div class="ptm-scorer-score" id="ptm-score-2">' + match.player2_score + '<\/div>' +
            '<div class="ptm-scorer-progress"><div class="ptm-scorer-progress-bar" id="ptm-progress-2" style="width:' + p2pct + '%"><\/div><\/div>' +
            '<div class="ptm-scorer-buttons">' +
            '<button type="button" class="ptm-scorer-minus" data-slot="2" aria-label="Remove game">−<\/button>' +
            '<button type="button" class="ptm-scorer-plus" data-slot="2" aria-label="Add game">+<\/button>' +
            '<\/div><\/div>' +
            '<\/div>' +
            '<div class="ptm-scorer-footer"><p class="ptm-scorer-hint">' + <?php echo json_encode( __( 'Tap + after each game. The match completes automatically when the race-to is reached.', 'ptm-tournaments' ) ); ?> + '<\/p><\/div>' +
            '<div id="ptm-confirm-panel" style="display:none; flex:1; flex-direction:column; align-items:center; justify-content:center; padding:32px 24px; text-align:center;">' +
            '<div style="font-size:52px; margin-bottom:16px;">🎱<\/div>' +
            '<p id="ptm-confirm-msg" style="font-size:20px; font-weight:700; color:#f1f5f9; margin:0 0 28px; line-height:1.4;"><\/p>' +
            '<div style="display:flex; gap:14px; width:100%; max-width:300px;">' +
            '<button type="button" id="ptm-confirm-yes" style="flex:1; padding:16px 0; font-size:17px; font-weight:700; border:none; border-radius:12px; background:#22c55e; color:#fff; cursor:pointer;">✓ Confirm Win<\/button>' +
            '<button type="button" id="ptm-confirm-no" style="flex:1; padding:16px 0; font-size:17px; font-weight:700; border:none; border-radius:12px; background:rgba(255,255,255,0.1); color:#94a3b8; cursor:pointer;">✗ Cancel<\/button>' +
            '<\/div><\/div>';

        // Attach scoring handlers
        attachHandlers(match.token, rt1, rt2, match.player1_name, match.player2_name);
    }

    function updateScores(match) {
        var s1 = document.getElementById('ptm-score-1');
        var s2 = document.getElementById('ptm-score-2');
        var b1 = document.getElementById('ptm-progress-1');
        var b2 = document.getElementById('ptm-progress-2');
        if (s1) s1.textContent = match.player1_score;
        if (s2) s2.textContent = match.player2_score;
        if (b1) b1.style.width = Math.min(100, Math.round(match.player1_score / match.race_to_player1 * 100)) + '%';
        if (b2) b2.style.width = Math.min(100, Math.round(match.player2_score / match.race_to_player2 * 100)) + '%';
    }

    function attachHandlers(token, rt1, rt2, p1name, p2name) {
        var scores = [0, 0];
        var s1el = document.getElementById('ptm-score-1');
        var s2el = document.getElementById('ptm-score-2');
        if (s1el) scores[0] = parseInt(s1el.textContent, 10) || 0;
        if (s2el) scores[1] = parseInt(s2el.textContent, 10) || 0;

        function submitScore(slot, action) {
            if (busy) return;
            busy = true;
            document.querySelectorAll('.ptm-scorer-plus, .ptm-scorer-minus').forEach(function(b) { b.disabled = true; });
            fetch(restUrl + 'match/' + token + '/score', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ player_slot: slot, action: action })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                busy = false;
                document.querySelectorAll('.ptm-scorer-plus, .ptm-scorer-minus').forEach(function(b) { b.disabled = false; });
                if (data.error) { alert(data.error); return; }
                var m = data.match;
                scores[0] = parseInt(m.player1_score, 10);
                scores[1] = parseInt(m.player2_score, 10);
                updateScores({ player1_score: scores[0], player2_score: scores[1], race_to_player1: rt1, race_to_player2: rt2 });
                if (data.completed) {
                    currentToken = null;
                    setTimeout(fetchTable, 2000);
                }
            })
            .catch(function() {
                busy = false;
                document.querySelectorAll('.ptm-scorer-plus, .ptm-scorer-minus').forEach(function(b) { b.disabled = false; });
            });
        }

        function showConfirm(msg, onConfirm) {
            var panel    = document.getElementById('ptm-confirm-panel');
            var msgEl    = document.getElementById('ptm-confirm-msg');
            var yesBtn   = document.getElementById('ptm-confirm-yes');
            var noBtn    = document.getElementById('ptm-confirm-no');
            var players  = document.getElementById('ptm-scorer-players');
            var footer   = document.querySelector('.ptm-scorer-footer');
            if (!panel || !msgEl || !yesBtn || !noBtn) { if (confirm(msg)) onConfirm(); return; }
            msgEl.textContent = msg;
            if (players) players.style.display = 'none';
            if (footer) footer.style.display = 'none';
            panel.style.display = 'flex';
            yesBtn.onclick = function() {
                panel.style.display = 'none';
                if (players) players.style.display = '';
                if (footer) footer.style.display = '';
                onConfirm();
            };
            noBtn.onclick = function() {
                panel.style.display = 'none';
                if (players) players.style.display = '';
                if (footer) footer.style.display = '';
            };
        }

        function scoreAction(slot, action) {
            if (busy) return;
            if (action === 'add') {
                var cur    = slot === 1 ? scores[0] : scores[1];
                var target = slot === 1 ? rt1 : rt2;
                var name   = slot === 1 ? p1name : p2name;
                var other  = slot === 1 ? scores[1] : scores[0];
                if (cur + 1 >= target) {
                    showConfirm(name + ' wins the match ' + (cur + 1) + '–' + other + '. Confirm?', function() { submitScore(slot, action); });
                    return;
                }
            }
            submitScore(slot, action);
        }

        document.querySelectorAll('.ptm-scorer-plus').forEach(function(btn) {
            btn.addEventListener('click', function() { scoreAction(parseInt(this.dataset.slot, 10), 'add'); });
        });
        document.querySelectorAll('.ptm-scorer-minus').forEach(function(btn) {
            btn.addEventListener('click', function() { scoreAction(parseInt(this.dataset.slot, 10), 'remove'); });
        });
    }

    fetchTable();
})();
</script>
</body>
</html>
