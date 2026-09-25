<script setup>
import { computed } from 'vue'
import { config } from '../../config/options'
import { t } from '../../i18n'
import { useGuideStore } from '../../stores/guide'

/**
 * Csillám the unicorn. Inline SVG on purpose: the mood animations (talking,
 * happy, sad, hop, party) drive the inner groups through the classes below.
 */
const props = defineProps({
  name: { type: String, default: '' },
})

const guide = useGuideStore()
// Unique gradient id, in case two unicorns are ever on screen.
const gradientId = `bz-horn-${Math.random().toString(36).slice(2, 8)}`
const classes = computed(() => [
  'uni',
  guide.mood !== 'idle' && guide.mood,
  { talking: guide.talking, party: guide.party },
])
const label = computed(() => t('guide.avatarLabel', { name: props.name || config.guideName }))
</script>

<template>
  <svg :class="classes" viewBox="0 0 200 222" role="img" :aria-label="label">
    <defs>
      <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#FFF1B8" />
        <stop offset="1" stop-color="#F5B83D" />
      </linearGradient>
    </defs>
    <g class="u-all">
      <!-- tail -->
      <g>
        <circle cx="158" cy="180" r="14" fill="#FF9EC7" />
        <circle cx="169" cy="165" r="11" fill="#FFD36E" />
        <circle cx="174" cy="151" r="9" fill="#9EE6C9" />
        <circle cx="172" cy="139" r="7" fill="#B9A6FF" />
      </g>
      <!-- body + feet -->
      <ellipse cx="100" cy="174" rx="54" ry="40" fill="#FFF7FC" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
      <ellipse cx="76" cy="210" rx="18" ry="9" fill="#C9B6FF" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
      <ellipse cx="124" cy="210" rx="18" ry="9" fill="#C9B6FF" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />

      <g class="u-head">
        <!-- mane -->
        <circle cx="56" cy="72" r="20" fill="#FF9EC7" />
        <circle cx="48" cy="100" r="17" fill="#FFD36E" />
        <circle cx="54" cy="127" r="14" fill="#9EE6C9" />
        <circle cx="144" cy="70" r="18" fill="#B9A6FF" />
        <circle cx="152" cy="96" r="14" fill="#FF9EC7" />
        <!-- ears -->
        <path d="M64 64 L58 30 L88 50 Z" fill="#FFF7FC" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
        <path d="M136 64 L142 30 L112 50 Z" fill="#FFF7FC" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
        <path d="M66 58 L63 40 L78 51 Z" fill="#FFC2DC" />
        <path d="M134 58 L137 40 L122 51 Z" fill="#FFC2DC" />
        <!-- face -->
        <ellipse cx="100" cy="98" rx="54" ry="50" fill="#FFF7FC" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
        <circle cx="78" cy="54" r="15" fill="#FF9EC7" />
        <circle cx="92" cy="46" r="10" fill="#FFD36E" />
        <circle cx="123" cy="52" r="12" fill="#9EE6C9" />
        <!-- horn -->
        <path d="M90 52 L100 6 L110 52 Z" :fill="`url(#${gradientId})`" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
        <path d="M93 40 L107 34 M95 28 L105 24" stroke="#D8962A" stroke-width="2.5" stroke-linecap="round" />
        <!-- cheeks -->
        <circle cx="64" cy="114" r="9" fill="#FFB3D1" opacity=".75" />
        <circle cx="136" cy="114" r="9" fill="#FFB3D1" opacity=".75" />
        <!-- eyes: open / happy / sad -->
        <g class="e-open">
          <ellipse cx="80" cy="94" rx="8.5" ry="11.5" fill="#3B1F4A" />
          <ellipse cx="120" cy="94" rx="8.5" ry="11.5" fill="#3B1F4A" />
          <circle cx="83" cy="89" r="3.2" fill="#fff" />
          <circle cx="123" cy="89" r="3.2" fill="#fff" />
          <path d="M71 86 L66 82 M125 86 L130 82" stroke="#3B1F4A" stroke-width="2.5" stroke-linecap="round" />
        </g>
        <g class="e-happy" stroke="#3B1F4A" stroke-width="4.5" fill="none" stroke-linecap="round">
          <path d="M71 97 Q80 84 89 97" />
          <path d="M111 97 Q120 84 129 97" />
        </g>
        <g class="e-sad">
          <ellipse cx="80" cy="97" rx="7.5" ry="9" fill="#3B1F4A" />
          <ellipse cx="120" cy="97" rx="7.5" ry="9" fill="#3B1F4A" />
          <path d="M69 84 L89 78 M131 84 L111 78" stroke="#3B1F4A" stroke-width="3.5" stroke-linecap="round" />
          <path d="M86 108 Q82 116 86 119 Q90 116 86 108 Z" fill="#7CC7FF" />
        </g>
        <!-- muzzle + mouths -->
        <ellipse cx="100" cy="124" rx="30" ry="19" fill="#FFD9EA" stroke="#5A3D6E" stroke-width="2.5" />
        <ellipse cx="91" cy="119" rx="3" ry="2.2" fill="#5A3D6E" opacity=".55" />
        <ellipse cx="109" cy="119" rx="3" ry="2.2" fill="#5A3D6E" opacity=".55" />
        <path class="m-smile" d="M90 130 Q100 138 110 130" stroke="#5A3D6E" stroke-width="3" fill="none" stroke-linecap="round" />
        <ellipse class="m-talk" cx="100" cy="132" rx="6" ry="5" fill="#8A2E4E" />
        <path class="m-happy" d="M86 127 Q100 147 114 127 Z" fill="#8A2E4E" stroke="#5A3D6E" stroke-width="2.5" stroke-linejoin="round" />
        <path class="m-sad" d="M91 136 Q100 129 109 136" stroke="#5A3D6E" stroke-width="3" fill="none" stroke-linecap="round" />
      </g>

      <!-- arms -->
      <g class="u-armL">
        <rect x="62" y="146" width="20" height="46" rx="10" fill="#FFF7FC" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
        <rect x="62" y="180" width="20" height="13" rx="6" fill="#C9B6FF" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
      </g>
      <g class="u-armR">
        <rect x="118" y="146" width="20" height="46" rx="10" fill="#FFF7FC" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
        <rect x="118" y="180" width="20" height="13" rx="6" fill="#C9B6FF" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
      </g>

      <!-- sparkles (happy) -->
      <g class="u-spark" fill="#FFD84D">
        <path transform="translate(26 40) scale(1.4)" d="M0 -8 L2 -2 L8 0 L2 2 L0 8 L-2 2 L-8 0 L-2 -2 Z" />
        <path transform="translate(176 26) scale(1.1)" d="M0 -8 L2 -2 L8 0 L2 2 L0 8 L-2 2 L-8 0 L-2 -2 Z" />
        <path transform="translate(18 150) scale(1)" d="M0 -8 L2 -2 L8 0 L2 2 L0 8 L-2 2 L-8 0 L-2 -2 Z" />
        <path transform="translate(186 118) scale(1.3)" d="M0 -8 L2 -2 L8 0 L2 2 L0 8 L-2 2 L-8 0 L-2 -2 Z" />
      </g>
    </g>
  </svg>
