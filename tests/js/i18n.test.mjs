// hu and en carry the same keys (module texts and app-shell texts), and every literal t('a.b') in the front end exists.
import assert from 'node:assert/strict'
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'
import { test } from 'node:test'
import { fileURLToPath } from 'node:url'
import appEn from '../../resources/js/app/texts.en.js'
import appHu from '../../resources/js/app/texts.hu.js'
import en from '../../resources/js/modules/beszed/i18n/en.js'
import hu from '../../resources/js/modules/beszed/i18n/hu.js'

/** "a.b.c" for every string leaf; arrays count as one leaf (their length is compared separately). */
const paths = (node, prefix = '') =>
  Object.entries(node).flatMap(([key, value]) =>
    value && typeof value === 'object' && !Array.isArray(value) ? paths(value, `${prefix}${key}.`) : [`${prefix}${key}`],
  )

const missingIn = (from, other) => paths(from).filter(path => !paths(other).includes(path))

test('module i18n: hu and en have the same keys', () => {
  assert.deepEqual(missingIn(hu, en), [], 'in hu, missing in en')
  assert.deepEqual(missingIn(en, hu), [], 'in en, missing in hu')
})

test('app shell texts: hu and en have the same keys', () => {
  assert.deepEqual(missingIn(appHu, appEn), [], 'in hu, missing in en')
  assert.deepEqual(missingIn(appEn, appHu), [], 'in en, missing in hu')
})

test('app shell texts: lists have the same length in both languages', () => {
  assert.equal(appHu.consent.storedItems.length, appEn.consent.storedItems.length)
})

test('placeholders match between hu and en', () => {
  const placeholders = text => [...String(text).matchAll(/\{(\w+)\}/g)].map(m => m[1]).sort()
  const get = (node, path) => path.split('.').reduce((n, part) => n?.[part], node)
  for (const [a, b, label] of [[hu, en, 'module'], [appHu, appEn, 'app']]) {
    for (const path of paths(a)) {
      const [x, y] = [get(a, path), get(b, path)]
      if (typeof x === 'string' && typeof y === 'string') assert.deepEqual(placeholders(x), placeholders(y), `${label}: ${path}`)
    }
  }
})

test('every literal t("key") used by the front end exists in hu', () => {
  const files = []
  const walk = dir => {
    for (const name of readdirSync(dir)) {
      const path = join(dir, name)
      if (statSync(path).isDirectory()) walk(path)
      else if (/\.(js|vue)$/.test(name)) files.push(path)
    }
  }
  const root = fileURLToPath(new URL('../../resources/js', import.meta.url))
  walk(root)
  const known = new Set(paths(hu))
  const missing = []
  for (const file of files) {
    for (const [, key] of readFileSync(file, 'utf8').matchAll(/\bt\(\s*'([a-zA-Z][\w.]*)'/g)) {
      // 'x.y' is the example in i18n/index.js's own comment
      if (key.includes('.') && key !== 'x.y' && !known.has(key)) missing.push(`${key}  (${relative(root, file)})`)
    }
  }
  assert.deepEqual(missing, [])
})
