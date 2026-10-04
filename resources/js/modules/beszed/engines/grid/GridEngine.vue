<script setup>
import { engineEmits, engineProps } from '../contract'
import MazeBoard from './MazeBoard.vue'
import RobotBoard from './RobotBoard.vue'

/**
 * Grid games: a square board of cells made on the server.
 *   maze     Labirintus: drag the hero through a maze to its goal (MazeBoard)
 *   program  Kis robot: arrows move a robot, at once or as a little program (RobotBoard)
 * data: GridMazeData | GridProgramData
 */
defineProps(engineProps)
const emit = defineEmits(engineEmits)
</script>

<template>
  <component
    :is="data.mode === 'maze' ? MazeBoard : RobotBoard"
    :data="data"
    :locked="locked"
    :prompt-done="promptDone"
    @answer="e => emit('answer', e)"
    @skip="s => emit('skip', s)"
    @say="s => emit('say', s)"
    @replay="p => emit('replay', p)"
  />
</template>
