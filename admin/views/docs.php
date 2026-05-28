<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap ptm-admin ptm-docs">

    <h1>📖 <?php _e( 'Pool Tournament Manager — How It Works', 'ptm-tournaments' ); ?></h1>
    <p class="ptm-docs-intro">
        <?php _e( 'A complete guide to running tournaments with this plugin.', 'ptm-tournaments' ); ?>
    </p>

    <div class="ptm-docs-toc">
        <strong><?php _e( 'Jump to:', 'ptm-tournaments' ); ?></strong>
        <a href="#section-overview">Overview</a>
        <a href="#section-setup">1. Setup</a>
        <a href="#section-players">2. Players</a>
        <a href="#section-tournament">3. Create a Tournament</a>
        <a href="#section-roster">4. Build the Roster</a>
        <a href="#section-bracket">5. Generate &amp; Run the Bracket</a>
        <a href="#section-scoring">6. Score Entry</a>
        <a href="#section-scorer-url">7. Table Scorer Links</a>
        <a href="#section-public">8. Public / Spectator View</a>
        <a href="#section-shortcodes">9. Shortcodes</a>
        <a href="#section-roles">10. User Roles</a>
        <a href="#section-handicap">11. Handicap System</a>
        <a href="#section-tables">12. Table Management</a>
    </div>

    <!-- ── Overview ─────────────────────────────────────────────────────── -->
    <div class="ptm-docs-section" id="section-overview">
        <h2>Overview</h2>
        <p>This plugin manages pool tournaments (8-ball, 9-ball, 10-ball) from start to finish:</p>
        <div class="ptm-docs-flow">
            <div class="ptm-flow-step">➕<br><strong>Add Players</strong><br>Build a permanent player registry</div>
            <div class="ptm-flow-arrow">→</div>
            <div class="ptm-flow-step">🏆<br><strong>Create Tournament</strong><br>Set game type, format, race-to</div>
            <div class="ptm-flow-arrow">→</div>
            <div class="ptm-flow-step">📋<br><strong>Build Roster</strong><br>Add players, assign seeds</div>
            <div class="ptm-flow-arrow">→</div>
            <div class="ptm-flow-step">🎯<br><strong>Generate Bracket</strong><br>Locks seeds, builds all matches</div>
            <div class="ptm-flow-arrow">→</div>
            <div class="ptm-flow-step">📱<br><strong>Score Matches</strong><br>Admin view or Table Scorer links</div>
        </div>
    </div>

    <!-- ── Setup ─────────────────────────────────────────────────────────── -->
    <div class="ptm-docs-section" id="section-setup">
        <h2>1. First-Time Setup</h2>
        <div class="ptm-docs-note ptm-note-important">
            <strong>⚠️ Required after activation:</strong> Go to <strong>Settings → Permalinks</strong> and click <strong>Save Changes</strong> (no changes needed — just click Save). This flushes WordPress rewrite rules so the Table Scorer URLs work correctly.
        </div>
        <p>That's the only setup step required. Tables are created automatically on activation.</p>
    </div>

    <!-- ── Players ───────────────────────────────────────────────────────── -->
    <div class="ptm-docs-section" id="section-players">
        <h2>2. Player Registry</h2>
        <p>Go to <strong>Tournaments → Player Registry</strong> to manage players.</p>
        <ul>
            <li>Players are stored permanently — add them once and they're available for every future tournament.</li>
            <li>Each player can have an optional email and phone number (for future notification features).</li>
            <li>The <strong>Stats</strong> button on each player shows their career match/game record and finish positions.</li>
            <li>Deleting a player from the registry does <em>not</em> remove their historical tournament results.</li>
        </ul>
        <div class="ptm-docs-note">
            <strong>Tip:</strong> You don't need to pre-populate the registry. When adding players to a tournament roster, you can type a new name and they'll be added to the registry automatically.
        </div>
    </div>

    <!-- ── Create Tournament ─────────────────────────────────────────────── -->
    <div class="ptm-docs-section" id="section-tournament">
        <h2>3. Creating a Tournament</h2>
        <p>Go to <strong>Tournaments → Add Tournament</strong> or click <strong>Add New</strong> from the tournament list.</p>

        <table class="ptm-docs-table">
            <thead><tr><th>Field</th><th>What it does</th></tr></thead>
            <tbody>
                <tr><td><strong>Tournament Name</strong></td><td>Displayed on the bracket and public pages.</td></tr>
                <tr><td><strong>Date</strong></td><td>Optional. Shown on the public listing page.</td></tr>
                <tr><td><strong>Game Type</strong></td><td>8-ball, 9-ball, or 10-ball. Displayed as a label only — does not affect bracket logic.</td></tr>
                <tr><td><strong>Bracket Format</strong></td><td><strong>Single Elimination</strong> — one loss and you're out. <strong>Double Elimination</strong> — players get a second chance in the Losers Bracket before being eliminated.</td></tr>
                <tr><td><strong>Race To (Winners)</strong></td><td>Number of games a player must win on the Winners side to win a match. E.g. "Race to 5" means first to 5 games wins.</td></tr>
                <tr><td><strong>Race To (Losers)</strong></td><td>Same but for the Losers Bracket in Double Elimination. Typically set 1 lower than Winners (e.g. 4 if Winners is 5).</td></tr>
                <tr><td><strong>Show on public site</strong></td><td>If checked, the tournament appears on your public bracket page. Uncheck to keep it private (admin-only).</td></tr>
                <tr><td><strong>Handicap</strong></td><td>Enable skill-based handicapping. When enabled, each match's race-to values are set individually based on the two players' skill levels. See the <a href="#section-handicap">Handicap section</a> below.</td></tr>
            </tbody>
        </table>

        <p>After saving, click <strong>Manage Roster →</strong> to add players.</p>
    </div>

    <!-- ── Roster ────────────────────────────────────────────────────────── -->
    <div class="ptm-docs-section" id="section-roster">
        <h2>4. Building the Roster &amp; Seeding</h2>
        <p>From <strong>Tournaments → [your tournament] → Roster</strong>:</p>

        <h3>Adding Players</h3>
        <p>Use the <strong>Add Player</strong> sidebar on the right:</p>
        <ul>
            <li><strong>Search Existing Player</strong> — type a name to search the registry and click a result to select them.</li>
            <li><strong>Add New Player</strong> — type a name in the second field to create a new player and add them in one step.</li>
            <li>If handicapping is enabled, enter their <strong>Skill Level</strong> (1–9) for this tournament.</li>
            <li>Click <strong>Add to Tournament</strong>.</li>
        </ul>

        <h3>Seeding</h3>
        <p>Seed order determines bracket placement — Seed 1 vs Seed 2 won't meet until the final.</p>
        <ul>
            <li>Click <strong>🔀 Randomize Seeds</strong> to assign a random seed order.</li>
            <li>Drag the <strong>☰</strong> handle on any row to manually adjust the order. Click <strong>Save Order</strong> when done.</li>
            <li>You can randomize and then fine-tune manually.</li>
        </ul>

        <h3>Generating the Bracket</h3>
        <div class="ptm-docs-note ptm-note-important">
            <strong>⚠️ This cannot be undone.</strong> Once you click Generate Bracket, the tournament becomes Active and scores can be entered.
        </div>
        <p>When you're happy with the roster and seeds, click <strong>🏆 Generate Bracket</strong>. The plugin will:</p>
        <ul>
            <li>Round up to the nearest power of 2 (e.g. 28 players → 32-slot bracket with 4 byes).</li>
            <li>Distribute byes so top seeds get them — Seed 1 won't face a live opponent in a round where lower seeds have byes.</li>
            <li>Build all match records and generate unique <strong>Table Scorer Links</strong> for each match.</li>
            <li>Set the tournament status to <strong>Active</strong>.</li>
        </ul>
    </div>

    <!-- ── Bracket ───────────────────────────────────────────────────────── -->
    <div class="ptm-docs-section" id="section-bracket">
        <h2>5. The Admin Bracket View</h2>
        <p>Go to <strong>Tournaments → [your tournament] → Bracket</strong>.</p>
        <p>This is your command center during a live event. You'll see:</p>
        <ul>
            <li>All matches organized by round, with current scores.</li>
            <li><strong>+ / −</strong> buttons on each player to add or remove a game.</li>
            <li>A match automatically completes and advances players when a player reaches their Race To number.</li>
            <li>For Double Elimination: tabs for <strong>Winners Bracket</strong>, <strong>Losers Bracket</strong>, and <strong>Grand Finals</strong>.</li>
            <li>A <strong>📱 Table Scorer Link</strong> under each active match — send this to whoever is running that table.</li>
        </ul>
        <div class="ptm-docs-note">
            <strong>Tip:</strong> Keep this page open on a laptop or tablet during the tournament. It auto-shows completion when matches finish via the score buttons. Click <strong>↻ Refresh</strong> or reload to pull in scores entered by table scorers on their devices.
        </div>
    </div>

    <!-- ── Score Entry ───────────────────────────────────────────────────── -->
    <div class="ptm-docs-section" id="section-scoring">
        <h2>6. Entering Scores</h2>
        <p>Scores are entered <strong>game by game</strong> — tap <strong>+</strong> after each game is won. The match completes automatically when a player reaches their Race To.</p>

        <p>There are two ways to enter scores:</p>

        <div class="ptm-docs-two-col">
            <div class="ptm-docs-col-card">
                <h3>🖥️ Admin Bracket View</h3>
                <p>Any logged-in user with the <strong>Tournament Organizer</strong> role (or Administrator) can use the + / − buttons directly on the bracket page.</p>
                <p>Best for: the person running the event who is watching the whole bracket.</p>
            </div>
            <div class="ptm-docs-col-card">
                <h3>📱 Table Scorer Link</h3>
                <p>Each match has a unique private URL that works on any phone or tablet — <strong>no WordPress login required</strong>. Give this link to whoever is running that specific table.</p>
                <p>Best for: distributing score entry across 4 tables simultaneously.</p>
                <p>See <a href="#section-scorer-url">Table Scorer Links</a> below for details.</p>
            </div>
        </div>

        <div class="ptm-docs-note">
            <strong>Made a mistake?</strong> Use the − button to remove the last game. If a match has already been marked complete in error, an Administrator can reset it from the bracket view (feature coming in a future update — for now, contact your site admin to manually correct the database).
        </div>
    </div>

    <!-- ── Scorer URL ────────────────────────────────────────────────────── -->
    <div class="ptm-docs-section" id="section-scorer-url">
        <h2>7. Table Scorer Links</h2>
        <p>Each match in an active tournament has a unique private URL, visible in the Admin Bracket view as <strong>📱 Table Scorer Link</strong>.</p>

        <div class="ptm-docs-url-example">
            <code><?php echo esc_html( home_url( '/ptm-score/abc123xyz...' ) ); ?></code>
        </div>

        <p><strong>How to use during an event with <?php echo esc_html( get_option('ptm_tables', 4) ); ?> tables:</strong></p>
        <ol>
            <li>Open the Admin Bracket view on your laptop or main device.</li>
            <li>For each active match, find the <strong>📱 Table Scorer Link</strong> and open it (or copy the URL).</li>
            <li>Hand the device/link to the person running that table — no login needed.</li>
            <li>They tap <strong>+</strong> after every game. The big score display updates instantly on their screen.</li>
            <li>When a player reaches the Race To, a winner overlay appears automatically and the bracket advances.</li>
            <li>The link becomes inactive once the match is complete. The next match at that table will have a new link.</li>
        </ol>

        <div class="ptm-docs-note ptm-note-important">
            <strong>Security note:</strong> These links are private — they're 48-character random tokens that are effectively impossible to guess. However, anyone with the link can update that specific match's score. Don't post them publicly; share only with the person at that table.
        </div>

        <div class="ptm-docs-note">
            <strong>Not seeing the scorer link?</strong> Make sure you visited <strong>Settings → Permalinks → Save Changes</strong> after activating the plugin. Without this step, the <code>/ptm-score/</code> URLs return a 404.
        </div>
    </div>

    <!-- ── Public View ───────────────────────────────────────────────────── -->
    <div class="ptm-docs-section" id="section-public">
        <h2>8. Public / Spectator View</h2>
        <p>Spectators can watch the bracket live from the front end of your site. There are two ways to display it:</p>
        <h3>Pretty Permalink URLs (recommended)</h3>
        <p>Every tournament automatically gets a clean URL based on its name slug:</p>
        <div class="ptm-docs-url-example"><code><?php echo esc_html(home_url('/tournament/my-tournament-name/')); ?></code></div>
        <div class="ptm-docs-url-example"><code><?php echo esc_html(home_url('/tournament/my-tournament-name/results/')); ?></code></div>
        <p>These work without creating any WordPress page. The slug is auto-generated from the tournament name and can be customized in the <strong>Edit Tournament</strong> form under "URL Slug".</p>
        <div class="ptm-docs-note ptm-note-important"><strong>⚠️ Required after updating:</strong> Visit <strong>Settings → Permalinks → Save Changes</strong> to register the new rewrite rules.</div>
        <h3>Child Theme Template Overrides</h3>
        <p>To customize how bracket and results pages look, create a <code>ptm-tournaments/</code> folder inside your child theme and add override templates:</p>
        <div class="ptm-docs-code-block">
            <p>Child theme folder structure:</p>
            <code>child-theme/<br>&nbsp;&nbsp;ptm-tournaments/<br>&nbsp;&nbsp;&nbsp;&nbsp;bracket.php &nbsp;&nbsp;← overrides bracket output<br>&nbsp;&nbsp;&nbsp;&nbsp;results.php &nbsp;&nbsp;← overrides results output<br>&nbsp;&nbsp;&nbsp;&nbsp;scorer.php &nbsp;&nbsp;&nbsp;← overrides scorer page</code>
        </div>
        <p>Copy the originals from <code>wp-content/plugins/ptm-tournaments/public/views/</code> into your child theme folder as a starting point.</p>
        <h3>Shortcode (alternative)</h3>
        <p>You can still use shortcodes on any WordPress page if you prefer:</p>

        <h3>Setting Up Your Public Bracket Page</h3>
        <ol>
            <li>Create a new WordPress page (e.g. "Live Bracket" or "Tournament").</li>
            <li>Add the shortcode <code>[ptm_bracket id="<em>tournament_id</em>"]</code> to the page content.</li>
            <li>Publish the page.</li>
        </ol>
        <p>The bracket updates <strong>automatically every 5 seconds</strong> without the spectator needing to refresh — scores, completed matches, and winner highlights all update live.</p>

        <h3>Finding Your Tournament ID</h3>
        <p>Look at the URL when you're on a tournament's bracket or roster page in the admin. You'll see <code>tournament_id=<strong>5</strong></code> in the URL — that number is your tournament ID.</p>

        <h3>Tournament Listing Page</h3>
        <p>Add <code>[ptm_tournaments]</code> to any page to show a list of upcoming and active tournaments with links to their brackets.</p>
    </div>

    <!-- ── Shortcodes ────────────────────────────────────────────────────── -->
    <div class="ptm-docs-section" id="section-shortcodes">
        <h2>9. Shortcodes</h2>

        <table class="ptm-docs-table">
            <thead><tr><th>Shortcode</th><th>What it displays</th><th>Options</th></tr></thead>
            <tbody>
                <tr>
                    <td><code>[ptm_bracket id="5"]</code></td>
                    <td>The full bracket for tournament ID 5. Shows live scores with automatic 5-second polling during active tournaments.</td>
                    <td><code>id</code> — the tournament ID (required)</td>
                </tr>
                <tr>
                    <td><code>[ptm_tournaments]</code></td>
                    <td>A card list of upcoming and active tournaments with date, format, player count, and a link to each bracket.</td>
                    <td>
                        <code>status="active,draft"</code> — which statuses to show<br>
                        <code>limit="10"</code> — max tournaments to show
                    </td>
                </tr>
            </tbody>
        </table>

        <h3>Example page setup</h3>
        <div class="ptm-docs-code-block">
            <p><strong>Page: "Tournaments"</strong></p>
            <code>[ptm_tournaments status="active,draft,complete" limit="20"]</code>
            <br><br>
            <p><strong>Page: "Live Bracket"</strong></p>
            <code>[ptm_bracket id="5"]</code>
        </div>
    </div>

    <!-- ── Roles ─────────────────────────────────────────────────────────── -->
    <div class="ptm-docs-section" id="section-roles">
        <h2>10. User Roles</h2>

        <table class="ptm-docs-table">
            <thead><tr><th>Role</th><th>Can do</th><th>How to assign</th></tr></thead>
            <tbody>
                <tr>
                    <td><strong>Administrator</strong></td>
                    <td>Everything — full access to all tournament features plus all WordPress admin functions.</td>
                    <td>Built-in WordPress role.</td>
                </tr>
                <tr>
                    <td><strong>Tournament Organizer</strong></td>
                    <td>Create and manage tournaments, manage the player registry, build rosters, generate brackets, enter scores via the admin bracket view.</td>
                    <td>Go to <strong>Users → [user] → Edit</strong>, change their role to <strong>Tournament Organizer</strong>.</td>
                </tr>
                <tr>
                    <td><strong>Table Scorer</strong><br><em>(no WP account needed)</em></td>
                    <td>Enter scores for one specific match via a private token URL. Cannot see any other data.</td>
                    <td>No account needed. Share the <strong>📱 Table Scorer Link</strong> from the bracket view.</td>
                </tr>
            </tbody>
        </table>

        <div class="ptm-docs-note">
            <strong>Spectators</strong> don't need any account. They view the public bracket page like any other page on your site.
        </div>
    </div>

    <!-- ── Table Management ────────────────────────────────────────────── -->
    <div class="ptm-docs-section" id="section-tables">
        <h2>12. Table Management</h2>
        <p>Set the number of physical tables in the <strong>Edit Tournament</strong> form. The default is 4.</p>

        <h3>How Automatic Assignment Works</h3>
        <p>The plugin keeps all tables busy at all times:</p>
        <ul>
            <li>When you generate the bracket, the first wave of matches is immediately assigned to all available tables.</li>
            <li>When a match completes, that table is instantly freed and the next waiting match is assigned to it.</li>
            <li>Priority goes to earlier rounds first so the bracket advances evenly front-to-back.</li>
            <li>Matches that have both players but no table assigned are shown in the bracket view waiting count.</li>
        </ul>

        <h3>Table Dashboard</h3>
        <p>At the top of the Admin Bracket view, a <strong>Table Status</strong> grid shows every table at a glance — who's playing, the current score, and a QR code to open the scorer on that table's device.</p>

        <h3>QR Codes</h3>
        <p>Every active match has a QR code that can be scanned to open the Table Scorer on any phone or tablet.</p>
        <ul>
            <li>In the <strong>Table Status dashboard</strong> at the top of the bracket page, each busy table shows a small QR code.</li>
            <li>In the full bracket, click the <strong>⊞ QR</strong> button next to any match's scorer link to show a larger QR code.</li>
            <li>On the <strong>scorer page itself</strong>, tap the ⊞ button in the top-right corner to show a share QR — useful if you need to hand off scoring to someone else mid-match.</li>
        </ul>
    </div>

    <!-- ── Handicap ──────────────────────────────────────────────────────── -->
    <div class="ptm-docs-section" id="section-handicap">
        <h2>11. Handicap System</h2>
        <p>When <strong>Enable skill-based handicapping</strong> is turned on for a tournament, each match gets its own race-to values based on the two players' skill levels instead of the tournament default.</p>

        <h3>Skill Levels</h3>
        <p>Skill levels are entered per-tournament on the Roster page (1 = lowest, 9 = highest). They don't carry over between tournaments — a player might be rated differently at different events.</p>

        <h3>Setting Up Handicap Rules</h3>
        <p>On the <strong>Edit Tournament</strong> page, when handicap is enabled, a <strong>Handicap Rules</strong> panel appears. Each rule defines a matchup:</p>

        <table class="ptm-docs-table">
            <thead><tr><th>Higher Skill</th><th>Lower Skill</th><th>Higher Race To</th><th>Lower Race To</th></tr></thead>
            <tbody>
                <tr><td>7</td><td>4</td><td>7</td><td>4</td></tr>
                <tr><td>6</td><td>3</td><td>7</td><td>4</td></tr>
                <tr><td>5</td><td>5</td><td>5</td><td>5</td></tr>
            </tbody>
        </table>
        <p>Click <strong>+ Add Rule</strong> to add more matchup combinations. Click <strong>×</strong> to remove a rule.</p>

        <h3>How It Works at Bracket Generation</h3>
        <p>When the bracket is generated, the plugin looks up both players' skill levels, finds the matching rule, and writes the race-to values directly onto each match record. So:</p>
        <ul>
            <li>A 7-skill player vs a 4-skill player might be Race to 7 vs Race to 4.</li>
            <li>Two equal-skill players get the tournament default (e.g. Race to 5 each).</li>
        </ul>
        <p>If no rule is found for a particular matchup, the tournament default is used.</p>
        <p>The race-to values are visible on every match card in the bracket view.</p>
    </div>

