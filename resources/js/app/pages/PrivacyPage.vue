<script setup>
import { useRouter } from 'vue-router'
import { BzButton } from '../../modules/beszed'
import '../../modules/beszed/styles/index.css'
import { appConfig } from '../config'

/**
 * Privacy notice (public). Operator details come from config/privacy.php
 * (PRIVACY_CONTROLLER / PRIVACY_CONTACT). Have it reviewed before real families use the app.
 */
const router = useRouter()
const privacy = appConfig.privacy ?? {}
const missing = 'a szolgáltató még nem adta meg'
const back = () => (window.history.length > 1 ? router.back() : router.push('/'))
</script>

<template>
  <main class="bz privacy">
    <article class="doc">
      <h1>Adatkezelési tájékoztató</h1>
      <p class="lead">
        Röviden: csak azt tároljuk, ami a játékokhoz és a haladás megmutatásához kell, csak te látod, és bármikor
        letöltheted vagy törölheted.
      </p>

      <h2>Ki kezeli az adatokat?</h2>
      <p>
        Adatkezelő: <b>{{ privacy.controller || missing }}</b><br />
        Kapcsolat: <b>{{ privacy.contact || missing }}</b>
      </p>

      <h2>Milyen adatokat tárolunk?</h2>
      <ul>
        <li><b>Rólad (szülő):</b> név, e-mail-cím, és Google-belépésnél a profilképed címe.</li>
        <li><b>A gyerekről:</b> keresztnév, és ha megadod, a születési dátum.</li>
        <li><b>Játék:</b> válaszok (jó / hány próbálkozásból), szintek, végigjátszott játékok, matricák, Csillám ruhái.</li>
        <li><b>Hangfelvételek:</b> csak ha te veszel fel mondatokat Csillám helyett.</li>
      </ul>

      <h2>Mire használjuk?</h2>
      <p>
        A játékok működtetésére, a nehézség gyerekhez igazítására, a jutalmakra, és hogy a Haladás oldalon lásd, hogyan
        fejlődik. Nincs reklám, nincs követés, az adatokat nem adjuk el és nem használjuk profilozásra.
      </p>

      <h2>Ki fér hozzá?</h2>
      <p>
        Csak te, bejelentkezve. Külső szolgáltatók: a <b>Google</b>, ha Google-fiókkal lépsz be (a belépéshez); és ha a
        szolgáltató bekapcsolta, a <b>Microsoft Azure</b> beszédszolgáltatása (Csillám gépi hangja a mondatok szövegéből,
        illetve a „Mondd utánam” kiejtésértékelésnél a felvett hang).
      </p>

      <h2>Meddig tároljuk?</h2>
      <p>
        Amíg a fiókod vagy a gyerek profilja létezik. Egy gyerek törlésével az összes eredménye azonnal törlődik; a fiók
        törlésével minden adat és hangfelvétel.
      </p>

      <h2>Sütik</h2>
      <p>Csak a bejelentkezéshez szükséges munkamenet-sütit használjuk. Nincs analitika és nincs harmadik féltől származó süti.</p>

      <h2>A jogaid</h2>
      <ul>
        <li><b>Letöltés:</b> „Ki játszik?” oldal → Adataim letöltése (JSON fájl).</li>
        <li><b>Törlés:</b> ugyanott → Fiók törlése; vagy egy gyerek törlése a Szerkesztés gombbal.</li>
        <li><b>Hozzájárulás visszavonása:</b> a fiók törlésével.</li>
        <li>
          <b>Panasz:</b> a Nemzeti Adatvédelmi és Információszabadság Hatóságnál (NAIH, naih.hu).
        </li>
      </ul>

      <p class="version">Tájékoztató változata: {{ privacy.version }}</p>
      <BzButton @click="back">Vissza</BzButton>
    </article>
  </main>
</template>

<style scoped>
.doc {
  padding: 22px 22px 26px;
  border-radius: 28px;
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
.lead {
  font-size: 19px;
  color: var(--bz-muted);
}
ul {
  margin: 0;
  padding-left: 22px;
}
li + li {
  margin-top: 4px;
}
.version {
  margin-top: 22px;
  font-size: 14px;
  color: var(--bz-muted);
}
</style>
