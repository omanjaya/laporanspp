-- CreateTable
CREATE TABLE "rekon_data" (
    "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "sekolah" TEXT NOT NULL,
    "idSiswa" TEXT NOT NULL,
    "namaSiswa" TEXT NOT NULL,
    "alamat" TEXT,
    "kelas" TEXT NOT NULL,
    "jurusan" TEXT NOT NULL,
    "jum_tagihan" INTEGER NOT NULL,
    "biaya_adm" INTEGER NOT NULL DEFAULT 0,
    "tagihan_lain" INTEGER NOT NULL DEFAULT 0,
    "ket_tagihan_lain" TEXT,
    "keterangan" TEXT,
    "tahun" INTEGER NOT NULL,
    "bulan" INTEGER NOT NULL,
    "dana_masyarakat" TEXT NOT NULL,
    "tgl_tx" DATETIME NOT NULL,
    "tgl_tx_formatted" TEXT NOT NULL,
    "sts_bayar" INTEGER NOT NULL,
    "kd_cab" TEXT NOT NULL,
    "kd_user" TEXT NOT NULL,
    "sts_reversal" INTEGER NOT NULL DEFAULT 0,
    "no_bukti" TEXT NOT NULL,
    "created_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updated_at" DATETIME NOT NULL,
    "school_id" INTEGER,
    CONSTRAINT "rekon_data_school_id_fkey" FOREIGN KEY ("school_id") REFERENCES "schools" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateTable
CREATE TABLE "schools" (
    "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "name" TEXT NOT NULL,
    "code" TEXT NOT NULL,
    "address" TEXT,
    "phone" TEXT,
    "email" TEXT,
    "is_active" BOOLEAN NOT NULL DEFAULT true,
    "created_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updated_at" DATETIME NOT NULL
);

-- CreateTable
CREATE TABLE "users" (
    "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "email" TEXT NOT NULL,
    "name" TEXT NOT NULL,
    "password" TEXT NOT NULL,
    "role" TEXT NOT NULL DEFAULT 'user',
    "school_id" INTEGER,
    "is_active" BOOLEAN NOT NULL DEFAULT true,
    "created_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updated_at" DATETIME NOT NULL,
    CONSTRAINT "users_school_id_fkey" FOREIGN KEY ("school_id") REFERENCES "schools" ("id") ON DELETE SET NULL ON UPDATE CASCADE
);

-- CreateIndex
CREATE INDEX "idx_sekolah_tahun_bulan" ON "rekon_data"("sekolah", "tahun", "bulan");

-- CreateIndex
CREATE INDEX "idx_siswa_tahun_bulan" ON "rekon_data"("idSiswa", "tahun", "bulan");

-- CreateIndex
CREATE INDEX "idx_nama_siswa" ON "rekon_data"("namaSiswa");

-- CreateIndex
CREATE INDEX "idx_no_bukti" ON "rekon_data"("no_bukti");

-- CreateIndex
CREATE UNIQUE INDEX "schools_name_key" ON "schools"("name");

-- CreateIndex
CREATE UNIQUE INDEX "schools_code_key" ON "schools"("code");

-- CreateIndex
CREATE UNIQUE INDEX "users_email_key" ON "users"("email");
