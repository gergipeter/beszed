/**
 * Little surprises: now and then something extra and delightful happens, on top of the reward
 * that is always there. They are meant to be a smile, not a hook, so they follow these rules:
 *  - nothing is ever collected, ranked or "rare": a surprise is gone when it ends;
 *  - they are never tied to how well, how long or how often the child plays;
 *  - at most one in a row, so there is no streak of luck to chase.
 */
export const SURPRISES = ['dance', 'rainbow', 'flowers']

let last = null

/** About one finished game in four brings a surprise (never two in a row); otherwise null. */
export function rollSurprise(chance = 0.25) {
  if (last || Math.random() >= chance) {
    last = null
    return null
  }
  last = SURPRISES[Math.floor(Math.random() * SURPRISES.length)]
  return last
}
