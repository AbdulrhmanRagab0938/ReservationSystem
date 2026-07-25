<?php
function rr_admin_menu(){
    add_menu_page("Reservations","Reservations","view_reservations","rr_reservations","rr_reservations_page","dashicons-calendar",6);
}
add_action('admin_menu','rr_admin_menu');

function rr_reservations_page(){
    global $wpdb;
    $table = $wpdb->prefix . 'reservations';

    if (isset($_GET['added']) && $_GET['added'] == 1)
        echo "<div class='notice notice-success is-dismissible'><p>Reservation added successfully!</p></div>";

    $month = isset($_GET['rr_month']) ? intval($_GET['rr_month']) : intval(date('n'));
    $year  = isset($_GET['rr_year'])  ? intval($_GET['rr_year'])  : intval(date('Y'));
    if ($month < 1)  { $month = 12; $year--; }
    if ($month > 12) { $month = 1;  $year++; }

    $first_day     = "$year-" . str_pad($month,2,'0',STR_PAD_LEFT) . "-01";
    $last_day      = date('Y-m-t', strtotime($first_day));
    $start_dow     = intval(date('w', strtotime($first_day)));
    $days_in_month = intval(date('t', strtotime($first_day)));

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE reservation_date BETWEEN %s AND %s ORDER BY reservation_date, reservation_time",
        $first_day, $last_day
    ));

    $by_date = [];
    foreach ($rows as $r) $by_date[$r->reservation_date][] = $r;

    $nonce_map = [];
    foreach ($rows as $r) $nonce_map[$r->id] = wp_create_nonce('rr_delete_reservation');

    $prev_m = $month == 1  ? 12 : $month - 1;
    $prev_y = $month == 1  ? $year - 1 : $year;
    $next_m = $month == 12 ? 1  : $month + 1;
    $next_y = $month == 12 ? $year + 1 : $year;

    $total_guests = array_sum(array_map(fn($r) => $r->guests, $rows));
    $today        = date('Y-m-d');
    $post_url     = admin_url('admin-post.php');
    ?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Mono:wght@400;500&display=swap');

        #rrd { font-family:'DM Mono',monospace; background:#c5b7a5; min-height:100vh; padding:28px 32px; color:#2c2218; box-sizing:border-box; }
        #rrd * { box-sizing:border-box; }

        /* Header */
        .rrd-hd { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; }
        .rrd-hd h1 { font-family:'DM Serif Display',serif; font-size:30px; font-weight:400; color:#2c2218; margin:0; }
        .rrd-hd h1 em { color:#a07438; }
        .rrd-nav { display:flex; align-items:center; gap:12px; }
        .rrd-nav a { width:30px; height:30px; border:1px solid #d6cdc0; border-radius:50%; color:#a07438; text-decoration:none; display:flex; align-items:center; justify-content:center; transition:.2s; background:#fff; }
        .rrd-nav a:hover { background:#a07438; color:#fff; border-color:#a07438; }
        .rrd-nav span { font-family:'DM Serif Display',serif; font-size:17px; font-style:italic; color:#a07438; min-width:160px; text-align:center; }

        /* Stats */
        .rrd-stats { display:flex; gap:12px; margin-bottom:20px; }
        .rrd-stat { flex:1; background:#fff; border:1px solid #e4ddd3; border-radius:8px; padding:12px 16px; box-shadow:0 1px 4px rgba(0,0,0,.05); }
        .rrd-stat small { display:block; font-size:9px; letter-spacing:2px; text-transform:uppercase; color:#a09080; margin-bottom:3px; }
        .rrd-stat strong { font-family:'DM Serif Display',serif; font-size:24px; color:#a07438; }

        /* Calendar */
        .rrd-cal { background:#fff; border:1px solid #e4ddd3; border-radius:10px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,.06); }
        .rrd-dow { display:grid; grid-template-columns:repeat(7,1fr); background:#faf7f3; border-bottom:1px solid #ede8e0; }
        .rrd-dow div { padding:8px 0; text-align:center; font-size:9px; letter-spacing:2px; text-transform:uppercase; color:#b0a090; }
        .rrd-grid { display:grid; grid-template-columns:repeat(7,1fr); }

        .rrd-day { min-height:88px; border-right:1px solid #ede8e0; border-bottom:1px solid #ede8e0; padding:8px 10px; transition:background .15s; }
        .rrd-day:nth-child(7n) { border-right:none; }
        .rrd-day.empty { background:#faf7f3; }
        .rrd-day.has { cursor:pointer; }
        .rrd-day.has:hover { background:#fdf5e8; }
        .rrd-day.active { background:#fdf0d8 !important; outline:1px solid #c8a96a; outline-offset:-1px; }

        .rrd-dn { display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; border-radius:50%; font-size:11px; color:#c0b0a0; }
        .rrd-day.has .rrd-dn { color:#2c2218; }
        .rrd-day.today .rrd-dn { background:#c8a96a; color:#fff; }

        .rrd-dot { display:flex; align-items:center; gap:5px; margin-top:5px; font-size:10px; color:#a07438; }
        .rrd-dot::before { content:''; width:5px; height:5px; border-radius:50%; background:#c8a96a; display:inline-block; flex-shrink:0; }
        .rrd-peek { font-size:9px; color:#b0a090; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

        /* Tooltip */
        .rrd-tip { display:none; position:fixed; z-index:9999; background:#fff; border:1px solid #c8a96a; border-radius:8px; padding:12px 14px; min-width:210px; pointer-events:none; box-shadow:0 8px 32px rgba(0,0,0,.15); }
        .rrd-tip.on { display:block; }
        .rrd-tip-hd { font-family:'DM Serif Display',serif; font-size:13px; color:#a07438; margin-bottom:8px; padding-bottom:6px; border-bottom:1px solid #ede8e0; }
        .rrd-tip-row { padding:5px 0; border-bottom:1px solid #f0ebe4; }
        .rrd-tip-row:last-child { border:none; }
        .rrd-tip-name { color:#2c2218; display:block; font-size:11px; }
        .rrd-tip-meta { color:#a09080; display:flex; gap:10px; margin-top:2px; font-size:10px; }
        .rrd-tip-more { font-size:9px; color:#c0b0a0; margin-top:6px; font-style:italic; }

        /* Slide-in panel */
        .rrd-panel { max-height:0; overflow:hidden; transition:max-height .35s ease, opacity .3s ease; opacity:0; }
        .rrd-panel.open { max-height:800px; opacity:1; }
        .rrd-panel-inner { background:#fff; border:1px solid #e4ddd3; border-radius:10px; margin-top:14px; padding:20px 24px; box-shadow:0 2px 10px rgba(0,0,0,.05); }
        .rrd-panel-hd { display:flex; align-items:baseline; gap:12px; margin-bottom:16px; }
        .rrd-panel-hd h2 { font-family:'DM Serif Display',serif; font-size:20px; color:#2c2218; margin:0; font-weight:400; }
        .rrd-panel-hd span { font-size:10px; color:#a09080; letter-spacing:1.5px; text-transform:uppercase; }
        .rrd-panel-close { margin-left:auto; background:none; border:1px solid #ddd6cc; color:#a09080; width:26px; height:26px; border-radius:50%; cursor:pointer; font-size:13px; display:flex; align-items:center; justify-content:center; transition:.2s; line-height:1; }
        .rrd-panel-close:hover { border-color:#a07438; color:#a07438; }

        /* Table */
        .rrd-table { width:100%; border-collapse:collapse; font-size:12px; }
        .rrd-table th { text-align:left; font-size:9px; letter-spacing:2px; text-transform:uppercase; color:#a09080; padding:0 12px 10px; border-bottom:1px solid #ede8e0; font-weight:400; }
        .rrd-table td { padding:12px; border-bottom:1px solid #f0ebe4; color:#5a4e42; vertical-align:middle; }
        .rrd-table tr:last-child td { border-bottom:none; }
        .rrd-table tr:hover td { background:#fdf7f0; }
        .rrd-table td.name { color:#2c2218; font-weight:500; }
        .rrd-table td.time { color:#a07438; white-space:nowrap; }
        .rrd-del { font-size:10px; color:#b0a090; text-decoration:none; border:1px solid #ddd6cc; border-radius:4px; padding:3px 8px; transition:.2s; white-space:nowrap; }
        .rrd-del:hover { color:#c0392b; border-color:#c0392b; }

        /* Add form */
        .rrd-add-sec { margin-top:20px; }
        .rrd-add-btn { background:#fff; border:1px solid #d6cdc0; color:#a07438; font-family:'DM Mono',monospace; font-size:11px; letter-spacing:2px; text-transform:uppercase; padding:9px 18px; border-radius:6px; cursor:pointer; transition:.2s; }
        .rrd-add-btn:hover { background:#a07438; color:#fff; border-color:#a07438; }
        .rrd-form-wrap { display:none; background:#fff; border:1px solid #e4ddd3; border-radius:10px; padding:20px 24px; margin-top:12px; max-width:500px; box-shadow:0 2px 8px rgba(0,0,0,.05); }
        .rrd-form-wrap label { display:block; font-size:9px; letter-spacing:2px; text-transform:uppercase; color:#a09080; margin-bottom:4px; }
        .rrd-form-wrap input, .rrd-form-wrap textarea {
            width:100%; background:#faf7f3; border:1px solid #ddd6cc; border-radius:6px; color:#2c2218;
            padding:9px 12px; font-family:'DM Mono',monospace; font-size:12px; margin-bottom:10px; outline:none; transition:border-color .2s;
        }
        .rrd-form-wrap input:focus, .rrd-form-wrap textarea:focus { border-color:#c8a96a; }
        .rrd-form-wrap input::placeholder, .rrd-form-wrap textarea::placeholder { color:#c0b0a0; }
        .rrd-form-row { display:flex; gap:12px; }
        .rrd-form-row > div { flex:1; }
        .rrd-sub { background:none; border:1px solid #c8a96a; color:#a07438; font-family:'DM Mono',monospace; font-size:11px; letter-spacing:2px; text-transform:uppercase; padding:9px 20px; border-radius:6px; cursor:pointer; transition:.2s; }
        .rrd-sub:hover { background:#c8a96a; color:#fff; }

        /* ── Mobile ── */
        @media (max-width: 700px) {
            #rrd { padding:16px; }
            .rrd-hd { flex-direction:column; align-items:flex-start; gap:12px; }
            .rrd-nav { width:100%; justify-content:space-between; }
            .rrd-nav span { flex:1; }
            .rrd-stats { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
            .rrd-stat { padding:10px 12px; }
            .rrd-stat strong { font-size:20px; }
            .rrd-day { min-height:54px; padding:5px 6px; }
            .rrd-peek { display:none; }
            .rrd-dot { font-size:9px; }
            .rrd-dow div { font-size:8px; letter-spacing:1px; }
            .rrd-dn { width:20px; height:20px; font-size:10px; }
            .rrd-panel-inner { padding:14px; }
            .rrd-panel-hd { flex-wrap:wrap; gap:6px; }
            .rrd-panel-hd h2 { font-size:16px; }
            .rrd-table thead { display:none; }
            .rrd-table tr { display:block; border:1px solid #ede8e0; border-radius:8px; margin-bottom:10px; padding:10px 12px; background:#faf7f3; }
            .rrd-table tr:last-child { margin-bottom:0; }
            .rrd-table tr:hover td { background:transparent; }
            .rrd-table td { display:flex; justify-content:space-between; align-items:flex-start; padding:5px 0; border:none; border-bottom:1px solid #f0ebe4; font-size:12px; }
            .rrd-table td:last-child { border-bottom:none; padding-top:8px; }
            .rrd-table td::before { content:attr(data-label); font-size:9px; letter-spacing:1.5px; text-transform:uppercase; color:#a09080; flex-shrink:0; margin-right:10px; min-width:60px; }
            .rrd-form-row { flex-direction:column; gap:0; }
            .rrd-form-wrap { max-width:100%; }
            .rrd-sub { width:100%; text-align:center; }
        }
    </style>

    <div class="rrd-tip" id="rrd-tip"></div>

    <div id="rrd">

        <div class="rrd-hd">
            <h1>Reservations <em>Calendar</em></h1>
            <div class="rrd-nav">
                <a href="<?php echo esc_url(admin_url("admin.php?page=rr_reservations&rr_month=$prev_m&rr_year=$prev_y")); ?>">&#8592;</a>
                <span><?php echo date('F', strtotime($first_day)) . ' ' . $year; ?></span>
                <a href="<?php echo esc_url(admin_url("admin.php?page=rr_reservations&rr_month=$next_m&rr_year=$next_y")); ?>">&#8594;</a>
            </div>
        </div>

        <div class="rrd-stats">
            <?php foreach ([
                'Reservations'   => count($rows),
                'Total Guests'   => $total_guests,
                'Active Days'    => count($by_date),
                'Avg Guests/Day' => count($by_date) ? round($total_guests / count($by_date), 1) : 0
            ] as $label => $val): ?>
            <div class="rrd-stat"><small><?php echo $label; ?></small><strong><?php echo $val; ?></strong></div>
            <?php endforeach; ?>
        </div>

        <div class="rrd-cal">
            <div class="rrd-dow">
                <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d) echo "<div>$d</div>"; ?>
            </div>
            <div class="rrd-grid">
                <?php
                for ($i = 0; $i < $start_dow; $i++) echo '<div class="rrd-day empty"></div>';
                for ($d = 1; $d <= $days_in_month; $d++) {
                    $ds  = "$year-" . str_pad($month,2,'0',STR_PAD_LEFT) . "-" . str_pad($d,2,'0',STR_PAD_LEFT);
                    $bks = $by_date[$ds] ?? [];
                    $cls = 'rrd-day' . ($bks ? ' has' : '') . ($ds === $today ? ' today' : '');
                    $dat = $bks ? "data-date=\"$ds\"" : '';
                    echo "<div class=\"$cls\" $dat><span class='rrd-dn'>$d</span>";
                    if ($bks) {
                        $tg = array_sum(array_map(fn($r)=>$r->guests, $bks));
                        echo "<div class='rrd-dot'>" . count($bks) . " · " . $tg . " guests</div>";
                        foreach (array_slice($bks,0,2) as $pk)
                            echo "<div class='rrd-peek'>" . esc_html($pk->name) . "</div>";
                    }
                    echo "</div>";
                }
                $trailing = (7 - (($start_dow + $days_in_month) % 7)) % 7;
                for ($i = 0; $i < $trailing; $i++) echo '<div class="rrd-day empty"></div>';
                ?>
            </div>
        </div>

        <!-- Slide-in panel -->
        <div class="rrd-panel" id="rrd-panel">
            <div class="rrd-panel-inner">
                <div class="rrd-panel-hd">
                    <h2 id="rrd-panel-date"></h2>
                    <span id="rrd-panel-meta"></span>
                    <button class="rrd-panel-close" id="rrd-panel-close">&#x2715;</button>
                </div>
                <table class="rrd-table">
                    <thead><tr>
                        <th>Time</th><th>Name</th><th>Guests</th><th>Phone</th><th>Email</th><th>Notes</th><th>Action</th>
                    </tr></thead>
                    <tbody id="rrd-panel-body"></tbody>
                </table>
            </div>
        </div>

        <!-- Add Reservation -->
        <div class="rrd-add-sec">
            <button class="rrd-add-btn" id="rrd-add-btn">+ Add Reservation</button>
            <div class="rrd-form-wrap" id="rrd-form-wrap">
                <form method="POST" action="<?php echo esc_url($post_url); ?>">
                    <input type="hidden" name="action" value="rr_add_reservation">
                    <?php wp_nonce_field('rr_add_reservation'); ?>
                    <div class="rrd-form-row">
                        <div><label>Name</label><input type="text" name="name" placeholder="Full name" required></div>
                        <div><label>Phone</label><input type="tel" name="phone" placeholder="+1 000 000 0000" required></div>
                    </div>
                    <label>Email</label><input type="email" name="email" placeholder="email@example.com" required>
                    <div class="rrd-form-row">
                        <div><label>Date</label><input type="date" name="date" min="<?php echo date('Y-m-d'); ?>" required></div>
                        <div><label>Time</label><input type="time" name="time" required></div>
                    </div>
                    <label>Guests</label><input type="number" name="guests" min="1" max="20" placeholder="Number of guests" required>
                    <label>Notes</label><textarea name="notes" placeholder="Allergies, special requests…" rows="2"></textarea>
                    <button type="submit" class="rrd-sub">Add Reservation</button>
                </form>
            </div>
        </div>

    </div><!-- #rrd -->

    <script>
    (function(){
        var data     = <?php echo json_encode($by_date); ?>;
        var nonceMap = <?php echo json_encode($nonce_map); ?>;
        var postUrl  = '<?php echo esc_js($post_url); ?>';
        var tip      = document.getElementById('rrd-tip');
        var panel    = document.getElementById('rrd-panel');
        var panelDate= document.getElementById('rrd-panel-date');
        var panelMeta= document.getElementById('rrd-panel-meta');
        var panelBody= document.getElementById('rrd-panel-body');
        var active   = null;

        function esc(s){ return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : '—'; }
        function fmtDate(ds){ return new Date(ds+'T00:00:00').toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric'}); }

        document.querySelectorAll('.rrd-day.has').forEach(function(day){
            // Tooltip
            day.addEventListener('mouseenter', function(e){
                var bks = data[day.dataset.date] || [];
                var html = '<div class="rrd-tip-hd">'+fmtDate(day.dataset.date)+'</div>';
                bks.slice(0,3).forEach(function(b){
                    html += '<div class="rrd-tip-row"><span class="rrd-tip-name">'+esc(b.name)+'</span>'
                          + '<div class="rrd-tip-meta"><span>&#x23F0; '+esc(b.reservation_time)+'</span>'
                          + '<span>&#x1F465; '+b.guests+'</span><span>&#x260E; '+esc(b.phone)+'</span></div></div>';
                });
                if (bks.length > 3) html += '<div class="rrd-tip-more">+'+(bks.length-3)+' more</div>';
                tip.innerHTML = html;
                tip.classList.add('on');
                moveTip(e);
            });
            day.addEventListener('mousemove', moveTip);
            day.addEventListener('mouseleave', function(){ tip.classList.remove('on'); });

            // Click → slide-in panel
            day.addEventListener('click', function(){
                tip.classList.remove('on');
                var date = day.dataset.date;
                var bks  = data[date] || [];

                // Toggle closed if same day
                if (active === date && panel.classList.contains('open')) {
                    panel.classList.remove('open');
                    day.classList.remove('active');
                    active = null; return;
                }

                // Deactivate previous
                if (active) { var prev = document.querySelector('.rrd-day.active'); if(prev) prev.classList.remove('active'); }
                active = date;
                day.classList.add('active');

                // Populate
                var tg = bks.reduce(function(s,b){ return s+parseInt(b.guests); },0);
                panelDate.textContent = fmtDate(date);
                panelMeta.textContent = bks.length+' reservation'+(bks.length!==1?'s':'')+' · '+tg+' guests';

                panelBody.innerHTML = bks.map(function(b){
                    var del = postUrl+'?action=rr_delete_reservation&id='+b.id+'&_wpnonce='+(nonceMap[b.id]||'');
                    return '<tr>'
                        +'<td class="time" data-label="Time">'+esc(b.reservation_time)+'</td>'
                        +'<td class="name" data-label="Name">'+esc(b.name)+'</td>'
                        +'<td data-label="Guests">'+b.guests+'</td>'
                        +'<td data-label="Phone">'+esc(b.phone)+'</td>'
                        +'<td data-label="Email">'+esc(b.email)+'</td>'
                        +'<td data-label="Notes">'+(b.notes?esc(b.notes):'—')+'</td>'
                        +'<td data-label="Action"><a href="'+del+'" class="rrd-del" onclick="return confirm(\'Delete this reservation?\')">Delete</a></td>'
                        +'</tr>';
                }).join('');

                panel.classList.add('open');
                setTimeout(function(){ panel.scrollIntoView({behavior:'smooth',block:'nearest'}); },50);
            });
        });

        function moveTip(e){
            var x=e.clientX+14, y=e.clientY+14;
            if(x+240>window.innerWidth)  x=e.clientX-240;
            if(y+180>window.innerHeight) y=e.clientY-180;
            tip.style.left=x+'px'; tip.style.top=y+'px';
        }

        document.getElementById('rrd-panel-close').addEventListener('click',function(){
            panel.classList.remove('open');
            if(active){ var el=document.querySelector('.rrd-day.active'); if(el) el.classList.remove('active'); active=null; }
        });

        document.getElementById('rrd-add-btn').addEventListener('click',function(){
            var w=document.getElementById('rrd-form-wrap'), open=w.style.display==='block';
            w.style.display=open?'none':'block';
            this.textContent=open?'+ Add Reservation':'− Close';
        });
    })();
    </script>
    <?php
}