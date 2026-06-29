<?php
require_once __DIR__ . '/db_related/db_connect.php';

// --- AJAX HANDLER FOR MEDS ---
if (isset($_POST['meds_taken']) && $_POST['meds_taken'] === 'yes') {
    header('Content-Type: application/json');
    error_reporting(0);
    try {
        // Create table if not exists, with a single-row constraint
        $pdo->exec("CREATE TABLE IF NOT EXISTS meds (
            id INT PRIMARY KEY DEFAULT 1,
            status BOOLEAN NOT NULL DEFAULT false,
            CONSTRAINT single_row_check CHECK (id = 1)
        )");
        // Seed the table with its one and only row if it's empty
        $pdo->exec("INSERT INTO meds (id, status) VALUES (1, false) ON CONFLICT (id) DO NOTHING");

        // Update status to true
        $stmt = $pdo->prepare("UPDATE meds SET status = true WHERE id = 1");
        $stmt->execute();

        echo json_encode(['status' => 'success', 'message' => 'Status updated.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Detect browser to apply specific classes
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$browserClass = '';
$current_user_identity = null;

if (strpos($userAgent, 'Chrome') !== false) {
    $current_user_identity = 'MJ';
    $browserClass = 'is-chrome';
} elseif (strpos($userAgent, 'Safari') !== false) {
    $current_user_identity = 'Kaye';
    $browserClass = 'is-safari';
} else {
    // Default for unknown browsers like Edge, Firefox, etc.
    $current_user_identity = 'Kaye';
    $browserClass = 'is-safari'; // Also apply safari class for consistent styling if needed
}

// --- MEDS MODAL LOGIC ---
$show_meds_modal = false;
if ($current_user_identity === 'Kaye') {
    try {
        // Ensure table exists before querying. This is self-healing.
        $pdo->exec("CREATE TABLE IF NOT EXISTS meds (id INT PRIMARY KEY DEFAULT 1, status BOOLEAN NOT NULL DEFAULT false, CONSTRAINT single_row_check CHECK (id = 1))");
        $pdo->exec("INSERT INTO meds (id, status) VALUES (1, false) ON CONFLICT (id) DO NOTHING");

        $stmt = $pdo->query("SELECT status FROM meds WHERE id = 1");
        $meds_status = $stmt->fetch();
        if ($meds_status && $meds_status['status'] === false) {
            $show_meds_modal = true;
        }
    } catch (PDOException $e) {
        // Fail silently, don't show modal if DB error occurs.
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive for Kaye</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        :root { --accent: #f472b6; }
        body { background-color: #030303; color: #d1d5db; font-family: 'Playfair Display', serif; overflow-x: hidden; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 28px; }
        .fade-in { opacity: 0; transform: translateY(30px); transition: all 1.2s cubic-bezier(0.22, 1, 0.36, 1); }
        .fade-in.visible { opacity: 1; transform: translateY(0); }

        /* Premium Enhancements */
        .noise-overlay { position: fixed; inset: 0; z-index: 50; pointer-events: none; opacity: 0.04; mix-blend-mode: overlay; background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E"); }
        .text-glow { text-shadow: 0 0 24px rgba(244, 114, 182, 0.5); }
        
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
<body class="selection:bg-pink-500/40 <?= $browserClass ?>">

    <!-- Cinematic Film Grain -->
    <div class="noise-overlay"></div>

    <!-- Moving Pink Background -->
    <div class="glow-sphere pink-1"></div>
    <div class="glow-sphere pink-2"></div>
    <div class="glow-sphere pink-3"></div>

    <main class="max-w-xl mx-auto px-8 py-32 relative">
        
        <header class="mb-16 fade-in">
            <h1 class="text-6xl text-transparent bg-clip-text bg-gradient-to-br from-white to-pink-200 mt-4 font-light italic tracking-tighter text-glow">Kaye.</h1>
            <p class="mono text-[9px] mt-6 opacity-40 uppercase tracking-[0.3em] leading-loose text-pink-300">
                Subject: The All-or-Nothing Risk <br>
                Location Context: Zamboanga → Cebu
            </p>
        </header>

        <div class="mb-24 text-center fade-in flex flex-wrap justify-center gap-4">
            <a href="res.php" class="inline-block glass hover:bg-pink-500/10 hover:border-pink-500/40 hover:shadow-[0_0_20px_rgba(244,114,182,0.15)] text-white py-4 px-10 rounded-xl mono text-sm uppercase tracking-widest transition-all duration-500 cursor-pointer group">How was your day? <span class="group-hover:translate-x-1 inline-block transition-transform duration-300">&rarr;</span></a>
            <a href="capsules.php" class="inline-block glass hover:bg-green-500/10 hover:border-green-500/40 hover:shadow-[0_0_20px_rgba(34,197,94,0.2)] text-white py-4 px-10 rounded-xl mono text-sm uppercase tracking-widest transition-all duration-500 cursor-pointer group">The Capsule Vault <span class="group-hover:translate-x-1 inline-block transition-transform duration-300">&rarr;</span></a>
            <a href="favorites.php" class="w-full md:w-auto inline-block glass hover:bg-yellow-500/10 hover:border-yellow-500/40 hover:shadow-[0_0_20px_rgba(234,179,8,0.2)] text-white py-4 px-10 rounded-xl mono text-sm uppercase tracking-widest transition-all duration-500 cursor-pointer group">Favorites <span class="group-hover:translate-x-1 inline-block transition-transform duration-300">&rarr;</span></a>
        </div>

        <section class="space-y-16 text-xl md:text-2xl leading-relaxed serif fade-in">
            <p>
                I know you told me not to get attached. I tried to follow the rules, but <span class="text-white italic">wala man koy mabuhat</span>.  This is how I am feeling and this is genuine.
            </p>
            <p>
            Last time you asked me "what's with the I LIKE U notes" and now I think I really do mean it and I would not "chicken out" just like you said, yeah I'm really sorry for the stupid things I said but I do really like you.
        </p>
        <p>
                Like I told you, if I like someone, it’s all or nothing. So here I am, risking it all, hoping I don’t get nothing. Because a connection like this doesn't happen twice.
                I would not bother to create this if I am not that down bad for you nah. (pinatono nimo) 
            </p>
            <p>
                When I said you reminded me of someone, I was wrong. I was looking for a way to explain why I'm so drawn to you, but you are a <span class="text-white">New Chapter</span> entirely.
            </p>
        </section>

        <div class="my-24 glass p-10 fade-in relative overflow-hidden hover:border-pink-500/20 hover:shadow-[0_0_30px_rgba(236,72,153,0.03)] transition-all duration-700">
            <div class="absolute top-0 right-0 p-4 mono text-[8px] opacity-10 rotate-90 tracking-widest">COMPILED_LOGS</div>
            <h3 class="mono text-[10px] uppercase tracking-[0.4em] text-pink-400 mb-12 font-bold italic">The specific details I value:</h3>
            
            <ul class="space-y-12 font-sans text-base">
                <li>
                    <span class="text-white font-semibold block text-lg mb-1 italic">The CNU Scholar</span>
                    <span class="text-slate-400 leading-relaxed">I admire how dedicated you are to your studies and your history. It’s that drive, the way you crave the 'productive pain' after a hike, that makes you who you are.</span>
                </li>
                <li>
                    <span class="text-white font-semibold block text-lg mb-1 italic">The "Mingaw ka nako?" Tease</span>
                    <span class="text-slate-400 leading-relaxed">No one else sends those texts. It’s my favorite glitch in my daily routine. And despite what I said on the call, the answer is always yes.</span>
                </li>
                <li>
                    <span class="text-white font-semibold block text-lg mb-1 italic">The Sophia's Promise</span>
                    <span class="text-slate-400 leading-relaxed">I haven’t forgotten. Once I land in Cebu, the first stop is <b>Sophia’s at Colon</b> near your school. I'm not looking at the past, Kaye, I’m looking at the pastry shop you recommended for our future.</span>
                </li>
            </ul>
        </div>

        <section class="space-y-12 text-xl md:text-2xl serif fade-in">
            <p>
                I messed up the words, but I hope this effort shows you that MJ is paying attention. I'm ready to watch Naruto, One Piece, and the sunset in Cebu with you.
            </p>
            <p>
                Just like you said bai, we will keep this site incase lang mingawon ka nako ayy HAHAHA and if you do I converted this site into our own safe space where we can still connect with less worries of being attached. I hope you like it, I made it with you in mind. I hope you can feel that.
            </p>
        </section>
    </main>

    <!-- Meds Reminder Modal -->
    <div id="medsModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm opacity-0 transition-opacity duration-300">
        <div class="glass p-6 sm:p-8 rounded-2xl max-w-sm w-full mx-4 transform scale-95 transition-transform duration-300 border-pink-500/20">
            <h3 id="medsModalTitle" class="text-xl sm:text-2xl text-white mb-4 font-light italic tracking-tighter">A Gentle Reminder</h3>
            <p id="medsModalBody" class="text-slate-400 font-sans text-sm mb-8 leading-relaxed">Have you taken your meds today, meam?</p>
            <div class="flex justify-end space-x-3 font-sans text-sm">
                <button id="medsNoBtn" type="button" class="px-5 py-2.5 text-slate-400 hover:text-white transition-colors cursor-pointer">No</button>
                <button id="medsYesBtn" type="button" class="bg-pink-500/10 text-pink-400 border border-pink-500/30 hover:bg-pink-500/20 px-5 py-2.5 rounded-xl transition-all cursor-pointer">Yes</button>
            </div>
        </div>
    </div>

    <script>
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) entry.target.classList.add('visible');
            });
        }, { threshold: 0.1 });
        document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));

        <?php if ($show_meds_modal): ?>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('medsModal');
            const modalContent = modal.querySelector('div.glass');
            const modalTitle = document.getElementById('medsModalTitle');
            const modalBody = document.getElementById('medsModalBody');
            const yesBtn = document.getElementById('medsYesBtn');
            const noBtn = document.getElementById('medsNoBtn');

            function openModal() {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
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

            // Show modal on page load after a short delay
            setTimeout(openModal, 1500);

            // "No" button logic
            const noMessages = [
                "Please take them when you can. I care about you.",
                "Don't forget, okay? Your health is important.",
                "I'll ask again later. Please remember them.",
                "Okay, but promise me you'll take them soon."
            ];
            let noClickCount = 0;
            noBtn.addEventListener('click', () => {
                modalBody.textContent = noMessages[noClickCount % noMessages.length];
                noClickCount++;
            });

            // "Yes" button logic
            yesBtn.addEventListener('click', () => {
                yesBtn.disabled = true;
                yesBtn.textContent = 'Thank you! 😡';

                fetch(window.location.pathname, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'meds_taken=yes'
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        modalTitle.textContent = 'Maayo';
                        modalBody.textContent = 'Thank you for taking care of yourself. I appreciate it. 😡';
                        noBtn.style.display = 'none';
                        yesBtn.textContent = 'Close';
                        yesBtn.onclick = closeModal;
                        yesBtn.disabled = false;
                    } else {
                        modalBody.textContent = 'Something went wrong on the server. I will fix it.';
                    }
                })
                .catch(err => {
                    console.error('Meds update error:', err);
                    modalBody.textContent = 'A network error occurred. Please try again.';
                    yesBtn.disabled = false;
                    yesBtn.textContent = 'Yes';
                });
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>