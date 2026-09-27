<template>
  <div class="leaderboard-view">
    <div class="header">
      <h1>🏆 Leaderboards</h1>
      <div class="tab-buttons">
        <button
          v-for="tab in ['family', 'classroom', 'regional']"
          :key="tab"
          @click="activeTab = tab"
          :class="['tab-btn', { active: activeTab === tab }]"
        >
          {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
        </button>
      </div>
    </div>

    <!-- Child's Current Rank -->
    <section class="my-rank card" v-if="childRank">
      <div class="rank-display">
        <div class="rank-badge">{{ childRank.rank }}</div>
        <div class="rank-info">
          <div class="rank-name">{{ childRank.name }}</div>
          <div class="rank-score">{{ childRank.score }} points</div>
        </div>
      </div>
    </section>

    <!-- Leaderboard Table -->
    <section class="leaderboard card">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Score</th>
            <th v-if="activeTab === 'family'">Badge</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(entry, index) in leaderboard" :key="entry.child_id" :class="{ 'your-rank': entry.child_id === childId }">
            <td class="rank">{{ entry.rank }}</td>
            <td class="name">
              <span v-if="entry.rank === 1">🥇</span>
              <span v-else-if="entry.rank === 2">🥈</span>
              <span v-else-if="entry.rank === 3">🥉</span>
              <span v-else class="rank-num">{{ entry.rank }}</span>
              {{ entry.name }}
            </td>
            <td class="score">{{ entry.score }}</td>
            <td v-if="activeTab === 'family'" class="badge">
              <span v-if="entry.score > 1000">⭐⭐⭐</span>
              <span v-else-if="entry.score > 500">⭐⭐</span>
              <span v-else-if="entry.score > 100">⭐</span>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- Message when classroom not available -->
    <div v-if="activeTab === 'classroom' && !childRank" class="message">
      <p>Your child is not assigned to a classroom yet.</p>
    </div>
  </div>
</template>

<script>
import { gamificationApi } from '../api/beszedClient'

export default {
  name: 'LeaderboardView',
  props: {
    childId: Number,
  },
  data() {
    return {
      activeTab: 'family',
      leaderboard: [],
      childRank: null,
      loading: false,
    }
  },
  watch: {
    activeTab() {
      this.loadLeaderboard()
    },
  },
  mounted() {
    this.loadLeaderboard()
  },
  methods: {
    async loadLeaderboard() {
      try {
        this.loading = true
        let response

        switch (this.activeTab) {
          case 'family':
            response = await gamificationApi.familyLeaderboard(this.childId)
            break
          case 'classroom':
            response = await gamificationApi.classroomLeaderboard(this.childId)
            break
          case 'regional':
            response = await gamificationApi.regionalLeaderboard()
            break
        }

        const { data } = response
        this.leaderboard = data.entries || []
        this.childRank = data.child_rank
      } catch (error) {
        console.error('Failed to load leaderboard:', error)
        this.leaderboard = []
        this.childRank = null
      } finally {
        this.loading = false
      }
    },
  },
}
</script>

<style scoped>
.leaderboard-view {
  padding: 2rem;
  max-width: 800px;
  margin: 0 auto;
}

.header {
  margin-bottom: 2rem;
}

.header h1 {
  font-size: 2rem;
  margin: 0 0 1rem 0;
}

.tab-buttons {
  display: flex;
  gap: 0.5rem;
}

.tab-btn {
  padding: 0.75rem 1.5rem;
  background: #f3f4f6;
  border: 2px solid transparent;
  border-radius: 0.5rem;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s;
}

.tab-btn.active {
  background: #4f46e5;
  color: white;
  border-color: #4f46e5;
}

.tab-btn:hover {
  border-color: #4f46e5;
}

.card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 0.75rem;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}

.my-rank {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.rank-display {
  display: flex;
  align-items: center;
  gap: 2rem;
}

.rank-badge {
  font-size: 3rem;
  font-weight: bold;
  width: 80px;
  height: 80px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.rank-info {
  flex: 1;
}

.rank-name {
  font-size: 1.5rem;
  font-weight: bold;
  margin-bottom: 0.5rem;
}

.rank-score {
  font-size: 1.25rem;
  opacity: 0.9;
}

.leaderboard table {
  width: 100%;
  border-collapse: collapse;
  font-size: 1rem;
}

.leaderboard thead {
  background: #f9fafb;
  border-bottom: 2px solid #e5e7eb;
}

.leaderboard th {
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: #374151;
}

.leaderboard tbody tr {
  border-bottom: 1px solid #e5e7eb;
  transition: background-color 0.2s;
}

.leaderboard tbody tr:hover {
  background: #f9fafb;
}

.leaderboard tbody tr.your-rank {
  background: #eff6ff;
  font-weight: 600;
  border-left: 4px solid #3b82f6;
}

.leaderboard td {
  padding: 1rem;
}

.rank {
  font-weight: 600;
  color: #6b7280;
  width: 50px;
}

.rank-num {
  display: inline-block;
  width: 24px;
  height: 24px;
  background: #e5e7eb;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  margin-right: 0.5rem;
}

.name {
  font-weight: 500;
}

.score {
  font-weight: bold;
  color: #4f46e5;
  font-size: 1.125rem;
}

.badge {
  text-align: center;
  font-size: 1.25rem;
}

.message {
  text-align: center;
  padding: 2rem;
  background: #fef3c7;
  border-radius: 0.5rem;
  color: #92400e;
}
</style>
