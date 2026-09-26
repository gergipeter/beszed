<script setup>
import { computed, onMounted, ref } from 'vue'
import CsillamAvatar from '../components/guide/CsillamAvatar.vue'
import LevelBar from '../components/rewards/LevelBar.vue'
import StickerCard from '../components/rewards/StickerCard.vue'
import StickerScene from '../components/rewards/StickerScene.vue'
import BzButton from '../components/ui/BzButton.vue'
import BzNotice from '../components/ui/BzNotice.vue'
import EmojiArt from '../components/ui/EmojiArt.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import { useModuleContext } from '../composables/useModuleContext'
import { ICONS } from '../config/icons'
import { t } from '../i18n'
import { sparkle } from '../services/audio/sfx'
import { useGuideStore } from '../stores/guide'
import { useRewardsStore } from '../stores/rewards'
import { errorMessage } from '../utils/errors'

/** The child's sticker album, level, streak, Csillám's wardrobe and their sticker scene. */
const { childId, guideName } = useModuleContext()
const rewards = useRewardsStore()
const guide = useGuideStore()
const failed = ref(false)
const message = ref('')
const sceneMessage = ref('')
const tab = ref('album')

async function load() {
  failed.value = !(await rewards.load(childId.value))
}

function sayBadge(badge) {
  guide.speak([badge.earned_at ? badge.name : badge.hint])
}

/** Tapping a worn item takes it off; tapping another in the same slot swaps it. */
async function wear(item) {
  message.value = ''
  const next = rewards.worn[item.slot] === item.id ? null : item.id
  try {
    await rewards.wear(childId.value, item.slot, next)
    guide.celebrate()
    sparkle()
  } catch (e) {
    message.value = errorMessage(e, t('rewards.wearFailed'))
  }
}

async function onSceneChange(scene) {
  sceneMessage.value = ''
  try {
    await rewards.saveScene(childId.value, scene)
  } catch (e) {
    sceneMessage.value = errorMessage(e, t('rewards.sceneSaveFailed'))
  }
}

const slots = computed(() => {
  const groups = { head: [], face: [], extra: [] }
  for (const item of rewards.accessories) groups[item.slot]?.push(item)
  return groups
})

onMounted(load)
</script>

<template>
  <PageHeader :title="t('rewards.title')" :back-to="{ name: 'beszed.hub', params: { childId } }" />

  <BzNotice v-if="failed" tone="warn">
    {{ t('rewards.loadFailed') }}
    <BzButton size="sm" @click="load">{{ t('common.retry') }}</BzButton>
  </BzNotice>

  <template v-else-if="rewards.summary">
    <section class="hero">
      <div class="hero-avatar"><CsillamAvatar :name="guideName" /></div>
      <div class="hero-body">
        <LevelBar :level="rewards.summary.level" />
        <p class="facts">
          <span class="fact"><EmojiArt :char="ICONS.star" /> {{ rewards.summary.stars }}</span>
          <span class="fact"><EmojiArt :char="ICONS.fire" /> {{ t('rewards.streak', { count: rewards.summary.streak.days }) }}</span>
          <span class="fact">
            <EmojiArt :char="ICONS.target" />
            {{
              t('rewards.daily', {
                done: Math.min(rewards.summary.daily.done, rewards.summary.daily.goal),
                goal: rewards.summary.daily.goal,
              })
            }}
          </span>
        </p>
      </div>
    </section>

    <div class="tabs" role="tablist">
      <button type="button" class="tab" :class="{ 'tab--on': tab === 'album' }" role="tab" :aria-selected="tab === 'album'" @click="tab = 'album'">
        {{ t('rewards.tabAlbum') }}
      </button>
      <button type="button" class="tab" :class="{ 'tab--on': tab === 'dressup' }" role="tab" :aria-selected="tab === 'dressup'" @click="tab = 'dressup'">
        {{ t('rewards.tabDressUp') }}
      </button>
      <button type="button" class="tab" :class="{ 'tab--on': tab === 'scene' }" role="tab" :aria-selected="tab === 'scene'" @click="tab = 'scene'">
        {{ t('rewards.tabScene') }}
      </button>
    </div>

    <template v-if="tab === 'album'">
      <h2 class="heading">{{ t('rewards.stickers', { count: rewards.earnedCount, total: rewards.badges.length }) }}</h2>
      <div class="album">
        <StickerCard
          v-for="badge in rewards.badges"
          :key="badge.id"
          :badge="badge"
          :earned="Boolean(badge.earned_at)"
          @click="sayBadge(badge)"
        />
      </div>
    </template>

    <template v-else-if="tab === 'dressup'">
      <p class="hint">{{ t('rewards.wardrobeHint') }}</p>
      <BzNotice v-if="message" tone="warn">{{ message }}</BzNotice>
      <div v-for="(items, slot) in slots" :key="slot" class="wardrobe">
        <button
          v-for="item in items"
          :key="item.id"
          type="button"
          class="outfit"
          :class="{ 'outfit--on': rewards.worn[slot] === item.id, 'outfit--locked': !item.unlocked }"
          :disabled="!item.unlocked"
          :aria-pressed="rewards.worn[slot] === item.id"
          @click="wear(item)"
        >
          <EmojiArt class="outfit-art" :char="item.emoji" />
          <small>{{ item.unlocked ? item.name : t('rewards.unlockAt', { level: item.level }) }}</small>
          <EmojiArt v-if="!item.unlocked" class="lock" :char="ICONS.lock" />
        </button>
      </div>
    </template>

    <template v-else>
      <p class="hint">{{ t('rewards.tabScene') }}</p>
      <BzNotice v-if="sceneMessage" tone="warn">{{ sceneMessage }}</BzNotice>
      <StickerScene
        :scene="rewards.scene"
        :earned-badges="rewards.earnedBadges"
        :backgrounds="rewards.backgrounds"
        @change="onSceneChange"
      />
    </template>
  </template>

  <p v-else class="bz-loading" aria-busy="true">{{ t('common.loading') }}</p>
</template>

<style scoped>
.hero {
  display: flex;
  align-items: center;
  gap: 16px;
  margin: 8px 0 20px;
  padding: 14px 18px;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow);
}
.hero-avatar {
  flex: none;
  width: clamp(100px, 26vw, 150px);
}
.hero-body {
  flex: 1;
  min-width: 0;
}
.facts {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 14px;
  margin: 10px 0 0;
  font-size: 17px;
  font-weight: 700;
}
.fact {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 16px;
}
.tab {
  padding: 9px 18px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  color: var(--bz-muted);
  font-weight: 700;
  font-size: var(--bz-text-sm);
  box-shadow: var(--bz-shadow-sm);
}
.tab--on {
  background: var(--bz-leaf);
  color: var(--bz-on-accent);
}
.heading {
  margin: 0 0 10px;
  font-size: 24px;
}
.album {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 12px;
}
.hint {
  margin: -4px 0 12px;
  color: var(--bz-muted);
}
.wardrobe {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 12px;
}
.outfit {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  width: 104px;
  padding: 12px 6px 10px;
  border: 4px solid transparent;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow);
  font-weight: 700;
}
.outfit--on {
  border-color: var(--bz-leaf);
  background: color-mix(in srgb, var(--bz-leaf) 16%, var(--bz-card));
}
.outfit--locked {
  cursor: default;
  opacity: 0.55;
}
.outfit--locked .outfit-art {
  filter: var(--bz-silhouette);
  opacity: 0.4;
}
.outfit-art {
  font-size: 44px;
}
.lock {
  position: absolute;
  top: 6px;
  right: 8px;
  font-size: 18px;
}
</style>
