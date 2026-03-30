<?php
/*
 * pages/pustaka.php
 * Fragment — di-include oleh user/dashboard.php
 * JANGAN tambahkan session_start(), <!DOCTYPE>, <head>, <body>, sidebar di sini
 */
?>

<div class="space-y-6">

    <!-- FILTER -->
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <h3 class="font-semibold text-gray-700 flex items-center gap-2 mb-4 text-sm">
            <i class="fa-solid fa-filter text-primary"></i> Filter Dokumen
        </h3>

        <div class="grid grid-cols-3 gap-4 text-sm">
            <div>
                <label class="font-medium text-gray-700">Pencarian Cepat</label>
                <div class="relative mt-1">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input type="text" id="searchInput"
                           placeholder="Cari nama file, jenis, atau user..."
                           class="w-full pl-8 p-2 rounded-lg border focus:outline-none focus:border-primary">
                </div>
            </div>

            <div>
                <label class="font-medium text-gray-700">Tahun</label>
                <select id="filterTahun" class="w-full mt-1 p-2 rounded-lg border text-sm focus:outline-none focus:border-primary">
                    <option value="">Semua Tahun</option>
                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                        <option><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div>
                <label class="font-medium text-gray-700">Jenis Dokumen</label>
                <select id="filterJenis" class="w-full mt-1 p-2 rounded-lg border text-sm focus:outline-none focus:border-primary">
                    <option value="">Semua Jenis</option>
                    <option>KHS</option>
                    <option>Transkrip</option>
                    <option>Laporan</option>
                    <option>Proposal</option>
                    <option>Surat Keputusan</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mt-4 text-sm">
            <div>
                <label class="font-medium text-gray-700">Tanggal Dari</label>
                <input type="date" class="w-full mt-1 p-2 rounded-lg border focus:outline-none focus:border-primary">
            </div>
            <div>
                <label class="font-medium text-gray-700">Tanggal Sampai</label>
                <input type="date" class="w-full mt-1 p-2 rounded-lg border focus:outline-none focus:border-primary">
            </div>
        </div>

        <div class="mt-4 flex gap-3">
            <button class="px-5 py-2 bg-primary text-white text-sm rounded-lg hover:bg-primaryDark transition">
                <i class="fa-solid fa-magnifying-glass mr-1"></i> Cari
            </button>
            <button class="px-5 py-2 bg-gray-100 text-gray-600 text-sm rounded-lg hover:bg-gray-200 transition">
                Reset Filter
            </button>
        </div>
    </div>

    <!-- PENCARIAN TERBARU -->
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <h3 class="font-semibold text-gray-700 flex items-center gap-2 mb-3 text-sm">
            <i class="fa-regular fa-clock text-primary"></i> Pencarian Terbaru
        </h3>
        <div class="flex gap-2 flex-wrap">
            <?php foreach (['KHS', 'Laporan', 'Transkrip', 'Proposal'] as $tag): ?>
                <span class="px-3 py-1 rounded-lg bg-primarySoft text-primary text-xs cursor-pointer hover:bg-primary hover:text-white transition">
                    📄 <?= $tag ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- DAFTAR DOKUMEN / EMPTY STATE -->
    <div class="bg-white rounded-xl p-6 shadow-sm">
        <h3 class="font-semibold text-gray-700 flex items-center gap-2 mb-4 text-sm">
            <i class="fa-solid fa-folder text-primary"></i> Daftar Dokumen
        </h3>

        <?php
        /*
         * TODO: Ganti dengan query ke database
         * Contoh: $docs = $pdo->query("SELECT * FROM documents WHERE user_id = ...")->fetchAll();
         */
        $docs = []; // Kosong sementara
        ?>

        <?php if (empty($docs)): ?>
            <div class="text-center py-12 text-gray-300">
                <i class="fa-regular fa-folder-open text-6xl mb-3"></i>
                <p class="text-sm text-gray-400">Belum ada dokumen ditemukan</p>
                <a href="dashboard.php?page=upload"
                   class="inline-block mt-4 px-5 py-2 bg-primary text-white text-sm rounded-lg hover:bg-primaryDark transition">
                    <i class="fa-solid fa-cloud-arrow-up mr-1"></i> Upload Dokumen
                </a>
            </div>
        <?php else: ?>
            <!-- Tabel dokumen (tampil jika ada data) -->
            <table class="w-full text-sm text-left">
                <thead>
                    <tr class="border-b text-gray-400 text-xs uppercase">
                        <th class="pb-3 font-medium">Nama File</th>
                        <th class="pb-3 font-medium">Jenis</th>
                        <th class="pb-3 font-medium">Tahun</th>
                        <th class="pb-3 font-medium">Tanggal Upload</th>
                        <th class="pb-3 font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($docs as $doc): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-3"><?= htmlspecialchars($doc['name']) ?></td>
                            <td class="py-3"><?= htmlspecialchars($doc['type']) ?></td>
                            <td class="py-3"><?= htmlspecialchars($doc['year']) ?></td>
                            <td class="py-3"><?= htmlspecialchars($doc['created_at']) ?></td>
                            <td class="py-3 flex gap-2">
                                <button class="text-primary hover:underline"><i class="fa-solid fa-eye"></i></button>
                                <button class="text-red-400 hover:underline"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>