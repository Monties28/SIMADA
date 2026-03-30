<?php
/*
 * pages/upload.php
 * Fragment — di-include oleh user/dashboard.php
 * JANGAN tambahkan session_start(), <!DOCTYPE>, <head>, <body>, sidebar di sini
 */
?>

<div class="space-y-6">

    <!-- DROPZONE -->
    <div id="dropzone"
         class="bg-white rounded-xl p-10 text-center shadow-sm border-2 border-dashed border-primary/40 cursor-pointer hover:border-primary transition"
         onclick="document.getElementById('fileInput').click()">
        <input type="file" id="fileInput" class="hidden" accept=".pdf,.doc,.docx">
        <div class="w-16 h-16 mx-auto bg-primarySoft rounded-full flex items-center justify-center mb-4">
            <i class="fa-solid fa-cloud-arrow-up text-primary text-2xl"></i>
        </div>
        <h3 class="font-semibold text-gray-700">Klik atau drag file ke sini</h3>
        <p class="text-sm text-gray-400 mt-1">Format: PDF, DOC, DOCX — Maks. 10MB</p>
        <p id="fileName" class="text-sm text-primary mt-3 hidden"></p>
    </div>

    <!-- KLASIFIKASI -->
    <div class="bg-primarySoft border border-primary/30 rounded-xl p-6">
        <h3 class="font-semibold text-sm text-gray-700 mb-4">
            <i class="fa-solid fa-folder-open text-primary mr-2"></i> Klasifikasi Dokumen
        </h3>

        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="text-sm font-medium text-gray-700">Folder Tahun <span class="text-red-500">*</span></label>
                <select id="selectTahun" class="w-full mt-1 p-2 rounded-lg border text-sm focus:outline-none focus:border-primary">
                    <option value="">Pilih tahun...</option>
                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-700">Subfolder / Periode <span class="text-red-500">*</span></label>
                <select class="w-full mt-1 p-2 rounded-lg border text-sm focus:outline-none focus:border-primary">
                    <option value="">Pilih periode...</option>
                    <option>Semester 1</option>
                    <option>Semester 2</option>
                    <option>Triwulan 1</option>
                    <option>Triwulan 2</option>
                    <option>Triwulan 3</option>
                    <option>Triwulan 4</option>
                </select>
            </div>
        </div>

        <div>
            <label class="text-sm font-medium text-gray-700">Jenis Dokumen <span class="text-red-500">*</span></label>
            <select class="w-full mt-1 p-2 rounded-lg border text-sm focus:outline-none focus:border-primary">
                <option value="">Pilih jenis dokumen...</option>
                <option>KHS</option>
                <option>Transkrip</option>
                <option>Laporan</option>
                <option>Proposal</option>
                <option>Surat Keputusan</option>
            </select>
        </div>
    </div>

    <!-- METODE PROCESSING -->
    <div class="bg-white rounded-xl p-6 shadow-sm space-y-4">
        <h3 class="font-semibold text-sm text-gray-700">Pilih Metode Processing</h3>

        <label class="flex gap-3 text-sm cursor-pointer">
            <input type="checkbox" checked class="mt-1 accent-primary">
            <div>
                <p class="font-medium">Gunakan OCR</p>
                <p class="text-xs text-gray-400">Ekstraksi teks otomatis dari dokumen</p>
            </div>
        </label>

        <label class="flex gap-3 text-sm cursor-pointer">
            <input type="checkbox" class="mt-1 accent-primary">
            <div>
                <p class="font-medium">Enkripsi Dokumen</p>
                <p class="text-xs text-gray-400">Enkripsi AES-256 untuk keamanan file</p>
            </div>
        </label>
    </div>

    <!-- ACTION BUTTONS -->
    <div class="flex items-center gap-4">
        <button onclick="handleUpload()"
            class="flex-1 py-2.5 rounded-lg bg-primary text-white hover:bg-primaryDark transition font-medium text-sm">
            <i class="fa-solid fa-cloud-arrow-up mr-2"></i> Upload & Process
        </button>
        <button onclick="resetForm()"
            class="px-10 py-2.5 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition text-sm">
            Reset
        </button>
    </div>

</div>

<script>
/* Preview nama file saat dipilih */
document.getElementById('fileInput').addEventListener('change', function () {
    const name = this.files[0]?.name;
    const el   = document.getElementById('fileName');
    if (name) {
        el.textContent = '📄 ' + name;
        el.classList.remove('hidden');
    }
});

function handleUpload() {
    const file = document.getElementById('fileInput').files[0];
    if (!file) {
        alert('Pilih file terlebih dahulu.');
        return;
    }
    /* TODO: kirim ke server via fetch/AJAX atau form POST */
    alert('Upload: ' + file.name + '\n(Sambungkan ke backend untuk proses sesungguhnya)');
}

function resetForm() {
    document.getElementById('fileInput').value = '';
    document.getElementById('fileName').classList.add('hidden');
}
</script>