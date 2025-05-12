#!/bin/bash
set -e

echo "Memperbaiki masalah upload file Livewire..."

# Check if docker-compose is running
if ! docker-compose ps | grep -q "app.*Up"; then
    echo "Error: Docker containers tidak berjalan. Silakan jalankan 'docker-compose up -d' terlebih dahulu."
    exit 1
fi

# Run the PHP script
docker-compose exec app php fix-livewire-upload.php

# Restart web server
echo "Merestart web server..."
docker-compose restart webserver

echo "Selesai! Silakan coba upload file lagi."
