<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#d6f0ff">
    <title>{{ $appConfig['name'] }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Csillám">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <script id="app-config" type="application/json">@json($appConfig)</script>
    @vite('resources/js/app.js')
</head>
<body>
    <div id="app"></div>

    <!-- DEV MODE BUTTON - Always visible -->
    <button id="dev-toggle-btn" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999; padding: 8px 12px; background: #222; border: 2px solid #0f0; color: #0f0; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 12px; font-family: monospace;">
        ⚙️ DEV MODE
    </button>

    <div id="dev-panel" style="position: fixed; bottom: 70px; right: 20px; width: 300px; background: #1a1a1a; border: 2px solid #0f0; border-radius: 8px; padding: 16px; color: #0f0; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto; z-index: 9999; box-shadow: 0 0 20px rgba(0, 255, 0, 0.2); display: none;">
        <h3 style="margin: 0 0 12px; border-bottom: 1px solid #0f0; padding-bottom: 8px;">🎮 GOD MODE</h3>

        <div style="margin-bottom: 16px;">
            <p style="margin: 0 0 8px; font-size: 10px; color: #888;">Memory Game Difficulty:</p>
            <select id="difficulty-select" style="width: 100%; padding: 8px; background: #0a0a0a; border: 1px solid #0f0; color: #0f0; border-radius: 4px; font-family: monospace; font-size: 11px;">
                <option value="auto">🔄 Auto (by level)</option>
                <option value="easy">🟢 Easy (Könnyű)</option>
                <option value="medium">🟡 Medium (Közepesen nehéz)</option>
                <option value="hard">🔴 Hard (Nehéz)</option>
            </select>
        </div>

        <div style="margin-bottom: 16px; padding: 8px; background: #0a0a0a; border: 1px solid #333; border-radius: 4px;">
            <p style="margin: 0 0 4px; font-size: 9px; color: #888;">💾 Saved:</p>
            <p id="saved-value" style="margin: 0; font-size: 11px; color: #0f0;">auto</p>
        </div>

        <p style="margin: 0; font-size: 9px; color: #888;">ℹ️ Refresh page to apply</p>
        <p style="margin: 4px 0 0; font-size: 9px; color: #888;">🎮 Used in Párkereső game</p>
    </div>

    <script>
        // Dev Mode - Simple vanilla JS
        const devBtn = document.getElementById('dev-toggle-btn');
        const devPanel = document.getElementById('dev-panel');
        const diffSelect = document.getElementById('difficulty-select');
        const savedValue = document.getElementById('saved-value');

        // Load saved difficulty
        const saved = localStorage.getItem('dev-difficulty') || 'auto';
        diffSelect.value = saved;
        savedValue.textContent = saved;

        // Toggle panel
        devBtn.addEventListener('click', () => {
            devPanel.style.display = devPanel.style.display === 'none' ? 'block' : 'none';
        });

        // Save difficulty
        diffSelect.addEventListener('change', (e) => {
            localStorage.setItem('dev-difficulty', e.target.value);
            savedValue.textContent = e.target.value;
            console.log('✅ Dev difficulty set to:', e.target.value);
        });

        // Log on page load
        console.log('🎮 DEV MODE available - look for ⚙️ button in bottom-right');
    </script>
</body>
</html>
