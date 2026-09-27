import { config } from '../../config/options'
import { emojiAssetName } from '../../utils/emoji'

/**
 * The sticker picture as an image to share ("Nézd, mit ragasztottam!"): the
 * background's colours, every sticker where it sits, turned and sized like on
 * screen with its white die-cut edge, and a line with the child's name. Drawn
 * on a canvas from the same Twemoji images the app shows, so it looks the same.
 */
const W = 1200
const H = 900

function loadImage(src) {
  return new Promise(resolve => {
    const img = new Image()
    img.onload = () => resolve(img)
    img.onerror = () => resolve(null)
    img.src = src
  })
}

/** A sticker's picture: its Twemoji image, or null to draw the emoji as text. */
function stickerImage(emoji) {
  const base = config.emoji.baseUrl
  return base ? loadImage(`${base.replace(/\/?$/, '/')}${emojiAssetName(emoji)}${config.emoji.ext}`) : Promise.resolve(null)
}

/**
 * @param {{ colors?: string[], stickers: { badge: string, x: number, y: number, rotate: number, scale: number }[], emojiOf: (badge: string) => string, caption: string }} scene
 * @returns {Promise<Blob | null>} a PNG
 */
export async function renderScene({ colors, stickers, emojiOf, caption }) {
  const canvas = document.createElement('canvas')
  canvas.width = W
  canvas.height = H
  const ctx = canvas.getContext('2d')

  const sky = ctx.createLinearGradient(0, 0, 0, H)
  sky.addColorStop(0, colors?.[0] ?? '#DFF4FF')
  sky.addColorStop(1, colors?.[1] ?? '#FFFFFF')
  ctx.fillStyle = sky
  ctx.fillRect(0, 0, W, H)

  const images = await Promise.all(stickers.map(s => stickerImage(emojiOf(s.badge))))
  stickers.forEach((s, i) => {
    const size = W * 0.13 * (s.scale ?? 1)
    ctx.save()
    ctx.translate((s.x / 100) * W, (s.y / 100) * H)
    ctx.rotate(((s.rotate ?? 0) * Math.PI) / 180)
    const draw = () => {
      if (images[i]) ctx.drawImage(images[i], -size / 2, -size / 2, size, size)
      else {
        ctx.font = `${size * 0.85}px sans-serif`
        ctx.textAlign = 'center'
        ctx.textBaseline = 'middle'
        ctx.fillText(emojiOf(s.badge), 0, 0)
      }
    }
    // the white die-cut edge: the picture's own shape, pushed out on four sides
    ctx.shadowColor = '#fff'
    for (const [dx, dy] of [[5, 0], [-5, 0], [0, 5], [0, -5]]) {
      ctx.shadowOffsetX = dx
      ctx.shadowOffsetY = dy
      draw()
    }
    ctx.shadowColor = 'rgba(0, 0, 0, 0.25)'
    ctx.shadowOffsetX = 0
    ctx.shadowOffsetY = 6
    ctx.shadowBlur = 8
    draw()
    ctx.restore()
  })

  // the caption on a soft band at the bottom
  ctx.fillStyle = 'rgba(255, 255, 255, 0.8)'
  ctx.fillRect(0, H - 90, W, 90)
  ctx.fillStyle = '#3b1f4a'
  ctx.font = 'bold 44px "Baloo 2", "Trebuchet MS", sans-serif'
  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'
  ctx.fillText(caption, W / 2, H - 45)

  return new Promise(resolve => canvas.toBlob(resolve, 'image/png'))
}

/** Hands the picture to the phone's share sheet (WhatsApp, Messenger…), or downloads it. */
export async function sharePicture(blob, { title, fileName = 'matricakep.png' }) {
  if (!blob) return false
  const file = new File([blob], fileName, { type: 'image/png' })
  if (navigator.canShare?.({ files: [file] })) {
    try {
      await navigator.share({ files: [file], title })
      return true
    } catch {
      return false // closed the share sheet
    }
  }
  const url = URL.createObjectURL(blob)
  const a = Object.assign(document.createElement('a'), { href: url, download: fileName })
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
  return true
}
