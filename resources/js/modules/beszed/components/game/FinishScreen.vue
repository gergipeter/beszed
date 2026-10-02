<script setup>
import { computed, onMounted, watch } from 'vue'
import { useTimers } from '../../composables/useTimers'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { useGuideStore } from '../../stores/guide'
import { useRewardsStore } from '../../stores/rewards'
import { applause, bloom, fanfare, gift, giggle, levelUp, sparkle } from '../../services/audio/sfx'
import { markRested, needsRest } from '../../services/care/rest'
import { rollSurprise } from '../../services/care/surprise'
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
const rewards = useRewardsStore()
const guide = useGuideStore()
/** Now and then an extra little surprise, only a smile on top of the usual reward (services/care/surprise.js). */
const surprise = rollSurprise()
const SURPRISE_ICON = { dance: '💃', rainbow: '🌈', flowers: '🌸' }
/** After a good stretch of playing: a gentle "let's rest a little" (the child can still play on). */
const rest = needsRest()
const answer = event => {
  markRested()
  emit(event)
}
/** Today's goal is reached: a warm "see you tomorrow" (no pressure to play on). */
const seeYouTomorrow = computed(() => Boolean(rewards.summary && rewards.summary.daily.done >= rewards.summary.daily.goal))

onMounted(() => {
  fanfare()
  confetti({ pieces: 45 })
  later(applause, 500)
  // the garden's flowers open one after the other
  later(bloom, 950)
  later(bloom, 1250)
  if (surprise) {
    later(() => {
      gift()
      giggle()
      guide.hop()
      confetti({ pieces: surprise === 'flowers' ? 110 : 70 })
    }, 1900)
  }
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

    <p v-if="surprise" class="surprise" role="status">
      <EmojiArt :char="SURPRISE_ICON[surprise]" /> {{ t(`game.surprise.${surprise}`) }}
    </p>
    <p v-if="savedLater" class="offline">{{ t('game.savedLater') }}</p>
    <!-- every finished game grows a plant in the garden (the hub shows it sprouting) -->
    <!-- the same reward every time: stars, a plant in the garden, and the game's stone in bloom -->
    <template v-if="result">
      <p class="garden-note"><EmojiArt :char="ICONS.sprout" /> {{ t('game.gardenGrew') }}</p>
      <p class="garden-note garden-note--stone"><EmojiArt :char="ICONS.flower" /> {{ t('game.stoneBloomed') }}</p>
    </template>

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
      <LevelBar v-if="level" class="level" :level="level" :next="rewards.nextGift" />

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

    <p v-if="result && seeYouTomorrow" class="tomorrow">{{ t('game.tomorrow') }}</p>

    <section v-if="rest" class="rest">
      <h3 class="rest-title"><EmojiArt :char="ICONS.rest" /> {{ t('game.rest.title') }}</h3>
      <p class="rest-text">{{ t('game.rest.text') }}</p>
    </section>

    <div class="bz-row actions">
      <BzButton :variant="rest ? 'soft' : 'primary'" @click="answer('again')">{{ t(rest ? 'game.rest.more' : 'game.again') }}</BzButton>
      <BzButton :variant="rest ? 'primary' : undefined" @click="answer('exit')">{{ t(rest ? 'game.rest.take' : 'game.otherGame') }}</BzButton>
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
.rest {
  max-width: 340px;
  padding: 10px 16px;
  border-radius: var(--bz-radius-lg);
  background: color-mix(in srgb, var(--bz-guide) 14%, var(--bz-card));
  animation: grow-in 0.6s var(--bz-spring) 1.4s backwards;
}
.rest-title {
  margin: 0;
  font-size: var(--bz-text-md);
}
.rest-text {
  margin: 4px 0 0;
  font-size: var(--bz-text-sm);
  font-weight: 700;
}
.surprise {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  padding: 8px 18px;
  border-radius: var(--bz-radius-pill);
  background: linear-gradient(135deg, #ffe08a, #ffb3d1);
  color: var(--bz-on-bright);
  font-size: var(--bz-text-md);
  font-weight: 800;
  box-shadow: var(--bz-shadow-sm);
  animation: grow-in 0.7s var(--bz-spring) 1.9s backwards;
}
.garden-note--stone {
  animation-delay: 1.2s;
}
.tomorrow {
  max-width: 340px;
  margin: 4px 0 0;
  font-size: var(--bz-text-md);
  font-weight: 700;
  color: var(--bz-muted);
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
