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
const voiceStaysHere = privacy.stt === 'whisper'
const azureListens = privacy.stt === 'azure'
const azureSpeaks = privacy.tts === 'azure'
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
        Csak te, bejelentkezve. Külső szolgáltatók: a <b>Google</b>, ha Google-fiókkal lépsz be (a belépéshez)<template
          v-if="azureSpeaks || azureListens"
          >; és a <b>Microsoft Azure</b> beszédszolgáltatása<template v-if="azureSpeaks"> (Csillám gépi hangja a mondatok
            szövegéből; gyerekhang nélkül)</template
          ><template v-if="azureListens"> (a „Mondd utánam” kiejtésértékelésnél a felvett hang)</template></template
        >. Az alapbeállítású gépi hang (Piper) a saját szerverünkön fut, a szöveg nem megy külső szolgáltatóhoz.
      </p>
      <p v-if="voiceStaysHere">
        <b>A gyerek hangja nálunk marad:</b> a „Mondd utánam” gyakorlatnál a felvételt a saját szerverünkön alakítjuk
        szöveggé (Whisper), külső szolgáltatóhoz nem kerül, és a felvételt nem mentjük el: a kiértékelés után törlődik.
      </p>
      <p>
        <b>Megosztás a logopédussal:</b> ha te készítesz egy megosztási linket, akinek odaadod, bejelentkezés nélkül
        láthatja a gyerek keresztnevét, korcsoportját és a játékok eredményeit (hangfelvételt, születési dátumot,
        e-mail-címet nem). A link legfeljebb 90 napig él, és bármikor visszavonhatod.
      </p>

      <p>
        <b>Kamera:</b> a Szájtorna „Tükör” gombja a telefon előlapi kamerájával mutatja a gyereknek önmagát. A kép csak a
        képernyőn jelenik meg: nem rögzítjük, nem mentjük el és nem küldjük el sehová.
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

      <h2>Köszönet</h2>
      <p class="credits">
        Piktogramok: a piktografikus jelek szerzője Sergio Palao, forrása az
        <a href="https://arasaac.org" rel="noopener" target="_blank">ARASAAC</a> (https://arasaac.org), tulajdonosa Aragónia
        kormánya (Spanyolország); licenc:
        <a href="https://creativecommons.org/licenses/by-nc-sa/4.0/deed.hu" rel="noopener" target="_blank">CC BY-NC-SA 4.0</a>.
      </p>
      <p class="credits">
        Szimbólumok: Mulberry Symbols – © Steve Lee,
        <a href="https://mulberrysymbols.org" rel="noopener" target="_blank">mulberrysymbols.org</a>, licenc:
        <a href="https://creativecommons.org/licenses/by-sa/4.0/deed.hu" rel="noopener" target="_blank">CC BY-SA 4.0</a>.
        A jeleket változtatás nélkül jelenítjük meg.
      </p>
      <p class="credits">
        Képek: <a href="https://github.com/jdecked/twemoji" rel="noopener" target="_blank">Twemoji</a> – © Twitter, Inc.
        és közreműködők,
        <a href="https://creativecommons.org/licenses/by/4.0/" rel="noopener" target="_blank">CC-BY 4.0</a>.
        Betűtípus: Baloo 2 (SIL Open Font License). Gépi hang: Piper (MIT), magyar hangok CC0 hangfelvételekből.
      </p>

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
.credits {
  font-size: 15px;
  color: var(--bz-muted);
}
.credits a {
  text-decoration: underline;
}
.version {
  margin-top: 22px;
  font-size: 14px;
  color: var(--bz-muted);
}
</style>
