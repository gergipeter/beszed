<template>
  <div class="parent-dashboard">
    <div class="header">
      <h1>{{ child.name }}'s Progress</h1>
      <div class="header-actions">
        <button @click="downloadWeeklyReport" class="btn btn-primary">
          📥 Weekly Report
        </button>
        <button @click="downloadMonthlyReport" class="btn btn-primary">
          📥 Monthly Report
        </button>
      </div>
    </div>

    <!-- Overview Stats -->
    <section class="overview-grid">
      <div class="stat-card">
        <div class="stat-value">{{ dashboard.overview.accuracy_rate }}%</div>
        <div class="stat-label">Overall Accuracy</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ dashboard.overview.total_attempts }}</div>
        <div class="stat-label">Total Attempts</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ dashboard.overview.games_played }}</div>
        <div class="stat-label">Games Played</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ dashboard.overview.days_active }}</div>
        <div class="stat-label">Days Active</div>
      </div>
    </section>

    <!-- Today's Activity -->
    <section class="card">
      <h2>Today's Activity</h2>
      <div class="stats-grid">
        <div class="stat">
          <span class="label">Attempts:</span>
          <span class="value">{{ dashboard.today.attempts }}</span>
        </div>
        <div class="stat">
          <span class="label">Accuracy:</span>
          <span class="value">{{ dashboard.today.accuracy }}%</span>
        </div>
        <div class="stat">
          <span class="label">Time:</span>
          <span class="value">{{ dashboard.today.time_spent_minutes }} min</span>
        </div>
        <div class="stat">
          <span class="label">Games:</span>
          <span class="value">{{ dashboard.today.games_today }}</span>
        </div>
      </div>
    </section>

    <!-- Speech Metrics -->
    <section class="card">
      <h2>Speech Analysis</h2>
      <div class="speech-metrics">
        <div class="metric" v-if="dashboard.speech.latest_scores">
          <div class="metric-name">Latest Pronunciation</div>
          <div class="metric-bar">
            <div class="progress" :style="{ width: dashboard.speech.latest_scores.pronunciation + '%' }"></div>
            <span>{{ dashboard.speech.latest_scores.pronunciation }}%</span>
          </div>
        </div>
        <div class="metric" v-if="dashboard.speech.latest_scores">
          <div class="metric-name">Latest Fluency</div>
          <div class="metric-bar">
            <div class="progress" :style="{ width: dashboard.speech.latest_scores.fluency + '%' }"></div>
            <span>{{ dashboard.speech.latest_scores.fluency }}%</span>
          </div>
        </div>
        <div class="metric">
          <div class="metric-name">Month Avg Pronunciation</div>
          <div class="metric-bar">
            <div class="progress" :style="{ width: dashboard.speech.avg_pronunciation + '%' }"></div>
            <span>{{ dashboard.speech.avg_pronunciation }}%</span>
          </div>
        </div>
        <div class="metric">
          <div class="metric-name">Trend</div>
          <div class="trend-indicator" :class="dashboard.speech.trend">
            {{ dashboard.speech.trend }}
          </div>
        </div>
      </div>
    </section>

    <!-- Top Games -->
    <section class="card">
      <h2>Most Played Games</h2>
      <div class="games-list">
        <div v-for="game in dashboard.games.most_played.slice(0, 5)" :key="game.game" class="game-row">
          <span class="game-name">{{ game.game }}</span>
          <span class="game-stats">{{ game.attempts }} attempts, {{ game.accuracy }}% accuracy</span>
        </div>
      </div>
    </section>

    <!-- Pet Status -->
    <section class="card" v-if="dashboard.pet.status !== 'no_active_pet'">
      <h2>Pet Status</h2>
      <div class="pet-info">
        <div class="pet-name">{{ dashboard.pet.name }} ({{ dashboard.pet.species }})</div>
        <div class="pet-stats">
          <div class="stat">Health: <span class="bar" :style="{ width: dashboard.pet.health + '%' }"></span></div>
          <div class="stat">Hunger: <span class="bar" :style="{ width: dashboard.pet.hunger + '%' }"></span></div>
          <div class="stat">Happiness: <span class="bar" :style="{ width: dashboard.pet.happiness + '%' }"></span></div>
          <div class="stat">Energy: <span class="bar" :style="{ width: dashboard.pet.energy + '%' }"></span></div>
        </div>
      </div>
    </section>

    <!-- Alerts -->
    <section class="card alerts" v-if="dashboard.alerts.length">
      <h2>⚠️ Alerts</h2>
      <div v-for="alert in dashboard.alerts" :key="alert.type" class="alert" :class="alert.severity">
        {{ alert.message }}
      </div>
    </section>

    <!-- Milestones -->
    <section class="card milestones" v-if="dashboard.milestones.length">
      <h2>🏆 Milestones</h2>
      <div v-for="milestone in dashboard.milestones" :key="milestone.title" class="milestone">
        <span class="title">{{ milestone.title }}</span>
        <span class="date">{{ milestone.date }}</span>
      </div>
    </section>
  </div>
</template>

<script>
import { dashboardApi } from '../api/beszedClient'

