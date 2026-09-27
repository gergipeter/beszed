# 👨‍💻 Developer Onboarding Guide

**Welcome to Beszéd!**  
**Time to productivity:** 2 hours  
**Prerequisites:** Node.js, PHP 8.1+, Docker (optional)  

---

## 1. Setup (30 min)

### 1.1 Clone Repository
```bash
git clone https://github.com/your-org/beszed.git
cd beszed
```

### 1.2 Install Dependencies
```bash
# Backend
composer install

# Frontend
npm install

# If Docker:
docker-compose up -d
```

### 1.3 Environment Setup
```bash
# Copy example env
cp .env.example .env

# Generate app key
php artisan key:generate

# Configure database
DB_HOST=127.0.0.1
DB_DATABASE=beszed
DB_USERNAME=root
DB_PASSWORD=

# Run migrations
php artisan migrate
```

### 1.4 Start Development
```bash
# Terminal 1: Laravel
php artisan serve

# Terminal 2: Frontend
npm run dev

# Terminal 3: Queue (optional)
php artisan queue:work

# Visit http://localhost:8000
```

---

## 2. Project Structure

```
beszed/
├── app/
│   ├── Http/
│   │   ├── Controllers/      (Request handlers)
│   │   ├── Middleware/       (Request processing)
│   │   └── Requests/         (Form validation)
│   ├── Models/               (Database entities)
│   ├── Services/             (Business logic)
│   └── Jobs/                 (Background tasks)
├── resources/
│   ├── js/                   (Vue components)
│   │   ├── components/       (Reusable UI)
│   │   ├── pages/           (Full pages)
│   │   └── modules/         (Feature modules)
│   ├── lang/                (Translations - hu, en)
│   └── views/               (Blade templates)
├── routes/
│   ├── api.php              (API routes)
│   ├── web.php              (Web routes)
│   └── auth.php             (Auth routes)
├── database/
│   ├── migrations/          (Schema changes)
│   ├── seeders/             (Test data)
│   └── factories/           (Test factories)
├── tests/
│   ├── Feature/             (Integration tests)
│   └── Unit/                (Unit tests)
├── docker-compose.yml       (Local dev environment)
└── Dockerfile               (Production image)
```

---

## 3. Common Workflows

### 3.1 Create a Feature
```bash
# 1. Create migration
php artisan make:migration create_feature_table

# 2. Create model
php artisan make:model Feature

# 3. Create controller
php artisan make:controller FeatureController --resource

# 4. Create service
touch app/Services/FeatureService.php

# 5. Add routes
# In routes/api.php:
Route::apiResource('features', FeatureController::class);

# 6. Create Vue component
touch resources/js/components/Feature.vue

# 7. Add tests
php artisan make:test FeatureTest --feature
```

### 3.2 Add Language Translation
```bash
# 1. Add key to resources/lang/en/app.json:
"feature": {
  "title": "Feature",
  "description": "A new feature"
}

# 2. Add Hungarian:
# resources/lang/hu/app.json:
"feature": {
  "title": "Funkció",
  "description": "Egy új funkció"
}

# 3. Use in code:
{{ $t('feature.title') }}
```

### 3.3 Create an API Endpoint
```bash
# 1. Create controller method:
public function store(Request $request) {
    $data = $request->validate([...]);
    $feature = Feature::create($data);
    return response()->json($feature, 201);
}

# 2. Add route:
Route::post('features', [FeatureController::class, 'store']);

# 3. Add test:
test('can create feature', function () {
    $response = $this->post('/api/features', [...]);
    $this->assertDatabaseHas('features', [...]);
});

# 4. Test it:
php artisan test --filter=create_feature
```

### 3.4 Deploy Changes
```bash
# 1. Commit
git add .
git commit -m "Add feature description"

# 2. Push
git push origin feature-branch

# 3. Create PR
gh pr create

# 4. Wait for checks (tests, linting)

# 5. Merge when green

# 6. Auto-deploys to staging/production
```

---

## 4. Testing

### 4.1 Run Tests
```bash
# All tests
php artisan test

# Specific test
php artisan test --filter=UserTest

# Feature tests only
php artisan test tests/Feature

# With coverage
php artisan test --coverage
```

### 4.2 Write a Test
```php
// tests/Feature/GameTest.php
test('user can play game', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();
    
    $response = $this
        ->actingAs($user)
        ->post('/api/games/play', ['game_id' => $game->id]);
    
    $response->assertSuccessful();
    $this->assertDatabaseHas('game_sessions', [
        'user_id' => $user->id,
        'game_id' => $game->id,
    ]);
});
```

### 4.3 Debug Tests
```bash
# Run with verbose output
php artisan test -v

# Stop on first failure
php artisan test --stop-on-failure

# Run single test class
php artisan test tests/Feature/GameTest.php
```

---

## 5. Database

### 5.1 Migrations
```bash
# Create migration
php artisan make:migration add_column_to_users

# Run migrations
php artisan migrate

# Rollback last batch
php artisan migrate:rollback

# Rollback all
php artisan migrate:reset

# Refresh (reset + migrate + seed)
php artisan migrate:refresh --seed
```

