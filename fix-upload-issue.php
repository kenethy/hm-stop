<?php

// Script untuk memperbaiki masalah upload file

// Load Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request = Illuminate\Http\Request::capture());

// Start the session
$session = $app->make('session');
$session->start();

echo "=== Memperbaiki Masalah Upload File ===\n\n";

// 1. Periksa konfigurasi CSRF
echo "1. Memeriksa konfigurasi CSRF...\n";
$exceptUrls = \Illuminate\Support\Facades\Config::get('app.debug_blacklist', []);
echo "CSRF Exception URLs: " . json_encode($exceptUrls) . "\n\n";

// 2. Periksa middleware untuk rute Livewire
echo "2. Memeriksa middleware untuk rute Livewire...\n";
$routes = \Illuminate\Support\Facades\Route::getRoutes();
$livewireRoutes = [];

foreach ($routes as $route) {
    if (strpos($route->uri, 'livewire') !== false) {
        $livewireRoutes[] = [
            'uri' => $route->uri,
            'methods' => $route->methods,
            'middleware' => $route->action['middleware'] ?? []
        ];
    }
}

echo "Livewire Routes: " . json_encode($livewireRoutes, JSON_PRETTY_PRINT) . "\n\n";

// 3. Periksa konfigurasi filesystem
echo "3. Memeriksa konfigurasi filesystem...\n";
$diskConfig = \Illuminate\Support\Facades\Config::get('filesystems.disks', []);
echo "Disk Configuration: " . json_encode($diskConfig, JSON_PRETTY_PRINT) . "\n\n";

// 4. Periksa izin direktori penyimpanan
echo "4. Memeriksa izin direktori penyimpanan...\n";
$storagePath = storage_path('app/public');
$permissions = substr(sprintf('%o', fileperms($storagePath)), -4);
echo "Storage Path: $storagePath\n";
echo "Permissions: $permissions\n\n";

// 5. Periksa konfigurasi Livewire
echo "5. Memeriksa konfigurasi Livewire...\n";
$livewireConfig = \Illuminate\Support\Facades\Config::get('livewire', []);
echo "Livewire Configuration: " . json_encode($livewireConfig, JSON_PRETTY_PRINT) . "\n\n";

echo "=== Selesai ===\n";

// Tambahkan rute Livewire upload-file jika belum ada
echo "Menambahkan rute Livewire upload-file jika belum ada...\n";
$routeExists = false;

foreach ($routes as $route) {
    if ($route->uri === 'livewire/upload-file' && in_array('POST', $route->methods)) {
        $routeExists = true;
        break;
    }
}

if (!$routeExists) {
    echo "Rute livewire/upload-file tidak ditemukan. Perlu ditambahkan secara manual.\n";
    echo "Tambahkan rute berikut ke routes/web.php:\n\n";
    echo "Route::post('livewire/upload-file', [\Livewire\Controllers\FileUploadHandler::class, 'handle'])\n";
    echo "    ->middleware(['web', 'auth']);\n\n";
} else {
    echo "Rute livewire/upload-file sudah ada.\n";
}

echo "Selesai memeriksa konfigurasi.\n";
