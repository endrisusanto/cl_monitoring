<?php
include 'config.php';
$id = $_GET['id'];

// Fetch existing data
$stmt = $conn->prepare("SELECT * FROM firmware_data WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $model = $_POST['model'];
    $p4_path = $_POST['p4_path'];
    $date_release = $_POST['date_release'];
    $ap = $_POST['ap'];
    $cp_modem = $_POST['cp_modem'];
    $csc = $_POST['csc'];
    $csc_qb = $_POST['csc_qb'];
    $cl_sync = $_POST['cl_sync'];
    $xid_cl_latest = $_POST['xid_cl_latest'];
    $latest_partial_cl = $_POST['latest_partial_cl'];
    $csc_reference = $_POST['csc_reference'];
    $status_model = $_POST['status_model'];

    $stmt = $conn->prepare("UPDATE firmware_data SET model=?, p4_path=?, date_release=?, ap=?, cp_modem=?, csc=?, csc_qb=?, cl_sync=?, xid_cl_latest=?, latest_partial_cl=?, csc_reference=?, status_model=? WHERE id=?");
    $stmt->bind_param("ssssssssssssi", $model, $p4_path, $date_release, $ap, $cp_modem, $csc, $csc_qb, $cl_sync, $xid_cl_latest, $latest_partial_cl, $csc_reference, $status_model, $id);
    
    if ($stmt->execute()) {
        header("Location: index.php");
        exit();
    } else {
        echo "Error updating record: " . $conn->error;
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Data Firmware</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script>
        // Skrip ini dijalankan lebih awal untuk menghindari kedipan tema (FOUC)
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <style>
        :root { /* Light Mode */
            --bg-color: #f0f2f5; --text-color: #1f2937; --text-secondary-color: #6b7280;
            --glass-bg: rgba(255, 255, 255, 0.6); --glass-border: rgba(0, 0, 0, 0.08);
            --input-bg: rgba(0, 0, 0, 0.05); --input-border: rgba(0, 0, 0, 0.1);
            --constellation-opacity: 0.1;
        }
        html.dark { /* Dark Mode */
            --bg-color: #0a0a1a; --text-color: #e0e0e0; --text-secondary-color: #9ca3af;
            --glass-bg: rgba(255, 255, 255, 0.05); --glass-border: rgba(255, 255, 255, 0.1);
            --input-bg: rgba(255, 255, 255, 0.05); --input-border: rgba(255, 255, 255, 0.1);
            --constellation-opacity: 1;
        }
        html { scroll-behavior: smooth; }
        body {
            background-color: var(--bg-color); color: var(--text-color);
            transition: background-color 0.5s ease, color 0.5s ease;
            overflow-x: hidden;
        }
        #constellation-canvas {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;
            opacity: var(--constellation-opacity); transition: opacity 0.5s ease;
        }
        .glass-container {
            background: var(--glass-bg); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
            border: 1px solid var(--glass-border); border-radius: 1rem;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            transition: background-color 0.5s ease, border-color 0.5s ease;
        }
        .themed-button {
            background: rgba(79, 70, 229, 0.7); color: white;
            padding: 0.5rem 1rem; border-radius: 0.5rem;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            border: 1px solid rgba(79, 70, 229, 0.9);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .themed-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4), 0 4px 6px -2px rgba(79, 70, 229, 0.2);
        }
        .themed-input, .themed-select, .themed-textarea {
            background: var(--input-bg); border: 1px solid var(--input-border);
            color: var(--text-color); border-radius: 0.5rem; transition: all 0.3s ease;
            width: 100%; padding: 0.625rem;
        }
        .themed-input::placeholder, .themed-textarea::placeholder { color: var(--text-secondary-color); }
        .themed-input:focus, .themed-select:focus, .themed-textarea:focus {
            outline: none; border-color: rgba(79, 70, 229, 0.8);
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.5);
        }
    </style>
