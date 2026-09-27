#!/bin/bash
# Run Beszéd Docker container

echo "🚀 Stopping any existing containers..."
docker stop beszed-prod 2>/dev/null || true
docker rm beszed-prod 2>/dev/null || true

echo "🐳 Starting Beszéd production container..."
docker run -d \
  --name beszed-prod \
  -p 8080:8000 \
  -e APP_NAME="Beszéd" \
  -e APP_ENV="production" \
  -e APP_DEBUG="false" \
  -e APP_URL="http://localhost:8080" \
  -e DB_HOST="host.docker.internal" \
  -e DB_PORT="3307" \
  -e DB_DATABASE="beszed" \
  -e DB_USERNAME="root" \
  -e DB_PASSWORD="secret" \
  -e CACHE_DRIVER="redis" \
  -e REDIS_HOST="host.docker.internal" \
  -e REDIS_PORT="6379" \
  -e APP_KEY="base64:$(head -c 32 /dev/urandom | base64)" \
  beszed:latest

echo ""
echo "✅ Container started!"
echo ""
echo "🌐 Access your app at:"
echo "   http://localhost:8080"
echo ""
echo "📊 View logs:"
echo "   docker logs -f beszed-prod"
echo ""
echo "🛑 Stop container:"
echo "   docker stop beszed-prod"
echo ""
echo "🗑️  Remove container:"
echo "   docker rm beszed-prod"
echo ""
