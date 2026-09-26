<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

/**
 * One series over time, as columns (counts) or a line (rates). Plain SVG sized to
 * its container. Marks: columns ≤ 24px with a 4px rounded cap on a square base,
 * 2px line with ≥ 8px markers in a 2px surface ring, hairline grid. Only the latest
 * value is labelled; every value is on hover/focus and in the page's table view.
 */
const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  /** @type {import('vue').PropType<{ key: string, label: string, long: string, value: number | null }[]>} */
  points: { type: Array, required: true },
  kind: { type: String, default: 'bar', validator: v => ['bar', 'line'].includes(v) },
  /** Fixed top of the scale (e.g. 1 for rates); otherwise a clean number above the largest value. */
  max: { type: Number, default: null },
  format: { type: Function, default: v => String(v) },
  emptyText: { type: String, default: '' },
})

const PLOT_H = 150
const AXIS_H = 26
const TOP = 16
const LEFT = 40
const RIGHT = 14
const MAX_BAR = 24
const CAP = 4

const box = ref(null)
const width = ref(320)
const active = ref(null)
let observer = null

onMounted(() => {
  width.value = box.value.clientWidth || 320
  if ('ResizeObserver' in window) {
    observer = new ResizeObserver(([entry]) => (width.value = Math.round(entry.contentRect.width) || width.value))
    observer.observe(box.value)
  }
})
onBeforeUnmount(() => observer?.disconnect())

const hasData = computed(() => props.points.some(p => p.value))

/** Clean ticks: 0, step, 2·step… covering the largest value in at most 3 steps. */
const ticks = computed(() => {
  if (props.max !== null) return [0, props.max / 2, props.max]
  const top = Math.max(1, ...props.points.map(p => p.value ?? 0))
  const step = [1, 2, 5, 10, 20, 50, 100].find(s => top / s <= 3) ?? 200
  return Array.from({ length: Math.ceil(top / step) + 1 }, (_, i) => i * step)
})

const plotW = computed(() => Math.max(60, width.value - LEFT - RIGHT))
const band = computed(() => plotW.value / props.points.length)
const barW = computed(() => Math.min(MAX_BAR, band.value * 0.6))
const top = computed(() => ticks.value[ticks.value.length - 1])
const x = i => LEFT + band.value * (i + 0.5)
const y = v => TOP + PLOT_H - (v / top.value) * PLOT_H
const base = TOP + PLOT_H

/** Column with a rounded data-end and a square base. */
function barPath(i, v) {
  const x0 = x(i) - barW.value / 2
  const x1 = x0 + barW.value
  const yTop = y(v)
  const r = Math.min(CAP, (base - yTop) / 2, barW.value / 2)
  return `M${x0},${base}V${yTop + r}Q${x0},${yTop} ${x0 + r},${yTop}H${x1 - r}Q${x1},${yTop} ${x1},${yTop + r}V${base}Z`
}

/** Line segments, broken where a week has no value. */
const segments = computed(() => {
  const out = []
  let current = []
  props.points.forEach((p, i) => {
    if (p.value === null) {
      if (current.length) out.push(current)
      current = []
    } else current.push(`${x(i)},${y(p.value)}`)
  })
  if (current.length) out.push(current)
  return out.map(s => s.join(' '))
})

/** Show every k-th week label so they never collide. */
const labelEvery = computed(() => Math.max(1, Math.ceil(props.points.length / Math.max(1, Math.floor(plotW.value / 52)))))

const lastIndex = computed(() => props.points.map(p => p.value).findLastIndex(v => v !== null && (props.kind === 'line' || v > 0)))

const tooltip = computed(() => {
  if (active.value === null) return null
  const p = props.points[active.value]
  const v = p.value
  return {
    left: Math.min(Math.max(x(active.value), 60), width.value - 60),
    top: v === null ? TOP + PLOT_H / 2 : y(v),
    value: v === null ? '–' : props.format(v),
    label: p.long,
  }
})

const summary = computed(() =>
  props.points.map(p => `${p.long}: ${p.value === null ? '–' : props.format(p.value)}`).join('; '),
)
</script>

