#!/bin/bash

# Quick Test Script for Beszéd
# Tests: Language files, OG images, Core functionality

echo "================================"
echo "🧪 Beszéd Quick Test Script"
echo "================================"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

PASSED=0
FAILED=0

# Test 1: Language Files
echo "📝 TEST 1: Language Files"
if [ -f "resources/lang/hu/app.json" ] && [ -f "resources/lang/en/app.json" ]; then
    echo -e "${GREEN}✅ Language files exist${NC}"
    PASSED=$((PASSED+1))

    # Count keys
    HU_KEYS=$(grep -o '":' resources/lang/hu/app.json | wc -l)
    EN_KEYS=$(grep -o '":' resources/lang/en/app.json | wc -l)
    echo "   🇭🇺 Hungarian: $HU_KEYS keys"
    echo "   🇬🇧 English: $EN_KEYS keys"
else
    echo -e "${RED}❌ Language files missing${NC}"
    FAILED=$((FAILED+1))
fi
echo ""

# Test 2: OG Images
echo "🖼️  TEST 2: OG Images"
if [ -f "public/og-image.svg" ] && [ -f "public/og-image-hu.svg" ] && [ -f "public/og-image-en.svg" ]; then
    echo -e "${GREEN}✅ OG images exist${NC}"
    PASSED=$((PASSED+1))
    echo "   ✅ og-image.svg (fallback)"
    echo "   ✅ og-image-hu.svg (Hungarian)"
    echo "   ✅ og-image-en.svg (English)"
else
    echo -e "${RED}❌ OG images missing${NC}"
    FAILED=$((FAILED+1))
fi
echo ""

# Test 3: robots.txt
echo "🤖 TEST 3: SEO Files"
if [ -f "public/robots.txt" ]; then
    echo -e "${GREEN}✅ robots.txt exists${NC}"
    PASSED=$((PASSED+1))
else
    echo -e "${RED}❌ robots.txt missing${NC}"
    FAILED=$((FAILED+1))
fi

if [ -f "public/sitemap.xml" ]; then
    echo -e "${GREEN}✅ sitemap.xml exists${NC}"
    PASSED=$((PASSED+1))
else
    echo -e "${RED}❌ sitemap.xml missing${NC}"
    FAILED=$((FAILED+1))
fi
echo ""

# Test 4: SEO Services
echo "⚙️  TEST 4: SEO Services"
if [ -f "app/Services/SeoService.php" ]; then
    echo -e "${GREEN}✅ SeoService.php exists${NC}"
    PASSED=$((PASSED+1))
else
    echo -e "${RED}❌ SeoService.php missing${NC}"
    FAILED=$((FAILED+1))
fi

if [ -f "app/Http/Controllers/SeoController.php" ]; then
    echo -e "${GREEN}✅ SeoController.php exists${NC}"
    PASSED=$((PASSED+1))
else
    echo -e "${RED}❌ SeoController.php missing${NC}"
    FAILED=$((FAILED+1))
fi
echo ""

# Test 5: Guides
echo "📚 TEST 5: Documentation Guides"
GUIDES=(
    "SEO_OPTIMIZATION_GUIDE.md"
    "GOOGLE_SEARCH_CONSOLE_SETUP.md"
    "MONITORING_DASHBOARD.md"
    "SEO_LAUNCH_CHECKLIST.md"
    "TESTING_AND_POLISH_GUIDE.md"
    "HOSTING_GUIDE.md"
    "HOSTING_GUIDE_HU.md"
    "LANGUAGE_README.md"
)

for guide in "${GUIDES[@]}"; do
    if [ -f "$guide" ]; then
        echo -e "${GREEN}✅ $guide${NC}"
        PASSED=$((PASSED+1))
    else
        echo -e "${YELLOW}⚠️  $guide missing${NC}"
    fi
done
echo ""

# Test 6: Build
echo "🏗️  TEST 6: Build Status"
if [ -d "public/build" ] && [ -f "public/build/manifest.json" ]; then
    echo -e "${GREEN}✅ Production build exists${NC}"
    PASSED=$((PASSED+1))

    # Count files
    JS_COUNT=$(ls public/build/assets/*.js 2>/dev/null | wc -l)
    CSS_COUNT=$(ls public/build/assets/*.css 2>/dev/null | wc -l)
    echo "   📦 JS files: $JS_COUNT"
    echo "   🎨 CSS files: $CSS_COUNT"
else
    echo -e "${YELLOW}⚠️  Production build not ready (run: npm run build)${NC}"
fi
echo ""

# Summary
echo "================================"
echo "📊 Test Summary"
echo "================================"
echo -e "${GREEN}✅ Passed: $PASSED${NC}"
if [ $FAILED -gt 0 ]; then
    echo -e "${RED}❌ Failed: $FAILED${NC}"
else
    echo -e "${GREEN}✅ No failures!${NC}"
fi

TOTAL=$((PASSED + FAILED))
echo "Total: $TOTAL tests"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}🚀 Ready for testing!${NC}"
    exit 0
else
    echo -e "${RED}⚠️  Fix failures before proceeding${NC}"
    exit 1
fi
