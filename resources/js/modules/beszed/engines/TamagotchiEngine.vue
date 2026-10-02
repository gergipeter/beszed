<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import EmojiArt from '../components/ui/EmojiArt.vue'
import BzButton from '../components/ui/BzButton.vue'
import TamagotchiPet from './TamagotchiPet.vue'
import { useDrag } from '../composables/useDrag'
import { t } from '../i18n'
import { pick } from '../utils/random'
import { engineEmits, engineProps } from './contract'

/** The "tamagotchi.nyami.n{x}" texts she says when fed. */
const NYAMI = [1, 2, 3]

/**
 * Pet-care mini-game (Tamagotchi): one continuous round, no drill questions.
 * Feed, play and let the pet sleep to raise its level; the round ends (one
 * 'answer' emit) once the pet reaches level 3 or its health runs out.
 * data: { petName, onCorrect }
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const LEVEL_TARGET = 3

const petHunger = ref(30)
const petHappiness = ref(80)
const petHealth = ref(90)
const petEnergy = ref(80)
const petLevel = ref(1)
const petExp = ref(0)
const expToLevelUp = ref(100)
const petMood = ref('happy')
/** One-shot reaction animation (bounce/wiggle/etc), layered on top of the idle mood pose. */
const petAction = ref('')
const leveling = ref(false)
let tries = 1
let actionTimer

/** TamagotchiPet knows sick/hungry/sad/sleeping/happy/idle; 'playing' just borrows the happy pose. */
const petUniMood = computed(() => (petMood.value === 'playing' ? 'happy' : petMood.value))

/** 'sick' | 'hungry' | 'sad' | 'sleeping' | 'playing', else happy. */
const moodLabel = computed(() => {
  const mood = ['sick', 'hungry', 'sad', 'sleeping', 'playing'].includes(petMood.value) ? petMood.value : 'happy'
  return t(`tamagotchi.mood.${mood}`)
})

/** One-shot particles (floating hearts, food, zzz) that pop up near the pet and fade out. */
const particles = ref([])
let particleId = 0
function popParticles(char, count = 3) {
  for (let i = 0; i < count; i++) {
    const id = particleId++
    particles.value.push({ id, char, x: 40 + Math.random() * 20 - Math.random() * 20, delay: i * 90 })
    setTimeout(() => {
      particles.value = particles.value.filter(p => p.id !== id)
    }, 1200)
  }
}

function playAction(name, duration = 700) {
  petAction.value = name
  clearTimeout(actionTimer)
  actionTimer = setTimeout(() => (petAction.value = ''), duration)
}

function updatePetState() {
  petHunger.value = Math.min(100, petHunger.value + 0.5)

  if (petHunger.value > 70) {
    petHappiness.value = Math.max(0, petHappiness.value - 1)
    petMood.value = 'hungry'
  } else if (petHealth.value < 30) {
    petHappiness.value = Math.max(0, petHappiness.value - 0.5)
    petMood.value = 'sick'
  } else if (petHappiness.value < 40) {
    petMood.value = 'sad'
  } else if (petMood.value !== 'sleeping' && Math.random() > 0.98) {
    petMood.value = 'playing'
  } else if (petMood.value !== 'sleeping') {
    petMood.value = 'happy'
  }

  if (petHunger.value > 80) petHealth.value = Math.max(0, petHealth.value - 1)
  if (petHappiness.value < 30) petHealth.value = Math.max(0, petHealth.value - 0.5)

  if (petHealth.value <= 0) finish(3)
}

function addExp(amount) {
  petExp.value += amount
  if (petExp.value >= expToLevelUp.value) levelUp()
}

async function levelUp() {
  petLevel.value += 1
  petExp.value = 0
  expToLevelUp.value += 50
  petHealth.value = 100
  petHappiness.value = 100
  petEnergy.value = 100
  emit('say', t('tamagotchi.levelUpSpeech', { level: petLevel.value }))
  leveling.value = true
  popParticles('✨', 6)
  await new Promise(r => setTimeout(r, 900))
  leveling.value = false
  if (petLevel.value >= LEVEL_TARGET) finish(tries)
}

/** @returns {boolean} whether the food was accepted (always true while hungry enough to feed) */
function feed() {
  if (props.locked || petHunger.value <= 0) return false
  petHunger.value = Math.max(0, petHunger.value - 20)
  petHealth.value = Math.min(100, petHealth.value + 5)
  petMood.value = 'happy'
  playAction('eat')
  popParticles('✨', 2)
  emit('say', t(`tamagotchi.nyami.n${pick(NYAMI)}`))
  addExp(10)
  return true
}

