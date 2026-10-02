// Entry of the native (iOS / Android) build, see vite.native.config.js and docs/native-app.md. The website's entry is
// resources/js/app.js; this one prepares what only the app has, then starts the same app.
import { KeychainAccess, SecureStorage } from '@aparajita/capacitor-secure-storage'
// Loads Capacitor's JavaScript side: window.Capacitor with registerPlugin(), which the RevenueCat purchases code uses.
import '@capacitor/core'
import { secureStorageOf, tokens } from '../js/app/authToken'

// The sign-in token lives in the Keychain / Keystore, not in localStorage.
tokens.useStorage(secureStorageOf(SecureStorage, KeychainAccess))

// After the line above, so that the first request already finds the storage.
import('../js/app.js')
