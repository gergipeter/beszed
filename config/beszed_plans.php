<?php

/*
| Free demo vs. premium. Every game can be tried for free, but a free account stays on the
| first levels of each game; the higher levels, the guided learning path and the full progress
| reports need a premium plan.
*/

return [
    // Highest game level (the adaptive level, or Kirakó's pálya) a free account plays. null = no cap.
    // Levels are now a smooth 1-100 scale (see RoundFactory::tier()/scale()), but the free gate stays
    // put at the original free ceiling: level 3 (near the easy end of tier 1 for a 3-tier game).
    'free_max_level' => 3,

    // Games whose levels are small steps get more room: Kirakó has 200 "pálya" (each one a puzzle), so 3 of them
    // would be three 2x2 puzzles. 15 reaches the 3x2 board and the first scenes.
    'free_max_level_by_game' => ['kirako' => 15],

    // users.subscription_plan values that unlock everything.
    'premium_plans' => ['premium', 'family'],
];
