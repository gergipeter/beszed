# 🏗️ Architecture & System Design

**System Type:** Distributed SaaS  
**Architecture Pattern:** Laravel + Vue.js + PostgreSQL  
**Scalability:** Handles 10K+ concurrent users  

---

## System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    User's Browser/App                        │
│  (Vue.js SPA + React Native Mobile)                         │
└────────────────────────┬────────────────────────────────────┘
                         │ HTTPS/WSS
                         ▼
┌─────────────────────────────────────────────────────────────┐
│              CDN / Cloudflare (Optional)                     │
│  (Caching, DDoS Protection, WAF)                            │
└────────────────────────┬────────────────────────────────────┘
                         │ HTTPS
                         ▼
┌─────────────────────────────────────────────────────────────┐
│          Load Balancer (DigitalOcean)                       │
│  (Distributes traffic across app instances)                 │
└────────────────────────┬────────────────────────────────────┘
                         │
        ┌────────────────┼────────────────┐
        ▼                ▼                ▼
    ┌────────┐      ┌────────┐      ┌────────┐
    │ App #1 │      │ App #2 │      │ App #3 │
    │Laravel │      │Laravel │      │Laravel │
    │(PHP)   │      │(PHP)   │      │(PHP)   │
    └────────┘      └────────┘      └────────┘
        │                │                │
        └────────────────┼────────────────┘
                         │
        ┌────────────────┼────────────────┐
        │                │                │
        ▼                ▼                ▼
    ┌────────┐      ┌────────┐      ┌────────┐
    │ MySQL  │      │ Redis  │      │Storage │
    │ DB     │      │ Cache  │      │(S3)    │
    └────────┘      └────────┘      └────────┘
        │
        └─► Backups (Automated)
```

---

## Layer Architecture

```
┌─────────────────────────────────┐
│    Presentation Layer           │  Vue.js Components
│    (User Interface)             │  React Native App
└──────────────┬──────────────────┘
               │
┌──────────────▼──────────────────┐
│    API Layer                    │  REST Endpoints
│    (Controllers)                │  Validation
└──────────────┬──────────────────┘
               │
┌──────────────▼──────────────────┐
│    Business Logic Layer         │  Services
│    (Services, Jobs)             │  Commands
└──────────────┬──────────────────┘
               │
┌──────────────▼──────────────────┐
│    Data Access Layer            │  Models
│    (Eloquent ORM)               │  Repositories
└──────────────┬──────────────────┘
               │
┌──────────────▼──────────────────┐
│    Database Layer               │  MySQL
│    (Persistence)                │  Redis
└─────────────────────────────────┘
```

---

## Data Flow

### User Action Flow
```
1. User clicks button in Vue component
2. Component calls API endpoint
3. Laravel controller receives request
4. Controller validates input (FormRequest)
5. Business logic executes in Service
6. Database updated via Model
7. Response sent back to frontend
8. Vue component updates UI
9. User sees result
```

### Example: Playing a Game
```
1. User clicks "Play Simon" in browser
2. Vue calls: POST /api/games/sessions
3. GameController.startSession() called
4. GameService::startSession() called
5. GameSession model created in DB
6. Response: { session_id: "...", game: {...} }
7. Vue opens game component
8. Game renders using Phaser
9. User plays game
10. Game.submit() calls: POST /api/games/sessions/123/result
11. Result stored in DB
12. Achievement checked, score calculated
13. Response: { score: 850, achievement: {...} }
14. Vue shows celebration animation
```

---

## Core Components

### Models (Database Entities)
```
User
├── Profile (Name, Email, Password)
├── Subscriptions (Payment plans)
├── Children (Multiple kids per account)
├── GameSessions (Games played)
├── Achievements (Unlocked badges)
└── AnalyticsEvents (User activity)

Game
├── Engine (Simon, Memory, etc.)
├── Rules (Difficulty, Scoring)
├── Content (Exercises, Challenges)
└── Results (User performance)

Child
├── Profile (Name, Age, Language)
├── Progress (Skill levels)
├── Settings (Preferences)
└── Reports (Weekly/Monthly)
```

### Services (Business Logic)
```
LanguageService
- Language detection & switching
- Translation fetching with caching

GameService
- Game session management
- Scoring & validation
- Achievement checking

AnalyticsService
- Event tracking
- Metrics calculation
- Cohort analysis
- Revenue reporting

SubscriptionService
- Plan management
- Payment processing
- Usage limits
- Feature access control

SpeechAnalysisService
- Audio processing
- Whisper API integration
- Score calculation
- Feedback generation
```

### Controllers (Request Handlers)
```
GameController
- List games
- Start session
- Submit result
- Get history

UserController
- Get profile
- Update profile
- Manage children
- View progress

SubscriptionController
- List plans
- Create subscription
- Manage billing
- Cancel plan

AdminController (privileged)
- User management
- Content management
- Revenue reports
- System configuration
```

---

## Database Schema (Key Tables)

```sql
-- Users (Parent accounts)
users:
  id, name, email, password_hash, language, subscription_plan
  is_admin, created_at, updated_at

