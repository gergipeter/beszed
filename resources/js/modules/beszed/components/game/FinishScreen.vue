<script setup>
import { onMounted, watch } from 'vue'
import { useTimers } from '../../composables/useTimers'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { applause, fanfare, levelUp, sparkle } from '../../services/audio/sfx'
import { confetti } from '../../services/effects/confetti'
import CsillamAvatar from '../guide/CsillamAvatar.vue'
import LevelBar from '../rewards/LevelBar.vue'
import MedalStars from '../rewards/MedalStars.vue'
import StickerCard from '../rewards/StickerCard.vue'
import BzButton from '../ui/BzButton.vue'
import EmojiArt from '../ui/EmojiArt.vue'

/** End of a game: stars earned, medal, level progress, and anything newly unlocked. */
const props = defineProps({
  /** Rounds solved in this game. */
  stars: { type: Number, required: true },
  rounds: { type: Number, required: true },
  guideName: { type: String, default: '' },
  /** @type {import('vue').PropType<import('../../types').RewardResult | null>} */
  result: { type: Object, default: null },
  /** @type {import('vue').PropType<import('../../types').PlayerLevel | null>} */
  level: { type: Object, default: null },
  /** Offline: the result will be uploaded later. */
  savedLater: { type: Boolean, default: false },
})

const emit = defineEmits(['again', 'exit'])
const { later } = useTimers()

onMounted(() => {
  fanfare()
  confetti({ pieces: 45 })
  later(applause, 500)
})

// The rewards arrive a moment later (server round trip): celebrate what they brought.
watch(
  () => props.result,
  result => {
    if (!result) return
    if (result.level_up || result.unlocked.length || result.daily_path?.just_completed) {
      later(() => {
        levelUp()
        confetti({ pieces: 80 })
      }, 700)
    } else if (result.new_badges.length) {
      later(sparkle, 900)
    }
  },
  { immediate: true },
)
</script>

<template>
  <div class="finish">
    <div class="avatar"><CsillamAvatar :name="guideName" /></div>
    <h2 class="title">{{ t('game.finishTitle') }}</h2>

    <!-- 1–3 star rating of this game (first-try answers); then the stars it added to the collection -->
    <MedalStars v-if="result" class="medal" :count="result.medal" size="lg" animated />
    <p class="earned" :aria-label="t('game.starsLabel', { count: stars })">
      +{{ stars }} <EmojiArt :char="ICONS.star" />
      <small class="earned-of">/ {{ rounds }}</small>
    </p>

    <p v-if="savedLater" class="offline">{{ t('game.savedLater') }}</p>
    <!-- every finished game grows a plant in the garden (the hub shows it sprouting) -->
    <p v-if="result" class="garden-note"><EmojiArt :char="ICONS.sprout" /> {{ t('game.gardenGrew') }}</p>

    <template v-if="result">
      <p v-if="result.daily_path?.ticked" class="path-note">
        <EmojiArt :char="result.daily_path.just_completed ? ICONS.party : ICONS.map" />
        {{
          result.daily_path.just_completed
            ? t('daily.finishAll')
            : t('daily.finishStep', { done: result.daily_path.done.length })
        }}
      </p>

      <p v-if="result.level_up" class="banner">
        <EmojiArt :char="ICONS.party" /> {{ t('game.levelUp', { level: level?.number }) }}
      </p>
      <LevelBar v-if="level" class="level" :level="level" />

      <p v-for="item in result.unlocked" :key="item.id" class="unlock">
        <EmojiArt class="unlock-art" :char="item.emoji" /> {{ t('game.unlocked', { name: item.name }) }}
      </p>

      <div v-if="result.new_badges.length" class="stickers">
        <b class="stickers-title">{{ t('game.newSticker') }}</b>
        <div class="stickers-row">
          <StickerCard v-for="badge in result.new_badges" :key="badge.id" :badge="badge" earned fresh />
        </div>
      </div>
    </template>

    <div class="bz-row actions">
      <BzButton variant="primary" @click="emit('again')">{{ t('game.again') }}</BzButton>
      <BzButton @click="emit('exit')">{{ t('game.otherGame') }}</BzButton>
    </div>
  </div>
</template>

<style scoped>
.finish {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding-top: 16px;
  text-align: center;
}
.avatar {
  width: min(210px, 55vw);
}
.title {
  margin: 0;
  font-size: 36px;
}
.garden-note {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  padding: 6px 14px;
  border-radius: var(--bz-radius-pill);
  background: color-mix(in srgb, var(--bz-leaf) 18%, var(--bz-card));
  font-weight: 800;
  animation: grow-in 0.6s var(--bz-spring) 0.9s backwards;
}
@keyframes grow-in {
  from {
    opacity: 0;
    transform: scale(0.6);
  }
}
.earned {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  padding: 4px 16px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-sun);
  color: var(--bz-on-bright);
  font-size: 24px;
  font-weight: 800;
}
.earned-of {
  font-size: 16px;
  opacity: 0.7;
}
.banner {
  margin: 4px 0 0;
  padding: 8px 20px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-sun);
  color: var(--bz-on-bright);
  font-size: 24px;
  font-weight: 800;
  box-shadow: var(--bz-shadow);
  animation: bounce 0.7s cubic-bezier(0.2, 1.6, 0.4, 1) both;
}
.path-note {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin: 4px 0 0;
  padding: 6px 16px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  font-size: var(--bz-text-md);
  font-weight: 800;
  box-shadow: var(--bz-shadow-sm);
  animation: bounce 0.7s cubic-bezier(0.2, 1.6, 0.4, 1) both;
}
.level {
  width: min(100%, 360px);
  text-align: left;
}
.unlock {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  padding: 6px 16px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  font-size: 19px;
  font-weight: 700;
  animation: bounce 0.7s 0.2s cubic-bezier(0.2, 1.6, 0.4, 1) both;
}
.unlock-art {
  font-size: 34px;
}
.stickers-title {
  display: block;
  margin-bottom: 6px;
  font-size: 20px;
}
.stickers-row {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 10px;
}
.stickers-row > * {
  width: 120px;
}
.actions {
  margin-top: 14px;
}
.offline {
  margin: 0;
  padding: 6px 14px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-soft);
  font-size: 15px;
  font-weight: 700;
  color: var(--bz-muted);
}
@keyframes bounce {
  from {
    transform: scale(0.3);
    opacity: 0;
  }
}
</style>
