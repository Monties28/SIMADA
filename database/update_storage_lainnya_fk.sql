-- Update database SIMADA untuk fitur jenis dokumen lainnya dan foreign key user-dokumen.
-- Jalankan hanya jika database lama belum memakai struktur terbaru.
-- Backup database terlebih dahulu sebelum menjalankan script ini.

ALTER TABLE documents
  ADD COLUMN jenis_dokumen_lainnya VARCHAR(255) NULL AFTER jenis_dokumen;

ALTER TABLE users
  MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE documents
  MODIFY user_id BIGINT UNSIGNED NOT NULL;

CREATE INDEX idx_documents_user_id ON documents(user_id);

ALTER TABLE documents
  ADD CONSTRAINT fk_documents_user
  FOREIGN KEY (user_id) REFERENCES users(id)
  ON UPDATE CASCADE
  ON DELETE RESTRICT;
