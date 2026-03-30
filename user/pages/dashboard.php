<?php
/*
 * pages/dashboard.php
 * Fragment — di-include oleh user/dashboard.php
 * JANGAN tambahkan session_start(), <!DOCTYPE>, <head>, <body>, sidebar di sini
 */
?>

<!-- STAT CARDS -->
<div class="grid grid-cols-4 gap-5 mb-6">

    <div class="bg-white rounded-xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-primarySoft flex items-center justify-center">
            <i class="fa-solid fa-file-lines text-primary text-xl"></i>
        </div>
        <div>
            <p class="text-xs text-gray-400">Total Dokumen</p>
            <p class="text-2xl font-bold text-gray-700">0</p>
        </div>
    </div>

    <div class="bg-white rounded-xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center">
            <i class="fa-solid fa-cloud-arrow-up text-blue-400 text-xl"></i>
        </div>
        <div>
            <p class="text-xs text-gray-400">Upload Bulan Ini</p>
            <p class="text-2xl font-bold text-gray-700">0</p>
        </div>
    </div>

    <div class="bg-white rounded-xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-yellow-50 flex items-center justify-center">
            <i class="fa-solid fa-hourglass-half text-yellow-400 text-xl"></i>
        </div>
        <div>
            <p class="text-xs text-gray-400">Menunggu Proses</p>
            <p class="text-2xl font-bold text-gray-700">0</p>
        </div>
    </div>

    <div class="bg-white rounded-xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center">
            <i class="fa-solid fa-circle-check text-green-400 text-xl"></i>
        </div>
        <div>
            <p class="text-xs text-gray-400">Selesai Diproses</p>
            <p class="text-2xl font-bold text-gray-700">0</p>
        </div>
    </div>

</div>

<!-- ROW 2: CHART + AKTIVITAS TERBARU -->
<div class="grid grid-cols-3 gap-5">

    <!-- Chart -->
    <div class="col-span-2 bg-white rounded-xl p-6 shadow-sm">
        <h3 class="font-semibold text-gray-700 mb-4 text-sm">
            <i class="fa-solid fa-chart-line text-primary mr-2"></i> Upload Per Bulan
        </h3>
        <canvas id="chartUpload" height="120"></canvas>
    </div>

    <!-- Aktivitas Terbaru -->
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <h3 class="font-semibold text-gray-700 mb-4 text-sm">
            <i class="fa-regular fa-clock text-primary mr-2"></i> Aktivitas Terbaru
        </h3>
        <div class="text-center py-8 text-gray-300">
            <i class="fa-regular fa-folder-open text-5xl mb-3"></i>
            <p class="text-sm">Belum ada aktivitas</p>
        </div>
    </div>

</div>

<!-- Quick Actions -->
<div class="mt-5 bg-white rounded-xl p-6 shadow-sm">
    <h3 class="font-semibold text-gray-700 mb-4 text-sm">
        <i class="fa-solid fa-bolt text-primary mr-2"></i> Aksi Cepat
    </h3>
    <div class="flex gap-3">
        <a href="dashboard.php?page=upload"
           class="flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primaryDark transition">
            <i class="fa-solid fa-cloud-arrow-up"></i> Upload Dokumen
        </a>
        <a href="dashboard.php?page=pustaka"
           class="flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">
            <i class="fa-solid fa-folder-open"></i> Lihat Pustaka
        </a>
    </div>
</div>

<!-- Chart Script -->
<script>
const ctx = document.getElementById('chartUpload').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'],
        datasets: [{
            label: 'Jumlah Upload',
            data: [0,0,0,0,0,0,0,0,0,0,0,0],
            backgroundColor: '#5BB4B1',
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 } }
        }
    }
});
</script>