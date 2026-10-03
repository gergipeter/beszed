<script setup>
import { useRouter } from 'vue-router'
import { BzButton } from '../../modules/beszed'
import '../../modules/beszed/styles/index.css'
import { appConfig } from '../config'
import LegalLinks from '../components/LegalLinks.vue'

/**
 * Cookie notice (public). The app only sets what it needs to work, so there is no consent banner; if analytics or any
 * third-party service is ever added, this page and the consent flow have to change first. DRAFT: have a lawyer read it.
 */
const router = useRouter()
const privacy = appConfig.privacy ?? {}
const back = () => (window.history.length > 1 ? router.back() : router.push('/'))
</script>

<template>
  <main class="bz legal-page">
    <article class="doc">
      <h1>Sütitájékoztató</h1>
      <p class="draft" role="note">Vázlat: a szöveget a megjelenés előtt jogásszal át kell nézni.</p>
      <p class="lead">
        Röviden: csak olyan sütit és helyi tárolást használunk, amely nélkül az alkalmazás nem működne. Nincs analitika,
        hirdetés és követés, ezért nem kérünk külön sütihozzájárulást.
      </p>

      <h2>Amit használunk</h2>
      <table>
        <thead>
          <tr>
            <th>Mi</th>
            <th>Mire kell</th>
            <th>Meddig él</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Munkamenet-süti</td>
            <td>Hogy bejelentkezve maradj, amíg használod az alkalmazást.</td>
            <td>Legfeljebb 2 óra tétlenségig.</td>
          </tr>
          <tr>
            <td>Biztonsági süti (XSRF-TOKEN)</td>
            <td>Megvéd attól, hogy valaki a nevedben küldjön kéréseket.</td>
            <td>A munkamenet idejéig.</td>
          </tr>
          <tr>
            <td>Bejelentkezve maradó süti</td>
            <td>Belépéskor beállítjuk, hogy ne kelljen minden alkalommal újra belépned a saját készülékeden.</td>
            <td>Hosszan érvényes (akár évekig), kijelentkezéskor törlődik. Közös készüléken jelentkezz ki.</td>
          </tr>
          <tr>
            <td>Helyi tárolás a készüléken</td>
            <td>A beállításaid (hang, utoljára választott téma, a gyerek kiválasztása) és a játékok gyorsítótára, hogy gyorsan és hálózat nélkül is induljanak.</td>
            <td>Amíg te nem törlöd a böngésző adatait.</td>
          </tr>
        </tbody>
      </table>
      <p>
        Ezekhez a jogszabályok szerint nincs szükség hozzájárulásra, mert az alkalmazás nem működne nélkülük. A helyi tárolásban nincs
        személyes adat, amit mi olvasnánk vagy továbbítanánk.
      </p>

      <h2>Amit nem használunk</h2>
      <ul>
        <li>Nincs hirdetési vagy követő süti, és nincs harmadik féltől származó süti.</li>
        <li>Nincs analitika, amely a használatodat külső szolgáltatónak jelentené.</li>
        <li>
          A Google-belépés a Google saját oldalán történik; ott a Google sütiszabályai érvényesek. Ha csak e-mail-címmel lépsz be,
          a Google nem kap semmit.
        </li>
      </ul>

      <h2>Hogyan törölheted?</h2>
      <p>
        A böngésződ beállításaiban törölheted a sütiket és a helyi adatokat; ilyenkor ki leszel jelentkeztetve, és a beállításaid
        visszaállnak. A fiókod adatait ez nem érinti, azokat az alkalmazásban („Ki játszik?” oldal) tudod letölteni vagy törölni.
      </p>

      <h2>Ha ez megváltozik</h2>
      <p>
        Ha valaha olyan szolgáltatást építünk be, amely nem feltétlenül szükséges sütit használ, előbb erről itt tájékoztatunk, és a
        használatához a hozzájárulásodat kérjük. Kérdés esetén: {{ privacy.contact }}.
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
.lead {
  font-size: 19px;
  color: var(--bz-muted);
}
.draft {
  padding: 8px 12px;
  border-radius: var(--bz-radius-sm);
  background: color-mix(in srgb, #ffd23f 35%, transparent);
  font-size: 15px;
  font-weight: 700;
}
table {
  width: 100%;
  border-collapse: collapse;
  font-size: 15px;
}
th,
td {
  padding: 8px 10px;
  border-bottom: 1px solid color-mix(in srgb, var(--bz-muted) 30%, transparent);
  text-align: left;
  vertical-align: top;
}
ul {
  margin: 0;
  padding-left: 22px;
}
li + li {
  margin-top: 6px;
}
.foot {
  margin: 22px 0 14px;
}
</style>
