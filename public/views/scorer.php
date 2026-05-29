<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo esc_html( $match->tournament_name ); ?> — Score Entry</title>
    <link rel="stylesheet" href="<?php echo PTM_PLUGIN_URL; ?>public/css/scorer.css">
</head>
<body class="ptm-scorer-body">

<div class="ptm-scorer">

    <header class="ptm-scorer-header">
        <?php if ( $match->table_number ) : ?>
            <div class="ptm-scorer-table-num">Table <?php echo (int) $match->table_number; ?></div>
        <?php endif; ?>
        <div class="ptm-scorer-tournament"><?php echo esc_html( $match->tournament_name ); ?></div>
        <div class="ptm-scorer-meta">
            <?php echo esc_html( strtoupper( $match->game_type ) ); ?>
            &nbsp;·&nbsp;
            <?php echo $match->bracket_side === 'losers' ? __( "Losers Bracket", 'ptm-tournaments' ) : ( $match->bracket_side === 'finals' ? __( "Grand Finals", 'ptm-tournaments' ) : __( "Winners Bracket", 'ptm-tournaments' ) ); ?>
            &nbsp;·&nbsp;
            <?php printf( __( 'Round %d', 'ptm-tournaments' ), $match->round ); ?>
        </div>
        <button type="button" class="ptm-scorer-qr-btn" id="ptm-qr-toggle" aria-label="Share QR code">⊞</button>
    </header>

    <!-- QR code share sheet -->
    <div class="ptm-scorer-qr-sheet" id="ptm-qr-sheet" style="display:none">
        <div class="ptm-scorer-qr-inner">
            <?php
            $scorer_url = home_url( '/ptm-score/' . $match->score_token );
            echo PTM_QR::svg( $scorer_url, 220 );
            ?>
            <p class="ptm-scorer-qr-url"><?php echo esc_html( $scorer_url ); ?></p>
            <p class="ptm-scorer-qr-hint">Scan to open scorer on another device</p>
            <button type="button" class="ptm-scorer-qr-close" id="ptm-qr-close">Close</button>
        </div>
    </div>

    <?php if ( $match->status === 'complete' ) : ?>

        <div class="ptm-scorer-complete">
            <div class="ptm-scorer-complete-icon">🏆</div>
            <h2><?php _e( 'Match Complete', 'ptm-tournaments' ); ?></h2>
            <p class="ptm-scorer-winner-label">
                <?php printf( __( 'Winner: %s', 'ptm-tournaments' ), '<strong>' . esc_html( $match->winner_name ) . '</strong>' ); ?>
            </p>
            <div class="ptm-scorer-final-score">
                <?php echo esc_html( $match->player1_name ); ?> <?php echo $match->player1_score; ?>
                &nbsp;—&nbsp;
                <?php echo $match->player2_score; ?> <?php echo esc_html( $match->player2_name ); ?>
            </div>
        </div>

    <?php else : ?>

        <div class="ptm-scorer-race">
            <?php if ( $match->race_to_player1 !== $match->race_to_player2 ) : ?>
                <?php printf( __( 'Race to %d / Race to %d', 'ptm-tournaments' ), $match->race_to_player1, $match->race_to_player2 ); ?>
            <?php else : ?>
                <?php printf( __( 'Race to %d', 'ptm-tournaments' ), $match->race_to_player1 ); ?>
            <?php endif; ?>
        </div>

        <div class="ptm-scorer-players" id="ptm-scorer-players">

            <!-- Player 1 -->
            <div class="ptm-scorer-player" id="ptm-sp-1">
                <div class="ptm-scorer-name"><?php echo esc_html( $match->player1_name ?: __( 'Player 1', 'ptm-tournaments' ) ); ?></div>
                <div class="ptm-scorer-score" id="ptm-score-1"><?php echo $match->player1_score; ?></div>
                <div class="ptm-scorer-progress">
                    <div class="ptm-scorer-progress-bar" id="ptm-progress-1"
                         style="width: <?php echo min( 100, round( $match->player1_score / $match->race_to_player1 * 100 ) ); ?>%"></div>
                </div>
                <div class="ptm-scorer-buttons">
                    <button type="button" class="ptm-scorer-minus" data-slot="1" aria-label="Remove game">−</button>
                    <button type="button" class="ptm-scorer-plus"  data-slot="1" aria-label="Add game">+</button>
                </div>
            </div>

            <div class="ptm-scorer-divider">VS</div>

            <!-- Player 2 -->
            <div class="ptm-scorer-player" id="ptm-sp-2">
                <div class="ptm-scorer-name"><?php echo esc_html( $match->player2_name ?: __( 'Player 2', 'ptm-tournaments' ) ); ?></div>
                <div class="ptm-scorer-score" id="ptm-score-2"><?php echo $match->player2_score; ?></div>
                <div class="ptm-scorer-progress">
                    <div class="ptm-scorer-progress-bar" id="ptm-progress-2"
                         style="width: <?php echo min( 100, round( $match->player2_score / $match->race_to_player2 * 100 ) ); ?>%"></div>
                </div>
                <div class="ptm-scorer-buttons">
                    <button type="button" class="ptm-scorer-minus" data-slot="2" aria-label="Remove game">−</button>
                    <button type="button" class="ptm-scorer-plus"  data-slot="2" aria-label="Add game">+</button>
                </div>
            </div>

        </div>

        <div class="ptm-scorer-footer">
            <p class="ptm-scorer-hint"><?php _e( 'Tap + after each game. The match completes automatically when the race-to is reached.', 'ptm-tournaments' ); ?></p>
        </div>

        <!-- Winner overlay -->
        <div class="ptm-scorer-winner-overlay" id="ptm-winner-overlay" style="display:none">
            <div class="ptm-scorer-winner-content">
                <div class="ptm-winner-icon">🏆</div>
                <h2 id="ptm-winner-name"></h2>
                <p><?php _e( 'Wins the match!', 'ptm-tournaments' ); ?></p>
            </div>
        </div>

        <!-- Confirm win — inline swap, no position:fixed needed -->
        <div id="ptm-confirm-panel" style="display:none; flex:1; flex-direction:column; align-items:center; justify-content:center; padding:32px 24px; text-align:center;">
            <div style="font-size:52px; margin-bottom:16px;">🎱</div>
            <p id="ptm-confirm-msg" style="font-size:20px; font-weight:700; color:#f1f5f9; margin:0 0 28px; line-height:1.4;"></p>
            <div style="display:flex; gap:14px; width:100%; max-width:300px;">
                <button type="button" id="ptm-confirm-yes" class="ptm-confirm-yes" style="flex:1; padding:16px 0; font-size:17px; font-weight:700; border:none; border-radius:12px; background:#22c55e; color:#fff; cursor:pointer;">✓ Confirm Win</button>
                <button type="button" id="ptm-confirm-no"  class="ptm-confirm-no"  style="flex:1; padding:16px 0; font-size:17px; font-weight:700; border:none; border-radius:12px; background:rgba(255,255,255,0.1); color:#94a3b8; cursor:pointer;">✗ Cancel</button>
            </div>
        </div>

    <?php endif; ?>

