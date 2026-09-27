<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useGameSession } from '../composables/useGameSession'
import { useGuideStore } from '../stores/guide'
import EmojiArt from '../components/ui/EmojiArt.vue'
import BzButton from '../components/ui/BzButton.vue'

const guide = useGuideStore()
const { recordResult } = useGameSession()

// Game state
const petName = ref('Pip')
const petHunger = ref(30)
const petHappiness = ref(80)
const petHealth = ref(90)
const petLevel = ref(1)
const petExp = ref(0)
const expToLevelUp = ref(100)
const petMood = ref('happy')
const score = ref(0)
const time = ref(0)
const gameActive = ref(true)

// Pet visuals
const petEmoji = computed(() => {
  if (petMood.value === 'sick') return '🤒'
  if (petMood.value === 'hungry') return '😵'
  if (petMood.value === 'sad') return '😢'
  if (petMood.value === 'sleeping') return '😴'
  if (petMood.value === 'playing') return '🤩'
  return '😊'
})

// Update pet state
const updatePetState = () => {
  // Increase hunger
  petHunger.value = Math.min(100, petHunger.value + 0.5)

  // Decrease happiness if hungry or unhealthy
  if (petHunger.value > 70) {
    petHappiness.value = Math.max(0, petHappiness.value - 1)
    petMood.value = 'hungry'
  } else if (petHealth.value < 30) {
    petHappiness.value = Math.max(0, petHappiness.value - 0.5)
    petMood.value = 'sick'
  } else if (petHappiness.value < 40) {
    petMood.value = 'sad'
  } else if (Math.random() > 0.98) {
    petMood.value = 'playing'
  } else {
    petMood.value = 'happy'
  }

  // Adjust health based on hunger and happiness
  if (petHunger.value > 80) {
    petHealth.value = Math.max(0, petHealth.value - 1)
  }
  if (petHappiness.value < 30) {
    petHealth.value = Math.max(0, petHealth.value - 0.5)
  }

  // Die if health too low
  if (petHealth.value <= 0) {
    endGame()
  }
}

const feed = () => {
  if (petHunger.value > 0) {
    petHunger.value = Math.max(0, petHunger.value - 20)
    petHealth.value = Math.min(100, petHealth.value + 5)
    petMood.value = 'happy'
    addExp(10)
    guide.celebrate()
  }
}

const play = () => {
  if (petEnergy.value > 20) {
    petHappiness.value = Math.min(100, petHappiness.value + 20)
    petHunger.value = Math.min(100, petHunger.value + 10)
    petEnergy.value = Math.max(0, petEnergy.value - 20)
    petMood.value = 'playing'
    addExp(15)
    score.value += 50
    guide.celebrate()
  }
}

const sleep = () => {
  petMood.value = 'sleeping'
  petHealth.value = Math.min(100, petHealth.value + 30)
  petEnergy.value = 100
  addExp(5)
}

const addExp = (amount) => {
  petExp.value += amount
  if (petExp.value >= expToLevelUp.value) {
    levelUp()
  }
}

const levelUp = () => {
  petLevel.value += 1
  petExp.value = 0
  expToLevelUp.value += 50
  petHealth.value = 100
  petHappiness.value = 100
  petEnergy.value = 100
  score.value += 200
  guide.speak([`${petName.value} leveled up to ${petLevel.value}!`])
}

const endGame = () => {
  gameActive.value = false
  recordResult({
    score: score.value,
    accuracy: Math.round((petHealth.value + petHappiness.value) / 2),
    feedback: `${petName.value} reached level ${petLevel.value}!`
  })
}

// Game loop
let gameLoop
onMounted(() => {
  gameLoop = setInterval(() => {
    time.value += 1
    updatePetState()
  }, 1000)
})

onUnmounted(() => {
  clearInterval(gameLoop)
})

// Pet energy (for playing)
const petEnergy = ref(80)
</script>