export default {
  name: 'ParentDashboard',
  props: {
    childId: Number,
  },
  data() {
    return {
      child: { name: '' },
      dashboard: {
        overview: {},
        today: {},
        week: {},
        month: {},
        speech: {},
        games: { most_played: [] },
        pet: {},
        milestones: [],
        alerts: [],
      },
      loading: true,
    }
  },
  mounted() {
    this.loadDashboard()
  },
  methods: {
    async loadDashboard() {
      try {
        this.loading = true
        const { data } = await dashboardApi.parentDashboard(this.childId)
        this.dashboard = data
        this.child = data.child
      } catch (error) {
        console.error('Failed to load dashboard:', error)
      } finally {
        this.loading = false
      }
    },
    async downloadWeeklyReport() {
      try {
        const response = await dashboardApi.weeklyReport(this.childId)
        this.downloadFile(response.data, `weekly-report-${this.childId}.pdf`)
      } catch (error) {
        console.error('Failed to download weekly report:', error)
      }
    },
    async downloadMonthlyReport() {
      try {
        const response = await dashboardApi.monthlyReport(this.childId)
        this.downloadFile(response.data, `monthly-report-${this.childId}.pdf`)
      } catch (error) {
        console.error('Failed to download monthly report:', error)
      }
    },
    downloadFile(data, filename) {
      const url = window.URL.createObjectURL(new Blob([data]))
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', filename)
      document.body.appendChild(link)
      link.click()
      link.parentNode.removeChild(link)
    },
  },
}
</script>

<style scoped>
.parent-dashboard {
  padding: 2rem;
  max-width: 1200px;
  margin: 0 auto;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.header h1 {
  font-size: 2rem;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 1rem;
}

.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 0.5rem;
  cursor: pointer;
  font-weight: 500;
}

.btn-primary {
  background-color: #4f46e5;
  color: white;
}

.btn-primary:hover {
  background-color: #4338ca;
}

.overview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 1.5rem;
  border-radius: 0.75rem;
  text-align: center;
}

.stat-value {
  font-size: 2rem;
  font-weight: bold;
  margin-bottom: 0.5rem;
}

.stat-label {
  font-size: 0.875rem;
  opacity: 0.9;
}

.card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 0.75rem;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}

.card h2 {
  margin-top: 0;
  margin-bottom: 1rem;
  font-size: 1.25rem;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 1rem;
}

.stat {
  display: flex;
  justify-content: space-between;
  padding: 0.75rem;
  background: #f9fafb;
  border-radius: 0.5rem;
}

.stat .label {
  color: #6b7280;
  font-weight: 500;
}

.stat .value {
  font-weight: bold;
  color: #1f2937;
}

.speech-metrics {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.metric {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.metric-name {
  min-width: 150px;
  font-weight: 500;
}

.metric-bar {
  flex: 1;
  position: relative;
  height: 24px;
  background: #e5e7eb;
  border-radius: 0.5rem;
  overflow: hidden;
  display: flex;
  align-items: center;
}

.progress {
  height: 100%;
  background: linear-gradient(90deg, #10b981, #059669);
  transition: width 0.3s ease;
}

.metric-bar span {
  position: absolute;
  right: 0.5rem;
  font-size: 0.75rem;
  font-weight: bold;
  color: #1f2937;
}

.trend-indicator {
  padding: 0.5rem 1rem;
  background: #f0fdf4;
  border-radius: 0.5rem;
  font-weight: 600;
}

.trend-indicator.improving {
  color: #10b981;
}

.trend-indicator.declining {
  color: #ef4444;
}

.trend-indicator.stable {
  color: #6b7280;
}

.games-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.game-row {
  display: flex;
  justify-content: space-between;
  padding: 0.75rem;
  background: #f9fafb;
  border-radius: 0.5rem;
}

.game-name {
  font-weight: 500;
}

.game-stats {
  color: #6b7280;
  font-size: 0.875rem;
}

.pet-info {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.pet-name {
  font-size: 1.125rem;
  font-weight: 600;
}

.pet-stats {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.pet-stats .stat {
  display: flex;
  align-items: center;
  gap: 1rem;
  background: transparent;
  padding: 0;
}

.pet-stats .bar {
  height: 16px;
  background: #e5e7eb;
  border-radius: 0.25rem;
  display: inline-block;
  background: linear-gradient(90deg, #f59e0b, #f97316);
}

.alerts .alert {
  padding: 1rem;
  border-radius: 0.5rem;
  margin-bottom: 0.75rem;
}

.alerts .alert.warning {
  background-color: #fef3c7;
  color: #92400e;
  border-left: 4px solid #f59e0b;
}

.alerts .alert.info {
  background-color: #dbeafe;
  color: #0c4a6e;
  border-left: 4px solid #3b82f6;
}

.milestones {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.milestone {
  display: flex;
  justify-content: space-between;
  padding: 0.75rem;
  background: #f0fdf4;
  border-radius: 0.5rem;
  border-left: 4px solid #10b981;
}

.milestone .title {
  font-weight: 500;
  color: #065f46;
}

.milestone .date {
  color: #059669;
  font-size: 0.875rem;
}
</style>
