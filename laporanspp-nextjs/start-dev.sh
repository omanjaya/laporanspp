#!/bin/bash

echo "🔄 Membersihkan port 3000..."

# Kill processes on port 3000 only
port=3000
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
echo "🚀 Menjalankan development server di port 3000..."

# Set DATABASE_URL and run npm dev on port 3000
DATABASE_URL="file:./dev.db" npm run dev