<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/db_related/db_connect.php';

try {
    // Auto-create the visitor_logs table if it doesn't exist.
    $pdo->exec("CREATE TABLE IF NOT EXISTS visitor_logs (
        id SERIAL PRIMARY KEY,
        device VARCHAR(255),
        action VARCHAR(255),
        time_spent_seconds INT DEFAULT 0,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Ensure the time column exists if updating from the old version of the table
    try { $pdo->exec("ALTER TABLE visitor_logs ADD COLUMN IF NOT EXISTS time_spent_seconds INT DEFAULT 0"); } catch (PDOException $e) {}

    // --- PAGINATION LOGIC (adapted from res.php) ---
    $items_per_page = 8; // Show 8 logs per page

    // Get total number of items
    $total_items_stmt = $pdo->query("SELECT COUNT(*) FROM visitor_logs");
    $total_items = $total_items_stmt->fetchColumn();

    // Calculate total pages
    $total_pages = ceil($total_items / $items_per_page);

    // Get current page from URL, default to 1, and validate it
    $current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $current_page = max(1, min($current_page, $total_pages > 0 ? $total_pages : 1));

    // Calculate the offset
    $offset = ($current_page - 1) * $items_per_page;

    // Fetch logs for the current page
    $stmt = $pdo->prepare("SELECT action, device, timestamp, time_spent_seconds FROM visitor_logs ORDER BY timestamp DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $visitor_logs = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Error fetching logs: " . $e->getMessage();
    $visitor_logs = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Logs - Archive for Kaye</title>
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
<body class="selection:bg-pink-500/40 min-h-screen flex items-center justify-center p-4 sm:p-6 relative z-0">
    <div class="noise-overlay"></div>
    <div class="glow-sphere pink-1"></div>
    <div class="glow-sphere pink-2"></div>
    <div class="glow-sphere pink-3"></div>

    <div class="w-full max-w-4xl">
        <main class="w-full glass p-6 sm:p-10 rounded-[24px] sm:rounded-[28px] relative overflow-hidden">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <h2 class="text-3xl sm:text-4xl text-white font-light italic tracking-tighter">Site Monitor</h2>
                <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                    <a href="index.php" class="block sm:inline-block w-full sm:w-auto text-center glass hover:bg-white/10 text-white py-3 px-6 rounded-xl mono text-[11px] uppercase tracking-widest transition-all">← Homepage</a>
                    <a href="res.php" class="block sm:inline-block w-full sm:w-auto text-center glass hover:bg-white/10 text-white py-3 px-6 rounded-xl mono text-[11px] uppercase tracking-widest transition-all">Archive ✎</a>
                </div>
            </div>
            
            <div class="mb-8 p-6 glass rounded-2xl border-white/10 text-slate-300 font-sans text-[15px] leading-relaxed">
                <strong class="text-pink-400 font-medium">System Note:</strong> This page serves as a real-time tracker. It logs the user's action, device, and actively counts the minutes/seconds they stayed on each specific page.
            </div>

            <?php if (isset($error)): ?>
                <div class='text-red-400 mb-6 p-4 glass rounded-xl border-red-500/30 font-sans text-sm'><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 font-sans">
                <?php if (!empty($visitor_logs)): ?>
                    <?php foreach ($visitor_logs as $log): ?>
                        <?php 
                            $seconds = $log['time_spent_seconds'] ?? 0;
                            $mins = floor($seconds / 60);
                            $secs = $seconds % 60;
                            $time_string = $mins > 0 ? "{$mins}m {$secs}s" : "{$secs}s";
                        ?>
                        <div class="glass p-6 rounded-2xl flex flex-col hover:bg-white/5 transition-colors border-white/5">
                            <span class="text-xs text-pink-400/80 mono mb-3 block uppercase tracking-widest">
                                <?= htmlspecialchars(date('M d, Y g:i a', strtotime($log['timestamp']))) ?>
                            </span>
                            <div class="mb-4">
                                <h3 class="text-xl text-white font-medium line-clamp-2 break-words mb-2">
                                    <?= htmlspecialchars($log['action']) ?>
                                </h3>
                            </div>
                            
                            <div class="mt-auto space-y-2 pt-4 border-t border-white/5">
                                <div class="flex items-center text-sm text-slate-400">
                                    <span class="mono text-[10px] uppercase tracking-widest text-white/50 w-20">Time Spent:</span>
                                    <span class="text-pink-300 font-mono"><?= $time_string ?></span>
                                </div>
                                <div class="flex items-center text-sm text-slate-400">
                                    <span class="mono text-[10px] uppercase tracking-widest text-white/50 w-20">Device:</span>
                                    <span><?= htmlspecialchars($log['device']) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full p-8 text-center text-slate-500 italic glass rounded-2xl border-white/5">
                        No visitor logs recorded yet. Visit other pages to generate logs.
                    </div>
                <?php endif; ?>
            </div>
        </main>
        <?php if ($total_pages > 1): ?>
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
                        if ($current_page <= 2) {
                            $pages = [1, 2, 3, '...', $total_pages];
                        } elseif ($current_page >= $total_pages - 2) {
                            $pages = [1, '...', $total_pages - 2, $total_pages - 1, $total_pages];
                        } else {
                            $pages = [1, '...', $current_page, '...', $total_pages];
                        }
                    }
                    foreach ($pages as $p): ?>
                        <?php if ($p === '...'): ?>
                            <span class="inline-flex items-center justify-center min-w-[32px] h-8 px-2 text-slate-400 mono text-xs">...</span>
                        <?php elseif ($p === $current_page): ?>
                            <span class="inline-flex items-center justify-center min-w-[32px] h-8 px-2 rounded-lg glass bg-pink-500/20 text-pink-400 border border-pink-500/30 mono text-xs"><?= $p ?></span>
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
    </div>
</body>
</html>