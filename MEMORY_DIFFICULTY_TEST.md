# Párkereső (Memory Game) Difficulty Levels - Test Guide

## Implementation Summary

Added three difficulty levels to the memory game (Párkereső) that automatically adjust based on player level:

### Difficulty Levels

| Level | Player Levels | Flip-back Time | Grading Criteria | Feel |
|-------|---------------|----------------|-----------------|------|
| **Easy** (Könnyű) | 3-4 | 1400ms | Lenient: always 2-3 stars | Relaxed, encouraging |
| **Medium** (Közepesen nehéz) | 5 | 1100ms | Standard: 0-1 mismatch = 1⭐, 2 = 2⭐, 3+ = 3⭐ | Balanced |
| **Hard** (Nehéz) | 6+ | 800ms | Strict: 0 mismatches = 1⭐, 1 = 2⭐, 2+ = 3⭐ | Challenging |

### How It Works

1. **Automatic Difficulty Assignment**: The server-side `ParkeresoRounds.php` maps player level to difficulty:
   - Levels 3-4 → Easy mode
   - Level 5 → Medium mode
   - Levels 6+ → Hard mode

2. **Visual Feedback**: A difficulty label appears above the cards showing the current level in Hungarian:
   - "KÖNNYŰ" (Easy)
   - "KÖZEPESEN NEHÉZ" (Medium)
   - "NEHÉZ" (Hard)

3. **Gameplay Differences**:
   - **Flip-back speed**: Easy gives more time to see cards; Hard requires quicker memory
   - **Grading**: Hard mode requires fewer mismatches to earn 3 stars (perfect score)

## Files Modified

### 1. `resources/js/modules/beszed/engines/memory/MemoryEngine.vue`
- Added `DIFFICULTY_SETTINGS` object with parameters for each level
- Computed `difficulty` from props.data
- Updated `FLIP_BACK_MS` to be reactive based on difficulty
- Modified `grade()` function to use difficulty-based thresholds
- Added visual difficulty label above cards
- Updated template to show difficulty

**Key changes:**
```javascript
const DIFFICULTY_SETTINGS = {
  easy: { flipBackMs: 1400, mismatchThresholds: [999, 999] },
  medium: { flipBackMs: 1100, mismatchThresholds: [1, 2] },
  hard: { flipBackMs: 800, mismatchThresholds: [0, 1] },
}
```

### 2. `app/Beszed/Rounds/ParkeresoRounds.php`
- Added `getDifficultyByLevel()` method to map player levels to difficulty
- Passes difficulty in the round data for the frontend

**Key addition:**
```php
private function getDifficultyByLevel(int $level): string
{
    return match (true) {
        $level <= 4 => 'easy',
        $level === 5 => 'medium',
        default => 'hard',
    };
}
```

### 3. `resources/js/modules/beszed/i18n/hu.js`
- Added Hungarian labels for difficulty levels:
  - `memory.easy: 'Könnyű'`
  - `memory.medium: 'Közepesen nehéz'`
  - `memory.hard: 'Nehéz'`

### 4. `config/beszed.php`
- Added two new achievement badges:
  - `memory_easy`: "Memória kezdő" - play on Easy mode
  - `memory_hard`: "Memória mester" - play on Hard mode

## Testing the Implementation

To test, you need to run the Docker setup:

```bash
cd D:\beszed
docker compose up
```

Then navigate to:
- Local: `http://localhost:8000`
- With Google sign-in: Set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` in `.env`

### Test Scenarios

#### 1. Test Easy Mode (Player Level 3-4)
- Sign in as a parent
- Add/select a child at level 3-4
- Play Párkereső game
- **Expected**: 
  - "KÖNNYŰ" label appears above cards
  - Cards flip back slowly (1400ms)
  - Win easily and see 2-3 stars awarded

#### 2. Test Medium Mode (Player Level 5)
- Same child at level 5
- Play Párkereső again
- **Expected**:
  - "KÖZEPESEN NEHÉZ" label appears
  - Cards flip back at normal speed (1100ms)
  - Need fewer mismatches to earn 3 stars

#### 3. Test Hard Mode (Player Level 6+)
- Same child at level 6 or higher
- Play Párkereső again
- **Expected**:
  - "NEHÉZ" label appears
  - Cards flip back quickly (800ms)
  - Requires perfect play to earn 3 stars

#### 4. Test Grading in Hard Mode
- Play and intentionally make 1 mismatch
- Win the game
- **Expected**: See 2 stars (not 3)
- Play again with 0 mismatches
- **Expected**: See 3 stars (perfect)

#### 5. Check New Achievements
- Navigate to Rewards page
- **Expected**: Two new badges visible:
  - "Memória kezdő" (🧠) - if child played on Easy
  - "Memória mester" (🧠‍💨) - if child played on Hard and won

## Performance Considerations

- No performance impact: calculations are simple
- Difficulty is computed reactively
- Grading logic moved to computed property for clarity

## Future Enhancements

Possible improvements:
1. Allow manual difficulty selection (instead of auto-based on level)
2. Add difficulty modifiers for other memory-based games
3. Create difficulty progression charts in the progress report
4. Add difficulty tips in the intro screens ("This is the easy version…")
