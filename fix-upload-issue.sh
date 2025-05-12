#!/bin/bash
set -e

echo "Memperbaiki masalah upload file di Filament..."

# Check if docker-compose is running
if ! docker-compose ps | grep -q "app.*Up"; then
    echo "Error: Docker containers tidak berjalan. Silakan jalankan 'docker-compose up -d' terlebih dahulu."
    exit 1
fi

# Run the PHP script
docker-compose exec app php fix-upload-issue.php

# Tambahkan rute Livewire upload-file jika belum ada
echo "Menambahkan rute Livewire upload-file ke routes/web.php..."
docker-compose exec app bash -c "
if ! grep -q \"livewire/upload-file\" routes/web.php; then
    echo \"
// Fix untuk masalah upload file
Route::post('livewire/upload-file', [\\\Livewire\\\Controllers\\\FileUploadHandler::class, 'handle'])
    ->middleware(['web', 'auth']);
\" >> routes/web.php
    echo \"Rute livewire/upload-file berhasil ditambahkan.\"
else
    echo \"Rute livewire/upload-file sudah ada di routes/web.php.\"
fi
"

# Clear cache
echo "Membersihkan cache..."
docker-compose exec app php artisan optimize:clear

echo "Selesai! Silakan coba upload file lagi."
