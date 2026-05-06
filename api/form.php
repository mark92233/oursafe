<?php
require_once __DIR__ . '/db_related/db_connect.php';

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
    // Suppress PHP warnings from breaking the JSON response
    error_reporting(0);

    // ⚠️ REPLACE THESE WITH YOUR ACTUAL SPOTIFY API KEYS
    $client_id = 'b87977d6f5674647b3db50d8e5024792';
    $client_secret = '7786ac9bc594488b8fa33fe6cc653538';

    $query = urlencode($search_query);
    
    // 1. Get Access Token
    $ch = curl_init('https://accounts.spotify.com/api/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . base64_encode($client_id . ':' . $client_secret), 'Content-Type: application/x-www-form-urlencoded']);
    $token_result = json_decode(curl_exec($ch), true);

    // 2. Search Spotify
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

// Catch "Payload Too Large" error where PHP drops $_POST and $_FILES due to post_max_size limit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0 && !$is_search) {
    $max_size = ini_get('post_max_size');
    $feedback = "<div class='text-red-400 mb-6 p-4 glass rounded-xl border-red-500/30 font-sans text-sm'>
        Error: The payload is too large. Server limit is {$max_size}. Please try a smaller image.
    </div>";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_search) {
    $title = $_POST['title'] ?? '';
    $message = $_POST['message'] ?? '';
    $writer = $_POST['writer'] ?? 'Kaye';
    $spotify_track_id = !empty($_POST['spotify_track_id']) ? $_POST['spotify_track_id'] : null;

    $image_path = null;
    $upload_ok = true;

    // Handle image upload logic
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_extension, $allowed_exts)) {
            $new_filename = uniqid('mem_') . '.' . $file_extension;
            
            // --- SUPABASE CONFIGURATION ---
            // ⚠️ CRITICAL: The "JWS Protected Header is invalid" error means the key below is incorrect or malformed.
            // For better security, consider using environment variables instead of hardcoding keys.
            // 1. Go to your Supabase Dashboard -> Project Settings -> API.
            // 2. Find the "Project API keys" section.
            // 3. Copy the ENTIRE key from the "service_role" (secret) field.
            // 4. Paste it here, replacing the placeholder.
            $supabase_url = 'https://jvqeqliakfulibnszgdj.supabase.co'; // Deduced from your db_connect
            $supabase_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imp2cWVxbGlha2Z1bGlibnN6Z2RqIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3NjE2MDc3MywiZXhwIjoyMDkxNzM2NzczfQ.amczCBuX11sjF6XitSU0OxM9hSl00Y5FcA9cEnDMG50'; // 👈 PASTE YOUR REAL KEY HERE
            $bucket_name = 'archive_images';
            
            $file_tmp_path = $_FILES['image']['tmp_name'];
            $file_content = file_get_contents($file_tmp_path);
            $file_mime = mime_content_type($file_tmp_path) ?: 'image/jpeg';
            
            $ch = curl_init("$supabase_url/storage/v1/object/$bucket_name/$new_filename");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $file_content);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $supabase_key",
                "Content-Type: $file_mime"
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($http_code === 200 || $http_code === 201) {
                // Store the absolute public URL directly in the database
                $image_path = "$supabase_url/storage/v1/object/public/$bucket_name/$new_filename";
            } else {
                $upload_ok = false;
                $error_msg = json_decode($response, true)['message'] ?? 'Unknown error';
                $feedback = "<div class='text-red-400 mb-6 p-4 glass rounded-xl border-red-500/30 font-sans text-sm'>Supabase Upload Error: " . htmlspecialchars($error_msg) . "</div>";
            }
        } else {
            $upload_ok = false;
            $feedback = "<div class='text-red-400 mb-6 p-4 glass rounded-xl border-red-500/30 font-sans text-sm'>Error: Invalid image format. Allowed: JPG, JPEG, PNG, GIF, WEBP.</div>";
        }
    }

    if (!empty($title) && !empty($message) && $upload_ok) {
        try {
            try { $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS writer VARCHAR(50) NOT NULL DEFAULT 'Kaye'"); } catch (PDOException $e) {}
            try { $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS view_count INT NOT NULL DEFAULT 0"); } catch (PDOException $e) {}
            try { $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS spotify_track_id VARCHAR(100)"); } catch (PDOException $e) {}
            try { $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS image_path VARCHAR(255)"); } catch (PDOException $e) {}

            // Auto-create the table if it doesn't exist yet
            $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
                id SERIAL PRIMARY KEY,
                writer VARCHAR(50) NOT NULL DEFAULT 'Kaye',
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                view_count INT NOT NULL DEFAULT 0,
                spotify_track_id VARCHAR(100),
                image_path VARCHAR(255)
            )");

            // Insert the form data into the database securely
            $stmt = $pdo->prepare("INSERT INTO messages (writer, title, message, spotify_track_id, image_path) VALUES (:writer, :title, :message, :spotify_track_id, :image_path)");
            $stmt->execute([
                'writer' => $writer,
                'title' => $title,
                'message' => $message,
                'spotify_track_id' => $spotify_track_id,
                'image_path' => $image_path
            ]);
            
            header("Location: res.php");
            exit;
        } catch (PDOException $e) {
            $feedback = "<div class='text-red-400 mb-6 p-4 glass rounded-xl border-red-500/30 font-sans text-sm'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } elseif (empty($feedback)) {
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
        
        <form action="" method="POST" enctype="multipart/form-data" class="space-y-6 font-sans">
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

            <!-- Image Upload UI -->
            <div>
                <label class="block mono text-[10px] uppercase tracking-[0.2em] text-pink-400 mb-2 font-bold">Attach an Image (Optional)</label>
                <div class="relative w-full glass border border-white/10 hover:border-pink-500/50 transition-colors rounded-xl p-4 flex flex-col items-center justify-center cursor-pointer group" onclick="document.getElementById('image_upload').click()">
                    <input type="file" id="image_upload" name="image" accept="image/*" class="hidden" onchange="previewImage(event)">
                    <div id="upload_placeholder" class="flex flex-col items-center pointer-events-none transition-opacity duration-300">
                        <svg class="w-8 h-8 text-white/30 group-hover:text-pink-400 transition-colors mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span class="text-white/50 text-sm font-sans group-hover:text-white transition-colors">Click to upload image</span>
                    </div>
                    <div id="image_preview_container" class="hidden w-full relative">
                        <img id="image_preview" src="" alt="Preview" class="w-full h-48 sm:h-64 object-contain rounded-lg">
                        <button type="button" onclick="removeImage(event)" class="absolute top-2 right-2 bg-black/60 text-white rounded-full p-2 hover:bg-red-500/80 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
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
                fetch(window.location.pathname, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'search_track=' + encodeURIComponent(query)
                })
                    .then(async res => {
                        const text = await res.text();
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Raw response from server:', text);
                            throw new Error('Server returned HTML instead of JSON. See console for details.');
                        }
                    })
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

        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                if (!file.type.startsWith('image/')) {
                    alert('Please select a valid image file.');
                    return;
                }

                // Show loading state while compressing
                const placeholder = document.getElementById('upload_placeholder');
                const originalHtml = placeholder.innerHTML;
                placeholder.innerHTML = '<span class="text-pink-400 text-sm font-sans animate-pulse">Compressing image...</span>';

                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = new Image();
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
                        const MAX_WIDTH = 1200; // Resize to a max reasonable width/height
                        const MAX_HEIGHT = 1200;
                        let width = img.width;
                        let height = img.height;

                        if (width > height && width > MAX_WIDTH) {
                            height *= MAX_WIDTH / width;
                            width = MAX_WIDTH;
                        } else if (height > MAX_HEIGHT) {
                            width *= MAX_HEIGHT / height;
                            height = MAX_HEIGHT;
                        }

                        canvas.width = width;
                        canvas.height = height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, width, height);

                        // Compress to JPEG at 80% quality
                        canvas.toBlob(function(blob) {
                            let fileName = file.name.replace(/\.[^/.]+$/, "") + ".jpg";
                            const newFile = new File([blob], fileName, { type: 'image/jpeg', lastModified: Date.now() });
                            
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(newFile);
                            document.getElementById('image_upload').files = dataTransfer.files;
                            
                            document.getElementById('image_preview').src = URL.createObjectURL(blob);
                            document.getElementById('image_preview_container').classList.remove('hidden');
                            placeholder.classList.add('hidden');
                            placeholder.innerHTML = originalHtml; // Restore placeholder for later
                        }, 'image/jpeg', 0.8);
                    };
                    img.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        }
        function removeImage(event) {
            event.stopPropagation();
            document.getElementById('image_upload').value = '';
            document.getElementById('image_preview').src = '';
            document.getElementById('image_preview_container').classList.add('hidden');
            document.getElementById('upload_placeholder').classList.remove('hidden');
        }
    </script>
</body>
</html>