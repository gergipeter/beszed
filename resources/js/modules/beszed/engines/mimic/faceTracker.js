import { config } from '../../config/options'

/**
 * The face tracker behind Szájtorna's mirror: Google's MediaPipe Face Landmarker (Apache-2.0), run on the
 * device in WebAssembly. Each video frame gives the face's movement scores (blendshapes); the frame itself
 * is never kept, recorded or sent. The library (~150 kB) and its files (~11 MB wasm + 3.7 MB model, copied
 * into the build by scripts/copy-face.mjs) download only when a child first turns the mirror on; the
 * service worker keeps them for the next time.
 *
 * loadFaceTracker() → { detect(video, nowMs) → MediaPipe result | null }, or throws when the
 * device can't run it (the mirror then stays a plain mirror and the parent's button counts).
 */
let shared = null
/** MediaPipe wants strictly increasing timestamps over the landmarker's whole life, across exercises. */
let lastTime = -1

async function create() {
  const { FaceLandmarker, FilesetResolver } = await import('@mediapipe/tasks-vision')
  const base = new URL(config.face.baseUrl, document.baseURI).href
  const files = await FilesetResolver.forVisionTasks(base.replace(/\/?$/, ''))
  const options = delegate => ({
    baseOptions: { modelAssetPath: `${base.replace(/\/?$/, '/')}face_landmarker.task`, delegate },
    runningMode: 'VIDEO',
    numFaces: 1,
    outputFaceBlendshapes: true,
    outputFaceLandmarks: false,
  })
  try {
    return await FaceLandmarker.createFromOptions(files, options('GPU'))
  } catch {
    // no WebGL2 (older phones, some web views): the CPU is slower but fine for one face
    return FaceLandmarker.createFromOptions(files, options('CPU'))
  }
}

export async function loadFaceTracker() {
  shared ??= create().catch(err => {
    shared = null
    throw err
  })
  const landmarker = await shared
  return {
    detect(video, now) {
      if (!video || video.readyState < 2 || !video.videoWidth) return null
      const ts = Math.max(Math.round(now), lastTime + 1)
      lastTime = ts
      return landmarker.detectForVideo(video, ts)
    },
  }
}
