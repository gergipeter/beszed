<script setup>
import { useRouter } from 'vue-router'
import SessionRunner from '../components/game/SessionRunner.vue'
import { useModuleContext } from '../composables/useModuleContext'

defineProps({
  game: { type: String, required: true },
})

const router = useRouter()
const { childId } = useModuleContext()

const toHub = () => router.push({ name: 'beszed.hub', params: { childId: childId.value } })
</script>

<template>
  <SessionRunner :key="game" :game="game" @exit="toHub" />
</template>
