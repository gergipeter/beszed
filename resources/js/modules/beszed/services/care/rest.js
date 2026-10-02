import { config } from '../../config/options'

/**
 * A gentle rest: after `config.restAfterMin` minutes of playing, the end of a game suggests
 * a little break (some water, a look out of the window). Only a suggestion: the child can
 * always play one more, and then it waits another stretch before asking again.
 */
let since = Date.now()

export function needsRest(now = Date.now()) {
  return config.restAfterMin > 0 && now - since >= config.restAfterMin * 60_000
}

/** The suggestion was answered (taken or not): the next one comes after another stretch. */
export function markRested() {
  since = Date.now()
}