// dragging the food chip to the unicorn's mouth: it "arrives" and gets swallowed there
const stage = ref(null)
const drag = useDrag({
  root: stage,
  onDrop: (item, zone) => (zone === 'mouth' ? feed() && 'keep' : false),
})

function play() {
  if (props.locked || petEnergy.value < 20) return
  petHappiness.value = Math.min(100, petHappiness.value + 20)
  petHunger.value = Math.min(100, petHunger.value + 10)
  petEnergy.value = Math.max(0, petEnergy.value - 20)
  petMood.value = 'playing'
  playAction('play', 900)
  popParticles('⭐')
  addExp(15)
}

function napTime() {
  if (props.locked) return
  petMood.value = 'sleeping'
  petHealth.value = Math.min(100, petHealth.value + 30)
  petEnergy.value = 100
  popParticles('💤')
  addExp(5)
}

/** Petting the pet directly: a small affection bump, no stat cost. */
function pet() {
  if (props.locked || petMood.value === 'sleeping') return
  petHappiness.value = Math.min(100, petHappiness.value + 3)
  playAction('pet', 500)
  popParticles('💗', 2)
}

/** Ends the round: 1 = reached the level without trouble, 2/3 = health dropped along the way. */
function finish(grade) {
  if (props.locked) return
  emit('answer', { correct: true, say: props.data.onCorrect, tries: grade })
}

let gameLoop
onMounted(() => {
  gameLoop = setInterval(updatePetState, 1000)
})
onUnmounted(() => {
  clearInterval(gameLoop)
  clearTimeout(actionTimer)
})
</script>

<template>
  <div class="tamagotchi">
    <b class="pet-name">{{ t('tamagotchi.nameLevel', { name: data.petName, level: petLevel }) }}</b>

    <div class="stage-wrap">
      <div ref="stage" class="pet-stage" :class="{ 'pet-stage--sleeping': petMood === 'sleeping' }">
        <div class="ground" />
        <div class="pet-frame" :class="{ 'pet-frame--locked': locked }">
          <TamagotchiPet :mood="petUniMood" :action="petAction" mouth-zone="mouth" @tap="pet" />
        </div>

        <!-- drag this onto the unicorn's mouth to feed it; a tap also works -->
        <button
          v-if="petHunger > 0"
          type="button"
          class="food-chip bz-draggable"
          :class="{ 'food-chip--over': drag.over.value === 'mouth' }"
          :disabled="locked"
          :aria-label="t('tamagotchi.foodLabel')"
          @pointerdown="drag.start($event, { id: 'apple' })"
          @click="feed()"
        >
          <EmojiArt char="🍎" />
        </button>

        <TransitionGroup name="particle" tag="div" class="particles">
          <span
            v-for="p in particles"
            :key="p.id"
            class="particle"
            :style="{ left: p.x + '%', animationDelay: p.delay + 'ms' }"
          >
            <EmojiArt :char="p.char" />
          </span>
        </TransitionGroup>

        <div v-if="leveling" class="level-burst">
          <EmojiArt char="🎉" /> {{ t('tamagotchi.levelBurst', { level: petLevel }) }} <EmojiArt char="🎉" />
        </div>
      </div>
      <p class="mood-caption" aria-live="polite">{{ moodLabel }}</p>
      <div class="exp-track"><div class="exp-bar" :style="{ width: (petExp / expToLevelUp) * 100 + '%' }" /></div>
    </div>

    <div class="bars">
      <div class="stat">
        <EmojiArt char="❤️" /><label>{{ t('tamagotchi.stats.health') }}</label>
        <div class="track"><div class="bar" :style="{ width: petHealth + '%', background: '#5bc27a' }" /></div>
      </div>
      <div class="stat">
        <EmojiArt char="😊" /><label>{{ t('tamagotchi.stats.happiness') }}</label>
        <div class="track"><div class="bar" :style="{ width: petHappiness + '%', background: '#ffd166' }" /></div>
      </div>
      <div class="stat" :class="{ 'stat--warn': petHunger > 70 }">
        <EmojiArt char="🍗" /><label>{{ t('tamagotchi.stats.hunger') }}</label>
        <div class="track"><div class="bar" :style="{ width: petHunger + '%', background: '#ff6b6b' }" /></div>
      </div>
      <div class="stat">
        <EmojiArt char="⚡" /><label>{{ t('tamagotchi.stats.energy') }}</label>
        <div class="track"><div class="bar" :style="{ width: petEnergy + '%', background: '#4ecdc4' }" /></div>
      </div>
    </div>

    <div class="actions">
      <BzButton :disabled="locked || petEnergy < 20" @click="play">🎮 {{ t('tamagotchi.play') }}</BzButton>
      <BzButton :disabled="locked" @click="napTime">😴 {{ t('tamagotchi.sleep') }}</BzButton>
    </div>
  </div>
