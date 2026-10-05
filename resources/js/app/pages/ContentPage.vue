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
const history = ref({}) // item id => list of edits
const loadingHistory = ref({}) // item id => is loading
const exportLoading = ref(false)
const uploadingField = ref(null) // field key being uploaded
const openMenu = ref(null) // item id whose overflow menu (⋯) is open

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
  if (spec.type === 'emoji_list' || spec.type === 'int_list') return value.join(' ')
  if (spec.type === 'pairs') return value.map(([e, n]) => `${e} ${n}`).join('\n')
  return String(value)
}
function fromText(spec, text) {
  const s = text.trim()
  if (spec.type === 'list') return s ? s.split(spec.separator).map(x => x.trim()).filter(Boolean) : []
  if (spec.type === 'emoji_list') return splitEmoji(s.replace(/\s+/g, ''))
  if (spec.type === 'int') return s === '' ? null : Number(s)
  if (spec.type === 'int_list') return s ? s.split(/[\s,]+/).map(Number) : []
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
  openMenu.value = null
  const note = item.uses ? ' Már játszottak vele, ezért csak kikapcsoljuk (az eredmények megmaradnak).' : ''
  if (window.confirm(`Biztosan törlöd: „${title(item)}”?${note}`)) act(http.delete(`/api/admin/content/${gameId.value}/${item.id}`))
}

function toggleMenu(item) {
  openMenu.value = openMenu.value === item.id ? null : item.id
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
  uploadingField.value = fieldKey
  const formData = new FormData()
  formData.append('image', file)
  try {
    const { data } = await http.post('/api/admin/content/images', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    form.value.payload[fieldKey] = data.reference
  } catch (e) {
    fieldErrors.value[fieldKey] = e.response?.data?.message || 'Upload sikertelen.'
  } finally {
    uploadingField.value = null
  }
}

const loadHistory = async (itemId) => {
  if (history.value[itemId]) return
  loadingHistory.value[itemId] = true
  try {
    // TODO: implement GET /api/admin/content/{game}/{item}/history endpoint
    // For now, placeholder that shows the feature is ready
    history.value[itemId] = []
  } catch (e) {
    history.value[itemId] = []
  } finally {
    loadingHistory.value[itemId] = false
  }
}

const doExportWithLoader = async () => {
  exportLoading.value = true
  try {
    doExport()
  } finally {
    setTimeout(() => {
      exportLoading.value = false
    }, 500)
  }
}

// Keyboard shortcuts
const handleKeyboard = (e) => {
  // Ctrl+E: Export CSV
  if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
    e.preventDefault()
    doExportWithLoader()
  }
  // Escape: Clear selection
  if (e.key === 'Escape' && selected.value.size > 0) {
    selected.value.clear()
    bulkAction.value = null
  }
}

watch(gameId, () => {
  form.value = null
  query.value = ''
  limit.value = PAGE
  selected.value.clear()
  loadItems()
})
const closeMenuOnOutsideClick = e => {
  if (openMenu.value !== null && !e.target.closest('.item-menu')) openMenu.value = null
}

onMounted(async () => {
  try {
    await loadGames()
    window.addEventListener('keydown', handleKeyboard)
    window.addEventListener('click', closeMenuOnOutsideClick)
  } catch (e) {
    error.value = e.response?.status === 403 ? 'Ehhez nincs jogosultságod.' : 'Nem sikerült betölteni.'
  }
})

watch(() => showHistory.value, async (itemId) => {
  if (itemId) {
    await loadHistory(itemId)
  }
})
</script>

