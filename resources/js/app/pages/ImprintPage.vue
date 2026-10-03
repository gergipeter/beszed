<script setup>
import { useRouter } from 'vue-router'
import { BzButton } from '../../modules/beszed'
import '../../modules/beszed/styles/index.css'
import { appConfig } from '../config'
import LegalLinks from '../components/LegalLinks.vue'

/**
 * Imprint (public): who runs the service. Hungarian e-commerce rules expect the provider's name, seat, contact, registration
 * number and hosting provider to be easy to find. DRAFT: filled from config/privacy.php (PRIVACY_*); have a lawyer read it.
 */
const router = useRouter()
const privacy = appConfig.privacy ?? {}
const back = () => (window.history.length > 1 ? router.back() : router.push('/'))
</script>

<template>
  <main class="bz legal-page">
    <article class="doc">
      <h1>Impresszum</h1>
      <p class="draft" role="note">Vázlat: az adatokat a megjelenés előtt ki kell tölteni, a szöveget jogásszal át kell nézni.</p>

      <h2>A szolgáltató</h2>
      <dl>
        <dt>Név és székhely</dt>
        <dd>{{ privacy.controller }}</dd>
        <dt>Nyilvántartási szám</dt>
        <dd>{{ privacy.registration }}</dd>
        <dt>Adószám</dt>
        <dd>{{ privacy.taxId }}</dd>
        <dt>Elérhetőség</dt>
        <dd>{{ privacy.contact }}</dd>
      </dl>

      <h2>Tárhelyszolgáltató</h2>
      <p>{{ privacy.hosting }}</p>

      <h2>Panaszok, adatvédelem</h2>
      <p>
        Észrevételeidet, panaszaidat az elérhetőségen fogadjuk, és legkésőbb 30 napon belül válaszolunk. Az adatok kezeléséről az
        <RouterLink class="link" :to="{ name: 'privacy' }">Adatkezelési tájékoztató</RouterLink>, a használat és az előfizetés
        feltételeiről az
        <RouterLink class="link" :to="{ name: 'terms' }">ÁSZF</RouterLink>, a sütikről a
        <RouterLink class="link" :to="{ name: 'cookies' }">Sütitájékoztató</RouterLink> szól.
      </p>

      <h2>Nem helyettesít szakembert</h2>
      <p>
        Az alkalmazás nem orvosi eszköz, nem diagnózis és nem terápia, és nem helyettesíti a logopédust, a fejlesztőpedagógust
        vagy a gyermekorvost.
      </p>

      <LegalLinks class="foot" />
      <BzButton @click="back">Vissza</BzButton>
    </article>
  </main>
</template>

<style scoped>
.doc {
  padding: 22px 22px 26px;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  font-size: 17px;
  line-height: 1.5;
  box-shadow: var(--bz-shadow);
}
h1 {
  margin: 0 0 8px;
  font-size: 30px;
  line-height: 1.15;
}
h2 {
  margin: 22px 0 6px;
  font-size: 20px;
}
.draft {
  padding: 8px 12px;
  border-radius: var(--bz-radius-sm);
  background: color-mix(in srgb, #ffd23f 35%, transparent);
  font-size: 15px;
  font-weight: 700;
}
dl {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 6px 16px;
  margin: 0;
}
dt {
  font-weight: 800;
}
dd {
  margin: 0;
}
.link {
  font-weight: 700;
  text-decoration: underline;
}
.foot {
  margin: 22px 0 14px;
}
</style>
