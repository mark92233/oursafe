<?php
require_once __DIR__ . '/db_related/db_connect.php';

// --- SPOTIFY API SEARCH HANDLER ---
if (isset($_GET['search_track'])) {
    // ⚠️ REPLACE THESE WITH YOUR ACTUAL SPOTIFY API KEYS
    $client_id = 'b87977d6f5674647b3db50d8e5024792';
    $client_secret = '7786ac9bc594488b8fa33fe6cc653538';

    $query = urlencode($_GET['search_track']);
    
    // 1. Get Access Token
    $ch = curl_init('https://accounts.spotify.com/api/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . base64_encode($client_id . ':' . $client_secret), 'Content-Type: application/x-www-form-urlencoded']);
    $token_result = json_decode(curl_exec($ch), true);
    curl_close($ch);

    // 2. Search Spotify
    if (isset($token_result['access_token'])) {
        $ch = curl_init("https://api.spotify.com/v1/search?q={$query}&type=track&limit=5");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token_result['access_token']]);
        $search_result = curl_exec($ch);
        curl_close($ch);
        header('Content-Type: application/json');
        echo $search_result;
    } else {
        echo json_encode(['error' => 'Authentication failed']);
    }
    exit;
}

$feedback = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $message = $_POST['message'] ?? '';
    $writer = $_POST['writer'] ?? 'Kaye';
    $spotify_track_id = !empty($_POST['spotify_track_id']) ? $_POST['spotify_track_id'] : null;

    if (!empty($title) && !empty($message)) {
        try {
            try { $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS writer VARCHAR(50) NOT NULL DEFAULT 'Kaye'"); } catch (PDOException $e) {}
            try { $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS view_count INT NOT NULL DEFAULT 0"); } catch (PDOException $e) {}
            try { $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS spotify_track_id VARCHAR(100)"); } catch (PDOException $e) {}

            // Auto-create the table if it doesn't exist yet
            $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
                id SERIAL PRIMARY KEY,
                writer VARCHAR(50) NOT NULL DEFAULT 'Kaye',
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                view_count INT NOT NULL DEFAULT 0,
                spotify_track_id VARCHAR(100)
            )");

            // Insert the form data into the database securely
            $stmt = $pdo->prepare("INSERT INTO messages (writer, title, message, spotify_track_id) VALUES (:writer, :title, :message, :spotify_track_id)");
            $stmt->execute([
                'writer' => $writer,
                'title' => $title,
                'message' => $message,
                'spotify_track_id' => $spotify_track_id
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

    <main class="w-full max-w-lg glass p-6 sm:p-10 rounded-[24px] sm:rounded-[28px] relative overflow-hidden">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
            <h2 class="text-3xl sm:text-4xl text-white font-light italic tracking-tighter">How was your day?</h2>
            <a href="res.php" class="block sm:inline-block w-full sm:w-auto text-center glass hover:bg-white/10 text-white py-3 px-6 rounded-xl mono text-[11px] uppercase tracking-widest transition-all">← Back to Archive</a>
        </div>

        <?= $feedback ?>
        
        <form action="" method="POST" class="space-y-6 font-sans">
            <div>
                <label for="writer" class="block mono text-[10px] uppercase tracking-[0.2em] text-pink-400 mb-2 font-bold">Writer</label>
                <select id="writer" name="writer" required class="w-full bg-black/40 border border-white/10 rounded-xl p-3.5 text-white focus:outline-none focus:border-pink-500 transition-colors appearance-none cursor-pointer">
                    <option value="MJ" class="bg-black text-white">MJ</option>
                    <option value="Kaye" class="bg-black text-white" selected>Kaye</option>
                </select>
            </div>
            <div>
                <label for="title" class="block mono text-[10px] uppercase tracking-[0.2em] text-pink-400 mb-2 font-bold">Title</label>
                <input type="text" id="title" name="title" placeholder="A summary of today..." required class="w-full bg-black/40 border border-white/10 rounded-xl p-3.5 text-white focus:outline-none focus:border-pink-500 transition-colors placeholder:text-white/30">
            </div>
            <div>
                <label for="message" class="block mono text-[10px] uppercase tracking-[0.2em] text-pink-400 mb-2 font-bold">Message </label>
                <textarea id="message" name="message" rows="6" placeholder="Tell the archive about it. Highs, lows, or just thoughts you want to park here. No pings, no pressure." required class="w-full bg-black/40 border border-white/10 rounded-xl p-3.5 text-white focus:outline-none focus:border-pink-500 transition-colors resize-none placeholder:text-white/30"></textarea>
            </div>
            
            <!-- Spotify Search UI -->
            <div class="relative">
                <label for="song_search" class="block mono text-[10px] uppercase tracking-[0.2em] text-pink-400 mb-2 font-bold">Attach a Song (Optional)</label>
                <input type="text" id="song_search" placeholder="Search a track on Spotify..." class="w-full bg-black/40 border border-white/10 rounded-xl p-3.5 text-white focus:outline-none focus:border-pink-500 transition-colors placeholder:text-white/30" autocomplete="off">
                <input type="hidden" name="spotify_track_id" id="spotify_track_id">
                
                <!-- Selected Song Display -->
                <div id="selected_song" class="hidden mt-3 p-3 border border-pink-500/30 rounded-xl bg-pink-500/10 items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <img id="selected_song_img" src="" class="w-10 h-10 rounded-md object-cover">
                        <div>
                            <div id="selected_song_title" class="text-white text-sm font-semibold"></div>
                            <div id="selected_song_artist" class="text-slate-400 text-xs"></div>
                        </div>
                    </div>
                    <button type="button" onclick="clearSong()" class="text-pink-400 hover:text-white text-xs mono uppercase cursor-pointer px-2">&times; Remove</button>
                </div>

                <!-- Search Results Dropdown -->
                <div id="search_results" class="hidden absolute z-50 w-full mt-2 bg-[#121212] border border-white/10 rounded-xl shadow-2xl max-h-60 overflow-y-auto">
                    <!-- Dynamic content injected here via JS -->
                </div>
            </div>

            <button type="submit" class="w-full glass hover:bg-white/5 text-white py-4 px-6 rounded-xl mono text-xs uppercase tracking-widest transition-all cursor-pointer mt-4">Submit Entry</button>
        </form>
    </main>

    <script>
        const searchInput = document.getElementById('song_search');
        const searchResults = document.getElementById('search_results');
        const selectedSong = document.getElementById('selected_song');
        const trackIdInput = document.getElementById('spotify_track_id');
        let searchTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                searchResults.classList.add('hidden');
                return;
            }

            // Debounce request by 500ms so we don't spam the API while typing
            searchTimeout = setTimeout(() => {
                fetch(`?search_track=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        searchResults.innerHTML = '';
                        if (data.tracks && data.tracks.items.length > 0) {
                            data.tracks.items.forEach(track => {
                                const artist = track.artists.map(a => a.name).join(', ');
                                const img = track.album.images.length > 0 ? track.album.images[track.album.images.length - 1].url : '';
                                
                                const div = document.createElement('div');
                                div.className = 'p-3 flex items-center space-x-3 hover:bg-white/10 cursor-pointer transition-colors border-b border-white/5 last:border-0';
                                div.innerHTML = `<img src="${img}" class="w-10 h-10 rounded-md object-cover">
                                                 <div><div class="text-white text-sm">${track.name}</div>
                                                 <div class="text-slate-400 text-xs">${artist}</div></div>`;
                                div.onclick = () => selectSong(track.id, track.name, artist, img);
                                searchResults.appendChild(div);
                            });
                            searchResults.classList.remove('hidden');
                        } else {
                            searchResults.innerHTML = '<div class="p-4 text-slate-400 text-sm italic">No results found or missing API keys.</div>';
                            searchResults.classList.remove('hidden');
                        }
                    }).catch(() => searchResults.classList.add('hidden'));
            }, 500);
        });

        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) searchResults.classList.add('hidden');
        });

        function selectSong(id, title, artist, img) {
            trackIdInput.value = id;
            document.getElementById('selected_song_title').innerText = title;
            document.getElementById('selected_song_artist').innerText = artist;
            document.getElementById('selected_song_img').src = img;
            searchInput.classList.add('hidden');
            searchResults.classList.add('hidden');
            selectedSong.classList.remove('hidden');
            selectedSong.classList.add('flex');
        }

        function clearSong() {
            trackIdInput.value = '';
            searchInput.value = '';
            searchInput.classList.remove('hidden');
            selectedSong.classList.add('hidden');
            selectedSong.classList.remove('flex');
        }
    </script>
</body>
</html>