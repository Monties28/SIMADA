-- UPDATE MASTER JENIS DOKUMEN LAINNYA UNTUK SEMUA KATEGORI
-- Jalankan di database simada jika pilihan Lainnya belum muncul di semua kategori.

INSERT INTO document_types (category_id, nama_jenis, slug_jenis, is_active, sort_order)
SELECT c.id, 'Lainnya', 'lainnya', 1, 999
FROM document_categories c
WHERE c.is_active = 1
ON DUPLICATE KEY UPDATE
  nama_jenis = VALUES(nama_jenis),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);
