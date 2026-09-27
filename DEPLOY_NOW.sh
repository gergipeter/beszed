#!/bin/bash
# 🚀 Beszéd Production Deployment Script
# Choose your platform and run!

set -e

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                                                                ║"
echo "║        🚀 BESZÉD PRODUCTION DEPLOYMENT - CHOOSE YOUR PATH     ║"
echo "║                                                                ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "Select deployment option:"
echo ""
echo "  1) 🌩️  Cloudflare Pages + Workers (FASTEST - 20 min, FREE)"
echo "  2) ⚡ Cloudflare + Railway (RECOMMENDED - 30 min, €5-50/mo)"
echo "  3) 🏢 DigitalOcean (FULL CONTROL - 45 min, €30-50/mo)"
echo "  4) 🐳 Docker Hub (Build & Push image only)"
echo ""
echo "Enter choice (1-4): "
read -r choice

case $choice in
  1)
    echo ""
    echo "📦 Building production frontend..."
    npm run build

    echo ""
    echo "🚀 Deploying to Cloudflare Pages..."
    echo "Make sure you're logged in: wrangler login"
    wrangler pages deploy dist

    echo ""
    echo "✅ Frontend deployed!"
    echo ""
    echo "Next steps:"
    echo "1. Deploy API to Workers: npm run build:worker && wrangler publish"
    echo "2. Setup D1 database: See CLOUDFLARE_DEPLOYMENT.md"
    echo "3. Point DNS to Cloudflare"
    echo ""
    ;;

  2)
    echo ""
    echo "📦 Building Docker image..."
    docker build -t beszed:latest .

    echo ""
    echo "🐳 Tagging for Docker Hub..."
    echo "Enter Docker Hub username: "
    read -r docker_user
    docker tag beszed:latest $docker_user/beszed:latest

    echo ""
    echo "📤 Pushing to Docker Hub..."
    docker push $docker_user/beszed:latest

    echo ""
    echo "✅ Image pushed to Docker Hub!"
    echo ""
    echo "Next steps:"
    echo "1. Create Railway account & project"
    echo "2. Connect GitHub repo"
    echo "3. Set environment variables"
    echo "4. Deploy triggers automatically"
    echo "5. Setup Cloudflare DNS pointing"
    echo ""
    echo "See CLOUDFLARE_DEPLOYMENT.md for details"
    echo ""
    ;;

  3)
    echo ""
    echo "📚 DigitalOcean Deployment Guide"
    echo ""
    echo "Follow steps in DEPLOYMENT_GUIDE_DOCKER.md:"
    echo ""
    echo "1. Create DigitalOcean account"
    echo "2. Create MySQL database"
    echo "3. Create Redis cache"
    echo "4. Setup App Platform"
    echo "5. Configure DNS"
    echo "6. Enable SSL/TLS"
    echo "7. Setup monitoring"
    echo ""
    echo "Estimated time: 45 minutes"
    echo ""
    ;;

  4)
    echo ""
    echo "🐳 Building and pushing Docker image..."

    echo ""
    echo "Enter Docker Hub username: "
    read -r docker_user

    echo "Enter Docker Hub password: "
    read -rs docker_pass

    echo ""
    echo "Logging into Docker Hub..."
    echo "$docker_pass" | docker login -u "$docker_user" --password-stdin

    echo ""
    echo "Building image..."
    docker build -t beszed:latest .

    echo ""
    echo "Tagging..."
    docker tag beszed:latest $docker_user/beszed:latest
    docker tag beszed:latest $docker_user/beszed:$(date +%Y-%m-%d)

    echo ""
    echo "Pushing to Docker Hub..."
    docker push $docker_user/beszed:latest
    docker push $docker_user/beszed:$(date +%Y-%m-%d)

    echo ""
    echo "✅ Done! Image pushed to Docker Hub"
    echo ""
    echo "Image available at: docker.io/$docker_user/beszed:latest"
    echo ""
    ;;

  *)
    echo "❌ Invalid choice"
    exit 1
    ;;
esac

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                                                                ║"
echo "║              🎉 DEPLOYMENT INITIATED SUCCESSFULLY            ║"
echo "║                                                                ║"
echo "║  Monitor progress in your platform dashboard                 ║"
echo "║  Check logs for any errors                                   ║"
echo "║  Test at https://yourdomain.com when ready                   ║"
echo "║                                                                ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
