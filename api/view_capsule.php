<?php
require_once __DIR__ . '/db_related/db_connect.php';

// Determine current user identity based on browser agent.
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

$msg = null;
$error = null;
$return_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        // 1. Check the global lock status
        $lock_stmt = $pdo->query("SELECT mj_key, kaye_key FROM capsule_lock WHERE id = 1");
        $lock_status = $lock_stmt->fetch();
        $capsule_key = ($lock_status && $lock_status['mj_key'] && $lock_status['kaye_key']);

        // 2. Fetch the specific message
        $stmt = $pdo->prepare("SELECT * FROM capsule_messages WHERE id = :id");
        $stmt->execute(['id' => $_GET['id']]);
        $msg = $stmt->fetch();

        if (!$msg) {
            $error = "Capsule entry not found.";
        } else {
            // 3. Enforce security rules: Allow view if vault is unlocked, or if it's locked but user is the writer.
            if (!$capsule_key && $msg['writer'] !== $current_writer) {
                $error = "Access Denied. This entry is sealed and does not belong to you. You can only view it once both parties have turned their keys.";
                $msg = null; // Unset message data to prevent display
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . htmlspecialchars($e->getMessage());
    }
} else {
    $error = "Invalid entry ID specified.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Sealed Entry - Capsule Vault</title>
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
        @keyframes float1 { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(40vw, 30vh) scale(1.3); } }
        @keyframes float2 { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(-40vw, -30vh) scale(1.4); } }
    </style>
</head>
<body class="selection:bg-green-500/40 min-h-screen flex items-center justify-center p-6 relative z-0 <?= $browserClass ?>">
    <div class="noise-overlay"></div>
    <div class="glow-sphere green-1"></div>
    <div class="glow-sphere green-2"></div>

    <main class="w-full max-w-2xl glass p-10 rounded-[28px] relative overflow-hidden z-10">

        <?php if ($error): ?>
            <div class='text-red-400 mb-6 p-4 glass rounded-xl border-red-500/30 font-sans text-sm'><?= htmlspecialchars($error) ?></div>
            <a href="capsules.php?page=<?= $return_page ?>" class="inline-block glass hover:bg-white/5 text-white py-3 px-6 rounded-xl mono text-xs uppercase tracking-widest transition-all">← Back to Vault</a>
        <?php elseif ($msg): ?>
            <div class="mb-8">
                <span class="mono text-[10px] uppercase tracking-[0.2em] text-green-400 font-bold">
                    Sealed by <?= htmlspecialchars($msg['writer']) ?> • <?= htmlspecialchars(date('F j, Y, g:i a', strtotime($msg['created_at']))) ?>
                </span>
                <h2 class="text-4xl text-white mt-2 font-light italic tracking-tighter"><?= htmlspecialchars($msg['title']) ?></h2>
            </div>
            
            <div class="font-sans text-lg text-slate-300 leading-relaxed whitespace-pre-wrap mb-10"><?= htmlspecialchars($msg['message']) ?></div>

            <?php if (!empty($msg['spotify_track_id'])): ?>
                <div class="mb-10">
                    <span class="mono text-[10px] uppercase tracking-[0.2em] text-green-400 font-bold mb-3 block">Attached Memory Track</span>
                    <iframe style="border-radius:12px" src="https://open.spotify.com/embed/track/<?= htmlspecialchars($msg['spotify_track_id']) ?>?utm_source=generator&theme=0" width="100%" height="152" frameBorder="0" allowfullscreen="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
                </div>
            <?php endif; ?>

            <div class="flex justify-between items-center">
                <a href="capsules.php?page=<?= $return_page ?>" class="inline-block glass hover:bg-white/5 text-white py-3 px-6 rounded-xl mono text-xs uppercase tracking-widest transition-all">← Back to Vault</a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>