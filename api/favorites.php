<?php
require_once __DIR__ . '/db_related/db_connect.php';

// Function to create a "time ago" string
function time_ago($datetime) {
    if (!$datetime) return 'a long time ago';
    try {
        $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
        $ago = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    } catch (Exception $e) {
        return 'a long time ago';
    }
    
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 1) return $diff->d . ' days ago';
    if ($diff->d == 1) return 'yesterday';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'just now';
}

// --- User Identity Detection ---
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$browserClass = '';
if (strpos($userAgent, 'Chrome') !== false) {
    $browserClass = 'is-chrome';
} elseif (strpos($userAgent, 'Safari') !== false) {
    $browserClass = 'is-safari';
}

// --- PAGINATION LOGIC ---
$items_per_page = 6;
$filter_writer = isset($_GET['writer']) ? $_GET['writer'] : '';

// Base WHERE clause is for starred messages
$where_conditions = ["is_starred = true"];
$params = [];

if ($filter_writer === 'MJ' || $filter_writer === 'Kaye') {
    $where_conditions[] = "writer = :writer";
    $params['writer'] = $filter_writer;
}

$where_clause = "WHERE " . implode(' AND ', $where_conditions);

try {
    // Get total number of items
    $total_items_stmt = $pdo->prepare("SELECT COUNT(*) FROM messages $where_clause");
    $total_items_stmt->execute($params);
    $total_items = $total_items_stmt->fetchColumn();

    // Calculate total pages
    $total_pages = ceil($total_items / $items_per_page);

    // Get current page from URL, default to 1, and validate it
    $current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $current_page = max(1, min($current_page, $total_pages > 0 ? $total_pages : 1));

    // Calculate the offset
    $offset = ($current_page - 1) * $items_per_page;

    // Fetch messages for the current page
    if ($total_items > 0) {
        $stmt = $pdo->prepare("SELECT id, writer, title, created_at, view_count, spotify_track_id, starred_by FROM messages $where_clause ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $messages = $stmt->fetchAll();
    } else {
        $messages = [];
    }
} catch (PDOException $e) {
    $error = "Error fetching messages: " . $e->getMessage();
    $messages = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Favorites - Archive for Kaye</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        :root { --accent: #f472b6; /* Pink */ }
        body { background-color: #030303; color: #d1d5db; font-family: 'Playfair Display', serif; overflow-x: hidden; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .noise-overlay { position: fixed; inset: 0; z-index: 50; pointer-events: none; opacity: 0.04; mix-blend-mode: overlay; background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E"); }
        .glow-sphere { position: fixed; border-radius: 50%; z-index: -1; filter: blur(90px); will-change: transform; }
        .pink-1 { width: 600px; height: 600px; background: radial-gradient(circle, rgba(236, 72, 153, 0.4) 0%, rgba(0, 0, 0, 0) 70%); top: -10%; left: -10%; animation: float1 8s infinite alternate ease-in-out; }
        .pink-2 { width: 700px; height: 700px; background: radial-gradient(circle, rgba(244, 114, 182, 0.3) 0%, rgba(0, 0, 0, 0) 70%); bottom: -20%; right: -10%; animation: float2 10s infinite alternate ease-in-out; }
        @keyframes float1 { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(40vw, 30vh) scale(1.3); } }
        @keyframes float2 { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(-40vw, -30vh) scale(1.4); } }
    </style>
</head>
<body class="selection:bg-pink-500/40 min-h-screen flex items-center justify-center p-4 sm:p-6 relative z-0 <?= $browserClass ?>">
    <div class="noise-overlay"></div>
    <div class="glow-sphere pink-1"></div>
    <div class="glow-sphere pink-2"></div>

    <div class="w-full max-w-4xl">
        <main class="w-full glass p-6 sm:p-10 rounded-[24px] sm:rounded-[28px] relative overflow-hidden">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <h2 class="text-3xl sm:text-4xl text-white font-light italic tracking-tighter">Favorite Notes</h2>
                <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                    <a href="index.php" class="block sm:inline-block w-full sm:w-auto text-center glass hover:bg-white/10 text-white py-3 px-6 rounded-xl mono text-[11px] uppercase tracking-widest transition-all">← Back</a>
                    <a href="form.php" class="block sm:inline-block w-full sm:w-auto text-center glass hover:bg-white/10 text-white py-3 px-6 rounded-xl mono text-[11px] uppercase tracking-widest transition-all">Add a Note ✎</a>
                </div>
            </div>
            <div class="mb-8 p-6 glass rounded-2xl border-white/10 text-slate-300 font-sans text-[15px] leading-relaxed">
                Hi kaye, I might have said or maybe not in a direct way to say that I want you to stay away from this site when I was drunk, that was the total opposite. In fact this site is still here because you are, this project was so special to me cause aside the fact that this is for you I actually learn things related to my passion like hosting this site for free (basically if you wanna host or live a website naa man guy bayad so I learned cheating it when doing this project for you) and some many more things. With that said I'm here again with a new feature, I'll let you explore this one.
            </div>
            
            <?php if (isset($error)): ?>
                <div class='text-red-400 mb-6 p-4 glass rounded-xl border-red-500/30 font-sans text-sm'><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="mb-6 flex justify-end">
                <form method="GET" action="" class="flex items-center space-x-3">
                    <label for="writer-filter" class="mono text-[10px] uppercase tracking-[0.2em] text-pink-400 font-bold">Filter:</label>
                    <select id="writer-filter" name="writer" onchange="this.form.submit()" class="bg-black/40 border border-white/10 rounded-xl p-2 px-4 text-white text-sm focus:outline-none focus:border-pink-500 transition-colors appearance-none cursor-pointer">s
                        <option value="" class="bg-black text-white" <?= empty($filter_writer) ? 'selected' : '' ?>>All Favorites</option>
                        <option value="MJ" class="bg-black text-white" <?= $filter_writer === 'MJ' ? 'selected' : '' ?>>MJ's</option>
                        <option value="Kaye" class="bg-black text-white" <?= $filter_writer === 'Kaye' ? 'selected' : '' ?>>Kaye's</option>
                    </select>
                </form>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 font-sans">
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="glass p-6 rounded-2xl flex flex-col hover:bg-white/5 transition-colors border-white/5">
                            <div class="mb-3">
                                <span class="text-xs text-pink-400/80 mono block uppercase tracking-widest">
                                    <?= htmlspecialchars($msg['writer'] ?? 'MJ') ?> • <?= htmlspecialchars(date('M d, Y g:i a', strtotime($msg['created_at']))) ?> • <?= htmlspecialchars($msg['view_count'] ?? 0) ?> views
                                </span>
                                <?php if (!empty($msg['starred_by'])): ?>
                                    <span class="text-xs text-yellow-400/90 mono block uppercase tracking-widest mt-2">★ Favorite ni <?= htmlspecialchars($msg['starred_by']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-start justify-between gap-3 mb-6">
                                <h3 class="text-xl text-white font-medium line-clamp-2 break-words">
                                    <?= htmlspecialchars($msg['title']) ?>
                                </h3>
                                <?php if (!empty($msg['spotify_track_id'])): ?>
                                    <svg class="w-5 h-5 text-[#f472b6] flex-shrink-0 mt-1 drop-shadow-[0_0_12px_rgba(244,114,182,0.8)]" fill="currentColor" viewBox="0 0 24 24" title="Song Attached"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
                                <?php endif; ?>
                            </div>
                            <div class="mt-auto flex space-x-3 pt-4 border-t border-white/5">
                                <a href="view.php?id=<?= $msg['id'] ?>&page=<?= $current_page ?>&from=favorites" class="flex-1 text-center glass hover:bg-white/10 text-white py-2.5 px-4 rounded-xl mono text-[10px] uppercase tracking-widest transition-all">View</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full p-8 text-center text-slate-500 italic glass rounded-2xl border-white/5">
                        No favorite notes yet. Star a note to see it here.
                    </div>
                <?php endif; ?>
            </div>
        </main>
        <?php if ($total_pages > 1): ?>
            <?php 
                $writer_param = !empty($filter_writer) ? '&writer=' . urlencode($filter_writer) : '';
            ?>
            <div class="mt-8 flex justify-between items-center font-sans">
                <div>
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?= $current_page - 1 ?><?= $writer_param ?>" class="inline-block glass hover:bg-white/10 text-white py-2 px-4 sm:px-5 rounded-xl mono text-xs uppercase tracking-widest transition-all"><span class="sm:hidden">←</span><span class="hidden sm:inline">← Previous</span></a>
                    <?php else: ?>
                        <span class="inline-block glass bg-white/5 text-white/30 py-2 px-4 sm:px-5 rounded-xl mono text-xs uppercase tracking-widest cursor-not-allowed"><span class="sm:hidden">←</span><span class="hidden sm:inline">← Previous</span></span>
                    <?php endif; ?>
                </div>      
                <div class="flex flex-nowrap justify-center gap-1 sm:gap-2 px-1 sm:px-2">
                    <?php 
                    $pages = [];
                    if ($total_pages <= 5) {
                        for ($i = 1; $i <= $total_pages; $i++) { $pages[] = $i; }
                    } else {
                        if ($current_page <= 2) {
                            $pages = [1, 2, 3, '...', $total_pages];
                        } elseif ($current_page >= $total_pages - 2) {
                            $pages = [1, '...', $total_pages - 2, $total_pages - 1, $total_pages];
                        } else {
                            $pages = [1, '...', $current_page, $current_page + 1, $current_page + 2, '...', $total_pages];
                        }
                    }
                    foreach ($pages as $p): ?>
                        <?php if ($p === '...'): ?>
                            <span class="inline-flex items-center justify-center min-w-[32px] h-8 px-2 text-slate-400 mono text-xs">...</span>
                        <?php elseif ($p === $current_page): ?>
                            <span class="inline-flex items-center justify-center min-w-[32px] h-8 px-2 rounded-lg glass bg-pink-500/20 text-pink-400 border border-pink-500/30 mono text-xs"><?= $p ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $p ?><?= $writer_param ?>" class="inline-flex items-center justify-center min-w-[32px] h-8 px-2 rounded-lg glass hover:bg-white/10 text-white mono text-xs transition-all"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div>
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?= $current_page + 1 ?><?= $writer_param ?>" class="inline-block glass hover:bg-white/10 text-white py-2 px-4 sm:px-5 rounded-xl mono text-xs uppercase tracking-widest transition-all"><span class="sm:hidden">→</span><span class="hidden sm:inline">Next →</span></a>
                    <?php else: ?>
                        <span class="inline-block glass bg-white/5 text-white/30 py-2 px-4 sm:px-5 rounded-xl mono text-xs uppercase tracking-widest cursor-not-allowed"><span class="sm:hidden">→</span><span class="hidden sm:inline">Next →</span></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>