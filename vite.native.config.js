// Build of the iOS / Android app's web part: `npm run build:native` (docs/native-app.md). The same Vue app as the
// website, built as static files for the Capacitor shell. Output: dist-native/ (capacitor.config.json: webDir).
// The website build is vite.config.js and is not affected by this file.
import vue from '@vitejs/plugin-vue'
import { fileURLToPath } from 'node:url'
import { defineConfig, loadEnv } from 'vite'

const repo = fileURLToPath(new URL('.', import.meta.url))

export default defineConfig(({ mode }) => {
  // .env.native (see .env.native.example) and the process environment; only VITE_* reach the browser code.
  const env = loadEnv(mode, repo, 'VITE_')

  const origin = String(env.VITE_API_ORIGIN ?? '').trim().replace(/\/+$/, '')
  if (!/^https?:\/\/[^/\s]+$/i.test(origin)) {
    throw new Error(
      `VITE_API_ORIGIN must be the API server's origin, e.g. https://beszed.example.hu (got "${origin}"). Set it in .env.native (see .env.native.example) or in the environment.`,
    )
  }
  if (!origin.startsWith('https://')) console.warn(`vite.native: VITE_API_ORIGIN is not https (${origin}); fine for a local test, not for a store build.`)

  // What SpaController renders into the website's page (#app-config); the same keys, for the app.
  const appConfig = {
    name: env.VITE_APP_NAME || 'Beszéd & DIFER',
    // Google blocks sign-in in an embedded web view; the app uses only its own e-mail sign-in (the demo login is a dev aid).
    auth: { google: false, email: true, demo: false },
    privacy: {
      version: env.VITE_PRIVACY_VERSION || '2026-09',
      controller: env.VITE_PRIVACY_CONTROLLER || '[Adatkezelő neve és címe – kitöltendő]',
      contact: env.VITE_PRIVACY_CONTACT || '[kapcsolattartó e-mail-cím – kitöltendő]',
      // which speech services are on, so the notice only names the ones that receive data (the server's STT_DRIVER / TTS_DRIVER)
      stt: env.VITE_STT_DRIVER || 'null',
      tts: env.VITE_TTS_DRIVER || 'piper',
    },
  }
  if (!env.VITE_PRIVACY_CONTROLLER || !env.VITE_PRIVACY_CONTACT) {
    console.warn('vite.native: VITE_PRIVACY_CONTROLLER / VITE_PRIVACY_CONTACT are not set: the in-app privacy notice shows placeholders (App Store blocker, see docs/ios-app-store-compliance.md).')
  }

  return {
    root: fileURLToPath(new URL('./resources/native', import.meta.url)),
    base: './',
    envDir: repo,
    publicDir: false,
    plugins: [
      vue(),
      {
        name: 'native-app-config',
        transformIndexHtml: html => html.replace('__APP_CONFIG__', () => JSON.stringify(appConfig).replace(/</g, '\\u003c')),
      },
    ],
    build: {
      outDir: fileURLToPath(new URL('./dist-native', import.meta.url)),
      emptyOutDir: true,
    },
  }
})
