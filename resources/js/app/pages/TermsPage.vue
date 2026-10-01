<script setup>
import { useRouter } from 'vue-router'
import { BzButton } from '../../modules/beszed'
import '../../modules/beszed/styles/index.css'
import { appConfig } from '../config'

/**
 * Terms of use (public). The App Store asks for them next to the privacy notice, and for any subscription they must
 * say what is bought, for how long, and how to cancel (guideline 3.1.2). DRAFT: the operator's details come from
 * config/privacy.php; have a lawyer read it before real families use the app or anyone is charged.
 */
const router = useRouter()
const privacy = appConfig.privacy ?? {}
const back = () => (window.history.length > 1 ? router.back() : router.push('/'))
</script>

<template>
  <main class="bz terms">
    <article class="doc">
      <h1>Felhasználási feltételek</h1>
      <p class="draft" role="note">
        Vázlat: a szolgáltató adatait és a hatálybalépés napját a megjelenés előtt ki kell tölteni, a szöveget jogásszal át kell nézni.
      </p>
      <p class="lead">
        Az alkalmazás szülőknek szól, hogy játékosan gyakorolhassanak beszéd- és iskolaelőkészítő feladatokat a 4–7 éves
        gyerekükkel. Az alkalmazás használatával elfogadod ezeket a feltételeket.
      </p>

      <h2>1. Ki a szolgáltató?</h2>
      <p>
        {{ privacy.controller }}<br />
        Kapcsolat: {{ privacy.contact }}
      </p>

      <h2>2. Mi ez a szolgáltatás, és mi nem?</h2>
      <p>
        Játékok, amelyekkel a gyerek a hangokat, szavakat, mondatokat és az alapvető készségeket gyakorolja, és egy haladási
        összefoglaló a szülőnek. <b>Nem orvosi eszköz, nem diagnózis, nem terápia</b>, és nem helyettesíti a logopédust,
        fejlesztőpedagógust vagy gyermekorvost. Ha aggódsz a gyerek beszédfejlődése miatt, fordulj szakemberhez.
      </p>

      <h2>3. A fiók</h2>
      <ul>
        <li>Fiókot csak 18 éven felüli szülő vagy törvényes képviselő hozhat létre. A gyerek nem hoz létre fiókot.</li>
        <li>A jelszavadat te őrzöd; a fiókodban történt dolgokért te felelsz. Ha gyanítod, hogy valaki más belépett, változtass jelszót.</li>
        <li>A fiókodat és minden adatodat bármikor törölheted az alkalmazásban („Ki játszik?” oldal → Fiók törlése).</li>
      </ul>

      <h2>4. Ingyenes és Prémium csomag</h2>
      <p>
        Minden játékot ingyen kipróbálhatsz a 3. szintig, időkorlát nélkül. A <b>Prémium csomag</b> megnyitja a magasabb
        szinteket, az Utazást (a készségek lépésről lépésre haladó útját) és a teljes haladási jelentéseket. Mi kerül az ingyenes
        és mi a Prémium csomagba, idővel változhat; azt, amit már megkaptál, nem veszünk el tőled egy előfizetés bevezetése miatt.
      </p>

      <h2>5. Előfizetés és fizetés</h2>
      <ul>
        <li>A Prémium csomag <b>automatikusan megújuló előfizetés</b>, havi vagy éves időszakkal. Az ár és az időszak a vásárlás előtt látható.</li>
        <li>
          A fizetést az Apple App Store vagy a Google Play terheli az Apple-azonosítódon, illetve a Google-fiókodon, a vásárlás
          megerősítésekor. Az előfizetés a jelenlegi időszak vége előtt legalább 24 órával történő lemondásig megújul.
        </li>
        <li>
          <b>Lemondás:</b> iPhone-on a Beállítások → a neved → Előfizetések menüben; Androidon a Google Play → Előfizetések menüben.
          Lemondás után a már kifizetett időszak végéig használhatod a Prémium csomagot.
        </li>
        <li>Ha van ingyenes próbaidő, az alkalmazás a vásárlás előtt kiírja a hosszát. Ha a próbaidő lejárta előtt nem mondod le, az előfizetés díjköteles lesz.</li>
        <li>Visszatérítést az áruház (Apple, Google) szabályai szerint, nála kérhetsz. Az előfizetés az azonos fiókkal belépett összes eszközödön működik, és a „Vásárlások visszaállítása” gombbal újra bekapcsolható.</li>
        <li>Az árat csak az új időszakra változtathatjuk, és előtte az áruház értesít, ha jóváhagyásod kell.</li>
      </ul>

      <h2>6. Helyes használat</h2>
      <p>
        Az alkalmazást a saját családod számára használhatod. Nem szabad feltörni, visszafejteni, tömegesen lementeni a tartalmát,
        másnak továbbadni, vagy törvénybe ütköző célra használni. A terapeutának készített megosztási linket csak olyannak add
        oda, akiben megbízol; bármikor visszavonhatod.
      </p>

      <h2>7. Tartalom és szerzői jog</h2>
      <p>
        Az alkalmazás szövegei, játékai és programkódja a szolgáltató tulajdona. Mások anyagait a saját licencük szerint használjuk;
        a forrásokat az
        <RouterLink class="link" :to="{ name: 'privacy' }">Adatkezelési tájékoztató</RouterLink>
        „Köszönet” része sorolja fel. A hangfelvételeid és a gyerek eredményei a tieid; csak azért tároljuk őket, hogy az
        alkalmazás működjön.
      </p>

      <h2>8. Felelősség</h2>
      <p>
        Gondosan készítjük az alkalmazást, de nem garantálhatjuk, hogy hibátlan és megszakítás nélküli. A törvény által megengedett
        mértékben nem felelünk közvetett károkért. A fogyasztót a jogszabályok szerint megillető jogaidat ez nem érinti.
      </p>

      <h2>9. Változások, megszűnés</h2>
      <p>
        A feltételeket módosíthatjuk; lényeges változásnál az alkalmazásban értesítünk, és ha kell, újra a hozzájárulásodat kérjük.
        Ha nem értesz egyet, töröld a fiókodat. A szolgáltatást megszüntethetjük; ilyenkor a fizetett időszakból hátralévő
        részt az áruház szabályai szerint kezeljük.
      </p>

      <h2>10. Irányadó jog</h2>
      <p>A feltételekre a magyar jog az irányadó, a fogyasztói jogaidat védő kötelező szabályok sérelme nélkül.</p>

      <p class="version">Hatály: [dátum – kitöltendő] · Tájékoztató változata: {{ privacy.version }}</p>
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
.draft {
  padding: 8px 12px;
  border-radius: 12px;
  background: color-mix(in srgb, #ffd23f 35%, transparent);
  font-size: 15px;
  font-weight: 700;
}
ul {
  margin: 0;
  padding-left: 22px;
}
li + li {
  margin-top: 6px;
}
.link {
  font-weight: 700;
  text-decoration: underline;
}
.version {
  margin-top: 22px;
  font-size: 14px;
  color: var(--bz-muted);
}
</style>
