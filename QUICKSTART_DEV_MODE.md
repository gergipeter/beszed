# 🚀 Quick Start - Dev Mode Testing

## Server is Running! ✅

Your Docker container is up at: **http://localhost:8000**

## How to Test Dev Mode:

### 1. **Open the App**
Go to: http://localhost:8000

### 2. **Sign In** 
- Click "Demo parent" (no Google sign-in needed)
- Select or create a child

### 3. **You're on the Hub Page**
This is where the ⚙️ DEV button appears!

### 4. **Look for ⚙️ DEV button**
- **Bottom-right corner** of the screen
- Green text on dark background
- Says "⚙️ DEV MODE"

### 5. **Click the Button**
A green panel will appear with:
- Memory Game Difficulty selector
- Options: Auto / Easy / Medium / Hard
- Shows current saved setting

### 6. **Select a Difficulty**
- **Auto** (default) - uses player level
- **🟢 Easy** - relaxed, more time to see cards
- **🟡 Medium** - standard difficulty
- **🔴 Hard** - fast, strict grading

### 7. **Play the Memory Game**
- Click on a game tile to play
- Look for "Párkereső" (🃏) or "Párkereső"
- The difficulty label will show above the cards
- Try different difficulties to see the difference!

## What to Notice:

### Easy Mode (🟢)
- Cards flip back slower (1400ms)
- The label says "KÖNNYŰ"
- You get 2-3 stars easily
- More forgiving

### Medium Mode (🟡)
- Normal speed (1100ms)
- The label says "KÖZEPESEN NEHÉZ"
- Standard grading
- Balanced difficulty

### Hard Mode (🔴)
- Cards flip back faster (800ms)
- The label says "NEHÉZ"
- Must play perfectly for 3 stars
- More challenging

## Troubleshooting:

### Server not running?
```bash
cd D:\beszed
docker compose up
```

### Can't see the dev button?
1. Refresh the page (F5)
2. Look bottom-right corner
3. Check console (F12) for errors

### Difficulty not changing?
1. Select difficulty in dev panel
2. **Refresh the page** (F5)
3. Play a new game
4. It should apply!

## Database & Data

The app comes pre-loaded with:
- ✅ All 18 games and content
- ✅ Demo parent account
- ✅ Multiple children to test with
- ✅ Existing progress data

You can create new children and test different levels:
- Level 1-10
- Different age groups (3-4, 5-6, 7+)
- Different play styles

## Stop the Server

When you're done:
```bash
cd D:\beszed
docker compose down
```

Or just close the terminal - containers will keep running.

---

**Questions?** Check the browser console (F12) for debug messages!
