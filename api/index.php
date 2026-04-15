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
        :root { --accent: #818cf8; }
        body { background-color: #030303; color: #d1d5db; font-family: 'Playfair Display', serif; overflow-x: hidden; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 28px; }
        .fade-in { opacity: 0; transform: translateY(30px); transition: all 1.2s cubic-bezier(0.22, 1, 0.36, 1); }
        .fade-in.visible { opacity: 1; transform: translateY(0); }
        .glow-sphere { position: fixed; width: 600px; height: 600px; background: radial-gradient(circle, rgba(99, 102, 241, 0.04) 0%, rgba(0, 0, 0, 0) 70%); z-index: -1; filter: blur(90px); }
    </style>
</head>
<body class="selection:bg-indigo-500/40">

    <div class="glow-sphere" style="top: -10%; left: -10%;"></div>
    <div class="glow-sphere" style="bottom: -10%; right: -10%;"></div>

    <main class="max-w-xl mx-auto px-8 py-32 relative">
        
        <header class="mb-16 fade-in">
            <h1 class="text-6xl text-white mt-4 font-light italic tracking-tighter">Kaye.</h1>
            <p class="mono text-[9px] mt-6 opacity-40 uppercase tracking-[0.3em] leading-loose text-indigo-300">
                Subject: The All-or-Nothing Risk <br>
                Location Context: Zamboanga → Cebu
            </p>
        </header>

        <div class="mb-24 text-center fade-in">
            <a href="res.php" class="inline-block glass hover:bg-white/10 text-white py-4 px-10 rounded-xl mono text-sm uppercase tracking-widest transition-all cursor-pointer">How was your day? &rarr;</a>
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

        <div class="my-24 glass p-10 fade-in relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 mono text-[8px] opacity-10 rotate-90 tracking-widest">COMPILED_LOGS</div>
            <h3 class="mono text-[10px] uppercase tracking-[0.4em] text-indigo-400 mb-12 font-bold italic">The specific details I value:</h3>
            
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