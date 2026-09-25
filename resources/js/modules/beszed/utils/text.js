export const capitalize = s => s.charAt(0).toUpperCase() + s.slice(1)

/** Splits text into sentences, so Web Speech can pause naturally between them. */
export const sentences = text =>
  text
    .replace(/([.!?…])\s+/g, '$1\u0001')
    .split('\u0001')
    .map(s => s.trim())
    .filter(Boolean)

/** `"hang.m4a"` → `"m4a"`; `fallback` when the name has no extension. */
export const fileExtension = (name, fallback) => /\.([a-z0-9]+)$/i.exec(name)?.[1].toLowerCase() ?? fallback
