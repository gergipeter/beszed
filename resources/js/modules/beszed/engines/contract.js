/**
 * The contract every engine implements, so SessionRunner treats them all alike.
 * Engines know nothing about specific games: the server's RoundFactory decides
 * every option, distractor and feedback sentence and sends it as `data`.
 *
 *   <script setup>
 *   const props = defineProps(engineProps)
 *   const emit = defineEmits(engineEmits)
 *
 * Emits:
 *   answer({ correct, say? })  one try; `say` is the feedback sentence
 *   skip(say?)                 give up on this round
 *   say(text)                  Csillám says `text` (tapping a picture…)
 *   replay(parts?)             say the prompt again, or `parts` instead
 */
export const engineProps = {
  /** Engine-specific round data (see types.js: ChoiceData, SequenceData…). */
  data: { type: Object, required: true },
  /** The round is decided; ignore further input. */
  locked: { type: Boolean, default: false },
  /** Csillám has finished saying the prompt. */
  promptDone: { type: Boolean, default: false },
}

export const engineEmits = ['answer', 'skip', 'say', 'replay']
