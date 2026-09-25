import { computed, inject, provide } from 'vue'
import { config } from '../config/options'

const KEY = Symbol('beszed')

/**
 * @typedef {object} ModuleContext
 * @property {import('vue').ComputedRef<number>} childId
 * @property {import('vue').ComputedRef<string>} childName
 * @property {import('vue').ComputedRef<string>} guideName
 */

/** Called by BeszedLayout, so every page below it knows whose session this is. */
export function provideModuleContext(/** @type {ModuleContext} */ context) {
  provide(KEY, context)
}

/** @returns {ModuleContext} */
export function useModuleContext() {
  const context = inject(KEY, null)
  if (!context) throw new Error('[beszed] pages must be rendered inside BeszedLayout (use createBeszedRoutes).')
  return context
}

/** Builds the context from the layout's props. */
export const createModuleContext = props => ({
  childId: computed(() => props.childId),
  childName: computed(() => props.childName),
  guideName: computed(() => props.guideName || config.guideName),
})
