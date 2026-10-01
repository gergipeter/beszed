# ARASAAC pictograms and a paid launch

**Status (2026-10-01):** the app still shows ARASAAC pictograms, which are licensed for non-commercial
use only. Do not charge for the app (or run ads) while they are on, unless ARASAAC has agreed in writing.

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
| Seed content | 8 games hold pictogram references directly: 2,277 of the 5,915 items (38%), about half the items in each of those games |
| Without ARASAAC | 3,638 items still play (165 to 372 per affected game), so every game stays playable |

The games with pictograms: Árnyékkereső, Első hang, Kirakó, Papagáj, Párkereső, Rímelő, Dobolós szavak,
Zümi vagy Susi.

## The switch: `BESZED_PICTOGRAMS=false`

Set it in the environment (default is `true`). Then:

- sessions leave out every item that needs a pictogram (no emoji fallback), and nothing swaps emojis for pictograms
- `/pictograms/{id}.png` answers 404 and downloads nothing from ARASAAC
- the content editor rejects new `arasaac:` pictures

Everything else uses emoji, drawn with Twemoji (CC BY 4.0, commercial use allowed with credit).
Tested in `tests/Feature/PictogramSwitchTest.php`.

## Options before a paid launch

1. **Ask ARASAAC for commercial permission** (draft below). Free of effort, but the answer and any terms are theirs.
2. **Launch with the switch off.** Works today; 38% of the picture content is hidden until it is replaced.
3. **Replace the 385 pictogram-only words** with a commercially licensed set, then switch off ARASAAC for good:
   - [Mulberry Symbols](https://mulberrysymbols.org/) — AAC-style symbols, CC BY-SA 4.0: commercial use allowed with credit, adaptations
     must be shared alike, and the symbols themselves cannot be sold (the product can).
   - [OpenMoji](https://openmoji.org/) — CC BY-SA 4.0, covers everyday objects and animals, not abstract AAC words.
   - Commissioned illustrations: full control, costs money.
   Check each licence again before relying on it, and keep the credits in the privacy page.

Recommended: send the request (1) now, and in parallel look at (3) for the words that matter most. Decide
between (1) and (3) when ARASAAC answers.

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
