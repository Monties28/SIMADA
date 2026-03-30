<?php
// Dummy data untuk demo
$total_pegawai = 80;
$upload_hari_ini = 6;
$file_terenkripsi = 4;
$ocr_processed = 5;
$total_files = 6;
$user_aktif_hari_ini = 6;

// Data clustering (dummy)
$cluster_data = [
    'labels' => ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt'],
    'clusterA' => [48, 53, 50, 61, 55, 67, 71, 68, 74, 76],
    'clusterB' => [18, 23, 25, 15, 20, 22, 18, 12, 8, 6],
    'clusterC' => [14, 4, 5, 4, 5, 1, 1, 0, 3, 2],
];

$kategori_dokumen = [
    ['name' => 'Keuangan', 'icon' => '💰'],
    ['name' => 'Proposal', 'icon' => '📝'],
    ['name' => 'SOP/Prosedur', 'icon' => '📋'],
    ['name' => 'Kepegawaian', 'icon' => '👥'],
    ['name' => 'Kontrak', 'icon' => '📄'],
    ['name' => 'Audit', 'icon' => '🔍'],
];

$folder_aktif = [
    '2025/Oktober',
    '2025/September',
];
?>

<div class="space-y-6">

    <!-- STAT CARDS -->
    <div class="grid grid-cols-4 gap-6">

        <!-- Total Pegawai -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fa-solid fa-users text-blue-400"></i>
                        <p class="text-xs text-gray-500 font-medium">Total Pegawai</p>
                    </div>
                    <p class="text-3xl font-bold text-gray-800 mb-1"><?= $total_pegawai ?></p>
                    <p class="text-xs text-gray-400">Pegawai aktif</p>
                </div>
            </div>
        </div>

        <!-- Upload Hari Ini -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fa-solid fa-cloud-arrow-up text-teal-400"></i>
                        <p class="text-xs text-gray-500 font-medium">Upload Hari Ini</p>
                    </div>
                    <p class="text-3xl font-bold text-gray-800 mb-1"><?= $upload_hari_ini ?></p>
                    <p class="text-xs text-teal-500 cursor-pointer hover:underline"><?= $user_aktif_hari_ini ?> user aktif</p>
                </div>
            </div>
        </div>

        <!-- File Terenkripsi -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fa-solid fa-lock text-yellow-400"></i>
                        <p class="text-xs text-gray-500 font-medium">File Terenkripsi</p>
                    </div>
                    <p class="text-3xl font-bold text-gray-800 mb-1"><?= $file_terenkripsi ?></p>
                    <p class="text-xs text-gray-400">67% dari total</p>
                </div>
            </div>
        </div>

        <!-- OCR Processed -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fa-solid fa-file-lines text-green-400"></i>
                        <p class="text-xs text-gray-500 font-medium">OCR Processed</p>
                    </div>
                    <p class="text-3xl font-bold text-gray-800 mb-1"><?= $ocr_processed ?></p>
                    <p class="text-xs text-gray-400">Teks terekstrak</p>
                </div>
            </div>
        </div>

    </div>

    <!-- ROW 2: CHART + UPLOAD SUMMARY -->
    <div class="grid grid-cols-3 gap-6">

        <!-- Clustering Chart -->
        <div class="col-span-2 bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="mb-6">
                <h3 class="font-bold text-gray-800 text-base mb-1">Clustering Absensi Pegawai</h3>
                <p class="text-xs text-gray-400">Pengelompokan pegawai berdasarkan pola kehadiran K-Means</p>
            </div>

            <div style="height: 240px;">
                <canvas id="clusterChart"></canvas>
            </div>

            <!-- Legend Manual (seperti screenshot) -->
            <div class="grid grid-cols-3 gap-4 mt-6">
                <div class="bg-gradient-to-br from-teal-50 to-teal-100 rounded-xl p-4 text-center border border-teal-200">
                    <div class="flex items-center justify-center gap-2 mb-2">
                        <div class="w-3 h-3 rounded-full bg-teal-400"></div>
                        <span class="text-xs font-semibold text-gray-700">Cluster A</span>
                    </div>
                    <p class="text-3xl font-bold text-gray-800">68</p>
                    <p class="text-xs text-gray-500 mt-1">Sempurna</p>
                </div>
                <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-4 text-center border border-orange-200">
                    <div class="flex items-center justify-center gap-2 mb-2">
                        <div class="w-3 h-3 rounded-full bg-orange-400"></div>
                        <span class="text-xs font-semibold text-gray-700">Cluster B</span>
                    </div>
                    <p class="text-3xl font-bold text-gray-800">10</p>
                    <p class="text-xs text-gray-500 mt-1">Terlambat</p>
                </div>
                <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-xl p-4 text-center border border-red-200">
                    <div class="flex items-center justify-center gap-2 mb-2">
                        <div class="w-3 h-3 rounded-full bg-red-400"></div>
                        <span class="text-xs font-semibold text-gray-700">Cluster C</span>
                    </div>
                    <p class="text-3xl font-bold text-gray-800">2</p>
                    <p class="text-xs text-gray-500 mt-1">Rendah</p>
                </div>
            </div>
        </div>

        <!-- Ringkasan Upload Hari Ini -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="mb-6">
                <h3 class="font-bold text-gray-800 text-base mb-1">Ringkasan Upload Hari Ini</h3>
                <p class="text-xs text-gray-400">Aktivitas upload dokumen oleh user</p>
            </div>

            <!-- Stats Mini -->
            <div class="grid grid-cols-2 gap-3 mb-6">
                <div class="bg-gradient-to-br from-teal-50 to-teal-100 rounded-lg p-4 border border-teal-200">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fa-solid fa-cloud-arrow-up text-teal-500 text-sm"></i>
                        <span class="text-xs text-gray-600 font-medium">Total Upload</span>
                    </div>
                    <p class="text-2xl font-bold text-gray-800"><?= $total_files ?></p>
                    <p class="text-xs text-gray-500 mt-1">file</p>
                </div>
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-4 border border-purple-200">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fa-solid fa-users text-purple-500 text-sm"></i>
                        <span class="text-xs text-gray-600 font-medium">User Aktif</span>
                    </div>
                    <p class="text-2xl font-bold text-gray-800"><?= $user_aktif_hari_ini ?></p>
                    <p class="text-xs text-gray-500 mt-1">users</p>
                </div>
            </div>

            <!-- Kategori Dokumen -->
            <div class="mb-6">
                <p class="text-sm font-semibold text-gray-700 mb-3">Kategori Dokumen:</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($kategori_dokumen as $kat): ?>
                        <span class="px-3 py-1.5 rounded-lg bg-teal-50 text-teal-600 text-xs font-medium border border-teal-200 hover:bg-teal-100 transition cursor-pointer">
                            <?= $kat['icon'] ?> <?= $kat['name'] ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Folder Aktif -->
            <div>
                <p class="text-sm font-semibold text-gray-700 mb-3">Folder Aktif:</p>
                <div class="space-y-2">
                    <?php foreach ($folder_aktif as $folder): ?>
                        <div class="px-3 py-2.5 rounded-lg bg-gray-50 border border-gray-200 text-gray-700 text-xs flex items-center gap-2 hover:bg-gray-100 transition">
                            <i class="fa-regular fa-folder text-gray-400"></i> <?= $folder ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

    </div>