</div>

<script>
(function() {
    var token   = <?php echo json_encode( $match->score_token ); ?>;
    var restUrl = <?php echo json_encode( rest_url( 'gdc/v1/' ) ); ?>;
    var rt1     = <?php echo (int) $match->race_to_player1; ?>;
    var rt2     = <?php echo (int) $match->race_to_player2; ?>;

    var scores  = [
        parseInt(document.getElementById('ptm-score-1').textContent, 10),
        parseInt(document.getElementById('ptm-score-2').textContent, 10)
    ];

    var busy = false;

    function updateUI(match) {
        scores[0] = parseInt(match.player1_score, 10);
        scores[1] = parseInt(match.player2_score, 10);

        document.getElementById('ptm-score-1').textContent = scores[0];
        document.getElementById('ptm-score-2').textContent = scores[1];

        var p1pct = Math.min(100, Math.round(scores[0] / rt1 * 100));
        var p2pct = Math.min(100, Math.round(scores[1] / rt2 * 100));
        document.getElementById('ptm-progress-1').style.width = p1pct + '%';
        document.getElementById('ptm-progress-2').style.width = p2pct + '%';
    }

    function showWinner(name) {
        document.getElementById('ptm-winner-name').textContent = name;
        document.getElementById('ptm-winner-overlay').style.display = 'flex';
        document.querySelectorAll('.ptm-scorer-plus, .ptm-scorer-minus').forEach(function(btn) {
            btn.disabled = true;
        });

        setTimeout(function() {
            location.reload();
        }, 3000);
    }

    // Confirm panel — swaps in place of scoring UI, no CSS overlay needed
    var confirmPanel  = document.getElementById('ptm-confirm-panel');
    var scoringPanel  = document.getElementById('ptm-scorer-players');
    var footerPanel   = document.querySelector('.ptm-scorer-footer');
    var confirmMsg    = document.getElementById('ptm-confirm-msg');
    var confirmYes    = document.getElementById('ptm-confirm-yes');
    var confirmNo     = document.getElementById('ptm-confirm-no');

    function showConfirm(message, onConfirm) {
        if (!confirmPanel || !confirmMsg || !confirmYes || !confirmNo) {
            if (window.confirm(message)) onConfirm();
            return;
        }
        confirmMsg.textContent = message;
        // Hide scoring UI, show confirm panel
        if (scoringPanel) scoringPanel.style.display = 'none';
        if (footerPanel)  footerPanel.style.display  = 'none';
        confirmPanel.style.display = 'flex';

        confirmYes.onclick = function() {
            // Restore scoring UI
            confirmPanel.style.display = 'none';
            if (scoringPanel) scoringPanel.style.display = '';
            if (footerPanel)  footerPanel.style.display  = '';
            onConfirm();
        };
        confirmNo.onclick = function() {
            // Restore scoring UI
            confirmPanel.style.display = 'none';
            if (scoringPanel) scoringPanel.style.display = '';
            if (footerPanel)  footerPanel.style.display  = '';
        };
    }

    function submitScore(slot, action) {
        if (busy) return;
        busy = true;

        // Disable all buttons while request is in flight
        document.querySelectorAll('.ptm-scorer-plus, .ptm-scorer-minus').forEach(function(b) {
            b.disabled = true;
        });

        fetch(restUrl + 'match/' + token + '/score', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ player_slot: slot, action: action })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            busy = false;
            document.querySelectorAll('.ptm-scorer-plus, .ptm-scorer-minus').forEach(function(b) {
                b.disabled = false;
            });

            if (data.error) {
                alert(data.error);
                return;
            }

            updateUI(data.match);

            if (data.completed) {
                var winnerName = data.match.winner_id == data.match.player1_id
                    ? data.match.player1_name
                    : data.match.player2_name;
                showWinner(winnerName);
            }
        })
        .catch(function() {
            busy = false;
            document.querySelectorAll('.ptm-scorer-plus, .ptm-scorer-minus').forEach(function(b) {
                b.disabled = false;
            });
            alert('Network error. Please try again.');
        });
    }

    function scoreAction(slot, action) {
        if (busy) return;

        // Check if this + tap would end the match — if so, confirm first
        if (action === 'add') {
            var currentScore = slot === 1 ? scores[0] : scores[1];
            var raceTarget   = slot === 1 ? rt1 : rt2;
            var playerName   = slot === 1
                ? document.querySelector('#ptm-sp-1 .ptm-scorer-name').textContent.trim()
                : document.querySelector('#ptm-sp-2 .ptm-scorer-name').textContent.trim();

            if (currentScore + 1 >= raceTarget) {
                showConfirm(
                    playerName + ' wins the match ' + (currentScore + 1) + '–' + (slot === 1 ? scores[1] : scores[0]) + '. Confirm?',
                    function() { submitScore(slot, action); }
                );
                return;
            }
        }

        submitScore(slot, action);
    }

    document.querySelectorAll('.ptm-scorer-plus').forEach(function(btn) {
        btn.addEventListener('click', function() {
            scoreAction(parseInt(this.dataset.slot, 10), 'add');
        });
    });

    document.querySelectorAll('.ptm-scorer-minus').forEach(function(btn) {
        btn.addEventListener('click', function() {
            scoreAction(parseInt(this.dataset.slot, 10), 'remove');
        });
    });
    // QR popup window
    var qrToggle = document.getElementById('ptm-qr-toggle');
    var qrSheet  = document.getElementById('ptm-qr-sheet');
    if (qrToggle && qrSheet) {
        qrToggle.addEventListener('click', function() {
            var svg = qrSheet.querySelector('svg');
            var urlEl = qrSheet.querySelector('.ptm-scorer-qr-url');
            if (!svg) return;
            var url = urlEl ? urlEl.textContent : '';
            var popup = window.open('', 'ptm_qr_popup', 'width=320,height=420,resizable=yes,scrollbars=no');
            if (!popup) return;
            popup.document.write(
                '<!DOCTYPE html><html><head><title>QR Code<\/title>' +
                '<style>body{margin:0;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;font-family:sans-serif;background:#fff}' +
                'svg{max-width:260px;height:auto}p{margin:12px 8px 4px;font-size:13px;word-break:break-all;text-align:center;color:#333}<\/style>' +
                '<\/head><body>' + svg.outerHTML + '<p>' + url + '<\/p><\/body><\/html>'
            );
            popup.document.close();
            popup.focus();
        });
    }
})();
</script>

</body>
</html>
