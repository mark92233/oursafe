<?php
require_once __DIR__ . '/db_related/db_connect.php';

$msg = null;
$error = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        // Increment view count
        $updateStmt = $pdo->prepare("UPDATE messages SET view_count = view_count + 1 WHERE id = :id");
        $updateStmt->execute(['id' => $_GET['id']]);

        // Fetch the specific message securely using a prepared statement
        $stmt = $pdo->prepare("SELECT title, message, writer, created_at, view_count, tiktok_link FROM messages WHERE id = :id");
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
        body { background-color: #030303; color: #d1d5db; font-family: 'Playfair Display', serif; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); }
    </style>
</head>
<body class="selection:bg-indigo-500/40 min-h-screen flex items-center justify-center p-6">

    <main class="w-full max-w-2xl glass p-10 rounded-[28px] relative overflow-hidden z-10">
        <?php if ($msg && !empty($msg['tiktok_link'])): ?>
        <?php 
            $isLocalVideo = strpos($msg['tiktok_link'], 'uploads/') === 0;
        ?>
        <div class="absolute inset-0 -z-10 overflow-hidden rounded-[28px]">
            <?php if ($isLocalVideo): ?>
            <!-- Seamless Local Video Background -->
            <video
                id="tiktok-player"
                src="<?= htmlspecialchars($msg['tiktok_link']) ?>"
                class="absolute w-full h-full object-cover opacity-40 blur-sm"
                autoplay loop muted playsinline>
            </video>
            <?php else: ?>
            <!-- Fallback Iframe Player -->
            <iframe
                id="tiktok-player"
                src="https://www.tiktok.com/player/v1/<?= htmlspecialchars($msg['tiktok_link']) ?>?music_info=0&description=0&autoplay=1&loop=1&muted=1"
                class="absolute w-full h-full scale-[3] opacity-40 blur-sm pointer-events-none"
                allow="autoplay; encrypted-media"
                frameborder="0">
            </iframe>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class='text-red-400 mb-6 p-4 glass rounded-xl border-red-500/30 font-sans text-sm'><?= htmlspecialchars($error) ?></div>
            <a href="res.php" class="inline-block glass hover:bg-white/5 text-white py-3 px-6 rounded-xl mono text-xs uppercase tracking-widest transition-all">← Back to Archive</a>
        <?php elseif ($msg): ?>
            <div class="mb-8">
                <span class="mono text-[10px] uppercase tracking-[0.2em] text-indigo-400 font-bold">
                    Written by <?= htmlspecialchars($msg['writer'] ?? 'MJ') ?> • <?= htmlspecialchars(date('F j, Y, g:i a', strtotime($msg['created_at']))) ?> • <?= htmlspecialchars($msg['view_count'] ?? 0) ?> views
                </span>
                <h2 class="text-4xl text-white mt-2 font-light italic tracking-tighter"><?= htmlspecialchars($msg['title']) ?></h2>
            </div>
            
            <div class="font-sans text-lg text-slate-300 leading-relaxed whitespace-pre-wrap mb-10"><?= htmlspecialchars($msg['message']) ?></div>
            
            <div class="flex justify-between items-center">
                <a href="res.php" class="inline-block glass hover:bg-white/5 text-white py-3 px-6 rounded-xl mono text-xs uppercase tracking-widest transition-all">← Back to Archive</a>
                
                <?php if (!empty($msg['tiktok_link'])): ?>
                <!-- Mute/Unmute Button -->
                <button id="mute-button" type="button" class="w-10 h-10 flex items-center justify-center rounded-full glass hover:bg-white/10 transition-colors" aria-label="Enable audio">
                    <!-- Icon will be inserted by JS -->
                </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php if ($msg && !empty($msg['tiktok_link'])): ?>
    <script>
        const player = document.getElementById('tiktok-player');
        const muteButton = document.getElementById('mute-button');

        const soundIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>`;
        const muteIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><line x1="23" y1="9" x2="17" y2="15"></line><line x1="17" y1="9" x2="23" y2="15"></line></svg>`;

        let hasSound = false;
        const initialSrc = player.src; // Store the initial muted URL
        const isLocalVideo = <?= isset($isLocalVideo) && $isLocalVideo ? 'true' : 'false' ?>;

        // Set initial state
        muteButton.innerHTML = soundIcon;

        muteButton.addEventListener('click', () => {
            hasSound = !hasSound;

            if (isLocalVideo) {
                // Local video - seamless unmute without resetting!
                player.muted = !hasSound;
                if (hasSound) {
                    muteButton.innerHTML = muteIcon;
                    muteButton.setAttribute('aria-label', 'Mute audio');
                    player.classList.remove('opacity-40', 'blur-sm');
                    player.classList.add('opacity-80');
                } else {
                    muteButton.innerHTML = soundIcon;
                    muteButton.setAttribute('aria-label', 'Enable audio');
                    player.classList.add('opacity-40', 'blur-sm');
                    player.classList.remove('opacity-80');
                }
            } else {
                // Fallback for older iframe links
                if (hasSound) {
                    let soundUrl = initialSrc.replace('&muted=1', '').replace('muted=1', '');
                    player.src = soundUrl;
                    muteButton.innerHTML = muteIcon;
                    muteButton.setAttribute('aria-label', 'Return to silent background');
                    player.classList.remove('opacity-40', 'blur-sm');
                    player.classList.add('opacity-80');
                } else {
                    player.src = initialSrc;
                    player.classList.add('opacity-40', 'blur-sm');
                    player.classList.remove('opacity-80');
                    muteButton.innerHTML = soundIcon;
                    muteButton.setAttribute('aria-label', 'Enable audio');
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>