<template>
  <figure class="chart">
    <figcaption class="head">
      <b class="title">{{ title }}</b>
      <small v-if="subtitle" class="sub">{{ subtitle }}</small>
    </figcaption>

    <div ref="box" class="plot">
      <p v-if="!hasData" class="empty">{{ emptyText }}</p>
      <template v-else>
        <svg :width="width" :height="TOP + PLOT_H + AXIS_H" role="img" :aria-label="`${title}. ${summary}`">
          <!-- grid + y ticks -->
          <g class="grid">
            <line v-for="tk in ticks" :key="tk" :x1="LEFT" :x2="LEFT + plotW" :y1="y(tk)" :y2="y(tk)" />
          </g>
          <g class="axis">
            <text v-for="tk in ticks" :key="tk" :x="LEFT - 8" :y="y(tk)" text-anchor="end" dominant-baseline="middle">
              {{ format(tk) }}
            </text>
            <template v-for="(p, i) in points" :key="p.key">
              <text v-if="i % labelEvery === (points.length - 1) % labelEvery" :x="x(i)" :y="base + 18" text-anchor="middle">
                {{ p.label }}
              </text>
            </template>
          </g>

          <!-- crosshair (line) -->
          <line v-if="kind === 'line' && active !== null" class="crosshair" :x1="x(active)" :x2="x(active)" :y1="TOP" :y2="base" />

          <!-- marks -->
          <g v-if="kind === 'bar'" class="bars">
            <path
              v-for="(p, i) in points"
              v-show="p.value"
              :key="p.key"
              :d="p.value ? barPath(i, p.value) : ''"
              :class="{ dim: active !== null && active !== i }"
            />
          </g>
          <g v-else class="line">
            <polyline v-for="(s, i) in segments" :key="i" :points="s" />
            <template v-for="(p, i) in points" :key="p.key">
              <circle v-if="p.value !== null" :cx="x(i)" :cy="y(p.value)" :r="active === i ? 5.5 : 4" />
            </template>
          </g>

          <!-- the one direct label: latest value -->
          <text
            v-if="lastIndex >= 0"
            class="value"
            :x="kind === 'bar' ? x(lastIndex) : Math.min(x(lastIndex) + 8, LEFT + plotW)"
            :y="kind === 'bar' ? y(points[lastIndex].value) - 7 : y(points[lastIndex].value) - 10"
            :text-anchor="kind === 'bar' ? 'middle' : 'end'"
          >
            {{ format(points[lastIndex].value) }}
          </text>

          <!-- hit targets: the whole week band, bigger than the mark; keyboard reachable -->
          <rect
            v-for="(p, i) in points"
            :key="p.key"
            class="hit"
            :x="LEFT + band * i"
            :y="TOP"
            :width="band"
            :height="PLOT_H"
            tabindex="0"
            :aria-label="`${p.long}: ${p.value === null ? '–' : format(p.value)}`"
            @pointerenter="active = i"
            @pointermove="active = i"
            @pointerleave="active = null"
            @focus="active = i"
            @blur="active = null"
          />
        </svg>

        <div v-if="tooltip" class="tip" :style="{ left: `${tooltip.left}px`, top: `${tooltip.top}px` }" aria-hidden="true">
          <b>{{ tooltip.value }}</b>
          <span>{{ tooltip.label }}</span>
        </div>
      </template>
    </div>
  </figure>
</template>

<style scoped>
.chart {
  margin: 0;
  padding: 14px 16px 10px;
  border-radius: var(--bz-radius);
  background: var(--bz-card);
}
.head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 4px 10px;
  margin-bottom: 6px;
}
.title {
  font-size: 17px;
}
.sub {
  font-size: 14px;
  color: var(--bz-muted);
}
.plot {
  position: relative;
  width: 100%;
}
svg {
  display: block;
  overflow: visible;
}
.grid line {
  stroke: var(--bz-chart-grid);
  stroke-width: 1;
  shape-rendering: crispEdges;
}
.axis text {
  fill: var(--bz-muted);
  font-size: 12px;
  font-variant-numeric: tabular-nums;
}
.bars path {
  fill: var(--bz-chart);
  transition: opacity 0.12s;
}
.bars path.dim {
  opacity: 0.45;
}
.line polyline {
  fill: none;
  stroke: var(--bz-chart);
  stroke-width: 2;
  stroke-linejoin: round;
  stroke-linecap: round;
}
.line circle {
  fill: var(--bz-chart);
  stroke: var(--bz-card);
  stroke-width: 2;
}
.crosshair {
  stroke: var(--bz-muted);
  stroke-width: 1;
  opacity: 0.5;
}
.value {
  fill: var(--bz-ink);
  font-size: 13px;
  font-weight: 700;
}
.hit {
  fill: transparent;
  cursor: default;
  outline: none;
}
.hit:focus-visible {
  stroke: var(--bz-coral);
  stroke-width: 2;
}
.tip {
  position: absolute;
  z-index: 2;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 5px 10px;
  border-radius: 10px;
  background: var(--bz-ink);
  color: var(--bz-card);
  font-size: 13px;
  line-height: 1.25;
  white-space: nowrap;
  pointer-events: none;
  transform: translate(-50%, calc(-100% - 12px));
}
.tip b {
  font-size: 16px;
}
.empty {
  margin: 24px 0;
  text-align: center;
  color: var(--bz-muted);
}
</style>
