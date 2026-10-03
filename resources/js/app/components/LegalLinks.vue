<script setup>
import { texts } from '../texts'

/** The legal pages, as one row of links: for the foot of the sign-in page, the family page and the legal pages themselves. */
</script>

<template>
  <nav class="legal" :aria-label="texts.legalNav">
    <RouterLink :to="{ name: 'privacy' }">{{ texts.legalPrivacy }}</RouterLink>
    <RouterLink :to="{ name: 'terms' }">{{ texts.legalTerms }}</RouterLink>
    <RouterLink :to="{ name: 'cookies' }">{{ texts.legalCookies }}</RouterLink>
    <RouterLink :to="{ name: 'imprint' }">{{ texts.legalImprint }}</RouterLink>
  </nav>
</template>

<style scoped>
.legal {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 6px 16px;
  font-size: 14px;
}
.legal a {
  font-weight: 700;
  text-decoration: underline;
  color: var(--bz-muted);
}
</style>
