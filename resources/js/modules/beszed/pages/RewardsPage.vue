<script setup>
import { onMounted, ref } from 'vue'
import CsillamAvatar from '../components/guide/CsillamAvatar.vue'
import DressUp from '../components/rewards/DressUp.vue'
import LevelBar from '../components/rewards/LevelBar.vue'
import StickerBook from '../components/rewards/StickerBook.vue'
import StickerScene from '../components/rewards/StickerScene.vue'
import BzButton from '../components/ui/BzButton.vue'
import BzNotice from '../components/ui/BzNotice.vue'
import EmojiArt from '../components/ui/EmojiArt.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import { useModuleContext } from '../composables/useModuleContext'
import { ICONS } from '../config/icons'
import { t } from '../i18n'
import { useGuideStore } from '../stores/guide'
import { useRewardsStore } from '../stores/rewards'
import { errorMessage } from '../utils/errors'

/**
 * "Matricáim": the child's treasure room. The sticker book (new stickers arrive
 * as packs to open), Csillám's dressing room, and the sticker picture.
 */
const { childId, childName, guideName } = useModuleContext()
const rewards = useRewardsStore()
const guide = useGuideStore()
const failed = ref(false)
const sceneMessage = ref('')
const TABS = [
  { id: 'album', icon: ICONS.book },
  { id: 'dressup', icon: ICONS.dress },
  { id: 'scene', icon: ICONS.picture },
]
const tab = ref('album')

async function load() {
  failed.value = !(await rewards.load(childId.value))
}

async function onSceneChange(scene) {
  sceneMessage.value = ''
  try {
    await rewards.saveScene(childId.value, scene)
  } catch (e) {
    sceneMessage.value = errorMessage(e, t('rewards.sceneSaveFailed'))
  }
}

onMounted(load)
</script>

<template>
  <PageHeader :title="t('rewards.title')" :back-to="{ name: 'beszed.hub', params: { childId } }" />

  <BzNotice v-if="failed" tone="warn">
    {{ t('rewards.loadFailed') }}
    <BzButton size="sm" @click="load">{{ t('common.retry') }}</BzButton>
  </BzNotice>

  <template v-else-if="rewards.summary">
    <section class="hero" :class="{ 'hero--slim': tab === 'dressup' }">
      <div v-if="tab !== 'dressup'" class="hero-avatar"><CsillamAvatar :name="guideName" /></div>
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
      <button
        v-for="item in TABS"
        :key="item.id"
        type="button"
        class="tab"
        :class="{ 'tab--on': tab === item.id }"
        role="tab"
        :aria-selected="tab === item.id"
        @click="tab = item.id"
      >
        <EmojiArt class="tab-icon" :char="item.icon" />
        <span class="tab-name">{{ t(`rewards.tabs.${item.id}`) }}</span>
      </button>
    </div>

    <Transition name="tab" mode="out-in">
      <StickerBook v-if="tab === 'album'" key="album" :badges="rewards.badges" />
      <DressUp v-else-if="tab === 'dressup'" key="dressup" />
      <div v-else key="scene">
        <BzNotice v-if="sceneMessage" tone="warn">{{ sceneMessage }}</BzNotice>
        <StickerScene
          :scene="rewards.scene"
          :earned-badges="rewards.earnedBadges"
          :backgrounds="rewards.backgrounds"
          :child-name="childName"
          @change="onSceneChange"
          @say="text => guide.speak([text])"
        />
      </div>
    </Transition>
  </template>

  <p v-else class="bz-loading" aria-busy="true">{{ t('common.loading') }}</p>
</template>

<style scoped>
.hero {
  display: flex;
  align-items: center;
  gap: 16px;
  margin: 8px 0 16px;
  padding: 14px 18px;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow);
}
.hero--slim {
  padding: 10px 16px;
}
.hero-avatar {
  flex: none;
  width: clamp(90px, 24vw, 140px);
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
/* three big picture tabs: the book, the wardrobe, the picture */
.tabs {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  margin-bottom: 16px;
}
.tab {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: 10px 6px 8px;
  border: 4px solid transparent;
  border-radius: 22px 24px 20px 26px;
  background: var(--bz-card);
  box-shadow: var(--bz-shadow);
  transition: transform 0.38s var(--bz-spring);
}
.tab:active {
  transform: scale(0.94);
  transition-duration: 0.07s;
}
.tab--on {
  border-color: var(--bz-sun);
  background: color-mix(in srgb, var(--bz-sun) 22%, var(--bz-card));
  transform: translateY(-3px);
}
.tab-icon {
  font-size: 34px;
}
.tab-name {
  font-size: 14px;
  font-weight: 800;
  line-height: 1.1;
  text-align: center;
}
.tab-enter-active {
  transition: opacity 0.2s, transform 0.25s var(--bz-spring);
}
.tab-leave-active {
  transition: opacity 0.12s;
}
.tab-enter-from {
  opacity: 0;
  transform: translateY(10px);
}
.tab-leave-to {
  opacity: 0;
}
</style>
