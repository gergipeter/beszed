# Frontend Integration Guide

This guide explains how to integrate the Beszéd Vue.js components with the backend API.

## Project Structure

```
resources/
├── js/
│   ├── api/
│   │   └── beszedClient.js          # API client for all phases
│   └── components/
│       ├── ParentDashboard.vue      # Phase 3 parent view
│       └── LeaderboardView.vue      # Phase 4 leaderboards
└── css/
    └── app.css                      # Global styles
```

## API Client (beszedClient.js)

The `beszedClient.js` file provides organized API methods grouped by feature:

### Speech API (Phase 1)
```javascript
import { speechApi } from '@/api/beszedClient'

// Analyze audio recording
await speechApi.analyze(childId, audioBlob)

// Get recording history
await speechApi.history(childId, limit)

// Get phoneme progress
await speechApi.phonemeProgress(childId)

// Get trends
await speechApi.trends(childId, days)
```

### Adaptive Learning API (Phase 2)
```javascript
import { adaptiveApi } from '@/api/beszedClient'

// Get personalized game recommendations
await adaptiveApi.recommendations(childId, limit)

// Get child's skill level in a game
await adaptiveApi.gameSkillLevel(childId, game)

// Predict pronunciation progress
await adaptiveApi.predictPronunciation(childId)

// Get overall progress summary
await adaptiveApi.progressSummary(childId)
```

### Dashboard API (Phase 3)
```javascript
import { dashboardApi } from '@/api/beszedClient'

// Get parent dashboard metrics
const { data } = await dashboardApi.parentDashboard(childId)

// Get therapist dashboard (multiple children)
await dashboardApi.therapistDashboard([childId1, childId2])

// Download reports (returns PDF blob)
await dashboardApi.weeklyReport(childId)
await dashboardApi.monthlyReport(childId)
await dashboardApi.therapistNote(childId)
```

### Gamification API (Phase 4)
```javascript
import { gamificationApi } from '@/api/beszedClient'

// Get leaderboards
await gamificationApi.familyLeaderboard(childId)
await gamificationApi.classroomLeaderboard(childId)
await gamificationApi.regionalLeaderboard()

// Get achievements
await gamificationApi.achievements(childId)

// Update score and check achievements
await gamificationApi.updateScore(childId)
await gamificationApi.checkAchievements(childId)
```

### Enterprise API (Phase 5)
```javascript
import { enterpriseApi } from '@/api/beszedClient'

// Export FHIR data
await enterpriseApi.fhirJson(childId)
await enterpriseApi.fhirXml(childId)

// Get encryption keys
await enterpriseApi.encryptionKeys(childId)

// Compliance & audit
await enterpriseApi.auditTrail(childId, days)
await enterpriseApi.validateCompliance(childId)

// Parental controls
await enterpriseApi.getParentalControls(childId)
await enterpriseApi.updateParentalControls(childId, controls)
```

## Components

### ParentDashboard.vue

Parent-facing dashboard showing child's progress.

**Props:**
- `childId` (Number) - The child's ID

**Features:**
- Overview metrics (accuracy, attempts, games, days active)
- Today's activity
- Speech analysis metrics with visual progress bars
- Top games with accuracy stats
- Pet status display
- Alert notifications
- Milestone tracking
- Download weekly/monthly PDF reports

**Usage:**
```vue
<template>
  <ParentDashboard :child-id="1" />
</template>

<script>
import ParentDashboard from '@/components/ParentDashboard.vue'

export default {
  components: { ParentDashboard }
}
</script>
```

### LeaderboardView.vue

Interactive leaderboard with family/classroom/regional rankings.

**Props:**
- `childId` (Number) - The child's ID

**Features:**
- Tabbed navigation between leaderboard types
- Child's current rank display
- Top rankings table
- Medal badges (🥇🥈🥉)
- Visual highlighting of child's rank
- Responsive design

**Usage:**
```vue
<template>
  <LeaderboardView :child-id="1" />
</template>

<script>
import LeaderboardView from '@/components/LeaderboardView.vue'

export default {
  components: { LeaderboardView }
}
</script>
```

## Setting Up Vue Components

### 1. Register Components Globally

In your main app file (e.g., `resources/js/app.js`):

```javascript
import { createApp } from 'vue'
import ParentDashboard from './components/ParentDashboard.vue'
import LeaderboardView from './components/LeaderboardView.vue'

const app = createApp(App)

app.component('ParentDashboard', ParentDashboard)
app.component('LeaderboardView', LeaderboardView)
```

### 2. Or Register Per Route

In your router config:

```javascript
import ParentDashboard from '@/components/ParentDashboard.vue'
import LeaderboardView from '@/components/LeaderboardView.vue'

export const routes = [
  {
    path: '/dashboard',
    component: ParentDashboard,
    props: route => ({ childId: parseInt(route.query.child_id) })
  },
  {
    path: '/leaderboard',
    component: LeaderboardView,
    props: route => ({ childId: parseInt(route.query.child_id) })
  },
]
```

## Building Components from Scratch

### Speech Recording Component

