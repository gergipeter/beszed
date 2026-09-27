/**
 * Beszéd API Client
 * Handles all API calls for speech therapy app
 */

import axios from 'axios'

const client = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

export const speechApi = {
  analyze: (childId, audioBlob) => {
    const formData = new FormData()
    formData.append('audio', audioBlob, 'recording.wav')
    return client.post(`/beszed/children/${childId}/speech/analyze`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  history: (childId, limit = 10) => {
    return client.get(`/beszed/children/${childId}/speech/history?limit=${limit}`)
  },

  phonemeProgress: (childId) => {
    return client.get(`/beszed/children/${childId}/speech/phonemes/progress`)
  },

  trends: (childId, days = 30) => {
    return client.get(`/beszed/children/${childId}/speech/trends?days=${days}`)
  },
}

export const adaptiveApi = {
  recommendations: (childId, limit = 5) => {
    return client.get(`/beszed/children/${childId}/adaptive/recommendations?limit=${limit}`)
  },

  gameSkillLevel: (childId, game) => {
    return client.get(`/beszed/children/${childId}/adaptive/games/${game}/skill-level`)
  },

  predictPronunciation: (childId) => {
    return client.get(`/beszed/children/${childId}/adaptive/predict/pronunciation`)
  },

  predictFluency: (childId) => {
    return client.get(`/beszed/children/${childId}/adaptive/predict/fluency`)
  },

  predictGameProgress: (childId, game) => {
    return client.get(`/beszed/children/${childId}/adaptive/predict/games/${game}`)
  },

  progressSummary: (childId) => {
    return client.get(`/beszed/children/${childId}/adaptive/summary`)
  },
}

export const dashboardApi = {
  parentDashboard: (childId) => {
    return client.get(`/beszed/children/${childId}/dashboard`)
  },

  weeklyReport: (childId) => {
    return client.get(`/beszed/children/${childId}/dashboard/reports/weekly`)
  },

  monthlyReport: (childId) => {
    return client.get(`/beszed/children/${childId}/dashboard/reports/monthly`)
  },

  therapistNote: (childId) => {
    return client.get(`/beszed/children/${childId}/dashboard/reports/therapist-note`)
  },

  therapistDashboard: (childIds) => {
    return client.get(`/beszed/therapist/dashboard?children=${childIds.join(',')}`)
  },
}

export const gamificationApi = {
  familyLeaderboard: (childId) => {
    return client.get(`/beszed/children/${childId}/gamification/leaderboard/family`)
  },

  classroomLeaderboard: (childId) => {
    return client.get(`/beszed/children/${childId}/gamification/leaderboard/classroom`)
  },

  regionalLeaderboard: () => {
    return client.get(`/beszed/children/1/gamification/leaderboard/regional`)
  },

  achievements: (childId) => {
    return client.get(`/beszed/children/${childId}/gamification/achievements`)
  },

  checkAchievements: (childId) => {
    return client.post(`/beszed/children/${childId}/gamification/achievements/check`)
  },

  updateScore: (childId) => {
    return client.post(`/beszed/children/${childId}/gamification/score/update`)
  },
}

export const enterpriseApi = {
  fhirJson: (childId) => {
    return client.get(`/beszed/children/${childId}/enterprise/fhir/json`)
  },

  fhirXml: (childId) => {
    return client.get(`/beszed/children/${childId}/enterprise/fhir/xml`)
  },

  encryptionKeys: (childId) => {
    return client.get(`/beszed/children/${childId}/enterprise/encryption/keys`)
  },

  auditTrail: (childId, days = 90) => {
    return client.get(`/beszed/children/${childId}/enterprise/compliance/audit-trail?days=${days}`)
  },

  dsarReport: (childId) => {
    return client.post(`/beszed/children/${childId}/enterprise/compliance/dsar`)
  },

  validateCompliance: (childId) => {
    return client.get(`/beszed/children/${childId}/enterprise/compliance/validate`)
  },

  requestDeletion: (childId, reason) => {
    return client.post(`/beszed/children/${childId}/enterprise/deletion-request`, { reason })
  },

  getParentalControls: (childId) => {
    return client.get(`/beszed/children/${childId}/enterprise/parental-controls`)
  },

  updateParentalControls: (childId, controls) => {
    return client.post(`/beszed/children/${childId}/enterprise/parental-controls`, controls)
  },

  sendNotification: (childId, message, type = 'info') => {
    return client.post(`/beszed/children/${childId}/enterprise/notifications/send`, {
      message,
      type,
    })
  },
}

export default client
