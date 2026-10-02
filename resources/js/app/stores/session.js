import { defineStore } from 'pinia'
import { clearAudioCache } from '../../modules/beszed/services/audio/blobCache'
import { tokens } from '../authToken'
import { http } from '../http'
import { deviceName, isNative } from '../native'

/** On sign-out, drop personal data kept for offline play (service worker API cache, pending results, recordings in memory). */
function clearOfflineData() {
  clearAudioCache()
  try {
    navigator.serviceWorker?.controller?.postMessage({ type: 'clear-user-data' })
    localStorage.removeItem('beszed/outbox')
  } catch {
    /* storage unavailable */
  }
}

/** Native app: sign-up / sign-in are answered with a token to keep (secure storage), not with a cookie session. */
async function signInWithToken(path, fields) {
  const { data } = await http.post(path, { ...fields, device_name: deviceName() })
  await tokens.set(data.token)
}

/** The signed-in parent and their children. */
export const useSessionStore = defineStore('app/session', {
  state: () => ({
    /** @type {{ id: number, name: string, email: string, avatar: string | null, milestone_emails_enabled: boolean } | null} */
    user: null,
    /** @type {{ id: number, name: string, birth_date: string | null }[]} */
    children: [],
    /** @type {{ id: string, name: string, emoji: string }[]} óvodai jelek to pick from */
    signs: [],
    loaded: false,
    /** The server couldn't be reached on the last check. */
    unreachable: false,
    /** Signed out on purpose this visit (so local auto demo sign-in stays off). */
    loggedOut: false,
    /** The parent still has to accept the current privacy notice. */
    consentRequired: false,
  }),

  getters: {
    child: state => id => state.children.find(c => c.id === Number(id)) ?? null,
  },

  actions: {
    async load(force = false) {
      if (this.loaded && !force) return
      // Native app: no token means nobody is signed in; no need to ask the server (or to wait for it offline).
      if (isNative() && !(await tokens.get())) {
        this.signedOut({ keepToken: true })
        this.unreachable = false
        this.loaded = true
        return
      }
      try {
        const { data } = await http.get('/api/me', { quiet401: true })
        this.user = data.user
        this.children = data.children
        this.signs = data.signs ?? []
        this.consentRequired = Boolean(data.consent?.required)
        this.unreachable = false
      } catch (e) {
        const gone = e.response?.status === 401
        // Only a 401 says the token is bad; with no connection the token, the results still waiting to be sent and the
        // offline caches all stay for the next start (the app has no service worker on a phone, and a child may well
        // play on a train).
        this.signedOut({ keepToken: !gone, keepData: !gone })
        this.unreachable = !gone
      }
      this.loaded = true
    },

    async demoLogin() {
      await http.post('/auth/demo')
      this.loggedOut = false
      await this.load(true)
    },

    /** Sign-in with an e-mail address and a password. */
    async emailLogin(email, password) {
      if (isNative()) await signInWithToken('/api/auth/login', { email, password })
      else await http.post('/auth/login', { email, password })
      this.loggedOut = false
      await this.load(true)
    },

    async emailRegister(name, email, password) {
      if (isNative()) await signInWithToken('/api/auth/register', { name, email, password })
      else await http.post('/auth/register', { name, email, password })
      this.loggedOut = false
      await this.load(true)
    },

    async forgotPassword(email) {
      await http.post(isNative() ? '/api/auth/forgot-password' : '/auth/forgot-password', { email })
    },

    async resetPassword(fields) {
      await http.post(isNative() ? '/api/auth/reset-password' : '/auth/reset-password', fields)
    },

    async logout() {
      // Native: this device's token stops working on the server (best effort; it is forgotten here either way).
      if (isNative()) await http.delete('/api/auth/token').catch(() => {})
      else await http.post('/logout')
      this.signedOut()
      this.loggedOut = true
    },

    /**
     * `keepToken` / `keepData`: the app could not reach the server, which says nothing about whether the sign-in is still
     * good, so the token and the personal data kept for offline play (unsent results, cached answers) are not touched.
     */
    signedOut({ keepToken = false, keepData = false } = {}) {
      this.user = null
      this.children = []
      this.consentRequired = false
      if (!keepData) clearOfflineData()
      if (isNative() && !keepToken) tokens.clear()
    },

    async acceptConsent() {
      await http.post('/api/me/consent', { accept: true })
      this.consentRequired = false
    },

    async savePreferences(patch) {
      const { data } = await http.put('/api/me/preferences', patch)
      this.user = { ...this.user, ...data }
    },

    /** Deletes the parent, all children and every result. `confirm` must be "TÖRLÉS". */
    async deleteAccount(confirm) {
      await http.delete('/api/me', { data: { confirm } })
      this.signedOut()
      this.loggedOut = true
    },

    async addChild(fields) {
      const { data } = await http.post('/api/children', fields)
      this.children.push(data.child)
      return data.child
    },

    async updateChild(id, fields) {
      const { data } = await http.put(`/api/children/${id}`, fields)
      this.children = this.children.map(c => (c.id === id ? data.child : c))
      return data.child
    },

    async removeChild(id) {
      await http.delete(`/api/children/${id}`)
      this.children = this.children.filter(c => c.id !== id)
    },
  },
})
