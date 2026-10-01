# ARASAAC pictograms and a paid launch

**Status (2026-10-01):** the app shows ARASAAC pictograms, which are licensed for non-commercial use only.
Do not charge for the app (or run ads) while they are on, unless ARASAAC has agreed in writing. Set
`BESZED_PICTOGRAMS=false` to launch without them (Mulberry Symbols and emoji fill in for 239 of the 385 words that only exist as pictograms).

## What the licence says

ARASAAC's pictograms (author Sergio Palao, property of the Government of Aragón) are distributed under
**Creative Commons BY-NC-SA 4.0**:

- **BY**: credit them. Their recommended wording: *"The pictographic symbols used are the property of the
  Government of Aragon and have been created by Sergio Palao for ARASAAC (https://arasaac.org) which
  distributes them under a Creative Commons license (BY-NC-SA)."* Include the ARASAAC logo where you use them.
  (The app's privacy page already carries a credit.)
- **NC**: no commercial use. A paid plan, a subscription, or an app sold in a store is commercial use.
  Commercial use needs separate permission: ask through <https://arasaac.org/contact-us>.
- **SA**: anything built from the pictograms must carry the same licence.

The app keeps its own copy of every pictogram it uses (`storage/app/private/pictograms`, served from
`/pictograms/{id}.png`), so it redistributes them rather than just linking to ARASAAC.

## How much the app depends on them

| | |
|---|---|
| Word bank (`database/lexicon/hu.json`) | 781 words: 712 have a pictogram, 396 have an emoji, **385 have a pictogram and no emoji** |
| Substitutes (`database/lexicon/substitutes.json`) | **239 of those 385 words**: 209 Mulberry symbols (205 SVGs, 1.4 MB in `public/symbols/`) and 30 plain emoji, each checked by eye |
| Still without a picture if ARASAAC is off | 146 words: mostly buildings, landscapes, jobs, instruments and some animals |

Seven games draw on pictograms. Each session picks from the first 1,000 items of its game; with ARASAAC off:

| Game | Pool today | Pool with ARASAAC off | Pictogram items kept |
|---|---|---|---|
| Kirakó | 1,000 | 854 | 238 of 384 |
| Papagáj | 1,000 | 854 | 239 of 385 |
| Párkereső | 1,000 | 854 | 239 of 385 |
| Dobolós szavak | 1,000 | 855 | 238 of 383 |
| Árnyékkereső | 1,000 | 880 | 90 of 210 |
| Rímelő | 1,000 | 815 | 284 of 469 |
| Zümi vagy Susi | 1,000 | 825 | 294 of 469 |

So every game stays fully playable; 12 to 19% of its pool is left out until it is replaced.
(Measured on the live database, which holds about 1.25 million generated items; the seed files hold 5,915.)

## The switch: `BESZED_PICTOGRAMS=false`

Set it in the environment (default is `true`). Then:

- a pictogram with a substitute is shown as that substitute: a Mulberry symbol (`mulberry:badger`, drawn
  from `/symbols/badger.svg`) or a plain emoji
- an item with any pictogram that has no substitute is not played
- `/pictograms/{id}.png` answers 404 and downloads nothing from ARASAAC
- the content editor rejects new `arasaac:` pictures

Emoji are drawn with Twemoji (CC BY 4.0, commercial use allowed with credit).
Tested in `tests/Feature/PictogramSwitchTest.php`.

## Mulberry Symbols

[Mulberry Symbols](https://mulberrysymbols.org/), by Steve Lee, CC BY-SA 4.0 (the licence text is in `public/symbols/LICENSE.txt`).
Commercial use is allowed. The conditions: credit them (done on the privacy page), share adaptations of the
symbols under the same licence (the app shows the SVGs unchanged), and do not charge for the symbols
themselves (the app is what is sold). Read the licence again before launch.

Matching was done by English name, then every match was looked at; 42 wrong ones were dropped (for example
"bat" the animal for a racket, "wood" shown as a log, "date" as a calendar date). Female roles use the
female variants (`teacher_2a`). To add or change one, edit `database/lexicon/substitutes.json` (pictogram id
→ symbol name, or `emoji:🏛️`) and copy the SVG into `public/symbols/`. An emoji is only used when no other
word in the word bank already has that picture, so two words never look the same.

## Options before a paid launch

1. **Ask ARASAAC for commercial permission** (draft below). Then the switch can stay on and nothing is lost.
2. **Launch with the switch off.** Works today with 81 to 88% of the picture pool. The 146 uncovered words
   need commissioned illustrations in one style (Mulberry and the emoji sets have no suitable picture for
   them), or hand-picked CC0 clipart (Openclipart); the style would not match the rest.
3. Both: ask ARASAAC, and keep the switch ready.

Recommended: send the request now; launch with the switch off if the answer is slow.

## Draft request (English)

> Subject: Request for commercial-use permission — Beszéd (Hungarian speech and language game for children)
>
> Dear ARASAAC team,
>
> We are building Beszéd, a web and mobile app of speech and language games for Hungarian-speaking
> children, used with their parents and speech therapists. It uses ARASAAC pictograms (credited, with the
> required attribution) as the pictures in several games.
>
> We plan a freemium model: all games can be tried free; a paid subscription unlocks the higher levels and
> the full progress reports. Because that is commercial use, we are asking whether you would permit it,
> and on what terms (a licence, a fee, specific credit, or limits on what we may do).
>
> If commercial use of the pictograms is not possible, please tell us, and we will replace them.
>
> Thank you for ARASAAC; it is a great help to our users.
>
> [Name, role, organisation, website, e-mail]

## Draft request (Spanish)

> Asunto: Solicitud de permiso de uso comercial — Beszéd (juegos de habla y lenguaje para niños)
>
> Estimado equipo de ARASAAC:
>
> Estamos desarrollando Beszéd, una aplicación web y móvil de juegos de habla y lenguaje para niños
> húngaros, que usan junto a sus familias y logopedas. La aplicación utiliza pictogramas de ARASAAC
> (con la atribución requerida) como imágenes en varios juegos.
>
> Prevemos un modelo freemium: todos los juegos se pueden probar gratis, y una suscripción de pago
> desbloquea los niveles superiores y los informes completos de progreso. Como se trata de un uso
> comercial, les consultamos si lo permitirían y en qué condiciones (licencia, tarifa, créditos concretos
> o límites de uso).
>
> Si el uso comercial no es posible, por favor indíquenoslo y sustituiremos los pictogramas.
>
> Gracias por ARASAAC; es de gran ayuda para nuestros usuarios.
>
> [Nombre, cargo, organización, web, correo]