```vue
<template>
  <div class="speech-recorder">
    <button @click="startRecording" v-if="!recording">
      🎤 Start Recording
    </button>
    <button @click="stopRecording" v-if="recording">
      ⏹️ Stop Recording
    </button>
    
    <div v-if="scores" class="scores">
      <div class="score">Pronunciation: {{ scores.pronunciation_score }}%</div>
      <div class="score">Fluency: {{ scores.fluency_score }}%</div>
      <div class="score">Clarity: {{ scores.clarity_score }}%</div>
    </div>
  </div>
</template>

<script>
import { speechApi } from '@/api/beszedClient'

export default {
  props: { childId: Number },
  data() {
    return {
      recording: false,
      mediaRecorder: null,
      chunks: [],
      scores: null,
    }
  },
  methods: {
    startRecording() {
      navigator.mediaDevices.getUserMedia({ audio: true })
        .then(stream => {
          this.mediaRecorder = new MediaRecorder(stream)
          this.mediaRecorder.ondataavailable = e => this.chunks.push(e.data)
          this.mediaRecorder.start()
          this.recording = true
        })
    },
    async stopRecording() {
      this.mediaRecorder.stop()
      this.recording = false
      
      const blob = new Blob(this.chunks, { type: 'audio/wav' })
      const { data } = await speechApi.analyze(this.childId, blob)
      this.scores = data
      this.chunks = []
    }
  }
}
</script>
```

### Game Recommendations Component

```vue
<template>
  <div class="recommendations">
    <h2>Recommended Games</h2>
    <div class="games-grid">
      <div v-for="game in recommendations" :key="game.game" class="game-card">
        <div class="game-name">{{ game.game }}</div>
        <div class="game-score">Score: {{ game.score }}</div>
        <div class="game-reason">{{ game.reason }}</div>
        <button @click="playGame(game.game)" class="btn-play">
          Play →
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { adaptiveApi } from '@/api/beszedClient'

export default {
  props: { childId: Number },
  data() {
    return {
      recommendations: [],
    }
  },
  mounted() {
    this.loadRecommendations()
  },
  methods: {
    async loadRecommendations() {
      const { data } = await adaptiveApi.recommendations(this.childId)
      this.recommendations = data.recommendations
    },
    playGame(gameName) {
      this.$router.push(`/games/${gameName}?child_id=${this.childId}`)
    },
  }
}
</script>

<style scoped>
.games-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
}

.game-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 1rem;
  text-align: center;
}

.btn-play {
  width: 100%;
  padding: 0.75rem;
  background: #4f46e5;
  color: white;
  border: none;
  border-radius: 0.25rem;
  cursor: pointer;
  margin-top: 1rem;
}
</style>
```

## Styling

All components use utility-first CSS with these color tokens:

```css
:root {
  --primary: #4f46e5;
  --success: #10b981;
  --warning: #f59e0b;
  --danger: #ef4444;
  --info: #3b82f6;
  
  --text-primary: #1f2937;
  --text-secondary: #6b7280;
  --bg-light: #f9fafb;
  --border: #e5e7eb;
}
```

## Build & Deploy

### Development
```bash
npm run dev
```

### Production Build
```bash
npm run build
```

### Docker Integration

Update your Docker setup to include frontend build:

```dockerfile
FROM node:18 AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY resources resources
RUN npm run build

FROM php:8.3-fpm
# ... PHP setup ...
COPY --from=frontend /app/public/js /var/www/html/public/js
COPY --from=frontend /app/public/css /var/www/html/public/css
```

## Testing Components

### Unit Test Example (Jest + Vue Test Utils)

```javascript
import { mount } from '@vue/test-utils'
import ParentDashboard from '@/components/ParentDashboard.vue'
import { dashboardApi } from '@/api/beszedClient'

jest.mock('@/api/beszedClient')

describe('ParentDashboard', () => {
  it('loads dashboard data on mount', async () => {
    dashboardApi.parentDashboard.mockResolvedValue({
      data: {
        child: { name: 'Alex' },
        overview: { accuracy_rate: 75 },
        // ... other data
      }
    })

    const wrapper = mount(ParentDashboard, {
      props: { childId: 1 }
    })

    await wrapper.vm.$nextTick()
    expect(wrapper.vm.dashboard.overview.accuracy_rate).toBe(75)
  })

  it('downloads weekly report', async () => {
    dashboardApi.weeklyReport.mockResolvedValue({
      data: new Blob()
    })

    const wrapper = mount(ParentDashboard, {
      props: { childId: 1 }
    })

    await wrapper.find('.btn-primary').trigger('click')
    expect(dashboardApi.weeklyReport).toHaveBeenCalledWith(1)
  })
})
```

## Performance Optimization

### Code Splitting
```javascript
const ParentDashboard = () => import('@/components/ParentDashboard.vue')
const LeaderboardView = () => import('@/components/LeaderboardView.vue')
```

### Lazy Loading Routes
```javascript
{
  path: '/dashboard',
  component: () => import('@/components/ParentDashboard.vue'),
  props: route => ({ childId: parseInt(route.query.child_id) })
}
```

### Caching API Responses
```javascript
const dashboardCache = new Map()

export async function getCachedDashboard(childId) {
  const key = `dashboard-${childId}`
  if (dashboardCache.has(key)) {
    return dashboardCache.get(key)
  }
  
  const data = await dashboardApi.parentDashboard(childId)
  dashboardCache.set(key, data)
  
  // Invalidate cache after 5 minutes
  setTimeout(() => dashboardCache.delete(key), 5 * 60 * 1000)
  
  return data
}
```

## Troubleshooting

**API calls failing with CORS errors:**
- Ensure Laravel is running with CORS middleware enabled
- Check that API baseURL matches your backend host

**Components not rendering:**
- Verify component registration in app.js
- Check browser console for import errors
- Ensure component props are passed correctly

**Data not updating:**
- Check that API responses return expected data structure
- Verify child_id prop is set correctly
- Check network tab for failed requests

---

For more details on specific features, refer to PHASES_COMPLETE.md and the individual service documentation in the backend code.
