# ARASAAC pictograms and a paid launch

**Status (2026-10-01):** the app shows ARASAAC pictograms, which are licensed for non-commercial use only.
Do not charge for the app (or run ads) while they are on, unless ARASAAC has agreed in writing. Set
`BESZED_PICTOGRAMS=false` to launch without them (Mulberry Symbols fill in for 209 of the 385 words that only exist as pictograms).

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
| Mulberry replacements (`database/lexicon/mulberry.json`) | **209 of those 385 words** (205 symbols, 1.4 MB in `public/symbols/`), each checked by eye |
| Still without a picture if ARASAAC is off | 176 words: buildings, landscapes, many jobs, instruments, some animals |

Seven games draw on pictograms. Each session picks from the first 1,000 items of its game; with ARASAAC off:

| Game | Pool today | Pool with ARASAAC off | Of which pictogram items |
|---|---|---|---|
| Kirakó | 1,000 | 824 | 208 of 384 kept as Mulberry |
| Papagáj | 1,000 | 824 | 209 of 385 |
| Párkereső | 1,000 | 824 | 209 of 385 |
| Dobolós szavak | 1,000 | 825 | 208 of 383 |
| Árnyékkereső | 1,000 | 850 | 129 of 210 |
| Rímelő | 1,000 | 783 | 262 of 469 |
| Zümi vagy Susi | 1,000 | 783 | 262 of 469 |

So every game stays fully playable; about 15 to 22% of its picture items are left out until they are replaced.
(Measured on the live database, which holds about 1.25 million generated items; the seed files hold 5,915.)

## The switch: `BESZED_PICTOGRAMS=false`

Set it in the environment (default is `true`). Then:

- a pictogram that has a Mulberry symbol is shown as that symbol (`mulberry:badger`, drawn from `/symbols/badger.svg`)
- an item with any pictogram that has no symbol is not played
- `/pictograms/{id}.png` answers 404 and downloads nothing from ARASAAC
- the content editor rejects new `arasaac:` pictures

Everything else uses emoji, drawn with Twemoji (CC BY 4.0, commercial use allowed with credit).
Tested in `tests/Feature/PictogramSwitchTest.php`.

## Mulberry Symbols

[Mulberry Symbols](https://mulberrysymbols.org/), by Steve Lee, CC BY-SA 4.0 (the licence text is in `public/symbols/LICENSE.txt`).
Commercial use is allowed. The conditions: credit them (done on the privacy page), share adaptations of the
symbols under the same licence (the app shows the SVGs unchanged), and do not charge for the symbols
themselves (the app is what is sold). Read the licence again before launch.

Matching was done by English name, then every match was looked at; 42 wrong ones were dropped (for example
"bat" the animal for a racket, "wood" shown as a log, "date" as a calendar date). Female roles use the
female variants (`teacher_2a`). To add or change one, edit `database/lexicon/mulberry.json` (pictogram id
→ symbol name) and copy the SVG into `public/symbols/`.

## Options before a paid launch

1. **Ask ARASAAC for commercial permission** (draft below). Then the switch can stay on and nothing is lost.
2. **Launch with the switch off.** Works today with 76 to 85% of the picture pool. The 176 uncovered words
   could be filled with more Mulberry-style symbols, OpenMoji, or commissioned illustrations.
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
