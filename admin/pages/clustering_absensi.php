<?php
/* =========================================================
   CLUSTERING ABSENSI SIMADA
   Metode: scoring/k-means ringan berbasis fitur hadir, izin, sakit, alpha, terlambat.
========================================================= */

declare(strict_types=1);

$db = appDb();
$flashMsg = '';
$flashMsgType = 'success';
$periode = trim((string) ($_GET['periode'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $periode)) {
    $periode = date('Y-m');
}

if (!$db) {
    echo '<div class="rounded-2xl bg-red-50 border border-red-200 text-red-700 p-4">Koneksi database tidak tersedia.</div>';
    return;
}
appEnsureSimadaCoreSchema($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $periodePost = trim((string) ($_POST['periode_bulan'] ?? date('Y-m')));
    if (!preg_match('/^\d{4}-\d{2}$/', $periodePost)) {
        $periodePost = date('Y-m');
    }
    $periode = $periodePost;

    if ($action === 'add_record') {
        $employeeName = trim((string) ($_POST['employee_name'] ?? ''));
        $hadir = max(0, (int) ($_POST['hadir'] ?? 0));
        $izin = max(0, (int) ($_POST['izin'] ?? 0));
        $sakit = max(0, (int) ($_POST['sakit'] ?? 0));
        $alpha = max(0, (int) ($_POST['alpha'] ?? 0));
        $terlambat = max(0, (int) ($_POST['terlambat'] ?? 0));

        if ($employeeName === '') {
            $flashMsg = 'Nama pegawai wajib diisi.';
            $flashMsgType = 'error';
        } else {
            [$clusterLabel, $clusterScore] = appAttendanceClusterLabel(compact('hadir','izin','sakit','alpha','terlambat'));
            $stmt = $db->prepare('INSERT INTO attendance_records (employee_name, periode_bulan, hadir, izin, sakit, alpha, terlambat, cluster_label, cluster_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param('ssiiiiisd', $employeeName, $periodePost, $hadir, $izin, $sakit, $alpha, $terlambat, $clusterLabel, $clusterScore);
                $stmt->execute();
                $stmt->close();
                $flashMsg = 'Data absensi berhasil ditambahkan dan diklaster.';
            }
        }
    }

    if ($action === 'seed_sample') {
        $samples = [
            ['Herwansyah', 22, 0, 0, 0, 1],
            ['Heni Triliyanawati', 20, 1, 1, 0, 2],
            ['Fahrony Hidayat', 18, 2, 1, 1, 4],
            ['Muhammad Ripai', 15, 1, 2, 4, 7],
        ];
        $stmt = $db->prepare('INSERT INTO attendance_records (employee_name, periode_bulan, hadir, izin, sakit, alpha, terlambat, cluster_label, cluster_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($samples as $sample) {
            [$name, $hadir, $izin, $sakit, $alpha, $terlambat] = $sample;
            [$label, $score] = appAttendanceClusterLabel(compact('hadir','izin','sakit','alpha','terlambat'));
            $stmt->bind_param('ssiiiiisd', $name, $periodePost, $hadir, $izin, $sakit, $alpha, $terlambat, $label, $score);
            $stmt->execute();
        }
        if ($stmt) { $stmt->close(); }
        $flashMsg = 'Data contoh absensi berhasil dibuat.';
    }

    if ($action === 'recluster') {
        $result = $db->query("SELECT id, hadir, izin, sakit, alpha, terlambat FROM attendance_records WHERE periode_bulan = '" . $db->real_escape_string($periodePost) . "'");
        while ($result && $row = $result->fetch_assoc()) {
            [$label, $score] = appAttendanceClusterLabel($row);
            $stmtUpdate = $db->prepare('UPDATE attendance_records SET cluster_label = ?, cluster_score = ? WHERE id = ?');
            if ($stmtUpdate) {
                $id = (int) $row['id'];
                $stmtUpdate->bind_param('sdi', $label, $score, $id);
                $stmtUpdate->execute();
                $stmtUpdate->close();
            }
        }
        $flashMsg = 'Clustering absensi berhasil dihitung ulang.';
    }
}

$records = [];
$stmt = $db->prepare('SELECT * FROM attendance_records WHERE periode_bulan = ? ORDER BY cluster_score DESC, employee_name ASC');
$stmt->bind_param('s', $periode);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}
$stmt->close();