</head>
<body class="p-4 md:p-8">
    <canvas id="constellation-canvas"></canvas>
    <div class="max-w-4xl mx-auto">
         <div class="flex justify-between items-center mb-8">
            <h1 class="text-4xl font-bold tracking-wider">Edit Data</h1>
            <button id="theme-toggle" type="button" class="themed-button bg-gray-600/70 border-gray-500/90 hover:shadow-gray-500/40 w-12 h-10 flex items-center justify-center">
                <i id="theme-toggle-dark-icon" class="fas fa-moon"></i>
                <i id="theme-toggle-light-icon" class="fas fa-sun hidden"></i>
            </button>
        </div>

        <div class="glass-container p-6 md:p-8">
            <form action="edit.php?id=<?= $id ?>" method="post" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="model" class="block mb-2 text-sm font-medium">Model</label>
                        <input type="text" id="model" name="model" class="themed-input" value="<?= htmlspecialchars($row['model']) ?>" required>
                    </div>
                    <div>
                        <label for="date_release" class="block mb-2 text-sm font-medium">Date Release</label>
                        <input type="date" id="date_release" name="date_release" class="themed-input" value="<?= htmlspecialchars($row['date_release']) ?>" required>
                    </div>
                </div>
                <div>
                    <label for="p4_path" class="block mb-2 text-sm font-medium">P4 Path</label>
                    <div class="flex">
                        <span class="inline-flex items-center px-3 text-sm rounded-l-md border border-r-0" style="background-color:var(--input-bg); border-color:var(--input-border); color:var(--text-secondary-color);">https://review1716.../</span>
                        <input type="text" id="p4_path" name="p4_path" class="themed-input rounded-l-none rounded-r-none" placeholder="//BENI_CSC/Latte/MTK/a06x/OMC/..." value="<?= htmlspecialchars($row['p4_path']) ?>">
                         <span class="inline-flex items-center px-3 text-sm rounded-r-md border border-l-0" style="background-color:var(--input-bg); border-color:var(--input-border); color:var(--text-secondary-color);">/.../XID/#commits</span>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="ap" class="block mb-2 text-sm font-medium">AP</label>
                        <input type="text" id="ap" name="ap" class="themed-input" value="<?= htmlspecialchars($row['ap']) ?>">
                    </div>
                    <div>
                        <label for="cp_modem" class="block mb-2 text-sm font-medium">CP(Modem)</label>
                        <input type="text" id="cp_modem" name="cp_modem" class="themed-input" value="<?= htmlspecialchars($row['cp_modem']) ?>">
                    </div>
                    <div>
                        <label for="csc" class="block mb-2 text-sm font-medium">CSC</label>
                        <input type="text" id="csc" name="csc" class="themed-input" value="<?= htmlspecialchars($row['csc']) ?>">
                    </div>
                </div>
                 <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="csc_qb" class="block mb-2 text-sm font-medium">CSC QB</label>
                        <input type="text" id="csc_qb" name="csc_qb" class="themed-input" value="<?= htmlspecialchars($row['csc_qb']) ?>">
                    </div>
                    <div>
                        <label for="cl_sync" class="block mb-2 text-sm font-medium">CL Sync</label>
                        <input type="text" id="cl_sync" name="cl_sync" class="themed-input" value="<?= htmlspecialchars($row['cl_sync']) ?>">
                    </div>
                    <div>
                        <label for="csc_reference" class="block mb-2 text-sm font-medium">CSC Reference</label>
                        <select id="csc_reference" name="csc_reference" class="themed-select">
                            <option <?= $row['csc_reference'] == 'OLM' ? 'selected' : '' ?>>OLM</option>
                            <option <?= $row['csc_reference'] == 'OXM' ? 'selected' : '' ?>>OXM</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label for="xid_cl_latest" class="block mb-2 text-sm font-medium">XID CL Latest</label>
                    <textarea id="xid_cl_latest" name="xid_cl_latest" rows="3" class="themed-textarea"><?= htmlspecialchars($row['xid_cl_latest']) ?></textarea>
                </div>
                 <div>
                    <label for="latest_partial_cl" class="block mb-2 text-sm font-medium">Latest Partial CL</label>
                    <textarea id="latest_partial_cl" name="latest_partial_cl" rows="3" class="themed-textarea"><?= htmlspecialchars($row['latest_partial_cl']) ?></textarea>
                </div>
                 <div>
                    <label for="status_model" class="block mb-2 text-sm font-medium">Status Model</label>
                    <select id="status_model" name="status_model" class="themed-select">
                        <option <?= $row['status_model'] == 'New Model' ? 'selected' : '' ?>>New Model</option>
                        <option <?= $row['status_model'] == 'OS Upgrade' ? 'selected' : '' ?>>OS Upgrade</option>
                    </select>
                </div>
                <div class="flex items-center gap-4 pt-4">
                    <button type="submit" class="themed-button bg-yellow-600/70 border-yellow-500/90 hover:shadow-yellow-500/40"><i class="fas fa-save mr-2"></i>Update Data</button>
                    <a href="index.php" class="themed-button bg-gray-600/70 border-gray-500/90 hover:shadow-gray-500/40"><i class="fas fa-times mr-2"></i>Batal</a>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Canvas Animation
    const canvas = document.getElementById('constellation-canvas');
    const ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    let particlesArray; 
    let hue = 0;
    
    class Particle {
        constructor(x, y, directionX, directionY, size, color) { this.x = x; this.y = y; this.directionX = directionX; this.directionY = directionY; this.size = size; this.color = color; }
        draw() { ctx.beginPath(); ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2, false); ctx.fillStyle = this.color; ctx.fill(); }
        update() { if (this.x > canvas.width || this.x < 0) { this.directionX = -this.directionX; } if (this.y > canvas.height || this.y < 0) { this.directionY = -this.directionY; } this.x += this.directionX; this.y += this.directionY; this.draw(); }
    }
    function init() {
        particlesArray = [];
        let numberOfParticles = (canvas.height * canvas.width) / 9000;
        for (let i = 0; i < numberOfParticles; i++) {
            let size = (Math.random() * 2) + 1;
            let x = (Math.random() * ((canvas.width - size * 2) - (size * 2)) + size * 2);
            let y = (Math.random() * ((canvas.height - size * 2) - (size * 2)) + size * 2);
            let directionX = (Math.random() * .4) - .2;
            let directionY = (Math.random() * .4) - .2;
            particlesArray.push(new Particle(x, y, directionX, directionY, size, `hsl(${hue}, 100%, 50%)`));
        }
    }
    function connect() {
        let opacityValue = 1;
        for (let a = 0; a < particlesArray.length; a++) { for (let b = a; b < particlesArray.length; b++) {
                let distance = ((particlesArray[a].x - particlesArray[b].x) * (particlesArray[a].x - particlesArray[b].x)) + ((particlesArray[a].y - particlesArray[b].y) * (particlesArray[a].y - particlesArray[b].y));
                if (distance < (canvas.width / 7) * (canvas.height / 7)) {
                    opacityValue = 1 - (distance / 20000);
                    ctx.strokeStyle = `hsla(${hue}, 100%, 50%, ${opacityValue})`;
                    ctx.lineWidth = 1;
                    ctx.beginPath(); ctx.moveTo(particlesArray[a].x, particlesArray[a].y); ctx.lineTo(particlesArray[b].x, particlesArray[b].y); ctx.stroke();
                }
            }
        }
    }
    function animate() {
        requestAnimationFrame(animate);
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hue += 0.5;
        for (let i = 0; i < particlesArray.length; i++) { particlesArray[i].color = `hsl(${hue}, 100%, 50%)`; particlesArray[i].update(); }
        connect();
    }
    window.addEventListener('resize', function() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; init(); });
    init(); animate();

    // Theme Toggle Logic
    const themeToggleBtn = document.getElementById('theme-toggle');
    const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
    const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        themeToggleLightIcon.classList.remove('hidden');
        themeToggleDarkIcon.classList.add('hidden');
    } else {
        themeToggleLightIcon.classList.add('hidden');
        themeToggleDarkIcon.classList.remove('hidden');
    }
    themeToggleBtn.addEventListener('click', function() {
        themeToggleDarkIcon.classList.toggle('hidden');
        themeToggleLightIcon.classList.toggle('hidden');
        if (localStorage.getItem('theme')) {
            if (localStorage.getItem('theme') === 'light') { document.documentElement.classList.add('dark'); localStorage.setItem('theme', 'dark');
            } else { document.documentElement.classList.remove('dark'); localStorage.setItem('theme', 'light'); }
        } else {
            if (document.documentElement.classList.contains('dark')) { document.documentElement.classList.remove('dark'); localStorage.setItem('theme', 'light');
            } else { document.documentElement.classList.add('dark'); localStorage.setItem('theme', 'dark'); }
        }
    });
    </script>
</body>
</html>

