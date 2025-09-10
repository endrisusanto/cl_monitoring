<?php
include 'config.php';

// Fungsi untuk memproses semua logika CL dengan logika status yang diperbarui
function processFirmwareCls($cl_sync, $xid_cl_latest_str, $latest_partial_cl_str) {
    $result = [
        'status' => 'OK',
        'greater_cls' => [],
        'missing_from_partial' => []
    ];

    if (empty($cl_sync) || empty($xid_cl_latest_str)) {
        return $result;
    }

    $xid_cl_latest = array_map('intval', array_map('trim', explode(',', $xid_cl_latest_str)));
    $latest_partial_cl = !empty($latest_partial_cl_str) ? array_map('intval', array_map('trim', explode(',', $latest_partial_cl_str))) : [];

    // 1. Cari CL di XID yang lebih besar dari CL Sync
    foreach ($xid_cl_latest as $cl) {
        if ($cl > (int)$cl_sync) {
            $result['greater_cls'][] = $cl;
        }
    }

    // 2. Bandingkan 'greater_cls' dengan 'latest_partial_cl' untuk menemukan CL yang benar-benar hilang
    if (!empty($result['greater_cls'])) {
        $result['missing_from_partial'] = array_diff($result['greater_cls'], $latest_partial_cl);
    }
    
    // 3. Tentukan status berdasarkan apakah ada CL yang benar-benar hilang (logika baru)
    if (!empty($result['missing_from_partial'])) {
        $result['status'] = 'Missing CL';
    } else {
        $result['status'] = 'OK';
    }

    return $result;
}

// Logic untuk filter, search, dan sort
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';
$sort_by = $_GET['sort_by'] ?? 'id';
$sort_order = $_GET['sort_order'] ?? 'DESC';

$allowed_sort_columns = ['id', 'model', 'date_release', 'cl_sync', 'csc_reference', 'status_model'];
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'id';
}
$sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

$sql = "SELECT * FROM firmware_data WHERE 1=1";

if (!empty($search)) {
    $sql .= " AND (model LIKE '%$search%' OR ap LIKE '%$search%' OR csc LIKE '%$search%')";
}

$result = $conn->query($sql . " ORDER BY $sort_by $sort_order");
$all_data = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $all_data[] = $row;
    }
}

