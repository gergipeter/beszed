#!/bin/bash
# 🚀 Cloudflare Pages + Workers Quick Deploy
# 20 minutes to production!

set -e

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                                                                ║"
echo "║      🚀 CLOUDFLARE PAGES + WORKERS QUICK DEPLOY 🚀           ║"
echo "║                                                                ║"
echo "║              Ready? Let's get you live!                       ║"
echo "║                                                                ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Check if build exists
if [ ! -d "dist" ]; then
  echo "📦 Building frontend..."
  npm run build
  echo "✅ Build complete!"
else
  echo "✅ dist/ folder exists"
fi

echo ""
echo "🔐 Logging into Cloudflare..."
wrangler login

echo ""
echo "⏳ Deploying to Cloudflare Pages..."
echo "   (This deploys your frontend)"
wrangler pages deploy dist

echo ""
echo "⏳ Deploying API Worker..."
echo "   (Configure wrangler.toml first!)"
wrangler publish --env production

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                                                                ║"
echo "║          ✅ DEPLOYMENT COMPLETE! 🎉                           ║"
echo "║                                                                ║"
echo "║  Your platform is now live on Cloudflare Edge!               ║"
echo "║                                                                ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "📍 Next steps:"
echo "  1. Update nameservers at your registrar"
echo "  2. Wait 5-15 minutes for DNS propagation"
echo "  3. Test at https://yourdomain.com"
echo "  4. Monitor logs: wrangler tail"
echo ""
echo "See CLOUDFLARE_DEPLOYMENT_STEPS.md for complete guide"
echo ""