<template>
  <main class="bz editor">
    <header class="top">
      <div>
        <h1>Tartalomszerkesztő</h1>
        <small class="shortcuts-hint" title="Ctrl+E: CSV exportálás | Esc: Kijelölés törlése">⌨️ Billentyűparancsok</small>
      </div>
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
              <label v-if="spec.type === 'emoji'" class="upload-btn" :title="uploadingField === key ? 'Feltöltés…' : 'Képfeltöltés'">
                <input type="file" accept="image/png,image/jpeg,image/webp" @change="e => uploadImage(e, key)" :disabled="uploadingField === key" />
                <span v-if="uploadingField === key" class="spinner"></span>
                <EmojiArt v-else char="📸" />
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

      <div class="toolbar">
        <div class="tools">
          <BzButton v-if="!form" variant="primary" @click="edit(null)">+ Új elem</BzButton>
          <input v-model="query" class="search" type="search" placeholder="Keresés…" aria-label="Keresés" />
          <label class="check"><input v-model="showInactive" type="checkbox" /> kikapcsoltak is</label>
          <BzButton size="sm" variant="soft" :disabled="exportLoading" @click="doExportWithLoader">
            <span v-if="exportLoading" class="spinner"></span>
            {{ exportLoading ? 'Letöltés…' : 'Exportálás CSV' }}
          </BzButton>
          <label class="file-input">
            <input type="file" accept=".csv,.txt" @change="e => importFile = e.target.files?.[0]" />
            Importálás CSV
          </label>
        </div>

        <div class="bulk-toolbar" :class="{ 'bulk-toolbar--active': selected.size > 0 }">
          <label class="check"><input type="checkbox" :checked="visible.length > 0 && selected.size === visible.length" @change="toggleAll" /> Mind</label>
          <span class="selected-count">{{ selected.size ? `${selected.size} kijelölve` : 'Nincs kijelölés' }}</span>
          <select v-model="bulkAction" :disabled="selected.size === 0">
            <option value="">-- Művelet --</option>
            <option value="activate">Aktiválás</option>
            <option value="deactivate">Deaktiválás</option>
            <option value="delete">Törlés</option>
          </select>
          <BzButton size="sm" :disabled="!bulkAction || selected.size === 0 || bulkLoading" @click="doBulkAction">Alkalmaz</BzButton>
        </div>

        <p class="muted count-line">
          {{ game.active }} elem van játékban ({{ game.total }} összesen).
          {{ game.adaptive ? 'A szint itt a nehézség.' : 'A szint itt a korcsoport: a kisebbek ritkábban kapnak nehéz elemet.' }}
        </p>
      </div>

      <div class="list" :aria-busy="loading">
        <div class="list-head">
          <span class="col-select"></span>
          <span class="col-art"></span>
          <span class="col-main">Elem</span>
          <span class="col-meta">Szint · eredet</span>
          <span class="col-actions"></span>
        </div>
        <div v-for="item in visible" :key="item.id" class="item" :class="{ 'item--off': !item.active }">
          <div class="item-row">
            <label class="item-select">
              <input type="checkbox" :checked="selected.has(item.id)" @change="() => toggleSelection(item)" />
            </label>
            <EmojiArt class="item-art" :char="picture(item) || '·'" />
            <div class="item-main">
              <div class="item-title">
                <b>{{ title(item) }}</b>
                <span v-if="item.status" :class="['status-badge', `status--${item.status}`]">
                  {{ item.status === 'draft' ? 'Piszkozat' : 'Élő' }}
                </span>
                <span v-if="!item.active" class="status-badge status--off">Kikapcsolva</span>
              </div>
            </div>
            <span class="item-meta muted">
              {{ item.level }}. szint ·
              {{ item.source === 'admin' ? 'saját' : item.edited ? 'alap, átírva' : 'alap' }}
              <template v-if="item.uses"> · {{ item.uses }}× játszva</template>
            </span>
            <div class="item-actions">
              <BzButton size="sm" variant="primary" @click="edit(item)">Szerkesztés</BzButton>
              <button
                type="button"
                class="icon-btn"
                :title="item.active ? 'Kikapcsolás' : 'Bekapcsolás'"
                :aria-label="item.active ? 'Kikapcsolás' : 'Bekapcsolás'"
                @click="toggle(item)"
              >
                <EmojiArt :char="item.active ? '👁️' : '🚫'" />
              </button>
              <div class="item-menu">
                <button type="button" class="icon-btn" title="Még több" aria-label="Még több" @click="toggleMenu(item)">⋯</button>
                <div v-if="openMenu === item.id" class="menu-popover">
                  <button type="button" @click="showHistory = showHistory === item.id ? null : item.id; openMenu = null">Előzmények</button>
                  <button type="button" class="danger" @click="remove(item)">Törlés</button>
                </div>
              </div>
            </div>
          </div>
          <div v-if="showHistory === item.id" class="item-history">
            <div v-if="loadingHistory[item.id]" class="history-loading">
              <span class="spinner small"></span>
              <span class="muted">Előzmények betöltése…</span>
            </div>
            <div v-else-if="history[item.id] && history[item.id].length > 0" class="history-list">
              <div v-for="(edit, i) in history[item.id]" :key="i" class="history-entry">
                <strong>{{ edit.action }}</strong>
                <span class="muted">{{ edit.editor_email }}</span>
                <span class="muted">{{ new Date(edit.created_at).toLocaleString('hu-HU') }}</span>
                <div v-if="edit.before || edit.after" class="history-diff">
                  <small v-if="edit.before" class="muted">volt: {{ JSON.stringify(edit.before).slice(0, 60) }}…</small>
                  <small v-if="edit.after" class="muted">lett: {{ JSON.stringify(edit.after).slice(0, 60) }}…</small>
                </div>
              </div>
            </div>
            <div v-else class="muted">
              Nincs szerkesztési előzmény
            </div>
          </div>
        </div>
      </div>
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
.top > div {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
h1 {
  margin: 0;
  font-size: 28px;
}
.shortcuts-hint {
  color: var(--bz-muted);
  font-size: 12px;
  cursor: help;
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
.toolbar {
  position: sticky;
  top: 0;
  z-index: 5;
  padding: 10px 16px 8px;
  margin: 0 -16px;
  background: var(--bz-card);
  border-bottom: 1px solid var(--bz-guide);
}
.tools {
  align-items: center;
}
.search {
  flex: 1;
  min-width: 160px;
  width: auto;
}
.count-line {
  margin: 8px 0 0;
}
.check {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-weight: 700;
}
.check input[type='checkbox'],
.item-select input[type='checkbox'] {
  width: 18px;
  height: 18px;
  appearance: none;
  border: 2px solid var(--bz-soft);
  border-radius: 5px;
  background-color: var(--bz-card);
  cursor: pointer;
}
.check input[type='checkbox']:checked,
.item-select input[type='checkbox']:checked {
  border-color: var(--bz-leaf);
  background-color: var(--bz-leaf);
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='9' viewBox='0 0 12 9'%3E%3Cpath d='M1 4.5l3.5 3.5L11 1' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: center;
}
.list {
  margin: 8px 0 0;
  padding: 0;
  transition: opacity 0.15s;
}
.list[aria-busy='true'] {
  opacity: 0.6;
}
.list-head {
  display: grid;
  grid-template-columns: 28px 30px 1fr 220px auto;
  align-items: center;
  gap: 12px;
  padding: 0 12px 6px;
  color: var(--bz-muted);
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}
.item {
  border-radius: var(--bz-radius-sm);
  background: var(--bz-card);
  margin-bottom: 4px;
}
.item-row {
  display: grid;
  grid-template-columns: 28px 30px 1fr 220px auto;
  align-items: center;
  gap: 12px;
  padding: 8px 12px;
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
  font-size: 26px;
}
.item-main {
  display: flex;
  min-width: 0;
}
.item-title {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
}
.item-title b {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.item-meta {
  font-size: 13px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.item-actions {
  display: flex;
  align-items: center;
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
.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  flex: none;
  border: none;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-soft);
  font-size: 16px;
  line-height: 1;
  cursor: pointer;
}
.icon-btn:hover {
  background: var(--bz-guide);
}
.item-menu {
  position: relative;
  flex: none;
}
.menu-popover {
  position: absolute;
  top: calc(100% + 4px);
  right: 0;
  z-index: 10;
  display: flex;
  flex-direction: column;
  min-width: 140px;
  padding: 4px;
  background: var(--bz-card);
  border: 1px solid var(--bz-guide);
  border-radius: 10px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
.menu-popover button {
  padding: 8px 10px;
  border: none;
  background: none;
  border-radius: 6px;
  text-align: left;
  font: inherit;
  font-weight: 600;
  color: var(--bz-ink);
  cursor: pointer;
}
.menu-popover button:hover {
  background: var(--bz-soft);
}
.menu-popover button.danger {
  color: var(--bz-coral-deep);
}
.item-history {
  width: 100%;
  padding-top: 10px;
  border-top: 1px solid var(--bz-guide);
  font-size: 14px;
}
.history-loading {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 0;
}
.history-list {
  max-height: 200px;
  overflow-y: auto;
  padding: 8px 0;
}
.history-entry {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 8px;
  margin-bottom: 6px;
  background: var(--bz-soft);
  border-radius: 4px;
  font-size: 12px;
}
.history-entry strong {
  color: var(--bz-ink);
  font-size: 13px;
}
.history-diff {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin-top: 4px;
  padding-top: 4px;
  border-top: 1px solid var(--bz-guide);
}
.spinner {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 2px solid var(--bz-guide);
  border-top-color: var(--bz-ink);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
.spinner.small {
  width: 12px;
  height: 12px;
  border-width: 1.5px;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
.bulk-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  margin-top: 8px;
  background: var(--bz-soft);
  border-radius: var(--bz-radius-sm);
  opacity: 0.6;
  transition: opacity 0.15s;
}
.bulk-toolbar--active {
  opacity: 1;
}
.bulk-toolbar select {
  padding: 6px 10px;
  border: 1px solid var(--bz-guide);
  border-radius: 6px;
  background: var(--bz-card);
}
.selected-count {
  font-weight: 600;
  color: var(--bz-ink);
  font-size: 14px;
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
.status--off {
  background: var(--bz-soft);
  color: var(--bz-muted);
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
@media (max-width: 720px) {
  .list-head {
    display: none;
  }
  .item-row {
    grid-template-columns: 24px 26px 1fr auto;
    row-gap: 6px;
  }
  .item-meta {
    grid-column: 3 / -1;
    white-space: normal;
  }
}
@media (max-width: 600px) {
  .results-modal {
    max-width: 90vw;
  }
}
</style>
