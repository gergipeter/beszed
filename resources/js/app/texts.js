/** Texts of the app shell (sign-in, child picker). The games' texts live in modules/beszed/i18n. */
export const texts = {
  tagline: 'Játékos beszéd- és iskolaelőkészítő gyakorlás 4–7 éveseknek.',
  google: 'Bejelentkezés Google-fiókkal',
  demo: 'Demó belépés',
  googleSetup: 'Google-belépéshez állítsd be a GOOGLE_CLIENT_ID és GOOGLE_CLIENT_SECRET értékét.',
  noLogin: 'A bejelentkezés most nem érhető el.',
  parentsOnly: 'Szülőként jelentkezz be. A gyerekek eredményei a te fiókodhoz tartoznak, és csak te látod őket.',
  errors: {
    cancelled: 'A Google-belépés megszakadt. Próbáld újra!',
    failed: 'Nem sikerült a Google-belépés. Próbáld újra!',
    email_taken: 'Ezzel az e-mail-címmel már van fiók, de a Google nem igazolta. Jelentkezz be a régi módon.',
    server: 'A szerver most nem érhető el. Próbáld újra kicsit később!',
    demo: 'Nem sikerült a demó belépés.',
  },
  whoPlays: 'Ki játszik ma?',
  addChild: 'Új gyerek',
  childName: 'Hogy hívják a gyereket?',
  childNamePlaceholder: 'pl. Zoé',
  birthDate: 'Születési dátum (nem kötelező)',
  birthDateHint: 'Ebből tudjuk, milyen nehéz feladatokkal kezdjünk.',
  birthDateOf: '{name} születési dátuma',
  contentEditor: 'Tartalomszerkesztő',
  save: 'Mentés',
  cancel: 'Mégse',
  edit: 'Szerkesztés',
  done: 'Kész',
  remove: '{name} törlése',
  removeConfirm: 'Biztosan törlöd {name} összes adatát (eredmények, matricák)? Ez nem vonható vissza.',
  logout: 'Kijelentkezés',
  saveFailed: 'Nem sikerült menteni.',
  firstChild: 'Add hozzá az első gyereket, és kezdődhet a játék!',

  privacyLink: 'Adatvédelem',
  exportData: 'Adataim letöltése',
  milestoneEmails: 'E-mail, ha a gyerek elér egy mérföldkövet',
  milestoneEmailsSaveFailed: 'Nem sikerült elmenteni ezt a beállítást.',
  deleteAccount: 'Fiók törlése',
  deletePrompt:
    'A fiókod, az összes gyerek, minden eredmény, matrica és hangfelvétel véglegesen törlődik. Ha biztos vagy benne, írd be: TÖRLÉS',
  deleteWord: 'TÖRLÉS',
  deleteFailed: 'Nem sikerült törölni a fiókot.',

  consent: {
    title: 'Mielőtt kezdünk',
    intro:
      'Az alkalmazás elmenti a gyerekek játékeredményeit, hogy lásd a haladásukat, és a játékok hozzájuk igazodjanak. Ehhez a te hozzájárulásodra van szükség.',
    stored: 'Mit tárolunk',
    storedItems: [
      'a te neved és e-mail-címed (a bejelentkezéshez);',
      'a gyerek keresztnevét (és ha megadod, a születési dátumát);',
      'a játékok eredményeit, szintjeit és matricáit;',
      'a hangfelvételeidet, ha készítesz.',
    ],
    rights: 'Az adataidat bármikor letöltheted vagy törölheted a „Ki játszik?” oldalon.',
    checkbox: 'Szülőként elolvastam az adatkezelési tájékoztatót, és hozzájárulok, hogy a gyerekeim eredményeit az alkalmazás tárolja.',
    accept: 'Elfogadom',
    failed: 'Nem sikerült menteni. Próbáld újra!',
  },
}

export const fill = (text, params) => text.replace(/\{(\w+)\}/g, (m, k) => (k in params ? params[k] : m))
