<?php
require_once __DIR__ . '/db_related/db_connect.php';

// Detect browser to apply specific classes and determine writer
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$browserClass = '';
$current_writer = 'Kaye'; // Default writer
if (strpos($userAgent, 'Edg') !== false) {
    // It's Edge, do nothing for now.
} elseif (strpos($userAgent, 'Chrome') !== false) {
    $browserClass = 'is-chrome';
    $current_writer = 'MJ';
} elseif (strpos($userAgent, 'Safari') !== false) {
    $browserClass = 'is-safari';
    $current_writer = 'Kaye';
}

// --- SPOTIFY API SEARCH HANDLER ---
$is_search = false;
$search_query = '';
if (isset($_POST['search_track'])) {
    $search_query = $_POST['search_track'];
    $is_search = true;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_body = file_get_contents('php://input');
    parse_str($raw_body, $parsed);
    if (isset($parsed['search_track'])) {
        $search_query = $parsed['search_track'];
        $is_search = true;
    }
}

if ($is_search) {
    error_reporting(0);
    $client_id = 'b87977d6f5674647b3db50d8e5024792';
    $client_secret = '7786ac9bc594488b8fa33fe6cc653538';
    $query = urlencode($search_query);
    
    $ch = curl_init('https://accounts.spotify.com/api/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . base64_encode($client_id . ':' . $client_secret), 'Content-Type: application/x-www-form-urlencoded']);
    $token_result = json_decode(curl_exec($ch), true);

    if (isset($token_result['access_token'])) {
        $ch = curl_init("https://api.spotify.com/v1/search?q={$query}&type=track&limit=5");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token_result['access_token']]);
        $search_result = curl_exec($ch);
        header('Content-Type: application/json');
        echo $search_result;
    } else {
        echo json_encode(['error' => 'Authentication failed']);
    }
    exit;
}

