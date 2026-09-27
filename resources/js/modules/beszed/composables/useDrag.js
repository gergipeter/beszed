import { nextTick, onBeforeUnmount, ref } from 'vue'
import { buzz } from '../services/touch/feel'

/**
 * Drag a picture to where it belongs, with a magnet. Near a drop zone (an
 * element with data-drop="<id>" inside `root`) the picture is pulled towards
 * it; let go there and it snaps in, then `onDrop(item, zoneId)` says whether
 * it belongs (true: the game takes it; false: it springs back home). A short
 * press that doesn't move stays a tap, and the click still fires, so every
 * drag game can also be played by tapping. Only transform moves (compositor).
 *
 *   const drag = useDrag({ root, onDrop })
 *   <button class="bz-draggable" @pointerdown="drag.start($event, item)">
 *   <div :data-drop="bin.id" :class="{ over: drag.over.value === bin.id }">
 *
 * @param {{ root: import('vue').Ref<HTMLElement | null>, onDrop: (item: any, zone: string) => boolean | 'keep' | Promise<boolean | 'keep'>, magnet?: number }} options
 */
export function useDrag({ root, onDrop, magnet = 80 }) {
  /** The item being dragged, and the zone it would land in. */
  const dragging = ref(null)
  const over = ref(null)

  let el = null
  let item = null
  let origin = null
  let zones = []
  let moved = false
  let frame = 0
  let shift = { x: 0, y: 0 }

  const TAP_SLOP = 8
  const reduced = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

  function start(event, dragged) {
    if (event.button > 0 || el) return
    el = event.currentTarget
    item = dragged
    origin = { x: event.clientX, y: event.clientY }
    moved = false
    el.setPointerCapture?.(event.pointerId)
    el.addEventListener('pointermove', move)
    el.addEventListener('pointerup', end)
    el.addEventListener('pointercancel', cancel)
  }

  /** Distance from the pointer to a zone's box (0 inside it). */
  const reach = (zone, x, y) => Math.hypot(Math.max(zone.left - x, 0, x - zone.right), Math.max(zone.top - y, 0, y - zone.bottom))

  function move(event) {
    const dx = event.clientX - origin.x
    const dy = event.clientY - origin.y
    if (!moved) {
      if (Math.hypot(dx, dy) < TAP_SLOP) return
      moved = true
      dragging.value = item
      zones = [...(root.value?.querySelectorAll('[data-drop]') ?? [])]
        .filter(z => z !== el && !el.contains(z))
        .map(z => {
          const box = z.getBoundingClientRect()
          return { id: z.dataset.drop, left: box.left, right: box.right, top: box.top, bottom: box.bottom, x: box.left + box.width / 2, y: box.top + box.height / 2 }
        })
      el.classList.add('bz-dragging')
      // follow the finger exactly (no spring lag) and float above the neighbours
      el.style.transition = 'none'
      el.style.zIndex = '20'
    }
    const near = zones
      .map(zone => ({ zone, d: reach(zone, event.clientX, event.clientY) }))
      .filter(z => z.d < magnet)
      .sort((a, b) => a.d - b.d)[0]
    if ((near?.zone.id ?? null) !== over.value) {
      over.value = near?.zone.id ?? null
      if (near) buzz(4)
    }
    // the magnet: the nearer the zone, the harder it pulls the picture to its middle
    const pull = near ? 0.4 * (1 - near.d / magnet) : 0
    shift = {
      x: dx + (near ? (near.zone.x - event.clientX) * pull : 0),
      y: dy + (near ? (near.zone.y - event.clientY) * pull : 0),
    }
    frame ||= requestAnimationFrame(paint)
  }

  function paint() {
    frame = 0
    if (!el) return
    const tilt = Math.max(-10, Math.min(10, shift.x / 18))
    el.style.transform = `translate(${shift.x}px, ${shift.y}px) scale(1.1) rotate(${tilt}deg)`
  }

  async function end() {
    const target = el
    const zone = zones.find(z => z.id === over.value)
    detach()
    if (!moved) return finish() // a tap: the click handler does the work
    swallowClick()
    if (zone) {
      await glide(target, zone)
      const taken = await onDrop(item, zone.id)
      if (taken) {
        // 'keep': the game animates the picture away from where it fell (a card leaving into a basket)
        await nextTick()
        if (taken !== 'keep') target.style.transform = ''
        return finish(true)
      }
    }
    await springBack(target)
    finish()
  }

  function cancel() {
    const target = el
    detach()
    if (moved) springBack(target).then(() => finish())
    else finish()
  }

  /** The snap: into the middle of the zone, a little smaller, as if it fell in. */
  function glide(target, zone) {
    const box = target.getBoundingClientRect()
    const x = shift.x + zone.x - (box.left + box.width / 2)
    const y = shift.y + zone.y - (box.top + box.height / 2)
    target.style.transform = `translate(${x}px, ${y}px) scale(0.9)`
    if (reduced() || !target.animate) return Promise.resolve()
    return target
      .animate([{ transform: `translate(${shift.x}px, ${shift.y}px) scale(1.1)` }, { transform: `translate(${x}px, ${y}px) scale(0.9)` }], {
        duration: 150,
        easing: 'ease-in',
      })
      .finished.catch(() => {})
  }

  /** Not here: back home with a springy bounce. */
  function springBack(target) {
    const from = target.style.transform
    target.style.transform = ''
    if (reduced() || !target.animate || !from) return Promise.resolve()
    return target
      .animate([{ transform: from }, { transform: 'translate(0, 0) scale(1)' }], {
        duration: 460,
        easing: 'cubic-bezier(0.3, 1.6, 0.45, 1)',
      })
      .finished.catch(() => {})
  }

  /** The drag already did the work; the click that follows pointerup must not do it again. */
  function swallowClick() {
    const stop = e => {
      e.stopPropagation()
      e.preventDefault()
    }
    window.addEventListener('click', stop, { capture: true, once: true })
    setTimeout(() => window.removeEventListener('click', stop, { capture: true }), 0)
  }

  function detach() {
    cancelAnimationFrame(frame)
    frame = 0
    el?.removeEventListener('pointermove', move)
    el?.removeEventListener('pointerup', end)
    el?.removeEventListener('pointercancel', cancel)
  }

  /** @param {boolean} [landed]  the game took it: leave the transform to the game's own update */
  function finish(landed = false) {
    if (el) {
      el.classList.remove('bz-dragging')
      el.style.transition = ''
      el.style.zIndex = ''
      if (!landed) el.style.transform = ''
    }
    el = null
    item = null
    moved = false
    dragging.value = null
    over.value = null
  }

  onBeforeUnmount(detach)

  return { start, dragging, over }
}