### 5.2 Query Database
```bash
# Tinker shell (interactive PHP)
php artisan tinker

# In tinker:
> User::where('name', 'John')->get()
> User::find(1)->update(['name' => 'Jane'])
> Game::count()
```

### 5.3 Database Inspection
```bash
# Check current schema
php artisan schema:dump

# Compare migrations
php artisan schema:inspect --table=users
```

---

## 6. Frontend Development

### 6.1 Vue.js Basics
```vue
<template>
  <div class="container">
    <h1>{{ title }}</h1>
    <button @click="handleClick">{{ buttonText }}</button>
    <p v-if="isLoading">Loading...</p>
    <ul v-for="item in items">
      <li :key="item.id">{{ item.name }}</li>
    </ul>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const title = ref('My Page')
const buttonText = ref('Click Me')
const isLoading = ref(false)
const items = ref([])

const handleClick = async () => {
  isLoading.value = true
  const response = await fetch('/api/items')
  items.value = await response.json()
  isLoading.value = false
}
</script>

<style scoped>
.container {
  padding: 20px;
}
</style>
```

### 6.2 Using Translations
```vue
<template>
  <h1>{{ $t('dashboard.title') }}</h1>
  <p>{{ $t('common.welcome') }}</p>
</template>
```

### 6.3 API Calls
```js
// services/api.js
import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('token')}`
  }
})

export const getGames = () => api.get('/games')
export const playGame = (gameId) => api.post(`/games/${gameId}/play`)
```

---

## 7. Debugging

### 7.1 Backend
```bash
# Enable debug mode in .env
APP_DEBUG=true

# Log to file
Log::info('User login', ['user_id' => $userId]);

# View logs
tail -f storage/logs/laravel.log

# Use debugbar (if enabled)
# Shows queries, logs, timings in browser
```

### 7.2 Frontend
```bash
# Browser DevTools
F12 → Console → type to inspect

# Vue DevTools
# Chrome/Firefox extension - inspect Vue state

# Network tab
# See API calls, responses, headers
```

### 7.3 Database Queries
```bash
# Enable query logging
DB::enableQueryLog();
// ... do stuff ...
dd(DB::getQueryLog());

# Or use laravel/debugbar
composer require barryvdh/laravel-debugbar --dev
```

---

## 8. Git Workflow

### 8.1 Branch Strategy
```bash
# Feature branch
git checkout -b feature/user-auth

# Make changes
git add .
git commit -m "Add user authentication"

# Push
git push origin feature/user-auth

# Create PR
gh pr create

# After approval & merge, delete branch
git push origin --delete feature/user-auth
```

### 8.2 Commit Messages
```
Format: type(scope): description

Types:
  feat: New feature
  fix: Bug fix
  refactor: Code reorganization
  test: Testing additions
  docs: Documentation
  perf: Performance improvement

Examples:
  feat(auth): add two-factor authentication
  fix(games): correct scoring algorithm
  refactor(api): simplify error handling
  test(language): add translation tests
```

### 8.3 Keep Updated
```bash
# Sync with main
git fetch origin
git merge origin/master

# Or rebase (cleaner history)
git rebase origin/master
```

---

## 9. Performance Tips

### 9.1 Optimize Queries
```php
// BAD: N+1 queries
$users = User::all();
foreach ($users as $user) {
    echo $user->games->count();
}

// GOOD: Single query with eager loading
$users = User::with('games')->get();
foreach ($users as $user) {
    echo $user->games->count();
}
```

### 9.2 Cache Data
```php
// Cache 24 hours
$games = Cache::remember('all_games', 24 * 60, function () {
    return Game::all();
});

// Clear cache when needed
Cache::forget('all_games');
```

### 9.3 Async Operations
```php
// Queue long-running jobs
SendWeeklyReportJob::dispatch($userId);

// User sees instant response, job runs in background
```

---

## 10. Useful Commands

```bash
# Show routes
php artisan route:list

# Show models
php artisan model:show User

# Clear cache
php artisan cache:clear

# Rebuild config
php artisan config:cache

# Test coverage report
php artisan test --coverage --min=80

# Code quality
vendor/bin/psalm        # Type checking
vendor/bin/phpstan      # Static analysis
vendor/bin/pint         # Code formatting

# Generate API docs
php artisan scribe:generate
```

---

## 11. Resources

**Documentation:**
- Laravel Docs: https://laravel.com/docs
- Vue.js Docs: https://vuejs.org
- Blade Templating: https://laravel.com/docs/blade

**Internal Docs:**
- API_DOCUMENTATION.md
- ARCHITECTURE.md
- DATABASE_SCHEMA.md

**Help:**
- Slack: #dev-help
- Issues: github.com/your-org/beszed/issues
- Docs: internal wiki

---

## 12. First Task

```
1. Clone repo
2. Setup .env
3. Run migrations
4. npm run dev
5. Visit http://localhost:8000
6. Read ARCHITECTURE.md
7. Pick an issue labeled "good first issue"
8. Create feature branch
9. Code & test
10. Push & create PR
11. Get review
12. Celebrate! 🎉
```

---

**Welcome aboard! 🚀**

Questions? Ask in #dev-help or contact your onboarding buddy!