$feedback = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_search) {
    $title = $_POST['title'] ?? '';
    $message = $_POST['message'] ?? '';
    $writer = $current_writer; // Automatically determined
    $spotify_track_id = !empty($_POST['spotify_track_id']) ? $_POST['spotify_track_id'] : null;

    if (!empty($title) && !empty($message)) {
        try {
            // The table creation is handled in capsules.php, but this is a safe fallback.
            $pdo->exec("CREATE TABLE IF NOT EXISTS capsule_messages (
                id SERIAL PRIMARY KEY,
                writer VARCHAR(10) NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                spotify_track_id VARCHAR(100) NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");

            $stmt = $pdo->prepare("INSERT INTO capsule_messages (writer, title, message, spotify_track_id) VALUES (:writer, :title, :message, :spotify_track_id)");
            $stmt->execute([
                'writer' => $writer,
                'title' => $title,
                'message' => $message,
                'spotify_track_id' => $spotify_track_id
            ]);
            
            header("Location: capsules.php");
            exit;
        } catch (PDOException $e) {
            $feedback = "<div class='text-red-400 mb-6 p-4 glass rounded-xl border-red-500/30 font-sans text-sm'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } elseif (empty($feedback)) {
        $feedback = "<div class='text-yellow-400 mb-6 p-4 glass rounded-xl border-yellow-500/30 font-sans text-sm'>Please fill in both title and message fields.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seal an Entry - Capsule Vault</title>
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
<body class="selection:bg-green-500/40 min-h-screen flex items-center justify-center p-4 sm:p-6 relative z-0 <?= $browserClass ?>">
    <div class="noise-overlay"></div>
    <div class="glow-sphere green-1"></div>
    <div class="glow-sphere green-2"></div>
    <div class="glow-sphere green-3"></div>

    <main class="w-full max-w-lg glass p-6 sm:p-10 rounded-[24px] sm:rounded-[28px] relative overflow-hidden">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
            <h2 class="text-3xl sm:text-4xl text-white font-light italic tracking-tighter">Seal an Entry</h2>
            <a href="capsules.php" class="block sm:inline-block w-full sm:w-auto text-center glass hover:bg-white/10 text-white py-3 px-6 rounded-xl mono text-[11px] uppercase tracking-widest transition-all">← Back to Vault</a>
        </div>

        <?= $feedback ?>
        
        <form action="" method="POST" class="space-y-6 font-sans">
            <div>
                <label for="title" class="block mono text-[10px] uppercase tracking-[0.2em] text-green-300 mb-2 font-bold">Title</label>
                <input type="text" id="title" name="title" placeholder="A title for this memory..." required class="w-full bg-black/40 border border-white/10 rounded-xl p-3.5 text-white focus:outline-none focus:border-green-500 transition-colors placeholder:text-white/30">
            </div>
            <div>
                <label for="message" class="block mono text-[10px] uppercase tracking-[0.2em] text-green-300 mb-2 font-bold">Message</label>
                <textarea id="message" name="message" rows="6" placeholder="Your private message, sealed until you both agree to unlock." required class="w-full bg-black/40 border border-white/10 rounded-xl p-3.5 text-white focus:outline-none focus:border-green-500 transition-colors resize-none placeholder:text-white/30"></textarea>
            </div>
            
            <!-- Spotify Search UI -->
            <div class="relative">
                <label for="song_search" class="block mono text-[10px] uppercase tracking-[0.2em] text-green-300 mb-2 font-bold">Attach a Song (Optional)</label>
                <input type="text" id="song_search" placeholder="Search a track on Spotify..." class="w-full bg-black/40 border border-white/10 rounded-xl p-3.5 text-white focus:outline-none focus:border-green-500 transition-colors placeholder:text-white/30" autocomplete="off">
                <input type="hidden" name="spotify_track_id" id="spotify_track_id">
                
                <div id="selected_song" class="hidden mt-3 p-3 border border-green-500/30 rounded-xl bg-green-500/10 items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <img id="selected_song_img" src="" class="w-10 h-10 rounded-md object-cover">
                        <div>
                            <div id="selected_song_title" class="text-white text-sm font-semibold"></div>
                            <div id="selected_song_artist" class="text-slate-400 text-xs"></div>
                        </div>
                    </div>
                    <button type="button" onclick="clearSong()" class="text-green-300 hover:text-white text-xs mono uppercase cursor-pointer px-2">&times; Remove</button>
                </div>

                <div id="search_results" class="hidden absolute z-50 w-full mt-2 bg-[#121212] border border-white/10 rounded-xl shadow-2xl max-h-60 overflow-y-auto"></div>
            </div>

            <button type="submit" class="w-full glass hover:bg-green-500/20 text-white py-4 px-6 rounded-xl mono text-xs uppercase tracking-widest transition-all cursor-pointer mt-4 border border-green-500/30">Seal Entry</button>
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
            if (query.length < 2) { searchResults.classList.add('hidden'); return; }

            searchTimeout = setTimeout(() => {
                fetch(window.location.pathname, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'search_track=' + encodeURIComponent(query)
                })
                .then(res => res.json())
                .then(data => {
                    searchResults.innerHTML = '';
                    if (data.tracks && data.tracks.items.length > 0) {
                        data.tracks.items.forEach(track => {
                            const artist = track.artists.map(a => a.name).join(', ');
                            const img = track.album.images.length > 0 ? track.album.images[track.album.images.length - 1].url : '';
                            const div = document.createElement('div');
                            div.className = 'p-3 flex items-center space-x-3 hover:bg-white/10 cursor-pointer transition-colors border-b border-white/5 last:border-0';
                            div.innerHTML = `<img src="${img}" class="w-10 h-10 rounded-md object-cover"><div class="overflow-hidden"><div class="text-white text-sm truncate">${track.name}</div><div class="text-slate-400 text-xs truncate">${artist}</div></div>`;
                            div.onclick = () => selectSong(track.id, track.name, artist, img);
                            searchResults.appendChild(div);
                        });
                        searchResults.classList.remove('hidden');
                    } else {
                        searchResults.innerHTML = '<div class="p-4 text-slate-400 text-sm italic">No results found.</div>';
                        searchResults.classList.remove('hidden');
                    }
                }).catch(err => {
                    console.error('Search error:', err);
                    searchResults.innerHTML = '<div class="p-4 text-red-400 text-sm italic">Failed to search. Check console.</div>';
                    searchResults.classList.remove('hidden');
                });
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