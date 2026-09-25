/**
 * Hungarian UI texts. Game content (prompts, options, feedback sentences) comes
 * from the server: config/beszed.php and the RoundFactories.
 */
export default {
  dateLocale: 'hu-HU',
  numbers: ['nulla', 'egy', 'két', 'három', 'négy', 'öt', 'hat', 'hét', 'nyolc', 'kilenc', 'tíz'],

  common: {
    back: 'Vissza',
    retry: 'Újra',
    reset: 'Újra',
    done: 'Kész',
    next: 'Tovább',
    loading: 'Betöltés…',
    picture: 'kép',
  },

  guide: {
    avatarLabel: '{name}, az egyszarvú',
  },

  hub: {
    hello: 'Szia!',
    helloNamed: 'Szia, {child}!',
    intro: 'Én vagyok {guide}. Melyikkel játszunk?',
    greeting: 'Szia! Én vagyok {guide}. Úgy örülök, hogy itt vagy! Mit játsszunk ma?',
    greetingNamed: 'Szia {child}! Én vagyok {guide}. Úgy örülök, hogy itt vagy! Mit játsszunk ma?',
    greetLabel: '{guide} köszön',
    forParents: 'Szülőknek',
    loadFailed: 'Nem sikerült betölteni a játékokat.',
  },

  game: {
    loadFailed: 'Nem sikerült betölteni a játékot.',
    replayLabel: 'Kérdés újra',
    repeatLabel: '{guide}, mondd el újra',
    progressLabel: 'Kör',
    starsLabel: '{count} csillag',
    help: 'Segítek! Figyelj.',
    skip: 'Semmi baj, menjünk tovább!',
    praiseFallback: 'Ügyes vagy!',
    retryFallback: 'Próbáld újra!',
    finishTitle: 'Nagyon ügyes voltál!',
    finishSpeech: 'Hurrá! Nagyon ügyes voltál! Kaptál {count} csillagot! Adj egy ötöst!',
    again: 'Még egyszer',
    otherGame: 'Másik játék',
    unsupported: 'Ezt a feladatot most nem tudom megmutatni.',
  },

  choice: {
    answer: 'válasz',
    sequence: 'sor',
    pickSpeaker: '{label} – ez a szép',
  },

  sequence: {
    words: '{count} szó',
    wrong: 'Nem ez jött. Figyeld újra!',
    tooHard: 'Semmi baj, próbáljunk egy rövidebbet!',
  },

  tapcount: {
    drum: 'Dob',
    help: 'Segíts',
    taps: '{count} dobbanás',
    takeOut: '{name} ki a kosárból',
    putIn: '{name} a kosárba',
    basketWrong: 'Most {have} van a kosárban. {want} {what} kérek! Számoljuk meg együtt!',
  },

  trace: {
    clear: 'Törlés',
    almost: 'Majdnem! Menj végig a pöttyökön a virágig!',
  },

  judged: {
    again: 'Még egyszer',
    byChunks: 'Darabonként',
    parentHint: 'Szülő: hallgasd meg, ahogy visszamondja, és te döntsd el.',
    approve: 'Jól mondta',
    notYet: 'Még nem',
    approved: 'Szuper! Pontosan így mondjuk!',
    together: 'Semmi baj! Mondjuk együtt, darabonként.',
    oneGo: 'Most egyben: {text}',
  },

  progress: {
    title: 'Haladás',
    period: 'Időszak',
    lastDays: 'Utolsó {count} nap',
    print: 'Nyomtatás',
    heading: '{name} – gyakorlás {since} óta',
    loadFailed: 'Nem sikerült betölteni a haladást.',
    columns: {
      game: 'Játék',
      skill: 'Mit gyakorol',
      rounds: 'Körök',
      firstTry: 'Elsőre jó',
      solved: 'Megoldva',
      level: 'Szint',
      lastPlayed: 'Utoljára',
    },
    levelNote:
      'Papagáj szint = ennyi szót mond vissza sorrendben. Mondd utánam szint: 1 rövid, 2 közepes, 3 összetett mondat.',
  },

  recordings: {
    title: 'Saját hang',
    intro:
      'Nyomd meg a {record} gombot, olvasd fel lassan és vidáman, mintha mesélnél, aztán nyomd meg a {stop} gombot. ' +
      'Nem kell szó szerint mondani. Ahol van felvétel, ott a te hangod szól; a kérdések szavait a gép mondja.',
    unsupported: 'Ez a böngésző nem tud hangot felvenni. Használd a {folder} gombot.',
    micUnavailable: 'A mikrofon nem érhető el. Engedélyezd a böngészőben, vagy válassz hangfájlt a {folder} gombbal.',
    uploadFailed: 'Nem sikerült feltölteni a felvételt.',
    deleteFailed: 'Nem sikerült törölni a felvételt.',
    recorded: 'felvéve',
    record: 'Felvétel',
    stop: 'Felvétel leállítása',
    play: 'Lejátszás',
    delete: 'Törlés',
    pickFile: 'Hangfájl választása',
  },
}
