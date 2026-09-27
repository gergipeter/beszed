<script setup>
/**
 * The sky over Zoé's garden, behind the hub's greeting: a slowly turning sun
 * and drifting clouds by day (low and warm in the evening), the moon and
 * twinkling stars at night. Only transform/opacity move. It follows the
 * layout's data-daytime.
 */
const STARS = [
  [6, 18, 3], [14, 62, 2], [23, 30, 2], [31, 8, 3], [42, 44, 2], [51, 14, 2],
  [58, 70, 3], [66, 26, 2], [74, 52, 2], [83, 10, 3], [90, 38, 2], [96, 64, 2],
]
</script>

<template>
  <div class="sky" aria-hidden="true">
    <span class="sun"><i class="rays" /></span>
    <span class="moon" />
    <i v-for="n in 3" :key="`c${n}`" class="cloud" :class="`cloud--${n}`" />
    <i
      v-for="([x, y, s], i) in STARS"
      :key="`s${i}`"
      class="star"
      :style="{ left: `${x}%`, top: `${y}%`, width: `${s}px`, height: `${s}px`, '--i': i }"
    />
  </div>
</template>

<style scoped>
.sky {
  position: absolute;
  top: calc(-16px - env(safe-area-inset-top, 0px));
  left: 50%;
  z-index: -1;
  width: 100vw;
  height: 300px;
  overflow: hidden;
  transform: translateX(-50%);
  pointer-events: none;
}
.sun {
  position: absolute;
  top: 26px;
  right: max(6vw, calc(50vw - 400px));
  width: 74px;
  height: 74px;
  border-radius: 50%;
  background: radial-gradient(circle at 40% 38%, #fff6b8, #ffd84d 55%, #ffb627);
  box-shadow: 0 0 40px 10px rgba(255, 216, 77, 0.45);
}
.rays {
  position: absolute;
  inset: -26px;
  z-index: -1;
  border-radius: 50%;
  background: repeating-conic-gradient(rgba(255, 226, 120, 0.55) 0 7deg, transparent 7deg 30deg);
  mask: radial-gradient(circle, transparent 42%, #000 44%, #000 62%, transparent 72%);
  animation: turn 40s linear infinite;
}
.moon {
  display: none;
  position: absolute;
  top: 30px;
  right: max(8vw, calc(50vw - 390px));
  width: 58px;
  height: 58px;
  border-radius: 50%;
  /* a crescent: the sky's colour bites into the full moon */
  background: radial-gradient(circle at 70% 36%, var(--bz-sky-top) 44%, transparent 46%), #fdf3c4;
  box-shadow: 0 0 30px 6px rgba(253, 243, 196, 0.35);
}
.cloud {
  position: absolute;
  width: 120px;
  height: 36px;
  border-radius: 40px;
  background: #fff;
  opacity: 0.85;
  animation: drift 70s linear infinite alternate;
}
.cloud::before,
.cloud::after {
  content: '';
  position: absolute;
  border-radius: 50%;
  background: inherit;
}
.cloud::before {
  left: 18px;
  top: -22px;
  width: 52px;
  height: 52px;
}
.cloud::after {
  left: 56px;
  top: -14px;
  width: 40px;
  height: 40px;
}
.cloud--1 {
  top: 110px;
  left: 4%;
}
.cloud--2 {
  top: 54px;
  left: 38%;
  transform: scale(0.7);
  animation-duration: 90s;
  animation-delay: -30s;
}
.cloud--3 {
  top: 150px;
  left: 68%;
  transform: scale(0.85);
  animation-duration: 80s;
  animation-delay: -55s;
}
.star {
  display: none;
  position: absolute;
  border-radius: 50%;
  background: #fff;
  animation: twinkle 2.6s ease-in-out infinite alternate;
  animation-delay: calc(var(--i) * -0.45s);
}

/* evening: the sun sits low and warm; the clouds blush */
.bz[data-daytime='evening'] .sun {
  top: 150px;
  background: radial-gradient(circle at 40% 38%, #ffe2a8, #ff9d5c 60%, #ff7a59);
  box-shadow: 0 0 50px 14px rgba(255, 140, 90, 0.4);
}
.bz[data-daytime='evening'] .cloud {
  background: #ffe1d6;
}
/* night: moon and stars, no sun, faint clouds */
.bz[data-daytime='night'] .sun {
  display: none;
}
.bz[data-daytime='night'] .moon,
.bz[data-daytime='night'] .star {
  display: block;
}
.bz[data-daytime='night'] .cloud {
  opacity: 0.12;
}

@keyframes turn {
  to {
    transform: rotate(1turn);
  }
}
@keyframes drift {
  to {
    translate: 22vw 0;
  }
}
@keyframes twinkle {
  from {
    opacity: 0.25;
    transform: scale(0.7);
  }
  to {
    opacity: 1;
    transform: scale(1.1);
  }
}
</style>