$summary = [];
foreach ($records as $record) {
    $label = (string) ($record['cluster_label'] ?? 'Belum Diklaster');
    $summary[$label] = ($summary[$label] ?? 0) + 1;
}
?>

<div class="space-y-6">
    <?php if ($flashMsg !== ''): ?>
        <div class="rounded-2xl p-4 text-sm <?= $flashMsgType === 'error' ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-emerald-50 border border-emerald-200 text-emerald-700' ?>">
            <?= e($flashMsg) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-3xl border border-slate-200 shadow-soft p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h3 class="text-xl font-bold text-slate-900">Clustering Absensi</h3>
                <p class="text-sm text-slate-500 mt-1">Kelompokkan pola absensi pegawai berdasarkan kehadiran, izin, sakit, alpha, dan keterlambatan.</p>
            </div>
            <form method="GET" action="main.php" class="flex gap-2">
                <input type="hidden" name="page" value="clustering_absensi">
                <input type="month" name="periode" value="<?= e($periode) ?>" class="rounded-2xl border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:border-primary">
                <button class="rounded-2xl bg-primary text-white px-4 py-2.5 text-sm font-semibold">Filter</button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <?php foreach (['Disiplin Tinggi','Stabil','Perlu Pemantauan','Risiko Absensi'] as $label): ?>
            <div class="bg-white rounded-3xl border border-slate-200 shadow-soft p-5">
                <p class="text-sm text-slate-500"><?= e($label) ?></p>
                <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= number_format($summary[$label] ?? 0) ?></h3>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <form method="POST" class="bg-white rounded-3xl border border-slate-200 shadow-soft p-6 space-y-4">
            <input type="hidden" name="action" value="add_record">
            <h4 class="font-bold text-slate-900">Input Data Absensi</h4>
            <input type="month" name="periode_bulan" value="<?= e($periode) ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm">
            <input name="employee_name" placeholder="Nama pegawai" required class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm">
            <div class="grid grid-cols-2 gap-3">
                <input type="number" name="hadir" min="0" placeholder="Hadir" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                <input type="number" name="izin" min="0" placeholder="Izin" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                <input type="number" name="sakit" min="0" placeholder="Sakit" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                <input type="number" name="alpha" min="0" placeholder="Alpha" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                <input type="number" name="terlambat" min="0" placeholder="Terlambat" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm col-span-2">
            </div>
            <button class="w-full rounded-2xl bg-primary text-white px-4 py-3 text-sm font-semibold">Simpan & Klaster</button>
        </form>

        <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-soft p-6 overflow-x-auto">
            <div class="flex items-center justify-between mb-4 gap-3">
                <h4 class="font-bold text-slate-900">Hasil Clustering <?= e($periode) ?></h4>
                <div class="flex gap-2">
                    <form method="POST"><input type="hidden" name="action" value="seed_sample"><input type="hidden" name="periode_bulan" value="<?= e($periode) ?>"><button class="px-3 py-2 rounded-2xl border border-slate-200 text-sm">Data Contoh</button></form>
                    <form method="POST"><input type="hidden" name="action" value="recluster"><input type="hidden" name="periode_bulan" value="<?= e($periode) ?>"><button class="px-3 py-2 rounded-2xl bg-slate-900 text-white text-sm">Hitung Ulang</button></form>
                </div>
            </div>
            <table class="w-full text-sm">
                <thead class="text-left text-slate-500 border-b"><tr><th class="py-3">Pegawai</th><th>H</th><th>I</th><th>S</th><th>A</th><th>T</th><th>Score</th><th>Cluster</th></tr></thead>
                <tbody class="divide-y">
                    <?php if (!$records): ?><tr><td colspan="8" class="py-8 text-center text-slate-400">Belum ada data absensi.</td></tr><?php endif; ?>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td class="py-3 font-medium text-slate-800"><?= e($record['employee_name'] ?? '') ?></td>
                            <td><?= (int) $record['hadir'] ?></td><td><?= (int) $record['izin'] ?></td><td><?= (int) $record['sakit'] ?></td><td><?= (int) $record['alpha'] ?></td><td><?= (int) $record['terlambat'] ?></td>
                            <td><?= e((string) $record['cluster_score']) ?></td>
                            <td><span class="px-2.5 py-1 rounded-xl bg-primarySoft text-primary text-xs font-semibold"><?= e($record['cluster_label'] ?? '-') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Sortable table headers
