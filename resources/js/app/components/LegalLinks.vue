<script setup>
/** The legal pages, as one row of links: for the foot of the sign-in page, the family page and the legal pages themselves. */
</script>

<template>
  <nav class="legal" aria-label="Jogi tájékoztatók">
    <RouterLink :to="{ name: 'privacy' }">Adatvédelem</RouterLink>
    <RouterLink :to="{ name: 'terms' }">ÁSZF</RouterLink>
    <RouterLink :to="{ name: 'cookies' }">Sütik</RouterLink>
    <RouterLink :to="{ name: 'imprint' }">Impresszum</RouterLink>
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
