<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db_related/db_connect.php';

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
        $stmt = $pdo->prepare("SELECT id, writer, title, created_at, view_count FROM messages $where_clause ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results - Archive for Kaye</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        body { background-color: #030303; color: #d1d5db; font-family: 'Playfair Display', serif; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); }
    </style>
</head>
<body class="selection:bg-indigo-500/40 min-h-screen flex items-center justify-center p-4 sm:p-6">
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
            <?php if (isset($error)): ?>
                <div class='text-red-400 mb-6 p-4 glass rounded-xl border-red-500/30 font-sans text-sm'><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if (isset($success_msg)): ?>
                <div class='text-green-400 mb-6 p-4 glass rounded-xl border-green-500/30 font-sans text-sm'><?= htmlspecialchars($success_msg) ?></div>
            <?php endif; ?>
            <div class="mb-6 flex justify-end">
                <form method="GET" action="" class="flex items-center space-x-3">
                    <label for="writer-filter" class="mono text-[10px] uppercase tracking-[0.2em] text-indigo-400 font-bold">Filter:</label>
                    <select id="writer-filter" name="writer" onchange="this.form.submit()" class="bg-black/40 border border-white/10 rounded-xl p-2 px-4 text-white text-sm focus:outline-none focus:border-indigo-500 transition-colors appearance-none cursor-pointer">
                        <option value="" class="bg-black text-white" <?= empty($filter_writer) ? 'selected' : '' ?>>All Notes</option>
                        <option value="MJ" class="bg-black text-white" <?= $filter_writer === 'MJ' ? 'selected' : '' ?>>MJ</option>
                        <option value="Kaye" class="bg-black text-white" <?= $filter_writer === 'Kaye' ? 'selected' : '' ?>>Kaye</option>
                    </select>
                </form>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 font-sans">
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="glass p-6 rounded-2xl flex flex-col hover:bg-white/5 transition-colors border-white/5">
                            <span class="text-xs text-indigo-400/80 mono mb-3 block uppercase tracking-widest">
                                <?= htmlspecialchars($msg['writer'] ?? 'MJ') ?> • <?= htmlspecialchars(date('M d, Y H:i', strtotime($msg['created_at']))) ?> • <?= htmlspecialchars($msg['view_count'] ?? 0) ?> views
                            </span>
                            <h3 class="text-xl text-white font-medium mb-6 line-clamp-2 break-words">
                                <?= htmlspecialchars($msg['title']) ?>
                            </h3>
                            <div class="mt-auto flex space-x-3 pt-4 border-t border-white/5">
                                <a href="view.php?id=<?= $msg['id'] ?>" class="flex-1 text-center glass hover:bg-white/10 text-white py-2.5 px-4 rounded-xl mono text-[10px] uppercase tracking-widest transition-all">View</a>
                                <button onclick="openModal(<?= $msg['id'] ?>)" class="flex-1 text-center glass hover:bg-red-500/20 hover:text-red-400 hover:border-red-500/30 text-white py-2.5 px-4 rounded-xl mono text-[10px] uppercase tracking-widest transition-all cursor-pointer">Delete</button>
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
            <?php $writer_param = !empty($filter_writer) ? '&writer=' . urlencode($filter_writer) : ''; ?>
            <div class="mt-8 flex justify-between items-center font-sans">
                <div>
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?= $current_page - 1 ?><?= $writer_param ?>" class="inline-block glass hover:bg-white/10 text-white py-2 px-5 rounded-xl mono text-xs uppercase tracking-widest transition-all">← Previous</a>
                    <?php else: ?>
                        <span class="inline-block glass bg-white/5 text-white/30 py-2 px-5 rounded-xl mono text-xs uppercase tracking-widest cursor-not-allowed">← Previous</span>
                    <?php endif; ?>
                </div>      
                <div class="text-slate-400 text-sm mono">
                    Page <?= $current_page ?> of <?= $total_pages ?>
                </div>
                <div>
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?= $current_page + 1 ?><?= $writer_param ?>" class="inline-block glass hover:bg-white/10 text-white py-2 px-5 rounded-xl mono text-xs uppercase tracking-widest transition-all">Next →</a>
                    <?php else: ?>
                        <span class="inline-block glass bg-white/5 text-white/30 py-2 px-5 rounded-xl mono text-xs uppercase tracking-widest cursor-not-allowed">Next →</span>
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
    </script>
</body>
</html>