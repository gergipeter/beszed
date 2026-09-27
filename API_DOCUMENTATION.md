# 🔌 Beszéd API Documentation

**API Version:** v1  
**Base URL:** `https://api.beszed.hu` or `http://localhost:8000/api`  
**Authentication:** Bearer Token (JWT)  
**Rate Limit:** 1000 requests/hour  

---

## Authentication

### Get Token (Login)
```bash
POST /api/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password123"
}

Response:
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com"
  }
}
```

### Refresh Token
```bash
POST /api/auth/refresh
Authorization: Bearer {token}

Response:
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

### Logout
```bash
POST /api/auth/logout
Authorization: Bearer {token}

Response:
{
  "message": "Successfully logged out"
}
```

---

## Users

### Get Current User
```bash
GET /api/users/me
Authorization: Bearer {token}

Response:
{
  "id": 1,
  "name": "John Doe",
  "email": "user@example.com",
  "language": "hu",
  "subscription_plan": "premium",
  "created_at": "2024-09-27T10:00:00Z"
}
```

### Update User Profile
```bash
PUT /api/users/me
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Jane Doe",
  "language": "en"
}

Response:
{
  "id": 1,
  "name": "Jane Doe",
  "email": "user@example.com",
  "language": "en",
  "updated_at": "2024-09-27T11:00:00Z"
}
```

### Get User Children
```bash
GET /api/users/children
Authorization: Bearer {token}

Response:
{
  "data": [
    {
      "id": 1,
      "name": "Emma",
      "age": 7,
      "parent_id": 1
    }
  ]
}
```

---

## Language System

### Get All Languages
```bash
GET /api/language
Authorization: Bearer {token}

Response:
{
  "current": "hu",
  "languages": [
    {
      "code": "hu",
      "name": "Hungarian",
      "native_name": "Magyar",
      "flag": "🇭🇺",
      "active": true
    },
    {
      "code": "en",
      "name": "English",
      "native_name": "English",
      "flag": "🇬🇧",
      "active": false
    }
  ]
}
```

### Get Current Language
```bash
GET /api/language/current
Authorization: Bearer {token}

Response:
{
  "current": "hu",
  "current_name": "Hungarian",
  "supported": [...]
}
```

### Switch Language
```bash
POST /api/language/switch
Authorization: Bearer {token}
Content-Type: application/json

{
  "lang": "en"
}

Response:
{
  "language": "en",
  "message": "Language switched successfully"
}
```

### Get Translations
```bash
GET /api/language/en/translations
Authorization: Bearer {token}

Response:
{
  "app_name": "Beszéd",
  "common": {
    "welcome": "Welcome",
    "hello": "Hello",
    "save": "Save"
  },
  "dashboard": {
    "title": "Dashboard",
    "overview": "Overview"
  }
}
```

---

## Games

### Get All Games
```bash
GET /api/games?category=speech&difficulty=easy&limit=10
Authorization: Bearer {token}

Response:
{
  "data": [
    {
      "id": 1,
      "name": "Simon Says",
      "engine": "simon",
      "category": "listening",
      "difficulty": "easy",
      "duration_seconds": 120,
      "icon": "👂"
    }
  ],
  "pagination": {
    "total": 45,
    "per_page": 10,
    "current_page": 1
  }
}
```

### Start Game Session
```bash
POST /api/games/sessions
Authorization: Bearer {token}
Content-Type: application/json

{
  "game_id": 1,
  "child_id": 1,
  "difficulty": "medium"
}

Response:
{
  "session_id": "sess_123abc",
  "game": {
    "id": 1,
    "name": "Simon Says",
    "engine": "simon"
  },
  "started_at": "2024-09-27T12:00:00Z"
}
```

### Submit Game Result
```bash
POST /api/games/sessions/{session_id}/result
Authorization: Bearer {token}
Content-Type: application/json

{
  "score": 850,
  "correct_answers": 17,
  "total_questions": 20,
  "time_taken": 95,
  "feedback": "Great job!"
}

Response:
{
  "session_id": "sess_123abc",
  "score": 850,
  "achievement_unlocked": {
    "id": 5,
    "name": "Perfect Score",
    "icon": "🌟"
  },
  "experience_gained": 100
}
```

---

## Speech Analysis

### Analyze Speech
```bash
POST /api/speech/analyze
Authorization: Bearer {token}
Content-Type: multipart/form-data

audio_file: <binary audio file>
language: "hu"
exercise_id: 1

Response:
{
  "analysis_id": "ana_123abc",
  "score": 78,
  "accuracy": 0.92,
  "clarity": 0.85,
  "speed": 0.71,
  "feedback": {
    "strengths": ["Good clarity", "Consistent pace"],
    "improvements": ["Work on pronunciation"]
  },
  "phonemes": [
    {
      "phoneme": "a",
      "correct": true,
      "confidence": 0.95
    }
  ]
}
```

### Get Speech History
```bash
GET /api/speech/history?child_id=1&limit=20
Authorization: Bearer {token}