</template>

<style scoped>
.uni {
  display: block;
  width: 100%;
  height: auto;
  overflow: visible;
}

/* which face parts show per mood */
.uni .e-happy,
.uni .e-sad,
.uni .m-talk,
.uni .m-happy,
.uni .m-sad,
.uni .u-spark {
  display: none;
}
.uni.talking .m-smile {
  display: none;
}
.uni.talking .m-talk {
  display: inline;
  transform-box: fill-box;
  transform-origin: center;
  animation: talk 0.2s infinite alternate;
}
.uni.happy .e-open,
.uni.happy .m-smile,
.uni.happy .m-talk,
.uni.sad .e-open,
.uni.sad .m-smile,
.uni.sad .m-talk {
  display: none;
}
.uni.happy .e-happy,
.uni.happy .m-happy,
.uni.happy .u-spark,
.uni.sad .e-sad,
.uni.sad .m-sad {
  display: inline;
}

/* pivots */
.u-all {
  transform-box: view-box;
  transform-origin: 100px 215px;
  animation: bob 3s ease-in-out infinite;
}
.u-armL {
  transform-box: view-box;
  transform-origin: 72px 150px;
}
.u-armR {
  transform-box: view-box;
  transform-origin: 128px 150px;
}
.u-head {
  transform-box: view-box;
  transform-origin: 100px 145px;
}
.u-spark {
  animation: twinkle 0.6s ease-in-out infinite alternate;
}

/* moods */
.uni.happy .u-all {
  animation: jump 0.5s ease-out 3;
}
.uni.hop .u-all {
  animation: jump 0.5s ease-out 1;
}
.uni.happy .u-armL {
  animation: clapL 0.22s ease-in-out 8 alternate;
}
.uni.happy .u-armR {
  animation: clapR 0.22s ease-in-out 8 alternate;
}
.uni.party.happy .u-all,
.uni.party.happy .u-armL,
.uni.party.happy .u-armR {
  animation-iteration-count: infinite;
}
.uni.sad .u-head {
  animation: droop 1.3s ease-in-out forwards;
}

@keyframes bob {
  50% {
    transform: translateY(-4px);
  }
}
@keyframes jump {
  0%,
  100% {
    transform: translateY(0);
  }
  40% {
    transform: translateY(-20px) scale(1.04);
  }
}
@keyframes clapL {
  to {
    transform: rotate(-50deg);
  }
}
@keyframes clapR {
  to {
    transform: rotate(50deg);
  }
}
@keyframes droop {
  25%,
  85% {
    transform: rotate(-9deg) translateY(5px);
  }
  100% {
    transform: none;
  }
}
@keyframes talk {
  from {
    transform: scaleY(0.4);
  }
  to {
    transform: scaleY(1.2);
  }
}
@keyframes twinkle {
  from {
    opacity: 0.4;
    transform: scale(0.8);
  }
  to {
    opacity: 1;
    transform: scale(1.1);
  }
}

@media (prefers-reduced-motion: reduce) {
  .uni,
  .uni * {
    animation: none !important;
  }
}
</style>