</div>

<style>
/* Docs-specific styles */
.ptm-docs { max-width: 960px; }
.ptm-docs-intro { font-size: 16px; color: #646970; margin-bottom: 24px; }

.ptm-docs-toc {
    background: #f6f7f7;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 14px 18px;
    margin-bottom: 32px;
    font-size: 13px;
}
.ptm-docs-toc a { margin: 0 8px; color: #2271b1; text-decoration: none; }
.ptm-docs-toc a:hover { text-decoration: underline; }

.ptm-docs-section {
    margin-bottom: 40px;
    padding-bottom: 32px;
    border-bottom: 1px solid #f0f0f0;
}
.ptm-docs-section h2 {
    font-size: 20px;
    font-weight: 700;
    color: #1d2327;
    margin: 0 0 16px;
    padding-bottom: 8px;
    border-bottom: 2px solid #2271b1;
    display: inline-block;
}
.ptm-docs-section h3 {
    font-size: 15px;
    font-weight: 600;
    margin: 20px 0 8px;
    color: #1d2327;
}
.ptm-docs-section p, .ptm-docs-section li { font-size: 14px; line-height: 1.7; color: #2c3338; }
.ptm-docs-section ul, .ptm-docs-section ol { margin-left: 20px; }
.ptm-docs-section li { margin-bottom: 6px; }

/* Process flow */
.ptm-docs-flow {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0;
    margin: 20px 0;
}
.ptm-flow-step {
    background: #f0f6fc;
    border: 1px solid #c3daf5;
    border-radius: 8px;
    padding: 14px 16px;
    text-align: center;
    font-size: 13px;
    min-width: 120px;
    line-height: 1.5;
}
.ptm-flow-step strong { display: block; margin-top: 4px; }
.ptm-flow-arrow { font-size: 20px; color: #2271b1; padding: 0 8px; }

/* Notes */
.ptm-docs-note {
    background: #f0f6fc;
    border-left: 4px solid #2271b1;
    border-radius: 0 4px 4px 0;
    padding: 12px 16px;
    font-size: 13px;
    margin: 16px 0;
    line-height: 1.6;
}
.ptm-note-important {
    background: #fff8e5;
    border-left-color: #dba617;
}

/* Tables */
.ptm-docs-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    margin: 12px 0;
}
.ptm-docs-table th {
    background: #f6f7f7;
    border: 1px solid #ddd;
    padding: 8px 12px;
    text-align: left;
    font-weight: 600;
}
.ptm-docs-table td {
    border: 1px solid #ddd;
    padding: 8px 12px;
    vertical-align: top;
    line-height: 1.6;
}
.ptm-docs-table code {
    background: #f0f0f1;
    padding: 1px 5px;
    border-radius: 3px;
    font-size: 12px;
}

/* Two-column cards */
.ptm-docs-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 16px 0; }
@media (max-width: 700px) { .ptm-docs-two-col { grid-template-columns: 1fr; } }
.ptm-docs-col-card {
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 16px;
    background: #fff;
}
.ptm-docs-col-card h3 { margin-top: 0; font-size: 14px; }
.ptm-docs-col-card p { font-size: 13px; }

/* URL example */
.ptm-docs-url-example {
    background: #1d2327;
    border-radius: 6px;
    padding: 12px 16px;
    margin: 12px 0;
}
.ptm-docs-url-example code {
    color: #a8d8a8;
    font-size: 13px;
    font-family: monospace;
}

/* Code block */
.ptm-docs-code-block {
    background: #f6f7f7;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 16px;
    font-family: monospace;
    font-size: 13px;
}
.ptm-docs-code-block p { margin: 0 0 4px; font-family: sans-serif; font-weight: 600; font-size: 13px; }
.ptm-docs-code-block code { background: none; padding: 0; }
</style>
