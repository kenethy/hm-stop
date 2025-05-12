<?php

// Script untuk memperbaiki masalah upload file Livewire

// Load Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request = Illuminate\Http\Request::capture());

// Start the session
$session = $app->make('session');
$session->start();

echo "=== Memperbaiki Masalah Upload File Livewire ===\n\n";

// 1. Periksa versi Livewire
echo "1. Memeriksa versi Livewire...\n";
$composerLock = json_decode(file_get_contents(__DIR__ . '/composer.lock'), true);
$livewireVersion = null;

foreach ($composerLock['packages'] as $package) {
    if ($package['name'] === 'livewire/livewire') {
        $livewireVersion = $package['version'];
        break;
    }
}

echo "Livewire Version: " . ($livewireVersion ?? 'Not found') . "\n\n";

// 2. Periksa rute Livewire yang ada
echo "2. Memeriksa rute Livewire yang ada...\n";
$routes = \Illuminate\Support\Facades\Route::getRoutes();
$livewireRoutes = [];

foreach ($routes as $route) {
    if (strpos($route->uri, 'livewire') !== false) {
        $livewireRoutes[] = [
            'uri' => $route->uri,
            'methods' => $route->methods,
            'name' => $route->getName(),
            'action' => $route->getActionName(),
        ];
    }
}

echo "Livewire Routes: " . json_encode($livewireRoutes, JSON_PRETTY_PRINT) . "\n\n";

// 3. Tambahkan rute Livewire upload-file yang benar
echo "3. Menambahkan rute Livewire upload-file yang benar...\n";

// Cek apakah rute livewire/upload-file sudah ada
$uploadFileRouteExists = false;
foreach ($routes as $route) {
    if ($route->uri === 'livewire/upload-file' && in_array('POST', $route->methods)) {
        $uploadFileRouteExists = true;
        break;
    }
}

if (!$uploadFileRouteExists) {
    echo "Rute livewire/upload-file tidak ditemukan. Akan ditambahkan ke routes/web.php.\n";
    
    // Tentukan rute yang benar berdasarkan versi Livewire
    if (version_compare($livewireVersion, '3.0.0', '>=')) {
        // Livewire 3
        $routeCode = "
// Fix untuk masalah upload file (Livewire 3)
Route::post('livewire/upload-file', [\\Livewire\\Features\\SupportFileUploads\\FileUploadController::class, 'handle'])
    ->name('livewire.upload-file')
    ->middleware(['web']);
";
    } else {
        // Livewire 2
        $routeCode = "
// Fix untuk masalah upload file (Livewire 2)
Route::post('livewire/upload-file', [\\Livewire\\Controllers\\FileUploadHandler::class, 'handle'])
    ->name('livewire.upload-file')
    ->middleware(['web']);
";
    }
    
    // Tambahkan rute ke routes/web.php
    $webRoutesPath = __DIR__ . '/routes/web.php';
    $webRoutesContent = file_get_contents($webRoutesPath);
    
    // Hapus rute livewire/upload-file yang mungkin sudah ada
    $webRoutesContent = preg_replace('/\/\/.*livewire\/upload-file.*\n.*livewire\/upload-file.*\n.*->name.*\n.*->middleware.*\n/m', '', $webRoutesContent);
    
    // Tambahkan rute baru di akhir file
    $webRoutesContent .= $routeCode;
    
    file_put_contents($webRoutesPath, $webRoutesContent);
    
    echo "Rute Livewire upload-file berhasil ditambahkan ke routes/web.php.\n\n";
} else {
    echo "Rute livewire/upload-file sudah ada.\n\n";
}

// 4. Bersihkan cache
echo "4. Membersihkan cache...\n";
\Illuminate\Support\Facades\Artisan::call('optimize:clear');
echo \Illuminate\Support\Facades\Artisan::output();

echo "=== Selesai ===\n";
echo "Silakan coba upload file lagi.\n";
