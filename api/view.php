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

    <?php if ($msg && !empty($msg['tiktok_link'])): ?>
    <div class="fixed inset-0 -z-10 overflow-hidden blur-sm">
        <iframe
            src="https://www.tiktok.com/embed/v2/<?= htmlspecialchars($msg['tiktok_link']) ?>?autoplay=1&loop=1&mute=1"
            class="w-full h-full scale-[1.8] pointer-events-none opacity-40"
            allow="autoplay; encrypted-media"
            frameborder="0">
        </iframe>
    </div>
    <?php endif; ?>

    <main class="w-full max-w-2xl glass p-10 rounded-[28px] relative overflow-hidden z-10">
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
            
            <a href="res.php" class="inline-block glass hover:bg-white/5 text-white py-3 px-6 rounded-xl mono text-xs uppercase tracking-widest transition-all">← Back to Archive</a>
        <?php endif; ?>
    </main>
</body>
</html>