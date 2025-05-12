#!/bin/bash
set -e

echo "Memperbaiki masalah rute Livewire..."

# Check if docker-compose is running
if ! docker-compose ps | grep -q "app.*Up"; then
    echo "Error: Docker containers tidak berjalan. Silakan jalankan 'docker-compose up -d' terlebih dahulu."
    exit 1
fi

# Run the PHP script
docker-compose exec app php fix-livewire-route.php

# Tambahkan rute Livewire yang benar ke routes/web.php
echo "Menambahkan rute Livewire yang benar ke routes/web.php..."
docker-compose exec app bash -c "
# Cek versi Livewire
LIVEWIRE_VERSION=\$(grep -o '\"livewire/livewire\": \".*\"' composer.lock | grep -o '[0-9]\+\.[0-9]\+\.[0-9]\+' | head -1)
echo \"Livewire version: \$LIVEWIRE_VERSION\"

# Hapus rute livewire/upload-file yang mungkin sudah ada
sed -i '/livewire\/upload-file/d' routes/web.php

# Tambahkan rute yang benar berdasarkan versi Livewire
if [[ \"\$LIVEWIRE_VERSION\" =~ ^3\. ]]; then
    # Livewire 3
    echo \"
// Fix untuk masalah upload file (Livewire 3)
Route::post('livewire/upload-file', [\\\\Livewire\\\\Features\\\\SupportFileUploads\\\\FileUploadController::class, 'handle'])
    ->name('livewire.upload-file')
    ->middleware(['web']);
\" >> routes/web.php
    echo \"Rute Livewire 3 berhasil ditambahkan.\"
else
    # Livewire 2
    echo \"
// Fix untuk masalah upload file (Livewire 2)
Route::post('livewire/upload-file', [\\\\Livewire\\\\Controllers\\\\FileUploadHandler::class, 'handle'])
    ->name('livewire.upload-file')
    ->middleware(['web']);
\" >> routes/web.php
    echo \"Rute Livewire 2 berhasil ditambahkan.\"
fi
"

# Clear cache
echo "Membersihkan cache..."
docker-compose exec app php artisan optimize:clear

echo "Selesai! Silakan coba upload file lagi."
