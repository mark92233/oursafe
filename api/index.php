<?php
require_once __DIR__ . '/db_related/db_connect.php';
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
<body class="selection:bg-pink-500/40">

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

        <div class="mb-24 text-center fade-in">
            <a href="res.php" class="inline-block glass hover:bg-pink-500/10 hover:border-pink-500/40 hover:shadow-[0_0_20px_rgba(244,114,182,0.15)] text-white py-4 px-10 rounded-xl mono text-sm uppercase tracking-widest transition-all duration-500 cursor-pointer group">How was your day? <span class="group-hover:translate-x-1 inline-block transition-transform duration-300">&rarr;</span></a>
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

    <script>
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) entry.target.classList.add('visible');
            });
        }, { threshold: 0.1 });
        document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));
    </script>
</body>
</html>