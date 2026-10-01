import { defineStore } from 'pinia'
import { http } from '../http'

/** On sign-out, drop personal data kept for offline play (service worker API cache, pending results). */
function clearOfflineData() {
  try {
    navigator.serviceWorker?.controller?.postMessage({ type: 'clear-user-data' })
    localStorage.removeItem('beszed/outbox')
  } catch {
    /* storage unavailable */
  }
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
      try {
        const { data } = await http.get('/api/me', { quiet401: true })
        this.user = data.user
        this.children = data.children
        this.signs = data.signs ?? []
        this.consentRequired = Boolean(data.consent?.required)
        this.unreachable = false
      } catch (e) {
        this.signedOut()
        this.unreachable = e.response?.status !== 401
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
      await http.post('/auth/login', { email, password })
      this.loggedOut = false
      await this.load(true)
    },

    async emailRegister(name, email, password) {
      await http.post('/auth/register', { name, email, password })
      this.loggedOut = false
      await this.load(true)
    },

    async forgotPassword(email) {
      await http.post('/auth/forgot-password', { email })
    },

    async resetPassword(fields) {
      await http.post('/auth/reset-password', fields)
    },

    async logout() {
      await http.post('/logout')
      this.signedOut()
      this.loggedOut = true
    },

    signedOut() {
      this.user = null
      this.children = []
      this.consentRequired = false
      clearOfflineData()
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
