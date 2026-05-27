<?php
require_once __DIR__ . '/db_related/db_connect.php';

// 1. Self-healing: Ensure capsule tables exist by executing the schema.
$error = null; // Initialize error variable to use throughout the script.
try {
    // The previous method of reading a .sql file can fail with PDO::exec on multi-statement scripts.
    // Executing them one by one is more reliable and robust.

    // Statement 1: Create the lock table.
    $pdo->exec("CREATE TABLE IF NOT EXISTS capsule_lock (
        id INT PRIMARY KEY DEFAULT 1,
        mj_key BOOLEAN NOT NULL DEFAULT false,
        kaye_key BOOLEAN NOT NULL DEFAULT false,
        CONSTRAINT single_row_check CHECK (id = 1)
    )");

    // Statement 2: Seed the lock table. ON CONFLICT is safe for repeated runs.
    $pdo->exec("INSERT INTO capsule_lock (id, mj_key, kaye_key) VALUES (1, false, false) ON CONFLICT (id) DO NOTHING");
    

    // Statement 3: Create the messages table.
    $pdo->exec("CREATE TABLE IF NOT EXISTS capsule_messages (
        id SERIAL PRIMARY KEY,
        writer VARCHAR(10) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        spotify_track_id VARCHAR(100) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");

} catch (PDOException $e) {
    // If table creation fails, it's a critical error. We should not proceed silently.
    $error = "Database initialization failed: " . htmlspecialchars($e->getMessage());
}

// 2. Determine current user identity based on browser agent.
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$browserClass = '';
$current_writer = 'Kaye'; // Default
if (strpos($userAgent, 'Chrome') !== false) {
    $browserClass = 'is-chrome';
    $current_writer = 'MJ';
} elseif (strpos($userAgent, 'Safari') !== false) {
    $browserClass = 'is-safari';
    $current_writer = 'Kaye';
}

// 3. AJAX Interceptor: Handle the key-turning POST request.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_unlock']) && $_POST['request_unlock'] == 1) {
    header('Content-Type: application/json');
    error_reporting(0); // Suppress warnings for clean JSON output.
    $key_column = ($current_writer === 'MJ') ? 'mj_key' : 'kaye_key';
    
    try {
        $stmt = $pdo->prepare("UPDATE capsule_lock SET $key_column = true WHERE id = 1");
        $stmt->execute();
        
        $lock_stmt = $pdo->query("SELECT mj_key, kaye_key FROM capsule_lock WHERE id = 1");
        $lock_status = $lock_stmt->fetch();
        $is_unlocked = ($lock_status && $lock_status['mj_key'] && $lock_status['kaye_key']);

        echo json_encode(['status' => 'success', 'message' => 'Key turned successfully.', 'unlocked' => $is_unlocked]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// 4. Core Display Logic: Fetch lock state and conditionally query messages.
$lock_status = null;
$capsule_key = false;
$my_messages = [];
$mj_messages = [];
$kaye_messages = [];

// Pagination variables
$items_per_page = 6; // Show 6 entries per page
$total_pages = 0;
$current_page = 1;

// Only proceed with database queries if the initialization was successful.
if ($error === null) {
    try {
        $lock_stmt = $pdo->query("SELECT mj_key, kaye_key FROM capsule_lock WHERE id = 1");
        $lock_status = $lock_stmt->fetch();
        $capsule_key = ($lock_status && $lock_status['mj_key'] && $lock_status['kaye_key']);

        if ($capsule_key) {
            // STATE: UNLOCKED. Fetch all messages and sort them into two columns.
            $stmt = $pdo->query("SELECT * FROM capsule_messages ORDER BY created_at ASC");
            while ($msg = $stmt->fetch()) {
                if ($msg['writer'] === 'MJ') $mj_messages[] = $msg;
                else $kaye_messages[] = $msg;
            }
        } else {
            // STATE: LOCKED. Fetch only the current user's messages with pagination.
            $total_items_stmt = $pdo->prepare("SELECT COUNT(*) FROM capsule_messages WHERE writer = :writer");
            $total_items_stmt->execute(['writer' => $current_writer]);
            $total_items = $total_items_stmt->fetchColumn();

            if ($total_items > 0) {
                // Calculate pagination details
                $total_pages = ceil($total_items / $items_per_page);
                $current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
                $current_page = max(1, min($current_page, $total_pages));
                $offset = ($current_page - 1) * $items_per_page;

                // Fetch paginated messages
                $stmt = $pdo->prepare("SELECT * FROM capsule_messages WHERE writer = :writer ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
                $stmt->bindValue(':writer', $current_writer);
                $stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $my_messages = $stmt->fetchAll();
            }
        }
    } catch (PDOException $e) {
        $error = "Database Error: " . htmlspecialchars($e->getMessage());
    }
}

$my_key_turned = ($lock_status && (($current_writer === 'MJ' && $lock_status['mj_key']) || ($current_writer === 'Kaye' && $lock_status['kaye_key'])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Capsule Vault</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        :root { --accent: #4ade80; }
        body { background-color: #030303; color: #d1d5db; font-family: 'Playfair Display', serif; overflow-x: hidden; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .noise-overlay { position: fixed; inset: 0; z-index: 50; pointer-events: none; opacity: 0.04; mix-blend-mode: overlay; background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E"); }
        .glow-sphere { position: fixed; border-radius: 50%; z-index: -1; filter: blur(90px); will-change: transform; }
        .green-1 { width: 600px; height: 600px; background: radial-gradient(circle, rgba(74, 222, 128, 0.3) 0%, rgba(0, 0, 0, 0) 70%); top: -10%; left: -10%; animation: float1 8s infinite alternate ease-in-out; }
        .green-2 { width: 700px; height: 700px; background: radial-gradient(circle, rgba(34, 197, 94, 0.25) 0%, rgba(0, 0, 0, 0) 70%); bottom: -20%; right: -10%; animation: float2 10s infinite alternate ease-in-out; }
        .green-3 { width: 450px; height: 450px; background: radial-gradient(circle, rgba(134, 239, 172, 0.2) 0%, rgba(0, 0, 0, 0) 70%); top: 40%; left: 30%; animation: float3 12s infinite alternate ease-in-out; }
        @keyframes float1 { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(40vw, 30vh) scale(1.3); } }
        @keyframes float2 { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(-40vw, -30vh) scale(1.4); } }
        @keyframes float3 { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(-30vw, 40vh) scale(0.8); } }
    </style>
</head>
<body class="selection:bg-green-500/40 min-h-screen p-4 sm:p-6 relative z-0 <?= $browserClass ?>">
    <div class="noise-overlay"></div>
    <div class="glow-sphere green-1"></div>
    <div class="glow-sphere green-2"></div>
    <div class="glow-sphere green-3"></div>

    <div class="w-full max-w-7xl mx-auto py-12">
        <main class="w-full glass p-6 sm:p-10 rounded-[24px] sm:rounded-[28px] relative overflow-hidden">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
                <h2 class="text-3xl sm:text-4xl text-white font-light italic tracking-tighter">The Capsule Vault</h2>
                <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                    <a href="index.php" class="block sm:inline-block w-full sm:w-auto text-center glass hover:bg-white/10 text-white py-3 px-6 rounded-xl mono text-[11px] uppercase tracking-widest transition-all">← Back to Main</a>
                    <a href="form_capsule.php" class="block sm:inline-block w-full sm:w-auto text-center glass hover:bg-green-500/20 border border-green-500/30 text-white py-3 px-6 rounded-xl mono text-[11px] uppercase tracking-widest transition-all">Seal a New Entry ✎</a>
                </div>
            </div>

            <div class="mb-8 p-6 glass rounded-2xl border-white/10 text-slate-300 font-sans text-[15px] leading-relaxed">
                Welcome to our time capsule. I was up late last night thinking about how nice it would be to have a secret space for the thoughts we aren't ready to share yet, so I built this for us. You can write down whatever is on your mind, but everything stays completely hidden in the dark. You can only see your own entries, and I can only see mine. It will only open when we both decide we are ready and mutually turn our keys. If only one of us clicks unlock, it stays beautifully sealed. Please feel no pressure at all to write here. Just let it be a quiet sanctuary for whenever you have a thought you want to park away.
            </div>

            <?php if ($error): ?>
                <div class='text-red-400 mb-6 p-4 glass rounded-xl border-red-500/30 font-sans text-sm'><?= $error ?></div>
            <?php elseif ($capsule_key): ?>
                <!-- UNLOCKED VIEW -->
                <div class="text-center mb-12">
                    <h3 class="text-2xl text-green-400 font-bold mono tracking-widest uppercase">VAULT UNSEALED</h3>
                    <p class="text-slate-400 mt-2 font-sans">Mutual consent achieved. The entries are now revealed.</p>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- MJ's Column -->
                    <div class="space-y-6">
                        <h4 class="text-2xl text-white font-light italic tracking-tighter border-b border-white/10 pb-3">MJ's Entries</h4>
                        <?php if (empty($mj_messages)): ?>
                            <div class="glass p-6 rounded-2xl text-slate-500 italic text-center font-sans">No entries were sealed by MJ.</div>
                        <?php else: foreach ($mj_messages as $msg): ?>
                            <div class="glass p-6 rounded-2xl">
                                <span class="text-xs text-green-300/80 mono mb-3 block uppercase tracking-widest"><?= htmlspecialchars(date('M d, Y g:i a', strtotime($msg['created_at']))) ?></span>
                                <h3 class="text-xl text-white font-medium mb-4"><?= htmlspecialchars($msg['title']) ?></h3>
                                <p class="font-sans text-slate-300 leading-relaxed whitespace-pre-wrap mb-6"><?= htmlspecialchars($msg['message']) ?></p>
                                <?php if (!empty($msg['spotify_track_id'])): ?>
                                    <iframe style="border-radius:12px" src="https://open.spotify.com/embed/track/<?= htmlspecialchars($msg['spotify_track_id']) ?>?utm_source=generator&theme=0" width="100%" height="152" frameBorder="0" allowfullscreen="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <!-- Kaye's Column -->
                    <div class="space-y-6">
                        <h4 class="text-2xl text-white font-light italic tracking-tighter border-b border-white/10 pb-3">Kaye's Entries</h4>
                        <?php if (empty($kaye_messages)): ?>
                            <div class="glass p-6 rounded-2xl text-slate-500 italic text-center font-sans">No entries were sealed by Kaye.</div>
                        <?php else: foreach ($kaye_messages as $msg): ?>
                            <div class="glass p-6 rounded-2xl">
                                <span class="text-xs text-green-300/80 mono mb-3 block uppercase tracking-widest"><?= htmlspecialchars(date('M d, Y g:i a', strtotime($msg['created_at']))) ?></span>
                                <h3 class="text-xl text-white font-medium mb-4"><?= htmlspecialchars($msg['title']) ?></h3>
                                <p class="font-sans text-slate-300 leading-relaxed whitespace-pre-wrap mb-6"><?= htmlspecialchars($msg['message']) ?></p>
                                <?php if (!empty($msg['spotify_track_id'])): ?>
                                    <iframe style="border-radius:12px" src="https://open.spotify.com/embed/track/<?= htmlspecialchars($msg['spotify_track_id']) ?>?utm_source=generator&theme=0" width="100%" height="152" frameBorder="0" allowfullscreen="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- LOCKED VIEW -->
                <div id="lock_status_ui" class="mb-12">
                    <?php if ($my_key_turned): ?>
                        <div class="glass rounded-2xl p-8 border-yellow-500/20">
                            <div class="flex flex-col items-center text-center">
                                <svg class="w-12 h-12 text-yellow-400 mb-4 animate-spin" style="animation-duration: 3s;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <h3 class="text-2xl text-yellow-400 font-bold mono tracking-widest uppercase">Awaiting Consent</h3>
                                <p class="text-slate-400 mt-2 font-sans max-w-md">Your key is turned. The vault will unseal when the other key is also turned.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="glass rounded-2xl p-8 border-red-500/20">
                            <div class="flex flex-col items-center text-center">
                                <svg class="w-12 h-12 text-red-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                <h3 class="text-2xl text-red-400 font-bold mono tracking-widest uppercase">Vault is Sealed</h3>
                                <p class="text-slate-400 mt-2 font-sans max-w-md mb-6">Turn your key to signal you are ready to unseal the vault. This action is irreversible.</p>
                                <button id="turn_key_btn" onclick="turnKey()" class="group glass hover:bg-green-500/20 text-white py-3 px-8 rounded-xl mono text-sm uppercase tracking-widest transition-all cursor-pointer border border-green-500/30 flex items-center gap-3">
                                    <svg class="w-5 h-5 group-hover:rotate-12 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.5 14.5l-5-5 2-2 5 5-2 2zM12 6.5a5.5 5.5 0 110 11 5.5 5.5 0 010-11z"></path></svg>
                                    <span>Turn Your Key</span>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <h4 class="text-2xl text-white font-light italic tracking-tighter border-b border-white/10 pb-3 mb-6">Your Private Sealed Entries</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 font-sans">
                    <?php if (!empty($my_messages)): ?>
                        <?php foreach ($my_messages as $msg): ?>
                            <a href="view_capsule.php?id=<?= $msg['id'] ?>&page=<?= $current_page ?>" class="glass p-6 rounded-2xl flex flex-col border-white/5 hover:bg-white/5 hover:border-white/10 transition-all duration-300 group">
                                <span class="text-xs text-green-300/80 mono mb-3 block uppercase tracking-widest"><?= htmlspecialchars(date('M d, Y', strtotime($msg['created_at']))) ?></span>
                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="text-xl text-white font-medium line-clamp-2 break-words flex-1 group-hover:text-green-300 transition-colors"><?= htmlspecialchars($msg['title']) ?></h3>
                                    <!-- View Icon -->
                                    <svg class="w-5 h-5 text-slate-500 flex-shrink-0 mt-1 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-full p-8 text-center text-slate-500 italic glass rounded-2xl border-white/5">
                            You have not sealed any entries in the capsule yet.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!$capsule_key && $total_pages > 1): ?>
                <div class="mt-8 flex justify-between items-center font-sans">
                    <div>
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?= $current_page - 1 ?>" class="inline-block glass hover:bg-white/10 text-white py-2 px-4 sm:px-5 rounded-xl mono text-xs uppercase tracking-widest transition-all"><span class="sm:hidden">←</span><span class="hidden sm:inline">← Previous</span></a>
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
                            if ($current_page <= 3) {
                                $pages = [1, 2, 3, '...', $total_pages];
                            } elseif ($current_page >= $total_pages - 2) {
                                $pages = [1, '...', $total_pages - 2, $total_pages - 1, $total_pages];
                            } else {
                                $pages = [1, '...', $current_page - 1, $current_page, $current_page + 1, '...', $total_pages];
                            }
                        }
                        foreach ($pages as $p): ?>
                            <?php if ($p === '...'): ?>
                                <span class="inline-flex items-center justify-center min-w-[32px] h-8 px-2 text-slate-400 mono text-xs">...</span>
                            <?php elseif ($p === $current_page): ?>
                                <span class="inline-flex items-center justify-center min-w-[32px] h-8 px-2 rounded-lg glass bg-green-500/20 text-green-400 border border-green-500/30 mono text-xs"><?= $p ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $p ?>" class="inline-flex items-center justify-center min-w-[32px] h-8 px-2 rounded-lg glass hover:bg-white/10 text-white mono text-xs transition-all"><?= $p ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <div>
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?>" class="inline-block glass hover:bg-white/10 text-white py-2 px-4 sm:px-5 rounded-xl mono text-xs uppercase tracking-widest transition-all"><span class="sm:hidden">→</span><span class="hidden sm:inline">Next →</span></a>
                        <?php else: ?>
                            <span class="inline-block glass bg-white/5 text-white/30 py-2 px-4 sm:px-5 rounded-xl mono text-xs uppercase tracking-widest cursor-not-allowed"><span class="sm:hidden">→</span><span class="hidden sm:inline">Next →</span></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Confirmation Modal for Turning Key -->
    <div id="turnKeyModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm opacity-0 transition-opacity duration-300">
        <div class="glass p-6 sm:p-8 rounded-2xl max-w-sm w-full mx-4 transform scale-95 transition-transform duration-300 border-yellow-500/20">
            <h3 class="text-xl sm:text-2xl text-white mb-4 font-light italic tracking-tighter">Turn Your Key?</h3>
            <p class="text-slate-400 font-sans text-sm mb-8 leading-relaxed">Are you sure you want to turn your key to unlock the vault? This action is irreversible.</p>
            <div class="flex justify-end space-x-3 font-sans text-sm">
                <button type="button" onclick="closeTurnKeyModal()" class="px-5 py-2.5 text-slate-400 hover:text-white transition-colors cursor-pointer">Cancel</button>
                <button id="confirm_turn_key_btn" type="button" class="bg-yellow-500/10 text-yellow-400 border border-yellow-500/30 hover:bg-yellow-500/20 px-5 py-2.5 rounded-xl transition-all cursor-pointer">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Generic Error Modal -->
    <div id="errorModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm opacity-0 transition-opacity duration-300">
        <div class="glass p-6 sm:p-8 rounded-2xl max-w-sm w-full mx-4 transform scale-95 transition-transform duration-300 border-red-500/20">
            <h3 class="text-xl sm:text-2xl text-white mb-4 font-light italic tracking-tighter">An Error Occurred</h3>
            <p id="error_message_p" class="text-slate-400 font-sans text-sm mb-8 leading-relaxed">Something went wrong. Please try again.</p>
            <div class="flex justify-end font-sans text-sm">
                <button type="button" onclick="closeErrorModal()" class="bg-red-500/10 text-red-400 border border-red-500/30 hover:bg-red-500/20 px-6 py-2.5 rounded-xl transition-all cursor-pointer">Okay</button>
            </div>
        </div>
    </div>

    <script>
        const turnKeyModal = document.getElementById('turnKeyModal');
        const turnKeyModalContent = turnKeyModal.querySelector('div.glass');
        const confirmTurnKeyBtn = document.getElementById('confirm_turn_key_btn');

        const errorModal = document.getElementById('errorModal');
        const errorModalContent = errorModal.querySelector('div.glass');
        const errorMessageP = document.getElementById('error_message_p');

        function openTurnKeyModal() {
            turnKeyModal.classList.remove('hidden');
            turnKeyModal.classList.add('flex');
            void turnKeyModal.offsetWidth;
            turnKeyModal.classList.remove('opacity-0');
            turnKeyModalContent.classList.remove('scale-95');
        }

        function closeTurnKeyModal() {
            turnKeyModal.classList.add('opacity-0');
            turnKeyModalContent.classList.add('scale-95');
            setTimeout(() => {
                turnKeyModal.classList.add('hidden');
                turnKeyModal.classList.remove('flex');
            }, 300);
        }

        function openErrorModal(message) {
            errorMessageP.textContent = message;
            errorModal.classList.remove('hidden');
            errorModal.classList.add('flex');
            void errorModal.offsetWidth;
            errorModal.classList.remove('opacity-0');
            errorModalContent.classList.remove('scale-95');
        }

        function closeErrorModal() {
            errorModal.classList.add('opacity-0');
            errorModalContent.classList.add('scale-95');
            setTimeout(() => {
                errorModal.classList.add('hidden');
                errorModal.classList.remove('flex');
            }, 300);
        }

        function turnKey() {
            // This function now just opens the confirmation modal.
            openTurnKeyModal();
        }

        // The logic is now handled by the button inside the modal.
        confirmTurnKeyBtn.addEventListener('click', () => {
            closeTurnKeyModal();
            
            const btn = document.getElementById('turn_key_btn');
            const lockUi = document.getElementById('lock_status_ui');
            if (!btn || !lockUi) return;

            btn.disabled = true;
            btn.textContent = 'TURNING KEY...';

            fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'request_unlock=1'
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.unlocked) {
                        lockUi.innerHTML = `<h3 class="text-2xl text-green-400 font-bold mono tracking-widest uppercase animate-pulse">MUTUAL CONSENT!</h3><p class="text-slate-400 mt-2 font-sans">Unsealing vault... Reloading page.</p>`;
                        setTimeout(() => window.location.reload(), 2000);
                    } else {
                        lockUi.innerHTML = `<h3 class="text-2xl text-yellow-400 font-bold mono tracking-widest uppercase">AWAITING CONSENT</h3><p class="text-slate-400 mt-2 font-sans">Your key is turned. The vault will unseal when the other key is also turned.</p>`;
                    }
                } else {
                    openErrorModal('An error occurred: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = 'Turn Your Key';
                }
            })
            .catch(err => {
                console.error('Fetch error:', err);
                openErrorModal('A network error occurred. Please try again.');
                btn.disabled = false;
                btn.textContent = 'Turn Your Key';
            });
        });
    </script>
</body>
</html>