// Terapkan filter PHP dengan logika status yang baru
$filtered_data = [];
if ($filter === 'all') {
    $filtered_data = $all_data;
} else {
    foreach ($all_data as $row) {
        $cl_info = processFirmwareCls($row['cl_sync'], $row['xid_cl_latest'], $row['latest_partial_cl']);
        $xid_status = $cl_info['status'];

        if ($filter === 'ok' && $xid_status === 'OK') {
            $filtered_data[] = $row;
        } elseif ($filter === 'missing' && $xid_status === 'Missing CL') {
            $filtered_data[] = $row;
        } elseif ($filter === 'p4_empty' && (empty($row['p4_path']) || $row['p4_path'] == '')) {
            $filtered_data[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Data Firmware</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>

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
            --bg-color: #f0f2f5;
            --text-color: #1f2937;
            --text-secondary-color: #6b7280;
            --glass-bg: rgba(255, 255, 255, 0.6);
            --glass-border: rgba(0, 0, 0, 0.08);
            --table-header-bg: rgba(0, 0, 0, 0.05);
            --table-hover-bg: rgba(0, 0, 0, 0.03);
            --table-border: rgba(0, 0, 0, 0.1);
            --input-bg: rgba(0, 0, 0, 0.05);
            --input-border: rgba(0, 0, 0, 0.1);
            --tooltip-bg: rgba(20, 20, 40, 0.8);
            --tooltip-text: white;
            --constellation-opacity: 0.1;
        }
        html.dark { /* Dark Mode */
            --bg-color: #0a0a1a;
            --text-color: #e0e0e0;
            --text-secondary-color: #9ca3af;
            --glass-bg: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
            --table-header-bg: rgba(255, 255, 255, 0.08);
            --table-hover-bg: rgba(255, 255, 255, 0.07);
            --table-border: rgba(255, 255, 255, 0.1);
            --input-bg: rgba(255, 255, 255, 0.05);
            --input-border: rgba(255, 255, 255, 0.1);
            --tooltip-bg: rgba(20, 20, 40, 0.8);
            --tooltip-text: white;
            --constellation-opacity: 1;
        }

        @keyframes pulse-bg {
            0%, 100% { box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.1); }
            50% { box-shadow: 0 0 8px 3px rgba(255, 255, 255, 0.15); }
        }
        html {
            scroll-behavior: smooth;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.5s ease, color 0.5s ease;
            overflow-x: hidden;
        }
        #constellation-canvas {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;
            opacity: var(--constellation-opacity);
            transition: opacity 0.5s ease;
        }
        .glass-container {
            background: var(--glass-bg);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid var(--glass-border);
            border-radius: 1rem;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            transition: background-color 0.5s ease, border-color 0.5s ease;
        }
        .themed-table {
            border-collapse: separate; border-spacing: 0; width: 100%;
        }
        .themed-table th, .themed-table td {
            border-bottom: 1px solid var(--table-border);
            padding: 0.75rem 1rem;
            text-align: center;
            vertical-align: middle;
            position: relative;
            white-space: nowrap;
        }
        .themed-table th {
            background-color: var(--table-header-bg);
            font-weight: 600;
        }
        .themed-table tr:hover {
            background-color: var(--table-hover-bg);
        }
        .themed-table td { cursor: pointer; }
        .themed-table th:first-child, .themed-table td:first-child { border-top-left-radius: 1rem; border-bottom-left-radius: 1rem; }
        .themed-table th:last-child, .themed-table td:last-child { border-top-right-radius: 1rem; border-bottom-right-radius: 1rem; }
        
        .themed-button {
            background: rgba(79, 70, 229, 0.7);
            color: white;
            padding: 0.5rem 1rem; border-radius: 0.5rem;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            border: 1px solid rgba(79, 70, 229, 0.9);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .themed-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4), 0 4px 6px -2px rgba(79, 70, 229, 0.2);
        }
        .themed-button:active { transform: translateY(0); }
        .themed-input {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            color: var(--text-color);
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        .themed-input::placeholder { color: var(--text-secondary-color); }
        .themed-input:focus {
            outline: none;
            border-color: rgba(79, 70, 229, 0.8);
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.5);
        }
        .tooltip {
            position: absolute;
            background: var(--tooltip-bg);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: var(--tooltip-text);
            padding: 6px 12px; border-radius: 6px; font-size: 13px;
            visibility: hidden; opacity: 0;
            transition: opacity 0.3s ease, transform 0.3s ease;
            z-index: 10; bottom: 120%; left: 50%;
            transform: translateX(-50%) translateY(5px);
            pointer-events: none; white-space: nowrap;
        }
        .tooltip-active {
            visibility: visible; opacity: 1; transform: translateX(-50%) translateY(0);
        }
        .animate-pulse-bg {
            animation: pulse-bg 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
            border-radius: 50%; display: inline-flex;
            align-items: center; justify-content: center;
        }
        .action-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 2rem; height: 2rem; border-radius: 50%;
            transition: all 0.2s ease-in-out;
        }
        .action-btn:hover {
            background-color: rgba(128, 128, 128, 0.2);
            transform: scale(1.1);
        }
        .actions-cell {
            width: 1%; white-space: nowrap;
        }
        .search-icon { color: var(--text-secondary-color); }
        .filter-btn {
             background: rgba(128, 128, 128, 0.1); color: var(--text-secondary-color);
        }
        .filter-btn:hover {
            background: rgba(128, 128, 128, 0.2);
        }
        .filter-btn.active {
            background: #4F46E5; color: white;
        }
    </style>
