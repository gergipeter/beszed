/**
 * The Bearer token of the native app. The server hands it out at sign-in (`POST /api/auth/login`), it lasts 90 days,
 * and the app sends it as `Authorization: Bearer …` (app/http.js). It is kept in the device's secure storage (iOS
 * Keychain, Android Keystore) and cached in memory, so a request never waits on the device after the first read.
 *
 * `storage` is `{ get(key): Promise<string | null>, set(key, value): Promise<void>, remove(key): Promise<void> }`.
 * The native build plugs in the secure one (resources/native/main.js); without any (the website, tests) the token
 * lives in memory only. The website never has a token: it signs in with the cookie session.
 */
const KEY = 'beszed-token'

export function createTokenStore(storage = null) {
  /** undefined = not read from the device yet */
  let cached
  let loading = null

  /** The device's storage can fail (locked keychain, restored backup): that only means "no token". */
  const safely = async work => {
    try {
      return await work()
    } catch {
      return null
    }
  }

  return {
    /** Plugs in the storage; before the first read. */
    useStorage(next) {
      storage = next
      cached = undefined
      loading = null
    },

    /** @returns {Promise<string | null>} the token, read from the device once */
    get() {
      if (cached !== undefined) return Promise.resolve(cached)
      loading ??= safely(() => storage?.get(KEY)).then(value => {
        // A token saved or cleared while this was reading is newer than what the device said.
        if (cached === undefined) cached = value || null
        loading = null
        return cached
      })
      return loading
    },

    /** The token if it is already in memory, else null (never reads the device). */
    peek: () => cached ?? null,

    async set(token) {
      cached = token || null
      await safely(() => (cached ? storage?.set(KEY, cached) : storage?.remove(KEY)))
    },

    async clear() {
      cached = null
      await safely(() => storage?.remove(KEY))
    },
  }
}

/** The one token store of the app. */
export const tokens = createTokenStore()

/**
 * Adapts the secure-storage plugin (@aparajita/capacitor-secure-storage) to the storage interface above. Only its
 * string methods are used (no JSON, no dates). `ThisDeviceOnly`: the token neither migrates with a backup nor to a
 * new phone; the parent signs in again there.
 */
export function secureStorageOf(SecureStorage, KeychainAccess) {
  return {
    async get(key) {
      return (await SecureStorage.getItem(key)) || null
    },
    async set(key, value) {
      await SecureStorage.setDefaultKeychainAccess(KeychainAccess.whenUnlockedThisDeviceOnly)
      await SecureStorage.setItem(key, value)
    },
    async remove(key) {
      await SecureStorage.removeItem(key)
    },
  }
}