Response:
{
  "data": [
    {
      "id": 1,
      "analysis_id": "ana_123abc",
      "score": 78,
      "accuracy": 0.92,
      "exercise_name": "Vowel Practice",
      "analyzed_at": "2024-09-27T12:00:00Z"
    }
  ]
}
```

---

## Progress & Reports

### Get Child Progress
```bash
GET /api/progress/child/{child_id}?start_date=2024-09-01&end_date=2024-09-30
Authorization: Bearer {token}

Response:
{
  "child": {
    "id": 1,
    "name": "Emma",
    "age": 7
  },
  "period": {
    "start": "2024-09-01",
    "end": "2024-09-30"
  },
  "statistics": {
    "games_played": 42,
    "total_score": 35640,
    "average_score": 848,
    "speech_score": 82,
    "streak_days": 7
  },
  "skills": {
    "listening": { "score": 85, "trend": "up" },
    "pronunciation": { "score": 78, "trend": "up" },
    "fluency": { "score": 81, "trend": "stable" }
  }
}
```

### Generate Weekly Report
```bash
POST /api/reports/weekly
Authorization: Bearer {token}
Content-Type: application/json

{
  "child_id": 1
}

Response:
{
  "report_id": "rep_123abc",
  "pdf_url": "https://storage.beszed.hu/reports/rep_123abc.pdf",
  "email_sent": true,
  "created_at": "2024-09-27T12:00:00Z"
}
```

---

## Achievements & Rewards

### Get Child Achievements
```bash
GET /api/achievements/child/{child_id}
Authorization: Bearer {token}

Response:
{
  "unlocked": [
    {
      "id": 1,
      "name": "First Steps",
      "description": "Complete your first game",
      "icon": "🎮",
      "rarity": "common",
      "unlocked_at": "2024-09-01T10:00:00Z"
    }
  ],
  "locked": [
    {
      "id": 5,
      "name": "Perfect Score",
      "description": "Get 100% on any game",
      "icon": "🌟",
      "rarity": "legendary",
      "progress": 0.6
    }
  ]
}
```

### Get Stickers/Rewards
```bash
GET /api/rewards/stickers?child_id=1
Authorization: Bearer {token}

Response:
{
  "total_stickers": 45,
  "categories": [
    {
      "category": "animals",
      "name": "Animals",
      "stickers": [
        {
          "id": 1,
          "name": "Dog",
          "icon": "🐕",
          "collected": true
        }
      ]
    }
  ]
}
```

---

## Subscriptions (Admin)

### Get Current Subscription
```bash
GET /api/subscriptions/current
Authorization: Bearer {token}

Response:
{
  "plan": "premium",
  "status": "active",
  "current_period_start": "2024-09-01",
  "current_period_end": "2024-10-01",
  "cancel_at_period_end": false,
  "next_invoice": {
    "amount": 999,
    "currency": "eur",
    "due_date": "2024-10-01"
  }
}
```

### Create Subscription
```bash
POST /api/subscriptions
Authorization: Bearer {token}
Content-Type: application/json

{
  "plan": "premium",
  "payment_method": "pm_123abc"
}

Response:
{
  "subscription_id": "sub_123abc",
  "plan": "premium",
  "status": "active",
  "trial_ends_at": null,
  "next_billing_date": "2024-10-01"
}
```

---

## Error Responses

### 400 Bad Request
```json
{
  "message": "Invalid input",
  "errors": {
    "email": ["Email is required"],
    "password": ["Password must be at least 8 characters"]
  }
}
```

### 401 Unauthorized
```json
{
  "message": "Unauthorized",
  "error": "Invalid or expired token"
}
```

### 403 Forbidden
```json
{
  "message": "Forbidden",
  "error": "Insufficient permissions"
}
```

### 404 Not Found
```json
{
  "message": "Resource not found",
  "error": "User with ID 999 not found"
}
```

### 429 Too Many Requests
```json
{
  "message": "Too many requests",
  "retry_after": 60
}
```

### 500 Server Error
```json
{
  "message": "Internal server error",
  "error_id": "err_123abc"
}
```

---

## Postman Collection

Import this to Postman: [Link to collection.json]

```bash
# Or with curl:
curl -X GET https://api.beszed.hu/api/games \
  -H "Authorization: Bearer {token}"
```

---

## Rate Limiting

- **Limit:** 1000 requests/hour
- **Headers:** 
  - `X-RateLimit-Limit: 1000`
  - `X-RateLimit-Remaining: 999`
  - `X-RateLimit-Reset: 1640995200`

---

## Webhooks

### Subscription Updated
```json
{
  "event": "subscription.updated",
  "data": {
    "subscription_id": "sub_123abc",
    "status": "active",
    "plan": "premium"
  }
}
```

### Achievement Unlocked
```json
{
  "event": "achievement.unlocked",
  "data": {
    "user_id": 1,
    "achievement_id": 5,
    "timestamp": "2024-09-27T12:00:00Z"
  }
}
```

---

## SDK Support

- **JavaScript:** `npm install beszed-sdk`
- **Python:** `pip install beszed-sdk`
- **Go:** `go get github.com/beszed/sdk-go`

---

**API Status:** ✅ [status.beszed.hu](https://status.beszed.hu)  
**Support:** api-support@beszed.hu  
**Last Updated:** 2026-09-27

