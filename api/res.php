<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db_related/db_connect.php';

// Function to create a "time ago" string
function time_ago($datetime) {
    if (!$datetime) return 'a long time ago';
    try {
        // Ensure both times are in the same timezone for an accurate comparison.
        $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
        $ago = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    } catch (Exception $e) {
        return 'a long time ago'; // Handle potential parsing errors
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

// --- AJAX Presence Handler ---
if (isset($_POST['update_presence_ajax'])) {
    // This part is for handling async presence updates from JS
    error_reporting(0);
    header('Content-Type: application/json');

    $current_user_identity = null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (strpos($userAgent, 'Chrome') !== false) {
        $current_user_identity = 'MJ';
    } elseif (strpos($userAgent, 'Safari') !== false) {
        $current_user_identity = 'Kaye';
    }

    if ($current_user_identity) {
        $status_state = $_POST['status_state'] ?? 'blurred';
        $page_status = $_POST['page_status'] ?? 'Idle / Away';

        try {
            if ($status_state === 'focused') {
                // The NOW() function uses the 'Asia/Manila' timezone set in db_connect.php
                $stmt = $pdo->prepare("INSERT INTO presence (user_identity, last_seen, is_active, current_page) VALUES (:identity, NOW(), true, :page_status) ON CONFLICT (user_identity) DO UPDATE SET last_seen = NOW(), is_active = true, current_page = :page_status");
                $stmt->execute(['identity' => $current_user_identity, 'page_status' => $page_status]);
            } else { // blurred
                $stmt = $pdo->prepare("UPDATE presence SET is_active = false, current_page = :page_status WHERE user_identity = :identity");
                $stmt->execute(['identity' => $current_user_identity, 'page_status' => $page_status]);
            }
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'User identity not determined.']);
    }
    exit;
}
// Detect browser to apply specific classes
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$browserClass = '';
if (strpos($userAgent, 'Edg') !== false) {
    // It's Edge, do nothing for now.
} elseif (strpos($userAgent, 'Chrome') !== false) {
    $browserClass = 'is-chrome';
} elseif (strpos($userAgent, 'Safari') !== false) {
    $browserClass = 'is-safari';
}

// --- PRESENCE DETECTION (Database Method) ---
$presence_data = [];
$kaye_is_recently_active = false; // For MJ's blinking screen
try {
    // Auto-create the presence table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS presence (
        id SERIAL PRIMARY KEY,
        user_identity VARCHAR(50) UNIQUE NOT NULL,
        last_seen TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
        is_active BOOLEAN NOT NULL DEFAULT true,
        current_page VARCHAR(100)
    )");

    // Mark users as inactive if they haven't been seen in the last 60 seconds
    $timeout_interval = 60; // seconds
    $pdo->exec("UPDATE presence SET is_active = false WHERE last_seen < NOW() - interval '$timeout_interval seconds'");
    
    // Fetch all users and their status for display
    $presence_stmt = $pdo->query("SELECT user_identity, current_page, is_active, last_seen FROM presence");
    $all_presence = $presence_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process presence data and check for Kaye's recent activity
    foreach ($all_presence as $user) {
        if ($user['user_identity'] === 'Kaye' && $user['last_seen']) {
            try {
                $last_seen_kaye = new DateTime($user['last_seen'], new DateTimeZone('Asia/Manila'));
                $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
                // Check if last seen was within the last 5 minutes (300 seconds)
                if (($now->getTimestamp() - $last_seen_kaye->getTimestamp()) < 300) {
                    $kaye_is_recently_active = true;
                }
            } catch (Exception $e) { /* Fails if last_seen is null or invalid, which is fine. */ }
        }
    }
    $presence_data = $all_presence;
} catch (PDOException $e) { /* Presence is non-critical, so we fail silently. */ }

// Handle deletion if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    try {
        $delStmt = $pdo->prepare("DELETE FROM messages WHERE id = :id");
        $delStmt->execute(['id' => $_POST['delete_id']]);
        $success_msg = "Note successfully deleted.";
    } catch (PDOException $e) {
        $error = "Error deleting message: " . $e->getMessage();
    }
}