document.querySelectorAll('th[data-sort]').forEach(th => {
    th.style.cursor = 'pointer';
    th.title = 'Klik untuk mengurutkan';
    th.addEventListener('click', () => {
        const table = th.closest('table');
        const col = [...th.parentElement.children].indexOf(th);
        const asc = th.dataset.dir !== 'asc';
        document.querySelectorAll('th[data-sort]').forEach(t => {
            delete t.dataset.dir;
            t.querySelector('.sort-icon')?.remove();
        });
        th.dataset.dir = asc ? 'asc' : 'desc';
        const icon = document.createElement('i');
        icon.className = 'sort-icon fa-solid ' + (asc ? 'fa-sort-up' : 'fa-sort-down') + ' ml-1 text-primary text-xs';
        th.appendChild(icon);
        [...table.querySelectorAll('tbody tr')]
            .sort((a, b) => {
                const x = a.cells[col]?.textContent.trim() ?? '';
                const y = b.cells[col]?.textContent.trim() ?? '';
                return asc ? x.localeCompare(y, 'id') : y.localeCompare(x, 'id');
            })
            .forEach(tr => table.querySelector('tbody').appendChild(tr));
    });
});
// Loading state on form submit
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const btn = this.querySelector('[type=submit]:not([data-no-load])');
        if (btn && !btn.disabled) {
            btn.disabled = true;
            const orig = btn.innerHTML;
            btn.setAttribute('data-orig', orig);
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Memproses...';
            setTimeout(() => { btn.disabled = false; btn.innerHTML = orig; }, 8000);
        }
    });
});
</script>

<script>
function showToast(msg, type) {
    type = type || 'success';
    const colors = { success: 'bg-emerald-600', error: 'bg-red-600', info: 'bg-blue-600', warning: 'bg-amber-500' };
    const icons  = { success: 'fa-circle-check', error: 'fa-circle-xmark', info: 'fa-circle-info', warning: 'fa-triangle-exclamation' };
    const t = document.createElement('div');
    t.className = 'fixed top-5 right-5 z-[9999] flex items-center gap-2.5 px-4 py-3 rounded-xl text-white text-sm font-medium shadow-lg ' + (colors[type] || colors.success);
    t.style.cssText = 'opacity:0;transform:translateX(20px);transition:all .25s ease';
    t.innerHTML = '<i class="fa-solid ' + (icons[type] || icons.success) + '"></i><span>' + msg + '</span>';
    document.body.appendChild(t);
    requestAnimationFrame(() => { t.style.opacity='1'; t.style.transform='translateX(0)'; });
    setTimeout(() => { t.style.opacity='0'; t.style.transform='translateX(20px)'; setTimeout(() => t.remove(), 300); }, 3500);
}
// Auto-show flash messages as toast if present
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-toast-msg]').forEach(el => {
        showToast(el.dataset.toastMsg, el.dataset.toastType || 'success');
        el.remove();
    });
});
</script>

