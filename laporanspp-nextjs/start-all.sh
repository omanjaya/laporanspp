#!/bin/bash

echo "🚀 Starting SPP System Development Environment"
echo "=========================================="

# Function to kill processes on specific ports
kill_port() {
    local port=$1
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
}

echo ""
echo "🔄 Membersihkan port yang berjalan..."

# Kill processes on ports 3000 and 8000
for port in 3000 8000; do
    kill_port $port
done

echo ""
echo "🚀 Menjalankan Laravel backend di port 8000..."

# Start Laravel server in background
cd /home/omanjaya/Project/laporanspp
php artisan serve --host=0.0.0.0 --port=8000 &
LARAVEL_PID=$!

echo "✅ Laravel backend dimulai dengan PID: $LARAVEL_PID"

sleep 3

echo ""
echo "🚀 Menjalankan Next.js frontend di port 3000..."

# Start Next.js in background
cd /home/omanjaya/Project/laporanspp/laporanspp-nextjs
DATABASE_URL="file:./dev.db" npm run dev &
NEXTJS_PID=$!

echo "✅ Next.js frontend dimulai dengan PID: $NEXTJS_PID"

echo ""
echo "🎉 SPP System Development Environment sudah berjalan!"
echo "📱 Frontend: http://localhost:3000"
echo "🔧 Backend API: http://localhost:8000"
echo ""
echo "Untuk menghentikan semua server, tekan Ctrl+C"

# Wait for user to stop
wait $LARAVEL_PID $NEXTJS_PID