<?php

// Script untuk membuat user admin dan staff

// Load Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request = Illuminate\Http\Request::capture());

// Start the session
$session = $app->make('session');
$session->start();

echo "=== Membuat User Admin dan Staff ===\n\n";

// 1. Membuat admin user
$adminEmail = 'hartonomotor1979@gmail.com';
$adminPassword = 'juanmak123';
$adminName = 'Hartono Motor Admin';

echo "1. Membuat/Memperbarui Admin User...\n";

// Check if the admin user exists
$adminUser = \App\Models\User::where('email', $adminEmail)->first();

if ($adminUser) {
    echo "Admin user dengan email $adminEmail sudah ada!\n";
    
    // Update the user's role and password
    $adminUser->update([
        'role' => 'admin',
        'password' => \Illuminate\Support\Facades\Hash::make($adminPassword)
    ]);
    echo "Admin user diperbarui dengan password baru dan role admin.\n";
} else {
    // Create the admin user
    \App\Models\User::create([
        'name' => $adminName,
        'email' => $adminEmail,
        'password' => \Illuminate\Support\Facades\Hash::make($adminPassword),
        'role' => 'admin'
    ]);
    
    echo "Admin user berhasil dibuat!\n";
}

echo "Email: $adminEmail\n";
echo "Password: $adminPassword\n";
echo "User ini memiliki akses penuh ke semua resource.\n\n";

// 2. Membuat staff user
$staffEmail = 'hartonomotor1979@user.com';
$staffPassword = 'hmbengkel1979';
$staffName = 'Hartono Motor Staff';

echo "2. Membuat/Memperbarui Staff User...\n";

// Check if the staff user exists
$staffUser = \App\Models\User::where('email', $staffEmail)->first();

if ($staffUser) {
    echo "Staff user dengan email $staffEmail sudah ada!\n";
    
    // Update the user's role and password
    $staffUser->update([
        'role' => 'staff',
        'password' => \Illuminate\Support\Facades\Hash::make($staffPassword)
    ]);
    echo "Staff user diperbarui dengan password baru dan role staff.\n";
} else {
    // Create the staff user
    \App\Models\User::create([
        'name' => $staffName,
        'email' => $staffEmail,
        'password' => \Illuminate\Support\Facades\Hash::make($staffPassword),
        'role' => 'staff'
    ]);
    
    echo "Staff user berhasil dibuat!\n";
}

echo "Email: $staffEmail\n";
echo "Password: $staffPassword\n";
echo "User ini memiliki akses terbatas hanya ke Bookings dan Services.\n";

echo "\n=== Selesai ===\n";
