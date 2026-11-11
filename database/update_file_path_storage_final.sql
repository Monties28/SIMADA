-- Migrasi opsional untuk menyamakan path database lama dengan struktur storage final.
-- Jalankan hanya jika file fisik lama sudah Anda pindahkan ke SIMADA_PENYIMPANAN.
-- Struktur final:
-- Status/Kategori/Jenis/2026-05-08/Nama_User/file.pdf
-- Catatan: script ini tidak otomatis memindahkan file fisik di Windows.
-- Untuk data lama, lebih aman upload ulang atau pindahkan file fisik sesuai file_path baru.

-- File path baru akan otomatis dipakai untuk upload baru dari aplikasi.
