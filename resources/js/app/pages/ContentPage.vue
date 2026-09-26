<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { BzButton, BzNotice, EmojiArt } from '../../modules/beszed'
import '../../modules/beszed/styles/index.css'
import { splitEmoji } from '../../modules/beszed/utils/emoji'
import { http } from '../http'

/**
 * Content editor (ADMIN_EMAILS only): the words, sentences and picture sets of
 * every game. The form comes from the game's schema (config/beszed_content.php);
 * the server checks each item against the Hungarian rules before saving.
 */
const LEVELS = {
  age: { 1: '1 – könnyű (3–4 év)', 2: '2 – közepes (5–6 év)', 3: '3 – nehéz (7+ év)' },
  adaptive: { 1: '1 – könnyű', 2: '2 – közepes', 3: '3 – nehéz' },
}

const games = ref([])
const gameId = ref(null)
const items = ref([])
const loading = ref(false)
const error = ref('')
const query = ref('')
const showInactive = ref(false)
/** Games have hundreds of items: render them a page at a time. */
const PAGE = 100
const limit = ref(PAGE)

/** The item being edited (id null = new), as form strings. */
const form = ref(null)
const fieldErrors = ref({})
const saving = ref(false)
const formEl = ref(null)

/** Bulk operations state */
const selected = ref(new Set())
const bulkAction = ref(null)
const bulkLoading = ref(false)
const importFile = ref(null)
const importResults = ref(null)
const showImportResults = ref(false)
const showHistory = ref(null) // item id whose history is shown

const game = computed(() => games.value.find(g => g.id === gameId.value) ?? null)
const fields = computed(() => Object.entries(game.value?.schema.fields ?? {}))
const levelLabels = computed(() => (game.value?.adaptive ? LEVELS.adaptive : LEVELS.age))

const matching = computed(() => {
  const q = query.value.trim().toLowerCase()
  return items.value.filter(
    i => (showInactive.value || i.active) && (!q || JSON.stringify(i.payload).toLowerCase().includes(q)),
  )
})

const visible = computed(() => matching.value.slice(0, limit.value))
watch([query, showInactive], () => (limit.value = PAGE))

const title = item => {
  const v = item.payload[game.value.schema.title]
  return Array.isArray(v) ? v.join(' ') : v
}
const picture = item => item.payload.emoji ?? item.payload.icon ?? item.payload.emojis?.slice(0, 4).join('') ?? ''
const shown = spec => !spec.when || form.value.payload[spec.when[0]] === spec.when[1]

// Payload value <-> the text the parent types.
function toText(spec, value) {
  if (value == null) return ''
  if (spec.type === 'list') return value.join(spec.separator === '|' ? ' | ' : spec.separator)
  if (spec.type === 'emoji_list') return value.join(' ')
  if (spec.type === 'pairs') return value.map(([e, n]) => `${e} ${n}`).join('\n')
  return String(value)
}
function fromText(spec, text) {
  const s = text.trim()
  if (spec.type === 'list') return s ? s.split(spec.separator).map(x => x.trim()).filter(Boolean) : []
  if (spec.type === 'emoji_list') return splitEmoji(s.replace(/\s+/g, ''))
  if (spec.type === 'pairs') {
    return s
      .split('\n')
      .map(line => line.trim())
      .filter(Boolean)
      .map(line => {
        const [first] = splitEmoji(line)
        return [first, line.slice(first.length).trim()]
      })
  }
  return s
}

async function loadGames() {
  const { data } = await http.get('/api/admin/content')
  games.value = data.games
  gameId.value ??= data.games[0]?.id ?? null
}

async function loadItems() {
  if (!gameId.value) return
  loading.value = true
  error.value = ''
  try {
    const { data } = await http.get(`/api/admin/content/${gameId.value}`)
    items.value = data.items
  } catch (e) {
    error.value = e.response?.data?.message || 'Nem sikerült betölteni.'
  } finally {
    loading.value = false
  }
}