</template>

<style scoped>
.tamagotchi {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 16px;
  width: min(100%, 420px);
}
.pet-name {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-size: var(--bz-text-lg);
}

/* the pet's little world: a soft platform it stands on, big enough to feel alive */
.stage-wrap {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 8px;
}
.pet-stage {
  position: relative;
  height: 200px;
  border-radius: var(--bz-radius-lg);
  background: linear-gradient(180deg, var(--bz-soft) 0%, var(--bz-card) 100%);
  box-shadow: var(--bz-shadow-sm);
  overflow: hidden;
  transition: filter 0.6s ease;
}
.pet-stage--sleeping {
  filter: brightness(0.85) saturate(0.7);
}
.ground {
  position: absolute;
  left: 8%;
  right: 8%;
  bottom: 22px;
  height: 14px;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.08);
  filter: blur(2px);
}
.pet-frame {
  position: absolute;
  left: 50%;
  bottom: 10px;
  transform: translateX(-50%);
  width: 150px;
}
.pet-frame--locked {
  pointer-events: none;
}

/* the food the child drags to the mouth; sits in a corner of the stage, ready to grab */
.food-chip {
  position: absolute;
  right: 14px;
  bottom: 14px;
  width: 52px;
  height: 52px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 30px;
  border-radius: 50%;
  border: none;
  background: var(--bz-card);
  box-shadow: var(--bz-shadow);
  animation: food-invite 1.8s ease-in-out infinite;
}
.food-chip:disabled {
  opacity: 0.4;
  animation: none;
}
.food-chip--over {
  transform: scale(1.15);
  box-shadow: 0 0 0 4px var(--bz-leaf);
}
@keyframes food-invite {
  50% {
    transform: translateY(-4px);
  }
}

.particles {
  position: absolute;
  inset: 0;
  pointer-events: none;
}
.particle {
  position: absolute;
  bottom: 90px;
  font-size: 28px;
  animation: float-up 1.1s ease-out forwards;
}
@keyframes float-up {
  0% {
    opacity: 0;
    transform: translateY(0) scale(0.6);
  }
  20% {
    opacity: 1;
    transform: translateY(-10px) scale(1);
  }
  100% {
    opacity: 0;
    transform: translateY(-90px) scale(1.1);
  }
}

.level-burst {
  position: absolute;
  top: 10px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 16px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow);
  font-weight: 800;
  font-size: var(--bz-text-sm);
  animation: burst-in 0.4s var(--bz-spring);
}
@keyframes burst-in {
  from {
    opacity: 0;
    transform: translateX(-50%) translateY(-12px) scale(0.8);
  }
}

.mood-caption {
  margin: 0;
  text-align: center;
  font-weight: 700;
  font-size: var(--bz-text-sm);
  color: var(--bz-ink-soft, inherit);
}
.exp-track {
  height: 8px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-soft);
  overflow: hidden;
}
.exp-bar {
  height: 100%;
  border-radius: var(--bz-radius-pill);
  background: linear-gradient(90deg, #ffd166, #ff9f5b);
  transition: width 0.4s ease;
}

.bars {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-sm);
}
.stat {
  display: flex;
  align-items: center;
  gap: 8px;
}
.stat label {
  min-width: 70px;
  font-weight: 700;
  font-size: var(--bz-text-sm);
}
.stat--warn .track {
  animation: warn-pulse 1s ease-in-out infinite;
}
@keyframes warn-pulse {
  50% {
    box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.35);
  }
}
.track {
  flex: 1;
  height: 16px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-soft);
  overflow: hidden;
}
.bar {
  height: 100%;
  border-radius: var(--bz-radius-pill);
  transition: width 0.3s ease;
}
.actions {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

@media (prefers-reduced-motion: reduce) {
  .particle,
  .level-burst,
  .food-chip {
    animation: none !important;
  }
}
</style>