// --- PAGINATION LOGIC ---
$items_per_page = 6; // Show 6 notes per page

$filter_writer = isset($_GET['writer']) ? $_GET['writer'] : '';
$where_clause = "";

if ($filter_writer === 'MJ' || $filter_writer === 'Kaye') {
    $where_clause = "WHERE writer = :writer";
}
try {
    // Get total number of items
    $total_items_stmt = $pdo->prepare("SELECT COUNT(*) FROM messages $where_clause");
    if (!empty($where_clause)) {
        $total_items_stmt->bindValue(':writer', $filter_writer);
    }
    $total_items_stmt->execute();
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
        $stmt = $pdo->prepare("SELECT id, writer, title, created_at, view_count, spotify_track_id FROM messages $where_clause ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
        if (!empty($where_clause)) {
            $stmt->bindValue(':writer', $filter_writer);
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

// Get the writer of the very last message
$last_message_writer = null;
try {
    $last_msg_stmt = $pdo->query("SELECT writer FROM messages ORDER BY created_at DESC LIMIT 1");
    $last_msg = $last_msg_stmt->fetch();
    if ($last_msg) {
        $last_message_writer = $last_msg['writer'];
    }
} catch (PDOException $e) { /* Fail silently */ }

$blink_class = ($browserClass === 'is-chrome' && $kaye_is_recently_active) ? 'blink-effect' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($browserClass === 'is-chrome'): ?>
    <meta http-equiv="refresh" content="30">
    <?php endif; ?>
    <title>Results - Archive for Kaye</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        :root { --accent: #f472b6; }
        body { background-color: #030303; color: #d1d5db; font-family: 'Playfair Display', serif; overflow-x: hidden; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); }

        /* Premium Enhancements */
        .noise-overlay { position: fixed; inset: 0; z-index: 50; pointer-events: none; opacity: 0.04; mix-blend-mode: overlay; background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E"); }
        
        /* Animated Pink Orbs */
        .glow-sphere { position: fixed; border-radius: 50%; z-index: -1; filter: blur(90px); will-change: transform; }
        .pink-1 { width: 600px; height: 600px; background: radial-gradient(circle, rgba(236, 72, 153, 0.4) 0%, rgba(0, 0, 0, 0) 70%); top: -10%; left: -10%; animation: float1 8s infinite alternate ease-in-out; }
        .pink-2 { width: 700px; height: 700px; background: radial-gradient(circle, rgba(244, 114, 182, 0.3) 0%, rgba(0, 0, 0, 0) 70%); bottom: -20%; right: -10%; animation: float2 10s infinite alternate ease-in-out; }
        .pink-3 { width: 450px; height: 450px; background: radial-gradient(circle, rgba(251, 113, 133, 0.35) 0%, rgba(0, 0, 0, 0) 70%); top: 40%; left: 30%; animation: float3 12s infinite alternate ease-in-out; }
        
        @keyframes float1 { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(40vw, 30vh) scale(1.3); } }
        @keyframes float2 { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(-40vw, -30vh) scale(1.4); } }
        @keyframes float3 { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(-30vw, 40vh) scale(0.8); } }

        @keyframes blink-animation {
            0%, 100% { background-color: #030303; }
            50% { background-color: #FFFFFF; }
        }
        .blink-effect {
            animation: blink-animation 1.5s infinite step-end;
        }
    </style>
</head>
<body class="selection:bg-pink-500/40 min-h-screen flex items-center justify-center p-4 sm:p-6 relative z-0 <?= $browserClass ?> <?= $blink_class ?>">
    <div class="noise-overlay"></div>
    <div class="glow-sphere pink-1"></div>
    <div class="glow-sphere pink-2"></div>
    <div class="glow-sphere pink-3"></div>

    <!-- Call Icon Button -->
    <button onclick="openCallModal()" class="fixed top-4 right-4 sm:top-6 sm:right-6 glass p-3 sm:p-4 rounded-full hover:bg-white/10 text-white transition-all z-40 shadow-lg cursor-pointer group" title="Call">
        <svg class="w-5 h-5 sm:w-6 sm:h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
    </button>

    <div class="w-full max-w-4xl">
        <main class="w-full glass p-6 sm:p-10 rounded-[24px] sm:rounded-[28px] relative overflow-hidden">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <h2 class="text-3xl sm:text-4xl text-white font-light italic tracking-tighter">Archived Notes</h2>
                <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                    <a href="index.php" class="block sm:inline-block w-full sm:w-auto text-center glass hover:bg-white/10 text-white py-3 px-6 rounded-xl mono text-[11px] uppercase tracking-widest transition-all">← Back</a>
                    <a href="form.php" class="block sm:inline-block w-full sm:w-auto text-center glass hover:bg-white/10 text-white py-3 px-6 rounded-xl mono text-[11px] uppercase tracking-widest transition-all">How was your day? ✎</a>
                </div>
            </div>
            <div class="mb-8 p-6 glass rounded-2xl border-white/10 text-slate-300 font-sans text-[15px] leading-relaxed">
                I’ve made a little space for you on the site. You don’t ever have to feel pressured to use it, but if you ever have a thought you want to get out or just want to talk without the 'ping' of a notification, you can leave it there. I’ll keep an eye on it whenever I’m writing in my own journal. It’s just a place for us to be, even when we’re standing our ground.
            </div>
            <div class="mb-6 flex items-center justify-center gap-x-6 gap-y-2 flex-wrap mono text-xs text-slate-400 opacity-80">
                <?php foreach ($presence_data as $user): ?>
                    <?php if ($user['user_identity'] === 'MJ'): ?>
                        <?php if ($browserClass === 'is-safari' && isset($last_message_writer) && $last_message_writer !== 'MJ'): ?>
                            <div class="flex items-center gap-2.5">
                                <span class="relative flex h-2 w-2"><span class="relative inline-flex rounded-full h-2 w-2 bg-slate-500"></span></span>
                                <span>mj is here and he wants you to come back in 5 minutes he says please baby</span>
                            </div>
                        <?php else: ?>
                            <div class="flex items-center gap-2.5">
                                <?php if ($user['is_active']): ?>
                                    <span class="relative flex h-2 w-2">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                                    </span>
                                    <span>MJ is here</span>
                                    <?php if ($user['current_page'] === 'Active & Looking 👋'): ?>
                                        <span class="animate-bounce inline-block">👋</span>
                                    <?php else: ?>
                                        <span class="opacity-60">(idle)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="relative flex h-2 w-2"><span class="relative inline-flex rounded-full h-2 w-2 bg-slate-500"></span></span>
                                    <span>MJ was last seen <?= time_ago($user['last_seen']) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($user['user_identity'] === 'Kaye'): ?>
                        <div class="flex items-center gap-2.5">
                            <?php if ($user['is_active']): ?>
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-pink-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-pink-500"></span>
                                </span>
                                <span>Kaye is here</span>
                                <?php if ($user['current_page'] === 'Active & Looking 👋'): ?>
                                    <span class="animate-bounce inline-block">👋</span>
                                <?php else: ?>
                                    <span class="opacity-60">(idle)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="relative flex h-2 w-2"><span class="relative inline-flex rounded-full h-2 w-2 bg-slate-500"></span></span>
                                <span>Kaye was last seen <?= time_ago($user['last_seen']) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php if (isset($error)): ?>
                <div class='text-red-400 mb-6 p-4 glass rounded-xl border-red-500/30 font-sans text-sm'><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if (isset($success_msg)): ?>
                <div class='text-green-400 mb-6 p-4 glass rounded-xl border-green-500/30 font-sans text-sm'><?= htmlspecialchars($success_msg) ?></div>
            <?php endif; ?>
            <div class="mb-6 flex justify-end">
                <form method="GET" action="" class="flex items-center space-x-3">
                    <label for="writer-filter" class="mono text-[10px] uppercase tracking-[0.2em] text-pink-400 font-bold">Filter:</label>
                    <select id="writer-filter" name="writer" onchange="this.form.submit()" class="bg-black/40 border border-white/10 rounded-xl p-2 px-4 text-white text-sm focus:outline-none focus:border-pink-500 transition-colors appearance-none cursor-pointer">
                        <option value="" class="bg-black text-white" <?= empty($filter_writer) ? 'selected' : '' ?>>All Notes</option>
                        <option value="MJ" class="bg-black text-white" <?= $filter_writer === 'MJ' ? 'selected' : '' ?>>MJ</option>
                        <option value="Kaye" class="bg-black text-white" <?= $filter_writer === 'Kaye' ? 'selected' : '' ?>>Kaye</option>
                    </select>
                </form>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 font-sans">
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="glass p-6 rounded-2xl flex flex-col hover:bg-white/5 transition-colors border-white/5 <?= ($msg['view_count'] ?? 0) == 0 ? 'bg-white/20 border-white/50 shadow-[0_0_25px_rgba(255,255,255,0.15)]' : '' ?>">
                            <span class="text-xs text-pink-400/80 mono mb-3 block uppercase tracking-widest">
                                <?= htmlspecialchars($msg['writer'] ?? 'MJ') ?> • <?= htmlspecialchars(date('M d, Y g:i a', strtotime($msg['created_at']))) ?> • <?= htmlspecialchars($msg['view_count'] ?? 0) ?> views
                            </span>
                            <div class="flex items-start justify-between gap-3 mb-6">
                                <h3 class="text-xl text-white font-medium line-clamp-2 break-words">
                                    <?= htmlspecialchars($msg['title']) ?>
                                </h3>
                                <?php if (!empty($msg['spotify_track_id'])): ?>
                                    <svg class="w-5 h-5 text-[#f472b6] flex-shrink-0 mt-1 drop-shadow-[0_0_12px_rgba(244,114,182,0.8)]" fill="currentColor" viewBox="0 0 24 24" title="Song Attached"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
                                <?php endif; ?>
                            </div>
                            <div class="mt-auto flex space-x-3 pt-4 border-t border-white/5">
                                <a href="view.php?id=<?= $msg['id'] ?>&page=<?= $current_page ?>" class="flex-1 text-center glass hover:bg-white/10 text-white py-2.5 px-4 rounded-xl mono text-[10px] uppercase tracking-widest transition-all">View</a>
 

                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full p-8 text-center text-slate-500 italic glass rounded-2xl border-white/5">
                        No notes found in the archive.
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
    <div id="deleteModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm opacity-0 transition-opacity duration-300">
        <div class="glass p-6 sm:p-8 rounded-2xl max-w-sm w-full mx-4 transform scale-95 transition-transform duration-300 border-red-500/20">
            <h3 class="text-xl sm:text-2xl text-white mb-4 font-light italic tracking-tighter">Delete Note?</h3>
            <p class="text-slate-400 font-sans text-sm mb-8 leading-relaxed">Are you sure you want to permanently delete this note? This action cannot be undone.</p>
            <form method="POST" action="">
                <input type="hidden" name="delete_id" id="delete_id_input" value="">
                <div class="flex justify-end space-x-3 font-sans text-sm">
                    <button type="button" onclick="closeModal()" class="px-5 py-2.5 text-slate-400 hover:text-white transition-colors cursor-pointer">Cancel</button>
                    <button type="submit" class="bg-red-500/10 text-red-400 border border-red-500/30 hover:bg-red-500/20 px-5 py-2.5 rounded-xl transition-all cursor-pointer">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <div id="callModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm opacity-0 transition-opacity duration-300">
        <div class="glass p-6 sm:p-8 rounded-2xl max-w-sm w-full mx-4 transform scale-95 transition-transform duration-300 border-pink-500/20">
            <h3 class="text-xl sm:text-2xl text-white mb-4 font-light italic tracking-tighter">Coming Soon 📞</h3>
            <p class="text-slate-400 font-sans text-sm mb-8 leading-relaxed">I am cooking something here, since you are going to deact on your socials next week I figure out to add this feature just incase you miss your baby boi's voice. Stay tuned meam.</p>
            <div class="flex justify-end font-sans text-sm">
                <button type="button" onclick="closeCallModal()" class="bg-pink-500/10 text-pink-400 border border-pink-500/30 hover:bg-pink-500/20 px-6 py-2.5 rounded-xl transition-all cursor-pointer">Okay</button>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('deleteModal');
        const deleteInput = document.getElementById('delete_id_input');
        const modalContent = modal.querySelector('div.glass');
        function openModal(id) {
            deleteInput.value = id;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            // Trigger reflow for animation
            void modal.offsetWidth;
            modal.classList.remove('opacity-0');
            modalContent.classList.remove('scale-95');
        }
        function closeModal() {
            modal.classList.add('opacity-0');
            modalContent.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 300);
        }

        const callModal = document.getElementById('callModal');
        const callModalContent = callModal.querySelector('div.glass');
        
        function openCallModal() {
            callModal.classList.remove('hidden');
            callModal.classList.add('flex');
            void callModal.offsetWidth;
            callModal.classList.remove('opacity-0');
            callModalContent.classList.remove('scale-95');
        }
        
        function closeCallModal() {
            callModal.classList.add('opacity-0');
            callModalContent.classList.add('scale-95');
            setTimeout(() => {
                callModal.classList.add('hidden');
                callModal.classList.remove('flex');
            }, 300);
        }
    </script>

    <!-- Replace 'your-audio-file.mp3' with the path to your actual audio file -->
    <audio id="viewNotificationSound" src="your-audio-file.mp3" preload="auto"></audio>

    <script>
        <?php if (!empty($messages) && isset($current_page) && $current_page === 1): ?>
            const latestNoteId = <?= json_encode($messages[0]['id']) ?>;
            const latestNoteViews = <?= (int)$messages[0]['view_count'] ?>;
            const storageKey = 'latest_note_views_' + latestNoteId;
            const previousViews = localStorage.getItem(storageKey);
            
            if (previousViews !== null) {
                if (parseInt(previousViews) === 0 && latestNoteViews > 0) {
                    const audio = document.getElementById('viewNotificationSound');
                    audio.play().catch(err => console.log('Audio playback prevented by browser autoplay policy:', err));
                }
            }
            localStorage.setItem(storageKey, latestNoteViews);
        <?php endif; ?>
    </script>

    <script>
    (function() {
        let presenceInterval;

        function updatePresence(state, pageStatus) {
            const body = `update_presence_ajax=1&status_state=${encodeURIComponent(state)}&page_status=${encodeURIComponent(pageStatus)}`;

            // navigator.sendBeacon is ideal for sending data on blur/unload
            if (navigator.sendBeacon) {
                const headers = { type: 'application/x-www-form-urlencoded' };
                const blob = new Blob([body], headers);
                navigator.sendBeacon('res.php', blob);
            } else {
                // Fallback to fetch for older browsers
                fetch('res.php', { 
                    method: 'POST', 
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body,
                    keepalive: true // Important for requests during page unload
                }).catch(console.error);
            }
        }

        function startPresenceUpdates() {
            clearInterval(presenceInterval);
            updatePresence('focused', 'Active & Looking 👋');
            presenceInterval = setInterval(() => {
                if (document.hasFocus()) {
                    updatePresence('focused', 'Active & Looking 👋');
                }
            }, 15000); // 15 seconds
        }

        function stopPresenceUpdates() {
            clearInterval(presenceInterval);
            updatePresence('blurred', 'Idle / Away');
        }

        // Listen for tab focus/blur events and visibility changes
        window.addEventListener('focus', startPresenceUpdates);
        window.addEventListener('blur', stopPresenceUpdates);
        document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' ? startPresenceUpdates() : stopPresenceUpdates());

        // Initial check on page load
        document.hasFocus() ? startPresenceUpdates() : stopPresenceUpdates();
    })();
    </script>
</body>
</html>