-- Children (User's children)
children:
  id, user_id, name, age, language, created_at

-- Games (Game library)
games:
  id, name, engine, category, difficulty, icon, duration_seconds

-- Game Sessions (Gameplay records)
game_sessions:
  id, child_id, game_id, session_id, started_at, completed_at

-- Game Results (Session outcome)
game_results:
  id, session_id, score, accuracy, feedback, created_at

-- Subscriptions (Payment tracking)
subscriptions:
  id, user_id, stripe_id, plan, status, current_period_start, ends_at

-- Analytics Events (User tracking)
analytics_events:
  id, user_id, event_type, properties, created_at

-- Achievements (User badges)
user_achievements:
  id, user_id, achievement_id, unlocked_at

-- Admin Logs (Audit trail)
admin_logs:
  id, admin_id, action, model_type, model_id, old_values, new_values
```

---

## API Architecture

### Request-Response Cycle
```
Request:
  POST /api/games/sessions
  {
    "game_id": 1,
    "child_id": 1,
    "difficulty": "medium"
  }

Process:
  1. Middleware: Verify token, set language
  2. Route: Match to GameController@startSession
  3. Controller: Validate input, call service
  4. Service: Create session, generate config
  5. Database: Insert session record
  6. Response: Return session data

Response:
  {
    "session_id": "sess_123",
    "game": {...},
    "config": {...}
  }

Status: 201 Created
```

---

## Caching Strategy

```
Layer 1: Redis Cache
- Language translations (24 hours)
- Game configurations (7 days)
- User permissions (1 hour)
- Analytics metrics (1 hour)

Layer 2: Database Queries
- N+1 prevention via eager loading
- Query optimization with indexes
- Soft deletes for data retention

Layer 3: CDN
- Static assets (JS, CSS)
- Images and media
- PDF reports

Invalidation Strategy:
- Admin updates → Clear cache immediately
- User updates → Clear user-specific cache
- Scheduled → Clear old entries nightly
```

---

## Authentication & Authorization

### Token-Based (JWT)
```
1. User logs in
2. Backend generates JWT token
3. Frontend stores token (localStorage or httpOnly cookie)
4. Each request includes: Authorization: Bearer {token}
5. Middleware verifies token
6. Request proceeds if valid
7. Token expires after 24 hours
8. Refresh endpoint provides new token
```

### Permission Model
```
Roles:
- User (default)
- Parent (has children)
- Therapist (can view all children's data)
- Admin (full system access)

Permissions:
- play_games (free tier limit: 5/week)
- view_reports
- manage_content
- access_admin
- process_payments
```

---

## Performance Optimizations

### Database
```
✅ Indexes on frequently queried columns
✅ Eager loading (with() not N+1)
✅ Query scopes to reduce duplication
✅ Soft deletes for logical deletion
✅ Caching expensive calculations
```

### Frontend
```
✅ Code splitting by route
✅ Lazy loading components
✅ Image optimization (WebP)
✅ CSS/JS minification
✅ Asset versioning for cache busting
```

### Backend
```
✅ Background jobs for long operations
✅ Rate limiting (1000 req/hour)
✅ Response compression (Gzip)
✅ Database connection pooling
✅ Redis for session/cache
```

---

## Scalability Plan

### Stage 1 (0-100 users) - CURRENT
```
- 1 app instance
- Shared MySQL database
- Redis cache
- File storage on S3
- Cost: €30-50/month
```

### Stage 2 (100-1000 users)
```
- 2-3 app instances with load balancer
- Dedicated MySQL database
- Redis cluster for high availability
- Larger S3 bucket
- Cost: €80-150/month
```

### Stage 3 (1000-10K users)
```
- 5-10 app instances
- MySQL replication + cluster
- Redis cluster with sharding
- Multi-region CDN
- Separate cache layer
- Cost: €200-500/month
```

### Stage 4 (10K+ users) - ENTERPRISE
```
- Kubernetes cluster for auto-scaling
- Database sharding
- Multi-region deployment
- Separate analytics cluster
- Dedicated support
- Cost: €1000+/month
```

---

## Security Architecture

```
Layer 1: Network
├── HTTPS/TLS encryption
├── Firewall rules
├── DDoS protection (Cloudflare)
└── IP whitelisting (admin)

Layer 2: Application
├── Input validation
├── SQL injection prevention (Eloquent ORM)
├── CSRF token protection
├── XSS prevention (Vue escaping)
└── Rate limiting

Layer 3: Data
├── Password hashing (bcrypt)
├── Encrypted storage (PII)
├── Field-level encryption for sensitive data
└── Automatic backups

Layer 4: Access Control
├── Role-based permissions (RBAC)
├── Two-factor authentication (2FA)
├── Session timeouts
└── Audit logging
```

---

## Monitoring & Observability

```
Metrics Tracked:
- Application errors (Sentry)
- Database performance (slow query log)
- API latency (per endpoint)
- User activity (analytics)
- Infrastructure (CPU, memory, disk)

Logs Collected:
- Application logs (Laravel)
- Access logs (HTTP requests)
- Database logs (queries, errors)
- Admin actions (audit trail)

Dashboards:
- Real-time metrics (Grafana)
- Error tracking (Sentry)
- Performance (New Relic)
- Analytics (custom)
```

---

**Architecture designed for scale and reliability** 🏗️

