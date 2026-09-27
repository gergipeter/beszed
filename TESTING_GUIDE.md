# 🧪 Complete Testing Guide

**Test Coverage Goal:** 80%+ coverage  
**Test Types:** Unit, Integration, E2E  
**CI/CD:** Automated on every push  

---

## Testing Pyramid

```
       ╱╲
      ╱  ╲      E2E Tests (5%)
     ╱────╲     Playwright/Cypress
    ╱      ╲    User workflows
   ╱────────╲
  ╱          ╲   Integration Tests (30%)
 ╱            ╲  Feature tests with DB
╱──────────────╲ API endpoints
╱                ╲ Service interactions
────────────────
Unit Tests (65%)
Isolated functions
Single responsibility
```

---

## Unit Tests

### Example: LanguageService
```php
// tests/Unit/LanguageServiceTest.php
test('can detect browser language', function () {
    $service = new LanguageService();
    $lang = $service->getBrowserLanguage();
    
    $this->assertIn($lang, ['hu', 'en']);
});

test('language cache works', function () {
    Cache::spy();
    
    LanguageService::get('hu');
    LanguageService::get('hu');
    
    Cache::shouldHaveReceived('remember')->once();
});
```

### Run Unit Tests
```bash
php artisan test tests/Unit --filter=LanguageService
```

---

## Feature/Integration Tests

### Example: GameController
```php
// tests/Feature/GameControllerTest.php
test('user can play game', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();
    
    $response = $this
        ->actingAs($user)
        ->post('/api/games/sessions', ['game_id' => $game->id]);
    
    $response->assertSuccessful();
    $this->assertDatabaseHas('game_sessions', [
        'user_id' => $user->id
    ]);
});

test('free users limited to 5 games per week', function () {
    $user = User::factory()->create(['subscription_plan' => 'free']);
    
    // Play 5 games
    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($user)
            ->post('/api/games/sessions', [...])
            ->assertSuccessful();
    }
    
    // 6th game should fail
    $this->actingAs($user)
        ->post('/api/games/sessions', [...])
        ->assertForbidden();
});
```

### Run Feature Tests
```bash
php artisan test tests/Feature --filter=GameController
```

---

## API Tests

### Example: Language API
```php
// tests/Feature/LanguageApiTest.php
test('can get all languages', function () {
    $response = $this
        ->actingAs(User::factory()->create())
        ->getJson('/api/language');
    
    $response
        ->assertSuccessful()
        ->assertJsonStructure([
            'current',
            'languages' => ['*' => ['code', 'name', 'flag']]
        ]);
});

test('can switch language', function () {
    $user = User::factory()->create();
    
    $response = $this
        ->actingAs($user)
        ->postJson('/api/language/switch', ['lang' => 'en']);
    
    $response->assertSuccessful();
    $this->assertEquals('en', $user->fresh()->language);
});
```

---

## E2E Tests (Playwright)

### Example: Login Flow
```javascript
// tests/e2e/auth.spec.js
import { test, expect } from '@playwright/test';

test('user can login and play game', async ({ page }) => {
    // Navigate to login
    await page.goto('http://localhost:8000/login');
    
    // Fill form
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password123');
    
    // Submit
    await page.click('button:has-text("Login")');
    
    // Wait for redirect
    await expect(page).toHaveURL('/dashboard');
    
    // Click play game
    await page.click('button:has-text("Play")');
    
    // Game should load
    await expect(page.locator('.game-engine')).toBeVisible();
});
```

### Run E2E Tests
```bash
npx playwright test
npx playwright test --ui  # Interactive mode
```

---

## Running All Tests

### Local Development
```bash
# All tests with coverage
php artisan test --coverage

# Stop on first failure
php artisan test --stop-on-failure

# With verbose output
php artisan test -v
```

### CI/CD Pipeline
```bash
# Run as part of deployment
vendor/bin/phpunit
npx playwright test
npm run lint
vendor/bin/psalm  # Type checking
```

---

## Test Factories

### Create Test Data
```php
// Create single user
$user = User::factory()->create(['name' => 'John']);

// Create 10 users
$users = User::factory()->count(10)->create();

// Create with relations
$user = User::factory()
    ->has(Child::factory()->count(3))
    ->create();
```

---

## Database Seeding for Tests

```php
// tests/TestCase.php
protected function setUp(): void {
    parent::setUp();
    
    // Refresh database before each test
    $this->refreshDatabase();
    
    // Seed test data
    $this->seed(TestSeeder::class);
}
```

---

## Mocking External Services

### Mock Whisper API
```php
test('speech analysis works with mock', function () {
    Http::fake([
        'api.openai.com/v1/audio/transcriptions' => Http::response([
            'text' => 'hello world',
            'confidence' => 0.95
        ])
    ]);
    
    $result = SpeechAnalysisService::analyze($audioFile);
    
    $this->assertEquals('hello world', $result['text']);
});
```

---

## Coverage Report

```bash
# Generate coverage report
php artisan test --coverage

# View HTML report
open build/coverage/index.html

# Minimum coverage threshold
php artisan test --coverage --min=80
```

---

## Test Checklist

- [ ] Unit tests for Services
- [ ] Feature tests for Controllers  
- [ ] API tests for Endpoints
- [ ] E2E tests for Critical Flows
- [ ] Database tests for Migrations
- [ ] Authentication tests
- [ ] Authorization tests
- [ ] Error handling tests
- [ ] Performance tests (load)
- [ ] Security tests (injection, XSS)

---

**Test Coverage: 80%+ | CI/CD Automated** ✅