<template>
  <div class="tamagotchi-container">
    <div v-if="gameActive" class="game">
      <!-- Pet Display -->
      <div class="pet-area">
        <div class="pet-info">
          <h2>{{ petName }} - Level {{ petLevel }}</h2>
          <div class="pet-display">
            <EmojiArt :char="petEmoji" />
          </div>
          <p class="mood">{{ petMood }}</p>
        </div>

        <!-- Stats Bars -->
        <div class="stats">
          <div class="stat">
            <label>Health</label>
            <div class="bar" :style="{ width: petHealth + '%', backgroundColor: getHealthColor() }"></div>
            <span>{{ Math.round(petHealth) }}%</span>
          </div>
          <div class="stat">
            <label>Happiness</label>
            <div class="bar" :style="{ width: petHappiness + '%', backgroundColor: '#FFD700' }"></div>
            <span>{{ Math.round(petHappiness) }}%</span>
          </div>
          <div class="stat">
            <label>Hunger</label>
            <div class="bar" :style="{ width: petHunger + '%', backgroundColor: '#FF6B6B' }"></div>
            <span>{{ Math.round(petHunger) }}%</span>
          </div>
          <div class="stat">
            <label>Energy</label>
            <div class="bar" :style="{ width: petEnergy + '%', backgroundColor: '#4ECDC4' }"></div>
            <span>{{ Math.round(petEnergy) }}%</span>
          </div>
          <div class="stat">
            <label>Experience</label>
            <div class="bar" :style="{ width: (petExp / expToLevelUp * 100) + '%', backgroundColor: '#9D84B7' }"></div>
            <span>{{ petExp }}/{{ expToLevelUp }}</span>
          </div>
        </div>

        <!-- Score -->
        <div class="score">
          <h3>Score: {{ score }}</h3>
          <p>Time: {{ Math.floor(time / 60) }}s</p>
        </div>

        <!-- Actions -->
        <div class="actions">
          <BzButton @click="feed" :disabled="petHunger < 20">
            🍕 Feed
          </BzButton>
          <BzButton @click="play" :disabled="petEnergy < 20">
            🎮 Play
          </BzButton>
          <BzButton @click="sleep">
            😴 Sleep
          </BzButton>
        </div>

        <!-- Tips -->
        <div class="tips">
          <p v-if="petHunger > 70">{{ petName }} is very hungry! 🍕</p>
          <p v-else-if="petHappiness < 40">{{ petName }} is sad! 😢 Play with them!</p>
          <p v-else-if="petHealth < 30">{{ petName }} is sick! 🤒 Let them sleep!</p>
          <p v-else>{{ petName }} is happy! 😊</p>
        </div>
      </div>
    </div>

    <!-- Game Over -->
    <div v-else class="game-over">
      <h2>Game Over!</h2>
      <p>{{ petName }} reached level {{ petLevel }}</p>
      <p>Final Score: {{ score }}</p>
      <p>Great job raising your pet! 🎉</p>
    </div>
  </div>
</template>

<style scoped>
.tamagotchi-container {
  width: 100%;
  max-width: 400px;
  margin: 0 auto;
  padding: 20px;
}

.game {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 20px;
  padding: 20px;
  color: white;
}

.pet-area {
  text-align: center;
}

.pet-info h2 {
  margin: 0 0 10px;
  font-size: 20px;
}

.pet-display {
  font-size: 120px;
  margin: 20px 0;
  animation: bob 3s ease-in-out infinite;
}

@keyframes bob {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
}

.mood {
  font-size: 14px;
  opacity: 0.9;
  margin: 10px 0;
  text-transform: capitalize;
}

.stats {
  margin: 20px 0;
  background: rgba(255, 255, 255, 0.1);
  padding: 15px;
  border-radius: 10px;
}

.stat {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
  font-size: 14px;
}

.stat label {
  min-width: 70px;
  text-align: right;
}

.stat .bar {
  flex: 1;
  height: 20px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.2);
  border: 2px solid white;
  transition: width 0.3s ease;
}

.stat span {
  min-width: 50px;
  text-align: right;
}

.score {
  background: rgba(0, 0, 0, 0.2);
  padding: 15px;
  border-radius: 10px;
  margin: 15px 0;
}

.score h3 {
  margin: 0;
  font-size: 24px;
}

.score p {
  margin: 5px 0 0;
  opacity: 0.9;
}

.actions {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  margin: 20px 0;
}

.actions button {
  padding: 12px;
  font-size: 14px;
  border: none;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.9);
  color: #667eea;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.2s;
}

.actions button:hover:not(:disabled) {
  background: white;
  transform: scale(1.05);
}

.actions button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.tips {
  background: rgba(255, 255, 255, 0.15);
  padding: 15px;
  border-radius: 10px;
  font-size: 14px;
  min-height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.tips p {
  margin: 0;
}

.game-over {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 20px;
  padding: 40px 20px;
  text-align: center;
  color: white;
}

.game-over h2 {
  font-size: 32px;
  margin-bottom: 20px;
}

.game-over p {
  font-size: 18px;
  margin: 10px 0;
  opacity: 0.9;
}
</style>
