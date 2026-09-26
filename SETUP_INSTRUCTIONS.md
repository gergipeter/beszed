# 🎮 Memory Game Difficulty Levels - Setup Guide

## What Was Implemented ✅

### 1. **Three Difficulty Levels for Párkereső (Memory Game)**
   - **Easy (Könnyű)**: 1400ms flip-back, lenient grading
   - **Medium (Közepesen nehéz)**: 1100ms flip-back, standard grading  
   - **Hard (Nehéz)**: 800ms flip-back, strict grading

### 2. **Dev Mode Control Panel**
   - ⚙️ Button in bottom-right of app
   - Allows manual difficulty override
   - Auto-saves to localStorage
   - Works without page reload

### 3. **Code Changes**
   - ✅ MemoryEngine.vue: Added difficulty system
   - ✅ ParkeresoRounds.php: Server assigns difficulty by level
   - ✅ i18n/hu.js: Hungarian labels
   - ✅ config/beszed.php: New achievement badges
   - ✅ app.blade.php: Dev button in HTML (no build needed!)

## How to See It Working

### Option A: **Test Offline** (Instant)
Open this file in your browser:
```
D:\beszed\TEST_DEV_MODE.html
```
This demonstrates the difficulty selector and localStorage integration.

### Option B: **Run Locally with Docker** (5 minutes)
```bash
cd D:\beszed
docker compose up
```
Then go to: `http://localhost:8000`

You'll see:
- ⚙️ DEV button in bottom-right
- Click it to open the dev panel
- Select Easy/Medium/Hard
- Play the memory game (Párkereső) to see it in action

### Option C: **Wait for Cloudflare to Update** (Unknown timing)
Your Cloudflare tunnel will eventually pull the new code. When it does:
- Go to your tunnel URL
- Look for ⚙️ button in bottom-right
- Use the dev panel

## Dev Mode Usage

1. **Open the app**
2. **Click ⚙️ DEV button** (bottom-right corner)
3. **Select difficulty:**
   - Auto (default, based on player level)
   - Easy (relaxed, always 2-3 stars)
   - Medium (balanced, standard grading)
   - Hard (challenging, strict grading)
4. **Refresh the page** to apply
5. **Play Párkereső** - it will use your selected difficulty

## Technical Details

### Difficulty Affects Two Things:

**1. Flip-Back Timing** (how long cards stay visible)
- Easy: 1400ms (extra time to remember)
- Medium: 1100ms (standard)
- Hard: 800ms (quick - tests memory)

**2. Grading (stars earned)**
- Easy: Always 2-3 stars (encouraging)
- Medium: Based on mismatches (0-1 = 1⭐, 2 = 2⭐, 3+ = 3⭐)
- Hard: Strict (0 = 1⭐, 1 = 2⭐, 2+ = 3⭐)

### Auto-Assignment by Player Level
Without dev override, difficulty is automatic:
- Levels 3-4 → Easy
- Level 5 → Medium
- Levels 6+ → Hard

## Troubleshooting

### "I don't see the ⚙️ button"
**Try:**
1. Hard refresh (Ctrl+Shift+R or Cmd+Shift+R)
2. Clear browser cache
3. Run locally with Docker (Option B)
4. Check browser console (F12) for errors

### "I selected difficulty but it didn't change"
**The setting is saved to localStorage.** You need to:
1. Refresh the page
2. Play a new game
3. It should apply to the current session

### "Cloudflare shows old code"
**Your Cloudflare tunnel hasn't rebuilt yet.** Options:
1. Restart the Cloudflare container
2. Trigger a rebuild in Cloudflare dashboard
3. Use Docker locally instead (Option B)

## Files Changed

```
✅ app/Beszed/Rounds/ParkeresoRounds.php - Server difficulty assignment
✅ resources/js/modules/beszed/engines/memory/MemoryEngine.vue - Game logic
✅ resources/js/modules/beszed/i18n/hu.js - Hungarian labels
✅ resources/js/modules/beszed/stores/devOverrides.js - Dev state management
✅ resources/js/modules/beszed/components/dev/DevPanel.vue - Complex version (optional)
✅ resources/js/modules/beszed/pages/HubPage.vue - Integration
✅ resources/views/app.blade.php - Simple HTML button (no build needed!)
✅ config/beszed.php - Achievement badges
✅ MEMORY_DIFFICULTY_TEST.md - Testing guide
```

## Git Status
```bash
cd D:\beszed
git log --oneline -5

# Should show:
# 189332d Add dev mode button directly to HTML (no build needed)
# 3f4193d Feature 3: Bulk content operations
# d3033f8 Add inline dev mode toggle button
# c89f9b2 Add comprehensive dev/god mode panel
# f9ee3b9 Add difficulty levels to Párkereső (memory game)
```

All commits are pushed to: `origin/feature/games-rewards-offline`

## Next Steps

1. **Try Option A or B above** to see it working
2. **Give feedback** on the difficulty levels
3. **Customize** if needed (timing, thresholds, etc.)
4. **Merge to main** when satisfied

---

**Questions?** Check the console (F12) for debug messages or the test HTML file.