function edit(item) {
  fieldErrors.value = {}
  const payload = {}
  for (const [key, spec] of fields.value) {
    const value = item?.payload[key] ?? (spec.type === 'select' ? Object.keys(spec.options)[0] : null)
    payload[key] = spec.type === 'select' ? value : toText(spec, value)
  }
  form.value = reactive({ id: item?.id ?? null, level: item?.level ?? 1, active: item?.active ?? true, status: item?.status ?? 'live', payload })
  // the form sits above the list: bring it into view when editing an item further down
  nextTick(() => formEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' }))
}

async function save() {
  saving.value = true
  fieldErrors.value = {}
  const payload = {}
  for (const [key, spec] of fields.value) {
    if (shown(spec)) payload[key] = spec.type === 'select' ? form.value.payload[key] : fromText(spec, form.value.payload[key])
  }
  const body = { level: form.value.level, active: form.value.active, payload }
  try {
    const url = `/api/admin/content/${gameId.value}`
    await (form.value.id ? http.put(`${url}/${form.value.id}`, body) : http.post(url, body))
    form.value = null
    await Promise.all([loadItems(), loadGames()])
  } catch (e) {
    const errors = e.response?.data?.errors ?? {}
    fieldErrors.value = Object.fromEntries(Object.entries(errors).map(([k, v]) => [k.replace(/^payload\./, ''), v[0]]))
    if (!Object.keys(errors).length) fieldErrors.value = { _: e.response?.data?.message || 'Nem sikerült menteni.' }
  } finally {
    saving.value = false
  }
}

/** Runs a list action; a refusal (e.g. an old item that breaks a newer rule) shows up top. */
async function act(request) {
  error.value = ''
  try {
    await request
    await Promise.all([loadItems(), loadGames()])
  } catch (e) {
    const first = Object.values(e.response?.data?.errors ?? {})[0]?.[0]
    error.value = first || e.response?.data?.message || 'Nem sikerült.'
  }
}

const toggle = item =>
  act(http.put(`/api/admin/content/${gameId.value}/${item.id}`, { level: item.level, active: !item.active, payload: item.payload }))

function remove(item) {
  const note = item.uses ? ' Már játszottak vele, ezért csak kikapcsoljuk (az eredmények megmaradnak).' : ''
  if (window.confirm(`Biztosan törlöd: „${title(item)}”?${note}`)) act(http.delete(`/api/admin/content/${gameId.value}/${item.id}`))
}

/** Hear it in Csillám's server voice (checks the pronunciation of a new word). */
function listen(text) {
  if (text) new Audio(`/api/beszed/tts?t=${encodeURIComponent(text)}`).play().catch(() => {})
}

/** Bulk actions */
const toggleSelection = item => {
  if (selected.value.has(item.id)) {
    selected.value.delete(item.id)
  } else {
    selected.value.add(item.id)
  }
}

const toggleAll = () => {
  if (selected.value.size === visible.value.length) {
    selected.value.clear()
  } else {
    visible.value.forEach(item => selected.value.add(item.id))
  }
}

const doBulkAction = async () => {
  if (!bulkAction.value || selected.value.size === 0) return
  bulkLoading.value = true
  try {
    await http.post(`/api/admin/content/${gameId.value}/bulk`, {
      ids: Array.from(selected.value),
      action: bulkAction.value,
    })
    selected.value.clear()
    bulkAction.value = null
    await Promise.all([loadItems(), loadGames()])
  } catch (e) {
    error.value = e.response?.data?.message || 'Nem sikerült.'
  } finally {
    bulkLoading.value = false
  }
}

const doExport = () => {
  window.location.href = `/api/admin/content/${gameId.value}/export`
}

const doImport = async () => {
  if (!importFile.value) return
  bulkLoading.value = true
  showImportResults.value = true
  const formData = new FormData()
  formData.append('file', importFile.value)
  try {
    const { data } = await http.post(`/api/admin/content/${gameId.value}/import`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    importResults.value = data
    importFile.value = null
    await Promise.all([loadItems(), loadGames()])
  } catch (e) {
    error.value = e.response?.data?.message || 'Import sikertelen.'
  } finally {
    bulkLoading.value = false
  }
}

const uploadImage = async (event, fieldKey) => {
  const file = event.target.files?.[0]
  if (!file) return
  const formData = new FormData()
  formData.append('image', file)
  try {
    const { data } = await http.post('/api/admin/content/images', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    form.value.payload[fieldKey] = data.reference
  } catch (e) {
    fieldErrors.value[fieldKey] = e.response?.data?.message || 'Upload sikertelen.'
  }
}

watch(gameId, () => {
  form.value = null
  query.value = ''
  limit.value = PAGE
  selected.value.clear()
  loadItems()
})
onMounted(async () => {
  try {
    await loadGames()
  } catch (e) {
    error.value = e.response?.status === 403 ? 'Ehhez nincs jogosultságod.' : 'Nem sikerült betölteni.'
  }
})
</script>

<template>
  <main class="bz editor">
    <header class="top">
      <h1>Tartalomszerkesztő</h1>
      <BzButton size="sm" variant="soft" :to="{ name: 'children' }">Vissza</BzButton>
    </header>

    <nav class="games" aria-label="Játékok">
      <button
        v-for="g in games"
        :key="g.id"
        type="button"
        class="chip"
        :class="{ 'chip--on': g.id === gameId }"
        :aria-pressed="g.id === gameId"
        @click="gameId = g.id"
      >
        <EmojiArt :char="g.emoji" /> {{ g.name }} <small>{{ g.active }}</small>
      </button>
    </nav>

    <BzNotice v-if="error" tone="warn">{{ error }}</BzNotice>

    <template v-if="game">
      <!-- the form -->
      <form v-if="form" ref="formEl" class="card form" @submit.prevent="save">
        <h2>{{ form.id ? 'Szerkesztés' : 'Új elem' }} – {{ game.name }}</h2>
        <BzNotice v-if="fieldErrors._" tone="warn">{{ fieldErrors._ }}</BzNotice>

        <template v-for="[key, spec] in fields" :key="key">
          <label v-if="shown(spec)" class="field" :class="{ 'field--error': fieldErrors[key] }">
            <span>{{ spec.label }}</span>
            <select v-if="spec.type === 'select'" v-model="form.payload[key]">
              <option v-for="(label, value) in spec.options" :key="value" :value="value">{{ label }}</option>
            </select>
            <textarea v-else-if="spec.type === 'pairs'" v-model="form.payload[key]" rows="6" :placeholder="spec.hint" />
            <span v-else class="with-preview">
              <input v-model="form.payload[key]" :placeholder="spec.hint" />
              <EmojiArt v-if="spec.type === 'emoji' || spec.type === 'emoji_list'" class="preview" :char="form.payload[key] || ' '" />
              <label v-if="spec.type === 'emoji'" class="upload-btn" title="Képfeltöltés">
                <input type="file" accept="image/png,image/jpeg,image/webp" @change="e => uploadImage(e, key)" />
                <EmojiArt char="📸" />
              </label>
              <button
                v-else-if="spec.type === 'text' && spec.speak !== false"
                type="button"
                class="listen"
                aria-label="Meghallgatom"
                title="Meghallgatom Csillám hangján"
                @click="listen(form.payload[key])"
              >
                <EmojiArt char="🔊" />
              </button>
            </span>
            <small v-if="spec.hint && spec.type === 'pairs'" class="hint">{{ spec.hint }}</small>
            <small v-if="fieldErrors[key]" class="error">{{ fieldErrors[key] }}</small>
          </label>
        </template>

        <div class="row">
          <label class="field">
            <span>Szint</span>
            <select v-model.number="form.level">
              <option v-for="(label, n) in levelLabels" :key="n" :value="Number(n)">{{ label }}</option>
            </select>
          </label>
          <label class="check"><input v-model="form.active" type="checkbox" /> Játékban</label>
          <label class="field">
            <span>Státusz</span>
            <select v-model="form.status">
              <option value="live">Élő</option>
              <option value="draft">Piszkozat</option>
            </select>
          </label>
        </div>

        <div class="bz-row">
          <BzButton variant="primary" :disabled="saving" @click="save">Mentés</BzButton>
          <BzButton @click="form = null">Mégse</BzButton>
        </div>
      </form>

      <div class="tools">
        <BzButton v-if="!form" variant="primary" @click="edit(null)">+ Új elem</BzButton>
        <input v-model="query" class="search" type="search" placeholder="Keresés…" aria-label="Keresés" />
        <label class="check"><input v-model="showInactive" type="checkbox" /> kikapcsoltak is</label>
        <BzButton size="sm" variant="soft" @click="doExport">Exportálás CSV</BzButton>
        <label class="file-input">
          <input type="file" accept=".csv,.txt" @change="e => importFile = e.target.files?.[0]" />
          Importálás CSV
        </label>
      </div>

      <div v-if="selected.size > 0" class="bulk-toolbar">
        <label class="check"><input type="checkbox" :checked="selected.size === visible.length" @change="toggleAll" /> Mind kijelölve</label>
        <span class="selected-count">{{ selected.size }} kijelölve</span>
        <select v-model="bulkAction">
          <option value="">-- Művelet --</option>
          <option value="activate">Aktiválás</option>
          <option value="deactivate">Deaktiválás</option>
          <option value="delete">Törlés</option>
        </select>
        <BzButton :disabled="!bulkAction || bulkLoading" @click="doBulkAction">Alkalmaz</BzButton>
      </div>

      <p class="muted">
        {{ game.active }} elem van játékban ({{ game.total }} összesen).
        {{ game.adaptive ? 'A szint itt a nehézség.' : 'A szint itt a korcsoport: a kisebbek ritkábban kapnak nehéz elemet.' }}
      </p>

      <ul class="list" :aria-busy="loading">
        <li v-for="item in visible" :key="item.id" class="item" :class="{ 'item--off': !item.active }">
          <label class="item-select">
            <input type="checkbox" :checked="selected.has(item.id)" @change="() => toggleSelection(item)" />
          </label>
          <EmojiArt class="item-art" :char="picture(item) || '·'" />
          <div class="item-main">
            <div>
              <b>{{ title(item) }}</b>
              <span v-if="item.status" :class="['status-badge', `status--${item.status}`]">
                {{ item.status === 'draft' ? 'Piszkozat' : 'Élő' }}
              </span>
            </div>
            <span class="muted">
              {{ item.level }}. szint ·
              {{ item.source === 'admin' ? 'saját' : item.edited ? 'alap, átírva' : 'alap' }}
              <template v-if="item.uses"> · {{ item.uses }}× játszva</template>
              <template v-if="!item.active"> · kikapcsolva</template>
            </span>
          </div>
          <div class="item-actions">
            <BzButton size="sm" @click="edit(item)">Szerkesztés</BzButton>
            <BzButton size="sm" variant="soft" @click="toggle(item)">{{ item.active ? 'Kikapcsolás' : 'Bekapcsolás' }}</BzButton>
            <BzButton size="sm" variant="danger" @click="remove(item)">Törlés</BzButton>
            <BzButton size="sm" variant="soft" @click="showHistory = showHistory === item.id ? null : item.id">Előzmények</BzButton>
          </div>
          <div v-if="showHistory === item.id" class="item-history">
            <p class="muted">Szerkesztési előzmények (hamarosan...)</p>
          </div>
        </li>
      </ul>
      <div v-if="matching.length > visible.length" class="more">
        <BzButton @click="limit += PAGE">Még {{ Math.min(PAGE, matching.length - visible.length) }} ({{ visible.length }} / {{ matching.length }})</BzButton>
      </div>

      <div v-if="showImportResults && importResults" class="import-results">
        <div class="results-overlay" @click="showImportResults = false" />
        <div class="results-modal">
          <h3>Import eredmények</h3>
          <p><strong>{{ importResults.imported }} sor sikeresen importálva</strong></p>
          <div v-if="importResults.errors.length > 0" class="errors">
            <p><strong>{{ importResults.errors.length }} sor hiba:</strong></p>
            <ul>
              <li v-for="(err, i) in importResults.errors.slice(0, 10)" :key="i" class="error-row">
                <strong>{{ err.row }}. sor:</strong> {{ Object.values(err.errors)[0] }}
              </li>
            </ul>
            <p v-if="importResults.errors.length > 10" class="muted">… és {{ importResults.errors.length - 10 }} sor még</p>
          </div>
          <BzButton @click="showImportResults = false">Bezárás</BzButton>
        </div>
      </div>
    </template>
  </main>
</template>

<style scoped>
.editor {
  max-width: 980px;
}
.top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
h1 {
  margin: 0;
  font-size: 28px;
}
h2 {
  margin: 0;
  font-size: 20px;
}
.games {
  display: flex;
  gap: 8px;
  margin: 14px -16px;
  padding: 0 16px 6px;
  overflow-x: auto;
  scrollbar-width: thin;
}
.chip {
  display: inline-flex;
  flex: none;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  font-weight: 700;
  white-space: nowrap;
}
.chip small {
  color: var(--bz-muted);
  font-variant-numeric: tabular-nums;
}
.chip--on {
  background: var(--bz-chart);
  color: #fff;
}
.chip--on small {
  color: inherit;
  opacity: 0.8;
}
.card {
  margin-bottom: 14px;
  padding: 16px;
  border-radius: var(--bz-radius);
  background: var(--bz-card);
}
.form {
  display: flex;
  flex-direction: column;
  gap: 12px;
  border: 3px solid var(--bz-chart);
}
.field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-weight: 700;
}
.field input,
.field select,
.field textarea,
.search {
  width: 100%;
  padding: 9px 12px;
  border: 2px solid var(--bz-guide);
  border-radius: 12px;
  background: var(--bz-soft);
  color: var(--bz-ink);
  font: inherit;
  font-weight: 400;
}
.field--error input,
.field--error textarea {
  border-color: var(--bz-coral);
}
.with-preview {
  display: flex;
  align-items: center;
  gap: 8px;
}
.preview {
  flex: none;
  font-size: 32px;
}
.listen {
  flex: none;
  font-size: 22px;
}
.hint,
.muted {
  color: var(--bz-muted);
  font-size: 14px;
  font-weight: 400;
}
.error {
  color: var(--bz-coral);
  font-weight: 700;
}
.row,
.tools {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 12px;
}
.tools {
  align-items: center;
  margin-bottom: 6px;
}
.search {
  flex: 1;
  min-width: 160px;
  width: auto;
}
.check {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-weight: 700;
}
.list {
  margin: 8px 0 0;
  padding: 0;
  list-style: none;
  transition: opacity 0.15s;
}
.list[aria-busy='true'] {
  opacity: 0.6;
}
.item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: var(--bz-radius-sm);
  background: var(--bz-card);
  margin-bottom: 6px;
}
.more {
  display: flex;
  justify-content: center;
  margin-top: 10px;
}
.item--off {
  opacity: 0.55;
}
.item-art {
  flex: none;
  font-size: 30px;
}
.item-main {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}
.item-main b {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.item-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 6px;
}
.item-select {
  display: inline-flex;
  align-items: center;
  flex: none;
}
.item-select input {
  margin: 0;
}
.item-history {
  width: 100%;
  padding-top: 10px;
  border-top: 1px solid var(--bz-guide);
  font-size: 14px;
}
.bulk-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  padding: 12px;
  margin: 12px 0;
  background: var(--bz-soft);
  border-radius: var(--bz-radius-sm);
}
.bulk-toolbar select {
  padding: 8px 12px;
  border: 1px solid var(--bz-guide);
  border-radius: 6px;
  background: var(--bz-card);
}
.selected-count {
  font-weight: 600;
  color: var(--bz-ink);
}
.status-badge {
  display: inline-block;
  margin-left: 8px;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
}
.status--draft {
  background: #fff3cd;
  color: #856404;
}
.status--live {
  background: #d4edda;
  color: #155724;
}
.upload-btn {
  display: flex;
  align-items: center;
  cursor: pointer;
  flex: none;
  font-size: 18px;
}
.upload-btn input {
  display: none;
}
.file-input {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border: 1px solid var(--bz-guide);
  border-radius: 6px;
  background: var(--bz-soft);
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
}
.file-input input {
  display: none;
}
.import-results {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.results-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
}
.results-modal {
  position: relative;
  background: var(--bz-card);
  padding: 20px;
  border-radius: var(--bz-radius);
  max-width: 500px;
  max-height: 80vh;
  overflow-y: auto;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
.results-modal h3 {
  margin: 0 0 12px;
}
.results-modal p {
  margin: 8px 0;
}
.results-modal .errors {
  margin: 12px 0;
  padding: 12px;
  background: var(--bz-soft);
  border-radius: 6px;
  border-left: 4px solid #ff6b6b;
}
.error-row {
  margin: 4px 0;
  font-size: 13px;
  color: #666;
}
@media (max-width: 600px) {
  .item {
    flex-wrap: wrap;
  }
  .item-actions {
    width: 100%;
  }
  .results-modal {
    max-width: 90vw;
  }
}
</style>