</head>
<body class="p-4 md:p-8">
    <canvas id="constellation-canvas"></canvas>
    <div class="w-full mx-auto px-4 md:px-8">
        <h1 class="text-4xl font-bold mb-8 text-center tracking-wider">Manajemen Data Firmware</h1>

        <!-- Search and Filter Section -->
        <div class="mb-8 p-4 glass-container">
            <div class="flex flex-col md:flex-row flex-wrap gap-2 items-center">
                <form method="GET" action="index.php" class="flex-grow flex gap-2 items-center w-full md:w-auto">
                    <div class="relative flex-grow">
                        <label for="search" class="sr-only">Cari Data</label>
                        <input type="text" name="search" id="search" placeholder="Cari Data..." value="<?= htmlspecialchars($search) ?>" class="themed-input w-full p-2 pl-10 h-10">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 search-icon"></i>
                    </div>
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                    <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sort_by) ?>">
                    <input type="hidden" name="sort_order" value="<?= htmlspecialchars($sort_order) ?>">
                    <button type="submit" class="themed-button flex-shrink-0 h-10"><i class="fas fa-search mr-2"></i>Cari</button>
                </form>

                <?php
                    $filters = [ 'all' => 'Semua', 'ok' => 'OK', 'missing' => 'Missing', 'p4_empty' => 'P4 Kosong' ];
                    foreach($filters as $key => $value) {
                        $isActive = ($filter === $key) ? 'active' : '';
                        $link = "?filter=$key&search=" . urlencode($search) . "&sort_by=$sort_by&sort_order=$sort_order";
                        echo "<a href='$link' class='filter-btn px-3 py-2 text-sm font-medium rounded-md transition-all duration-300 $isActive whitespace-nowrap h-10 flex items-center'>$value</a>";
                    }
                ?>
                <a href="index.php" class="filter-btn px-3 py-2 text-sm font-medium rounded-md transition-all duration-300 flex items-center h-10" title="Reset"><i class="fas fa-undo"></i></a>
            </div>
        </div>


        <div class="flex flex-wrap gap-4 mb-6">
            <a href="create.php" class="themed-button bg-green-600/70 border-green-500/90 hover:shadow-green-500/40"><i class="fas fa-plus mr-2"></i>Tambah Data Baru</a>
            <button id="exportBtn" class="themed-button bg-blue-600/70 border-blue-500/90 hover:shadow-blue-500/40"><i class="fas fa-file-excel mr-2"></i>Export ke Excel</button>
            <button id="theme-toggle" type="button" class="themed-button bg-gray-600/70 border-gray-500/90 hover:shadow-gray-500/40 w-12 h-10 flex items-center justify-center">
                <i id="theme-toggle-dark-icon" class="fas fa-moon"></i>
                <i id="theme-toggle-light-icon" class="fas fa-sun hidden"></i>
            </button>
        </div>

        <div class="overflow-x-auto glass-container">
            <table class="themed-table min-w-full" id="dataTable">
                <thead>
                    <tr>
                        <?php
                        $columns = [ 'model' => 'Model', 'p4_path' => 'P4 Path', 'date_release' => 'Date Release', 'ap' => 'AP', 'cp_modem' => 'CP(Modem)', 'csc' => 'CSC', 'csc_qb' => 'CSC QB', 'cl_sync' => 'CL Sync', 'xid_cl_latest' => 'XID CL Latest', 'latest_partial_cl' => 'Latest Partial CL', 'status_xid_cl' => 'Status XID CL', 'xid_cl_greater' => 'XID CL > CL Sync', 'xid_cl_missing' => 'XID CL Missing', 'csc_reference' => 'CSC Ref', 'status_model' => 'Status Model' ];
                        $current_sort_order = $sort_order === 'ASC' ? 'DESC' : 'ASC';
                        foreach ($columns as $key => $value) {
                            $is_sortable = in_array($key, $allowed_sort_columns);
                            $sort_icon = '';
                            if ($sort_by === $key && $is_sortable) {
                                $sort_icon = $sort_order === 'ASC' ? '<i class="fas fa-sort-up ml-2"></i>' : '<i class="fas fa-sort-down ml-2"></i>';
                            }
                            if ($is_sortable) {
                                echo "<th><a href='?sort_by=$key&sort_order=$current_sort_order&search=$search&filter=$filter'>$value $sort_icon</a></th>";
                            } else {
                                echo "<th>$value</th>";
                            }
                        }
                        ?>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($filtered_data)): ?>
                        <?php foreach ($filtered_data as $row): ?>
                            <?php 
                                $cl_info = processFirmwareCls($row['cl_sync'], $row['xid_cl_latest'], $row['latest_partial_cl']);
                                $p4_path_clean = trim($row['p4_path'] ?? '', '/');
                                $p4_link = "https://review1716.sec.samsung.net/files/" . $p4_path_clean . "/" . $row['csc_reference'] . "/XID/#commits";
                                $row_json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr>
                                <td data-copy="<?= htmlspecialchars($row['model']) ?>"><?= htmlspecialchars($row['model']) ?><span class="tooltip">Copied!</span></td>
                                <td data-copy="<?= $p4_link ?>"><?= htmlspecialchars($row['p4_path']) ?><span class="tooltip">Copied!</span></td>
                                <td data-copy="<?= htmlspecialchars($row['date_release']) ?>"><?= htmlspecialchars($row['date_release']) ?><span class="tooltip">Copied!</span></td>
                                <td data-copy="<?= htmlspecialchars($row['ap']) ?>"><?= htmlspecialchars($row['ap']) ?><span class="tooltip">Copied!</span></td>
                                <td data-copy="<?= htmlspecialchars($row['cp_modem']) ?>"><?= htmlspecialchars($row['cp_modem']) ?><span class="tooltip">Copied!</span></td>
                                <td data-copy="<?= htmlspecialchars($row['csc']) ?>"><?= htmlspecialchars($row['csc']) ?><span class="tooltip">Copied!</span></td>
                                <td class="text-center" data-nocopy><button onclick="copyToClipboard('<?= htmlspecialchars($row['csc_qb']) ?>', this)" class="text-blue-400 hover:text-blue-300 transition-transform duration-200 hover:scale-125"><i class="fas fa-link"></i></button><span class="tooltip">Copy Value</span></td>
                                <td data-copy="<?= htmlspecialchars($row['cl_sync']) ?>"><?= htmlspecialchars($row['cl_sync']) ?><span class="tooltip">Copied!</span></td>
                                <td class="text-center" data-nocopy>
                                    <button class="copy-cl-list-btn text-gray-400 hover:text-white transition-transform duration-200 hover:scale-125" data-copy="<?= htmlspecialchars($row['xid_cl_latest']) ?>">
                                        <i class="fas fa-list-alt"></i>
                                    </button>
                                    <span class="tooltip">Salin Daftar CL</span>
                                </td>
                                <td class="text-center" data-nocopy>
                                     <button class="copy-cl-list-btn text-gray-400 hover:text-white transition-transform duration-200 hover:scale-125" data-copy="<?= htmlspecialchars($row['latest_partial_cl']) ?>">
                                        <i class="fas fa-list-alt"></i>
                                    </button>
                                    <span class="tooltip">Salin Daftar CL</span>
                                </td>
                                <td data-nocopy><span class="px-2 py-1 font-semibold leading-tight rounded-full <?= $cl_info['status'] === 'OK' ? 'bg-green-700/70 text-green-100' : 'bg-red-700/70 text-red-100' ?>"><?= $cl_info['status'] ?></span></td>
                                <td data-nocopy>
                                    <ul class="list-none p-0 m-0 space-y-2">
                                    <?php if (!empty($cl_info['greater_cls'])): ?>
                                        <?php foreach ($cl_info['greater_cls'] as $greater_cl): ?>
                                            <li class="copyable-item flex items-center justify-center" data-copy="<?= htmlspecialchars($greater_cl) ?>">
                                                <span class="animate-pulse-bg mr-2 p-1"><i class="fas fa-check-square text-green-400"></i></span>
                                                <span><?= htmlspecialchars($greater_cl) ?></span>
                                                <span class="tooltip">Copied!</span>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </ul>
                                </td>
                                <td data-nocopy>
                                    <ul class="list-none p-0 m-0 space-y-2">
                                    <?php if (!empty($cl_info['missing_from_partial'])): ?>
                                        <?php foreach ($cl_info['missing_from_partial'] as $missing_cl): ?>
                                            <li class="copyable-item flex items-center justify-center" data-copy="<?= htmlspecialchars($missing_cl) ?>">
                                                <span class="animate-pulse-bg mr-2 p-1"><i class="fas fa-exclamation-triangle text-yellow-400"></i></span>
                                                <?= htmlspecialchars($missing_cl) ?>
                                                <span class="tooltip">Copied!</span>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </ul>
                                </td>
                                <td data-copy="<?= htmlspecialchars($row['csc_reference']) ?>"><?= htmlspecialchars($row['csc_reference']) ?><span class="tooltip">Copied!</span></td>
                                <td data-copy="<?= htmlspecialchars($row['status_model']) ?>"><?= htmlspecialchars($row['status_model']) ?><span class="tooltip">Copied!</span></td>
                                <td class="actions-cell" data-nocopy>
                                    <div class="flex items-center justify-center gap-2">
                                        <a href="#" class="view-btn action-btn text-blue-400" title="View" data-row='<?= $row_json ?>'><i class="fas fa-eye"></i></a>
                                        <a href="edit.php?id=<?= $row['id'] ?>" class="action-btn text-yellow-400" title="Update"><i class="fas fa-edit"></i></a>
                                        <a href="delete.php?id=<?= $row['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')" class="action-btn text-red-400" title="Delete"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="17" class="text-center py-4">Tidak ada data ditemukan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-70 flex justify-center items-center z-50 hidden transition-opacity duration-300">
        <div class="glass-container p-6 w-full max-w-3xl max-h-[90vh] overflow-y-auto transform transition-all duration-300 scale-95 opacity-0">
            <div class="flex justify-between items-center mb-4 border-b pb-3" style="border-color: var(--glass-border);">
                <h2 class="text-2xl font-bold">Detail Data</h2>
                <button id="closeModalBtn" class="text-3xl transition-transform duration-200 hover:rotate-90" style="color: var(--text-secondary-color);">&times;</button>
            </div>
            <div id="modalContent" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Data akan diinjeksikan di sini oleh JavaScript -->
            </div>
        </div>
    </div>

    <script>
    // --- Canvas Animation Script ---
    const canvas = document.getElementById('constellation-canvas');
    const ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;

    let particlesArray;
    let hue = 0;

    class Particle {
        constructor(x, y, directionX, directionY, size, color) {
            this.x = x; this.y = y; this.directionX = directionX; this.directionY = directionY; this.size = size; this.color = color;
        }
        draw() {
            ctx.beginPath(); ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2, false); ctx.fillStyle = this.color; ctx.fill();
        }
        update() {
            if (this.x > canvas.width || this.x < 0) { this.directionX = -this.directionX; }
            if (this.y > canvas.height || this.y < 0) { this.directionY = -this.directionY; }
            this.x += this.directionX; this.y += this.directionY; this.draw();
        }
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
        for (let a = 0; a < particlesArray.length; a++) {
            for (let b = a; b < particlesArray.length; b++) {
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
        for (let i = 0; i < particlesArray.length; i++) {
            particlesArray[i].color = `hsl(${hue}, 100%, 50%)`;
            particlesArray[i].update();
        }
        connect();
    }
    
    window.addEventListener('resize', function() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; init(); });
    init();
    animate();

    // --- Other Scripts ---
    function copyToClipboard(text, element) {
        if (!text) return;
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);

        let tooltip = element.querySelector('.tooltip') || element.parentElement.querySelector('.tooltip');
        if (tooltip) {
            tooltip.classList.add('tooltip-active');
            const originalText = tooltip.textContent;
            tooltip.textContent = 'Copied!';
            setTimeout(() => {
                tooltip.classList.remove('tooltip-active');
                tooltip.textContent = originalText;
            }, 1500);
        }
    }

    document.querySelectorAll('td[data-copy]').forEach(td => {
        td.addEventListener('click', () => {
            const textToCopy = td.getAttribute('data-copy');
            if(textToCopy) copyToClipboard(textToCopy, td);
        });
    });

    document.querySelectorAll('.copyable-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.stopPropagation();
            const textToCopy = item.getAttribute('data-copy');
            if(textToCopy) copyToClipboard(textToCopy, item);
        });
    });

    document.querySelectorAll('.copy-cl-list-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            e.stopPropagation();
            const textToCopy = button.getAttribute('data-copy');
            if (textToCopy) copyToClipboard(textToCopy, button.parentElement);
        });
    });

    document.getElementById('exportBtn').addEventListener('click', function () {
        const table = document.getElementById('dataTable');
        const table_clone = table.cloneNode(true);
        table_clone.querySelectorAll('.tooltip').forEach(tooltip => tooltip.remove());
        const rows = table_clone.querySelectorAll('tr');
        for (let i = 0; i < rows.length; i++) {
            rows[i].removeChild(rows[i].lastElementChild);
        }
        const wb = XLSX.utils.table_to_book(table_clone, { sheet: "Firmware Data" });
        XLSX.writeFile(wb, 'FirmwareData.xlsx');
    });

    // Modal Logic
    const viewModal = document.getElementById('viewModal');
    const modalContainer = viewModal.querySelector('.glass-container');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const modalContent = document.getElementById('modalContent');
    document.querySelectorAll('.view-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const data = JSON.parse(button.dataset.row);
            let contentHtml = '';
            const keyOrder = ['id', 'model', 'p4_path', 'date_release', 'ap', 'cp_modem', 'csc', 'csc_qb', 'cl_sync', 'xid_cl_latest', 'latest_partial_cl', 'csc_reference', 'status_model', 'created_at'];
            
            keyOrder.forEach(key => {
                if (data.hasOwnProperty(key)) {
                    const value = data[key] || '-';
                    const formattedKey = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    contentHtml += `<div class="p-3 bg-white/5 rounded-md break-words"><strong class="font-semibold block mb-1" style="color:var(--text-color);">${formattedKey}</strong>${value}</div>`;
                }
            });

            modalContent.innerHTML = contentHtml;
            viewModal.classList.remove('hidden');
            setTimeout(() => {
                viewModal.style.opacity = 1;
                modalContainer.classList.remove('scale-95', 'opacity-0');
                modalContainer.classList.add('scale-100', 'opacity-100');
            }, 10);
        });
    });

    function hideModal() {
        modalContainer.classList.add('scale-95', 'opacity-0');
        viewModal.style.opacity = 0;
        setTimeout(() => { viewModal.classList.add('hidden'); }, 300);
    }
    closeModalBtn.addEventListener('click', hideModal);
    viewModal.addEventListener('click', (e) => { if (e.target === viewModal) hideModal(); });

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
            if (localStorage.getItem('theme') === 'light') {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            } else {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            }
        } else {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            }
        }
    });
    </script>
</body>
</html>

