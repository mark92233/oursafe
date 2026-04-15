<?php
require_once __DIR__ . '/db_related/db_connect.php';

$feedback = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $message = $_POST['message'] ?? '';
    $writer = $_POST['writer'] ?? 'Kaye';

    if (!empty($title) && !empty($message)) {
        try {
            try { $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS writer VARCHAR(50) NOT NULL DEFAULT 'Kaye'"); } catch (PDOException $e) {}
            try { $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS view_count INT NOT NULL DEFAULT 0"); } catch (PDOException $e) {}

            // Auto-create the table if it doesn't exist yet
            $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
                id SERIAL PRIMARY KEY,
                writer VARCHAR(50) NOT NULL DEFAULT 'Kaye',
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                view_count INT NOT NULL DEFAULT 0
            )");

            // Insert the form data into the database securely
            $stmt = $pdo->prepare("INSERT INTO messages (writer, title, message) VALUES (:writer, :title, :message)");
            $stmt->execute([
                'writer' => $writer,
                'title' => $title,
                'message' => $message
            ]);
            
            header("Location: res.php");
            exit;
        } catch (PDOException $e) {
            $feedback = "<div class='text-red-400 mb-6 p-4 glass rounded-xl border-red-500/30 font-sans text-sm'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $feedback = "<div class='text-yellow-400 mb-6 p-4 glass rounded-xl border-yellow-500/30 font-sans text-sm'>Please fill in both fields.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Entry - Archive for Kaye</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        body { background-color: #030303; color: #d1d5db; font-family: 'Playfair Display', serif; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); }
    </style>
</head>
<body class="selection:bg-indigo-500/40 min-h-screen flex items-center justify-center p-4 sm:p-6">
    <main class="w-full max-w-lg glass p-6 sm:p-10 rounded-[24px] sm:rounded-[28px] relative overflow-hidden">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
            <h2 class="text-3xl sm:text-4xl text-white font-light italic tracking-tighter">How was your day?</h2>
            <a href="res.php" class="block sm:inline-block w-full sm:w-auto text-center glass hover:bg-white/10 text-white py-3 px-6 rounded-xl mono text-[11px] uppercase tracking-widest transition-all">← Back to Archive</a>
        </div>

        <?= $feedback ?>
        
        <form action="" method="POST" class="space-y-6 font-sans">
            <div>
                <label for="writer" class="block mono text-[10px] uppercase tracking-[0.2em] text-indigo-400 mb-2 font-bold">Writer</label>
                <select id="writer" name="writer" required class="w-full bg-black/40 border border-white/10 rounded-xl p-3.5 text-white focus:outline-none focus:border-indigo-500 transition-colors appearance-none cursor-pointer">
                    <option value="MJ" class="bg-black text-white">MJ</option>
                    <option value="Kaye" class="bg-black text-white" selected>Kaye</option>
                </select>
            </div>
            <div>
                <label for="title" class="block mono text-[10px] uppercase tracking-[0.2em] text-indigo-400 mb-2 font-bold">Title</label>
                <input type="text" id="title" name="title" placeholder="A summary of today..." required class="w-full bg-black/40 border border-white/10 rounded-xl p-3.5 text-white focus:outline-none focus:border-indigo-500 transition-colors placeholder:text-white/30">
            </div>
            <div>
                <label for="message" class="block mono text-[10px] uppercase tracking-[0.2em] text-indigo-400 mb-2 font-bold">Message </label>
                <textarea id="message" name="message" rows="6" placeholder="Tell the archive about it. Highs, lows, or just thoughts you want to park here. No pings, no pressure." required class="w-full bg-black/40 border border-white/10 rounded-xl p-3.5 text-white focus:outline-none focus:border-indigo-500 transition-colors resize-none placeholder:text-white/30"></textarea>
            </div>
            <button type="submit" class="w-full glass hover:bg-white/5 text-white py-4 px-6 rounded-xl mono text-xs uppercase tracking-widest transition-all cursor-pointer mt-4">Submit Entry</button>
        </form>
    </main>
</body>
</html>