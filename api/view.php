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
        $stmt = $pdo->prepare("SELECT title, message, writer, created_at, view_count, media_url, media_type FROM messages WHERE id = :id");
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
        
        <?php if (!empty($msg['media_url'])): ?>
        <div class="absolute inset-0 -z-10 overflow-hidden rounded-[28px]">
            <?php if ($msg['media_type'] === 'video'): ?>
                <video id="bg-media" src="<?= htmlspecialchars($msg['media_url']) ?>" class="absolute w-full h-full object-cover opacity-40 blur-sm transition-all duration-500" autoplay loop muted playsinline></video>
            <?php else: ?>
                <img src="<?= htmlspecialchars($msg['media_url']) ?>" class="absolute w-full h-full object-cover opacity-40 blur-sm" alt="Background Media">
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
                
                <?php if (!empty($msg['media_url']) && $msg['media_type'] === 'video'): ?>
                <!-- Play/Pause and Mute Controls -->
                <div class="flex items-center gap-2">
                    <button id="play-button" type="button" class="w-10 h-10 flex items-center justify-center rounded-full glass hover:bg-white/10 transition-colors shadow-lg" aria-label="Play/Pause">
                    </button>
                    <button id="mute-button" type="button" class="w-10 h-10 flex items-center justify-center rounded-full glass hover:bg-white/10 transition-colors shadow-lg" aria-label="Toggle audio">
                    </button>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php if (!empty($msg['media_url']) && $msg['media_type'] === 'video'): ?>
    <script>
        const media = document.getElementById('bg-media');
        const playBtn = document.getElementById('play-button');
        const muteBtn = document.getElementById('mute-button');

        if (media && playBtn && muteBtn) {
            const playIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>`;
            const pauseIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"></rect><rect x="14" y="4" width="4" height="16"></rect></svg>`;
            const soundIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>`;
            const muteIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><line x1="23" y1="9" x2="17" y2="15"></line><line x1="17" y1="9" x2="23" y2="15"></line></svg>`;

            playBtn.innerHTML = pauseIcon;
            muteBtn.innerHTML = soundIcon;

            playBtn.addEventListener('click', () => {
                if (media.paused) {
                    media.play();
                    playBtn.innerHTML = pauseIcon;
                } else {
                    media.pause();
                    playBtn.innerHTML = playIcon;
                }
            });

            muteBtn.addEventListener('click', () => {
                media.muted = !media.muted;
                muteBtn.innerHTML = media.muted ? soundIcon : muteIcon;
                media.classList.toggle('opacity-40');
                media.classList.toggle('opacity-80');
                media.classList.toggle('blur-sm');
            });
        }
    </script>
    <?php endif; ?>
</body>
</html>