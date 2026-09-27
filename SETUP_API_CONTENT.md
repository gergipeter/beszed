# Content API Setup Complete

Your game content is now fully managed in MySQL with both API and admin interfaces.

## What's New

### 1. **Public Content API** (`/api/content/*`)
- ✅ REST endpoints for CRUD operations (no auth required)
- ✅ Bulk import for multiple items at once
- ✅ Accessible from anywhere (localhost, Docker, external clients)

### 2. **Database Persistence**
- ✅ All 5,614 items stored in MySQL (`horizon-mysql`)
- ✅ Tracks item source (seed, api, admin)
- ✅ Soft delete for played items (preserves history)

### 3. **Python Upload Script**
- ✅ Easy bulk upload from JSON or CSV files
- ✅ Supports both local and remote API endpoints

## Quick Start

### Test the API

Get all items for a game:
```bash
curl http://localhost:8000/api/content/kirako
```

Create a single item:
```bash
curl -X POST http://localhost:8000/api/content/kirako \
  -H "Content-Type: application/json" \
  -d '{
    "level": 1,
    "payload": {
      "kind": "tale",
      "name": "My Story",
      "prop": "🎯",
      "emoji": "🎭",
      "scene": "stage"
    }
  }'
```

Bulk import from file:
```bash
python3 scripts/upload-content.py --game kirako --file scripts/sample-content.json
```

### With Docker

```bash
docker exec beszed-app-1 curl http://localhost:8000/api/content/papagaj
```

## Files Created

### Code
- `app/Http/Controllers/Api/Content/GameContentController.php` — REST API controller
- `routes/api.php` — Updated with new routes

### Documentation
- `API_CONTENT.md` — Full API reference with examples
- `scripts/upload-content.py` — Bulk upload utility (JSON/CSV support)
- `scripts/sample-content.json` — Example data for testing

## API Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/content/{game}` | List all items for a game |
| POST | `/api/content/{game}` | Create a single item |
| POST | `/api/content/{game}/bulk` | Bulk import multiple items |
| PUT | `/api/content/{game}/{id}` | Update an item |
| DELETE | `/api/content/{game}/{id}` | Delete an item |

## Admin Interface

The `/tartalom` (content editor) is still available for admins:
- **URL:** `http://localhost:8000/tartalom`
- **Access:** via ADMIN_EMAILS in compose.yaml
- **Features:** UI-based editing, export/import CSVs, audit trail

## Database Schema

Items are stored in `beszed_content_items`:
```
id: int (primary key)
game: string (game name)
level: tinyint (1-3)
payload: json (game-specific fields)
active: boolean (soft delete flag)
status: enum ('draft', 'live')
source: enum ('seed', 'api', 'admin')
created_at, updated_at, edited_at: timestamp
```

## Example: Adding 100 Items via Python

1. Create a JSON file with 100 items (or use CSV):
```json
[
  {
    "level": 1,
    "payload": {
      "kind": "tale",
      "name": "Story Name",
      "prop": "emoji",
      "emoji": "emoji",
      "scene": "location"
    }
  },
  // ... 99 more items
]
```

2. Upload:
```bash
python3 scripts/upload-content.py --game kirako --file items.json
```

Result: All 100 items in the database instantly, available to the app.

## Game Schemas

Each game has specific required fields in the payload. See `config/beszed_content.php` for details.

### Common games:
- **kirako** (Picture puzzle): kind, name, prop, emoji, scene
- **papagaj** (Parrot): word, sentence
- **erzelmek** (Emotions): emotion, emoji
- **hallgasd** (Listen): sentence, word
- **mondd** (Say it): word, image_id
- **szamol** (Count): count, image_id

Full list in `API_CONTENT.md`.

## Testing

Verify the API is working:
```bash
# From host
curl -s http://localhost:8000/api/content/kirako | jq '.count'

# From Docker
docker exec beszed-app-1 curl http://localhost:8000/api/content/kirako | head -20
```

Check MySQL directly:
```bash
docker exec horizon-mysql mysql -u root -proot beszed \
  -e "SELECT COUNT(*) FROM beszed_content_items WHERE source='api';"
```

## Security Notes

- **No authentication required** for the public API (add if needed via middleware)
- **Source tracking** distinguishes API items from seeded/admin items
- **Soft deletes** preserve played item history (important for reports)
- **Validation** checks payload against game schema before storing

## Next Steps

1. **Use the API** to populate more content programmatically
2. **Integrate with your content management** system (export/import pipelines)
3. **Add auth** if you want to restrict API access (see `routes/api.php`)
4. **Monitor usage** via `source` column (track which items come from where)

## Troubleshooting

### Items not showing in the app?
- Check `status` is 'live' (not 'draft')
- Check `active` is true
- Verify the game name is correct

### 404 on API endpoint?
- Clear route cache: `docker exec beszed-app-1 php artisan route:cache`
- Check game name is valid

### CSV upload errors?
- Verify headers match schema
- Use dot notation for nested fields: `payload.name`, `payload.emoji`
- Check encoding is UTF-8
