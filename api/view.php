<?php
require_once __DIR__ . '/db_related/db_connect.php';

// --- AJAX: Handle starring/unstarring a note ---
if (isset($_POST['toggle_star']) && isset($_POST['id'])) {
    header('Content-Type: application/json');
    error_reporting(0); // Suppress warnings for clean JSON output

    // --- User Identity Detection for AJAX ---
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $current_user_identity = null;
    if (strpos($userAgent, 'Chrome') !== false) {
        $current_user_identity = 'MJ';
    } else { // Default for Safari, Edge, Firefox, etc.
        $current_user_identity = 'Kaye';
    }

    try {
        $id = $_POST['id'];
        // Make sure we're dealing with a boolean
        $status = filter_var($_POST['is_starred'], FILTER_VALIDATE_BOOLEAN);

        if ($status) {
            // Starring: set status and who starred it
            $stmt = $pdo->prepare("UPDATE messages SET is_starred = :status, starred_by = :user_identity WHERE id = :id");
            $stmt->execute(['status' => $status, 'user_identity' => $current_user_identity, 'id' => $id]);
        } else {
            // Unstarring: clear status and who starred it
            $stmt = $pdo->prepare("UPDATE messages SET is_starred = false, starred_by = NULL WHERE id = :id");
            $stmt->execute(['id' => $id]);
        }
        echo json_encode(['status' => 'success', 'is_starred' => $status]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
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

$msg = null;
$error = null;
$return_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

// Determine where to go back to
$from = $_GET['from'] ?? 'res';
$return_path = 'res.php';
$return_text = 'Back to Archive';
if ($from === 'favorites') {
    $return_path = 'favorites.php';
    $return_text = 'Back to Favorites';
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        // Auto-patch database schema if columns are missing
        try { $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS view_count INT NOT NULL DEFAULT 0"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS spotify_track_id VARCHAR(100)"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS image_path VARCHAR(255)"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS is_starred BOOLEAN NOT NULL DEFAULT false"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS starred_by VARCHAR(50)"); } catch (PDOException $e) {}

        // Increment view count
        $updateStmt = $pdo->prepare("UPDATE messages SET view_count = view_count + 1 WHERE id = :id");
        $updateStmt->execute(['id' => $_GET['id']]);

        // Fetch the specific message securely using a prepared statement
        $stmt = $pdo->prepare("SELECT id, title, message, writer, created_at, view_count, spotify_track_id, image_path, is_starred, starred_by FROM messages WHERE id = :id");
        $stmt->execute(['id' => $_GET['id']]);
        $msg = $stmt->fetch();
        
        if (!$msg) {
            $error = "Note not found in the archive.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . htmlspecialchars($e->getMessage());
    }
} else {
    $error = "Invalid note ID specified.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Note - Archive for Kaye</title>
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
    </style>
</head>
<body class="selection:bg-pink-500/40 min-h-screen flex items-center justify-center p-6 relative z-0 <?= $browserClass ?>">
    <div class="noise-overlay"></div>
    <div class="glow-sphere pink-1"></div>
    <div class="glow-sphere pink-2"></div>
    <div class="glow-sphere pink-3"></div>

    <main class="w-full max-w-2xl glass p-10 rounded-[28px] relative overflow-hidden z-10">

        <?php if ($error): ?>
            <div class='text-red-400 mb-6 p-4 glass rounded-xl border-red-500/30 font-sans text-sm'><?= htmlspecialchars($error) ?></div>
            <a href="<?= $return_path ?>?page=<?= $return_page ?>" class="inline-block glass hover:bg-white/5 text-white py-3 px-6 rounded-xl mono text-xs uppercase tracking-widest transition-all">← <?= $return_text ?></a>
        <?php elseif ($msg): ?>
            <div class="mb-8">
                <span class="mono text-[10px] uppercase tracking-[0.2em] text-pink-400 font-bold">
                    Written by <?= htmlspecialchars($msg['writer'] ?? 'MJ') ?> • <?= htmlspecialchars(date('F j, Y, g:i a', strtotime($msg['created_at']))) ?> • <?= htmlspecialchars($msg['view_count'] ?? 0) ?> views
                </span>
                <h2 class="text-4xl text-white mt-2 font-light italic tracking-tighter"><?= htmlspecialchars($msg['title']) ?></h2>
            </div>
            
            <div class="font-sans text-lg text-slate-300 leading-relaxed whitespace-pre-wrap mb-10"><?= htmlspecialchars($msg['message']) ?></div>
            
            <?php if (!empty($msg['image_path'])): ?>
                <div class="mb-10">
                    <span class="mono text-[10px] uppercase tracking-[0.2em] text-pink-400 font-bold mb-3 block">Attached Memory Snapshot</span>
                    
                    <div class="glass p-3 rounded-[24px] inline-block cursor-pointer group hover:bg-white/10 hover:shadow-[0_8px_32px_rgba(244,114,182,0.15)] hover:-translate-y-1 transition-all duration-500 w-full sm:w-3/4 max-w-md" onclick="openImageViewer()">
                        <div class="overflow-hidden rounded-xl relative ring-1 ring-white/10">
                            <img src="<?= htmlspecialchars($msg['image_path']) ?>" alt="Memory Thumbnail" class="w-full h-56 sm:h-72 object-cover opacity-90 group-hover:opacity-100 group-hover:scale-110 transition-transform duration-700 ease-out">
                            <div class="absolute inset-0 flex flex-col items-center justify-center bg-black/50 backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-500">
                                <div class="transform translate-y-4 group-hover:translate-y-0 transition-transform duration-500 flex flex-col items-center">
                                    <svg class="w-10 h-10 text-pink-400 drop-shadow-[0_0_8px_rgba(244,114,182,0.6)] mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path>
                                    </svg>
                                    <span class="mono text-xs uppercase tracking-[0.2em] text-white font-medium">Expand</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($msg['spotify_track_id'])): ?>
                <div class="mb-10">
                    <span class="mono text-[10px] uppercase tracking-[0.2em] text-pink-400 font-bold mb-3 block">Attached Memory Track</span>
                    <iframe style="border-radius:12px" src="https://open.spotify.com/embed/track/<?= htmlspecialchars($msg['spotify_track_id']) ?>?utm_source=generator&theme=0" width="100%" height="152" frameBorder="0" allowfullscreen="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
                </div>
            <?php endif; ?>

            <div class="flex justify-between items-center">
                <a href="<?= $return_path ?>?page=<?= $return_page ?>" class="inline-block glass hover:bg-white/5 text-white py-3 px-6 rounded-xl mono text-xs uppercase tracking-widest transition-all">← <?= $return_text ?></a>
                <button id="starBtn" 
                        class="group glass hover:bg-yellow-500/20 text-white py-3 px-5 rounded-xl mono text-xs uppercase tracking-widest transition-all cursor-pointer border <?= $msg['is_starred'] ? 'border-yellow-400/50 bg-yellow-500/10 text-yellow-300' : 'border-transparent' ?>"
                        onclick="toggleStar(<?= $msg['id'] ?>, <?= $msg['is_starred'] ? 'true' : 'false' ?>)">
                    <div class="flex items-center gap-2">
                        <svg id="starIcon" class="w-4 h-4 transition-all <?= $msg['is_starred'] ? 'text-yellow-400' : 'text-white/50 group-hover:text-yellow-300' ?>" fill="<?= $msg['is_starred'] ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                        </svg>
                        <span id="starText"><?= $msg['is_starred'] ? 'Starred' : 'Star' ?></span>
                    </div>
                </button>
            </div>
        <?php endif; ?>
    </main>

    <?php if (!empty($msg) && !empty($msg['image_path'])): ?>
    <!-- Premium Image Viewer Modal (Lightbox) -->
    <div id="imageViewerModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/90 backdrop-blur-2xl opacity-0 transition-opacity duration-300" onclick="closeImageViewer()">
        <button class="absolute top-6 right-6 text-white/50 hover:text-white hover:bg-red-500/20 hover:border-red-500/50 transition-all cursor-pointer p-3 z-50 glass rounded-full hover:rotate-90 duration-300" onclick="closeImageViewer()" title="Close Viewer">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
        <div class="max-w-5xl w-full max-h-[90vh] p-4 flex justify-center transform scale-95 transition-transform duration-300" id="imageViewerContent" onclick="event.stopPropagation()">
            <div class="glass p-3 sm:p-4 rounded-3xl shadow-[0_0_40px_rgba(0,0,0,0.8)] flex flex-col items-center ring-1 ring-white/10 relative">
                <img src="<?= htmlspecialchars($msg['image_path']) ?>" alt="Memory Full Image" class="max-w-full max-h-[75vh] object-contain rounded-xl">
                <div class="w-full pt-4 flex justify-between items-center px-2 opacity-80">
                    <span class="mono text-[10px] uppercase tracking-[0.2em] text-pink-400">Captured Memory</span>
                    <span class="mono text-[10px] text-slate-400"><?= date('m.d.Y', strtotime($msg['created_at'])) ?></span>
                </div>
            </div>
        </div>
    </div>

    <script>
        const imageViewerModal = document.getElementById('imageViewerModal');
        const imageViewerContent = document.getElementById('imageViewerContent');
        function openImageViewer() {
            if (!imageViewerModal) return;
            imageViewerModal.classList.remove('hidden');
            imageViewerModal.classList.add('flex');
            void imageViewerModal.offsetWidth; 
            imageViewerModal.classList.remove('opacity-0');
            imageViewerContent.classList.remove('scale-95');
        }
        function closeImageViewer() {
            if (!imageViewerModal) return;
            imageViewerModal.classList.add('opacity-0');
            imageViewerContent.classList.add('scale-95');
            setTimeout(() => {
                imageViewerModal.classList.add('hidden');
                imageViewerModal.classList.remove('flex');
            }, 300);
        }
    </script>
    <?php endif; ?>

    <?php if ($msg): ?>
    <script>
        function toggleStar(id, isCurrentlyStarred) {
            const starBtn = document.getElementById('starBtn');
            const starIcon = document.getElementById('starIcon');
            const starText = document.getElementById('starText');
            const newStarredState = !isCurrentlyStarred;
 
            // Optimistically update UI
            starBtn.disabled = true;
            starText.textContent = newStarredState ? 'Starred' : 'Star';
            starBtn.classList.toggle('border-yellow-400/50', newStarredState);
            starBtn.classList.toggle('bg-yellow-500/10', newStarredState);
            starBtn.classList.toggle('text-yellow-300', newStarredState);
            starBtn.classList.toggle('border-transparent', !newStarredState);
            starIcon.classList.toggle('text-yellow-400', newStarredState);
            starIcon.classList.toggle('text-white/50', !newStarredState);
            starIcon.setAttribute('fill', newStarredState ? 'currentColor' : 'none');
 
            fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `toggle_star=1&id=${id}&is_starred=${newStarredState}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // Update the button's onclick for the next toggle
                    starBtn.setAttribute('onclick', `toggleStar(${id}, ${newStarredState})`);
                } else {
                    // Revert UI on failure
                    console.error("Failed to update star status:", data.message);
                    toggleStar(id, newStarredState); // This will revert the state
                }
            }).finally(() => {
                starBtn.disabled = false;
            });
        }
    </script>
    <?php endif; ?>
</body>
</html>