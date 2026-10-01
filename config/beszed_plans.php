<?php

/*
| Free demo vs. premium. Every game can be tried for free, but a free account stays on the
| first levels of each game; the higher levels, the guided learning path and the full progress
| reports need a premium plan.
*/

return [
    // Highest game level (the adaptive level, or Kirakó's pálya) a free account plays. null = no cap.
    'free_max_level' => 3,

    // users.subscription_plan values that unlock everything.
    'premium_plans' => ['premium', 'family'],
];
