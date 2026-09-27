# Game Content API

Populate and manage game content via REST API. No authentication required.

## Endpoints

### List Content
```
GET /api/content/{game}
```
Returns all active, live items for a game.

**Example:**
```bash
curl http://localhost:8000/api/content/kirako
```

**Response:**
```json
{
  "game": "kirako",
  "count": 756,
  "items": [
    {
      "id": 2830,
      "game": "kirako",
      "level": 1,
      "payload": {
        "kind": "tale",
        "name": "Hófehérke",
        "prop": "🍎",
        "emoji": "👸",
        "scene": "forest"
      },
      "active": true,
      "status": "live",
      "source": "seed"
    }
  ]
}
```

### Create Item
```
POST /api/content/{game}
Content-Type: application/json
```

**Request Body:**
```json
{
  "level": 1,
  "payload": {
    "kind": "tale",
    "name": "My Story",
    "prop": "🎉",
    "emoji": "🎪",
    "scene": "circus"
  }
}
```

**Example:**
```bash
curl -X POST http://localhost:8000/api/content/kirako \
  -H "Content-Type: application/json" \
  -d '{
    "level": 1,
    "payload": {
      "kind": "tale",
      "name": "Test Item",
      "prop": "🎯",
      "emoji": "🎭",
      "scene": "stage"
    }
  }'
```

**Response:** `201 Created`
```json
{
  "item": {
    "id": 5612,
    "game": "kirako",
    "level": 1,
    "payload": {
      "kind": "tale",
      "name": "Test Item",
      "prop": "🎯",
      "emoji": "🎭",
      "scene": "stage"
    },
    "active": true,
    "status": "live",
    "source": "api"
  }
}
```

### Bulk Import
```
POST /api/content/{game}/bulk
Content-Type: application/json
```

Import multiple items in one request.

**Request Body:**
```json
{
  "items": [
    {
      "level": 1,
      "payload": {
        "kind": "tale",
        "name": "Item 1",
        "prop": "🎯",
        "emoji": "🎭",
        "scene": "stage"
      }
    },
    {
      "level": 2,
      "payload": {
        "kind": "tale",
        "name": "Item 2",
        "prop": "🎪",
        "emoji": "🤹",
        "scene": "circus"
      }
    }
  ]
}
```

**Example:**
```bash
curl -X POST http://localhost:8000/api/content/kirako/bulk \
  -H "Content-Type: application/json" \
  -d '{
    "items": [
      {
        "level": 1,
        "payload": {
          "kind": "tale",
          "name": "Item 1",
          "prop": "🎯",
          "emoji": "🎭",
          "scene": "stage"
        }
      }
    ]
  }'
```

**Response:**
```json
{
  "imported": 1,
  "errors": []
}
```

### Update Item
```
PUT /api/content/{game}/{id}
Content-Type: application/json
```

**Request Body:**
```json
{
  "level": 2,
  "payload": {
    "kind": "tale",
    "name": "Updated Item",
    "prop": "🎉",
    "emoji": "🎪",
    "scene": "circus"
  }
}
```

**Example:**
```bash
curl -X PUT http://localhost:8000/api/content/kirako/5612 \
  -H "Content-Type: application/json" \
  -d '{
    "level": 2,
    "payload": {
      "kind": "tale",
      "name": "Updated Item",
      "prop": "🎉",
      "emoji": "🎪",
      "scene": "circus"
    }
  }'
```

### Delete Item
```
DELETE /api/content/{game}/{id}
```

Deletes API-sourced items. Seeded items (from JSON files) cannot be deleted directly—deactivate them via the admin UI instead.

**Example:**
```bash
curl -X DELETE http://localhost:8000/api/content/kirako/5612
```

**Response:**
```json
{
  "deleted": true,
  "id": 5612
}
```

## Payload Schema

Each game has a specific schema defined in `config/beszed_content.php`. Common fields:

### Kirako (Picture puzzle)
```json
{
  "kind": "tale|picture",
  "name": "string (required)",
  "prop": "emoji (required)",
  "emoji": "emoji (required)",
  "scene": "string (required)"
}
```

### Erzelmek (Emotions)
```json
{
  "emotion": "string (required)",
  "emoji": "emoji (required)"
}
```

### Papagaj (Parrot)
```json
{
  "word": "string (required)",
  "sentence": "string (required)"
}
```

See `config/beszed_content.php` for all game schemas.

## Available Games

- arnyek (Shadows)
- ceruza (Pencil)
- erzelmek (Emotions)
- hallgasd (Listen)
- hol (Where)
- ikerhangok (Twin sounds)
- kezdo (Beginner)
- kirako (Picture puzzle)
- korus (Chorus)
- kulonbseg (Difference)
- melyik (Which)
- mitunt (What does it do)
- mondd (Say it)
- nagysag (Size)
- okoska (Smart)
- papagaj (Parrot)
- parkereso (Park finder)
- rimelo (Rhyme finder)
- rimparok (Rhyme pairs)
- szamol (Count)
- szotag (Syllable)
- tortenet (Story)
- utasitas (Instruction)
- valogato (Chooser)
- zs (ZS sound)

## Error Handling

### 404 Not Found
Invalid game name:
```json
{
  "message": "Not found"
}
```

### 422 Unprocessable Content
Validation error in bulk import:
```json
{
  "imported": 1,
  "errors": [
    {
      "index": 1,
      "errors": {
        "payload.name": ["This field is required"]
      }
    }
  ]
}
```

## Source Tracking

Each item tracks its source:
- `seed`: Loaded from JSON files in `database/seeders/data/beszed/`
- `api`: Created via this API
- `admin`: Created via the admin UI (`/tartalom`)

Seeded items can be edited but cannot be deleted directly. API-sourced items can be freely deleted.

## Database

Data is stored in `beszed_content_items` table in MySQL:
- `id`: Unique identifier
- `game`: Game name
- `level`: Difficulty level (1-3)
- `payload`: JSON object with game-specific fields
- `active`: Boolean (soft delete flag)
- `status`: 'draft' or 'live'
- `source`: 'seed', 'api', or 'admin'
- `created_at`, `updated_at`, `edited_at`
