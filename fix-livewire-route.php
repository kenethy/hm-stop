<?php

// Script untuk memperbaiki masalah rute Livewire

// Load Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request = Illuminate\Http\Request::capture());

// Start the session
$session = $app->make('session');
$session->start();

echo "=== Memperbaiki Masalah Rute Livewire ===\n\n";

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

// 2. Periksa konfigurasi rute Livewire
echo "2. Memeriksa konfigurasi rute Livewire...\n";
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

// 3. Periksa file konfigurasi Livewire
echo "3. Memeriksa file konfigurasi Livewire...\n";
$livewireConfigPath = config_path('livewire.php');
if (file_exists($livewireConfigPath)) {
    echo "Livewire config file exists.\n";
    $livewireConfig = include $livewireConfigPath;
    echo "Livewire Config: " . json_encode($livewireConfig, JSON_PRETTY_PRINT) . "\n\n";
} else {
    echo "Livewire config file does not exist.\n\n";
}

// 4. Periksa service provider Livewire
echo "4. Memeriksa service provider Livewire...\n";
$providers = config('app.providers', []);
$livewireProvider = null;

foreach ($providers as $provider) {
    if (strpos($provider, 'Livewire') !== false) {
        $livewireProvider = $provider;
        break;
    }
}

echo "Livewire Service Provider: " . ($livewireProvider ?? 'Not found') . "\n\n";

// 5. Periksa middleware ForceHttps
echo "5. Memeriksa middleware ForceHttps...\n";
$forceHttpsPath = app_path('Http/Middleware/ForceHttps.php');
if (file_exists($forceHttpsPath)) {
    echo "ForceHttps middleware content:\n";
    echo file_get_contents($forceHttpsPath) . "\n\n";
} else {
    echo "ForceHttps middleware does not exist.\n\n";
}

echo "=== Selesai ===\n";

// Tambahkan rute Livewire yang benar
echo "Menambahkan rute Livewire yang benar...\n";

// Cek apakah rute livewire/upload-file sudah ada
$uploadFileRouteExists = false;
foreach ($routes as $route) {
    if ($route->uri === 'livewire/upload-file' && in_array('POST', $route->methods)) {
        $uploadFileRouteExists = true;
        break;
    }
}

if (!$uploadFileRouteExists) {
    echo "Rute livewire/upload-file tidak ditemukan. Perlu ditambahkan secara manual.\n";
    echo "Tambahkan rute berikut ke routes/web.php:\n\n";
    
    if (version_compare($livewireVersion, '3.0.0', '>=')) {
        // Livewire 3
        echo "// Livewire 3 route\n";
        echo "Route::post('livewire/upload-file', [\Livewire\Features\SupportFileUploads\FileUploadController::class, 'handle'])\n";
        echo "    ->name('livewire.upload-file')\n";
        echo "    ->middleware(['web', 'auth']);\n\n";
    } else {
        // Livewire 2
        echo "// Livewire 2 route\n";
        echo "Route::post('livewire/upload-file', [\Livewire\Controllers\FileUploadHandler::class, 'handle'])\n";
        echo "    ->name('livewire.upload-file')\n";
        echo "    ->middleware(['web', 'auth']);\n\n";
    }
} else {
    echo "Rute livewire/upload-file sudah ada.\n";
}

echo "Selesai memeriksa konfigurasi.\n";