</div>

<!-- Chart Script -->
<script>
const ctx = document.getElementById('clusterChart');
if (ctx) {
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($cluster_data['labels']) ?>,
            datasets: [
                {
                    label: 'Cluster A (Sempurna)',
                    data: <?= json_encode($cluster_data['clusterA']) ?>,
                    borderColor: '#5eead4',
                    backgroundColor: 'rgba(94, 234, 212, 0.1)',
                    tension: 0.4,
                    borderWidth: 3,
                    pointRadius: 5,
                    pointBackgroundColor: '#5eead4',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    fill: false
                },
                {
                    label: 'Cluster B (Terlambat)',
                    data: <?= json_encode($cluster_data['clusterB']) ?>,
                    borderColor: '#fb923c',
                    backgroundColor: 'rgba(251, 146, 60, 0.1)',
                    tension: 0.4,
                    borderWidth: 3,
                    pointRadius: 5,
                    pointBackgroundColor: '#fb923c',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    fill: false
                },
                {
                    label: 'Cluster C (Rendah)',
                    data: <?= json_encode($cluster_data['clusterC']) ?>,
                    borderColor: '#f87171',
                    backgroundColor: 'rgba(248, 113, 113, 0.1)',
                    tension: 0.4,
                    borderWidth: 3,
                    pointRadius: 5,
                    pointBackgroundColor: '#f87171',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.85)',
                    padding: 12,
                    titleFont: { size: 13, weight: 'bold' },
                    bodyFont: { size: 12 },
                    borderColor: '#e5e7eb',
                    borderWidth: 1,
                    cornerRadius: 8
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 80,
                    ticks: {
                        stepSize: 20,
                        font: { size: 11 },
                        color: '#9ca3af'
                    },
                    grid: {
                        color: '#f3f4f6',
                        drawBorder: false
                    }
                },
                x: {
                    ticks: {
                        font: { size: 11 },
                        color: '#9ca3af'
                    },
                    grid: {
                        display: false,
                        drawBorder: false
                    }
                }
            }
        }
    });
}
</script>