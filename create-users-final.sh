#!/bin/bash
set -e

echo "Membuat admin dan staff user untuk Filament..."

# Check if docker-compose is running
if ! docker-compose ps | grep -q "app.*Up"; then
    echo "Error: Docker containers tidak berjalan. Silakan jalankan 'docker-compose up -d' terlebih dahulu."
    exit 1
fi

# Run the PHP script
docker-compose exec app php create-users-final.php

echo "Selesai!"
echo ""
echo "Admin user:"
echo "Email: hartonomotor1979@gmail.com"
echo "Password: juanmak123"
echo ""
echo "Staff user:"
echo "Email: hartonomotor1979@user.com"
echo "Password: hmbengkel1979"
