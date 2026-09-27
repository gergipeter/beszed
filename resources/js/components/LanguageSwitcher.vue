<template>
  <div class="language-switcher">
    <!-- Dropdown Button -->
    <div class="dropdown">
      <button
        @click="isOpen = !isOpen"
        class="btn-language"
        :title="`Current: ${currentLanguageName}`"
      >
        <span class="flag">{{ currentFlag }}</span>
        <span class="name">{{ currentLanguageCode.toUpperCase() }}</span>
        <span class="icon">▼</span>
      </button>

      <!-- Dropdown Menu -->
      <div v-if="isOpen" class="dropdown-menu">
        <div
          v-for="(lang, code) in supportedLanguages"
          :key="code"
          @click="switchLanguage(code)"
          :class="['language-option', { active: code === currentLanguageCode }]"
        >
          <span class="flag">{{ lang.flag }}</span>
          <span class="name">{{ lang.native_name }}</span>
          <span v-if="code === currentLanguageCode" class="checkmark">✓</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'LanguageSwitcher',
  data() {
    return {
      isOpen: false,
      currentLanguageCode: 'hu',
      supportedLanguages: {
        hu: { name: 'Hungarian', native_name: 'Magyar', flag: '🇭🇺' },
        en: { name: 'English', native_name: 'English', flag: '🇬🇧' },
      },
    }
  },
  computed: {
    currentLanguageName() {
      return this.supportedLanguages[this.currentLanguageCode]?.name || 'Unknown'
    },
    currentFlag() {
      return this.supportedLanguages[this.currentLanguageCode]?.flag || '🌐'
    },
  },
  mounted() {
    this.loadCurrentLanguage()
    this.loadSupportedLanguages()

    // Close dropdown when clicking outside
    document.addEventListener('click', this.closeDropdown)
  },
  beforeUnmount() {
    document.removeEventListener('click', this.closeDropdown)
  },
  methods: {
    async loadCurrentLanguage() {
      try {
        const response = await fetch('/api/language/current')
        const data = await response.json()
        this.currentLanguageCode = data.current
      } catch (error) {
        console.error('Failed to load current language:', error)
      }
    },
    async loadSupportedLanguages() {
      try {
        const response = await fetch('/api/language')
        const data = await response.json()
        this.supportedLanguages = data.languages.reduce((acc, lang) => {
          acc[lang.code] = {
            name: lang.name,
            native_name: lang.native_name,
            flag: lang.flag,
          }
          return acc
        }, {})
      } catch (error) {
        console.error('Failed to load languages:', error)
      }
    },
    async switchLanguage(languageCode) {
      try {
        const response = await fetch('/api/language/switch', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
          },
          body: JSON.stringify({ lang: languageCode }),
        })

        if (response.ok) {
          this.currentLanguageCode = languageCode
          this.isOpen = false

          // Emit event for parent component to reload translations
          this.$emit('language-changed', languageCode)

          // Reload page to apply new language globally
          window.location.reload()
        }
      } catch (error) {
        console.error('Failed to switch language:', error)
      }
    },
    closeDropdown(event) {
      if (!this.$el.contains(event.target)) {
        this.isOpen = false
      }
    },
  },
}
</script>

<style scoped>
.language-switcher {
  position: relative;
  display: inline-block;
}

.dropdown {
  position: relative;
  display: inline-block;
}

.btn-language {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: #f3f4f6;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  cursor: pointer;
  font-weight: 500;
  color: #374151;
  transition: all 0.2s;
}

.btn-language:hover {
  background: #e5e7eb;
  border-color: #9ca3af;
}

.btn-language:active {
  background: #d1d5db;
}

.flag {
  font-size: 1.25rem;
  line-height: 1;
}

.name {
  min-width: 30px;
  text-align: center;
  font-size: 0.875rem;
}

.icon {
  font-size: 0.75rem;
  color: #6b7280;
  transition: transform 0.2s;
}

.dropdown.open .icon {
  transform: rotate(180deg);
}

.dropdown-menu {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  margin-top: 0.5rem;
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  z-index: 1000;
  min-width: 150px;
}

.language-option {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  cursor: pointer;
  transition: background 0.2s;
  border-bottom: 1px solid #f3f4f6;
}

.language-option:last-child {
  border-bottom: none;
}

.language-option:hover {
  background: #f9fafb;
}

.language-option.active {
  background: #eff6ff;
  font-weight: 600;
}

.language-option .flag {
  font-size: 1.5rem;
  line-height: 1;
}

.language-option .name {
  flex: 1;
  font-size: 0.9375rem;
}

.checkmark {
  color: #10b981;
  font-weight: bold;
  font-size: 1.25rem;
}

/* Responsive */
@media (max-width: 640px) {
  .btn-language {
    padding: 0.5rem 0.75rem;
  }

  .name {
    display: none;
  }

  .dropdown-menu {
    right: auto;
    left: -50px;
    min-width: 200px;
  }
}
</style>
