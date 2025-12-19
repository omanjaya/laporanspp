#!/bin/bash

echo "🔄 Membersihkan port 8000..."

# Kill processes on port 8000
port=8000
echo "🔍 Memeriksa port $port..."
pid=$(lsof -ti:$port)
if [ -n "$pid" ]; then
    echo "⚠️  Port $port digunakan oleh PID $pid, menghentikan..."
    kill -9 $pid
    sleep 1
    echo "✅ Port $port telah dibersihkan"
else
    echo "✅ Port $port sudah kosong"
fi

echo ""
echo "🚀 Menjalankan Laravel server di port 8000..."

# Change to Laravel directory and start server
cd /home/omanjaya/Project/laporanspp
php artisan serve --host=0.0.0.0 --port=8000