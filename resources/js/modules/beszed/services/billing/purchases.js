import { config } from '../../config/options'

/**
 * The native side of the premium plan: Apple In-App Purchase / Google Play Billing through the RevenueCat Capacitor
 * plugin (`@revenuecat/purchases-capacitor`, installed in the native shell, not in this web bundle). In a plain browser
 * there is no store, so nothing can be bought: `available()` is false and the paywall only describes the plan.
 * The plugin's native half is found by name (`Capacitor.registerPlugin('Purchases')`; the native build loads
 * @capacitor/core in resources/native/main.js, so `registerPlugin` exists). Method names and the shapes used below
 * (configure, getOfferings, purchasePackage, restorePurchases; product.identifier / priceString / introPrice) were
 * checked against @revenuecat/purchases-capacitor 13.7.
 *
 * The app never decides who is premium. After a purchase or a restore it only asks the server to look
 * (`config.billing.sync`), and the server checks with RevenueCat.
 */

let purchases = null
let configured = null

function capacitor() {
  return typeof window === 'undefined' ? null : window.Capacitor ?? null
}

/** Running inside the iOS / Android app (and the RevenueCat plugin is part of it). */
export function available() {
  const cap = capacitor()
  return Boolean(cap?.isNativePlatform?.() && cap.isPluginAvailable?.('Purchases') !== false)
}

/** 'ios' | 'android' | 'web' */
export function platform() {
  return capacitor()?.getPlatform?.() ?? 'web'
}

function plugin() {
  purchases ??= capacitor().registerPlugin('Purchases')
  return purchases
}

/** Starts RevenueCat for this parent, once per visit; the id ties the purchase to the account the server knows. */
function setup() {
  configured ??= (async () => {
    const info = await config.billing.info()
    const apiKey = platform() === 'ios' ? info.keys?.ios : info.keys?.android
    if (!info.enabled || !apiKey) throw new Error('billing-not-configured')
    await plugin().configure({ apiKey, appUserID: info.app_user_id })
    return info
  })().catch(error => {
    configured = null
    throw error
  })
  return configured
}

const UNIT_DAYS = { DAY: 1, WEEK: 7 }

/** "7 days" of free trial when the store reports a free introductory offer, else null. */
function trialDays(product) {
  const intro = product.introPrice
  if (!intro || Number(intro.price) !== 0) return null
  const days = (UNIT_DAYS[intro.periodUnit] ?? 0) * Number(intro.periodNumberOfUnits ?? 0)
  return days > 0 ? days : null
}

/**
 * The plans on offer, with the store's own price text (right currency, VAT included).
 * @returns {Promise<{ id: string, period: 'month' | 'year', price: string, trialDays: number | null, pkg: object }[]>}
 */
export async function loadPlans() {
  const info = await setup()
  const { current } = await plugin().getOfferings()
  const byProduct = new Map((current?.availablePackages ?? []).map(pkg => [pkg.product.identifier, pkg]))

  return [
    ['monthly', 'month'],
    ['yearly', 'year'],
  ]
    .map(([key, period]) => {
      // Google Play product ids may carry a base-plan suffix ("beszed.premium.yearly:yearly").
      const id = info.products?.[key]
      const pkg = id && ([...byProduct.keys()].find(k => k === id || k.startsWith(`${id}:`)) ?? null)
      return pkg ? { id: key, period, price: byProduct.get(pkg).product.priceString, trialDays: trialDays(byProduct.get(pkg).product), pkg: byProduct.get(pkg) } : null
    })
    .filter(Boolean)
}

/**
 * Buys a plan, then has the server confirm it. Resolves true when the parent is premium afterwards,
 * false when the server does not see a subscription, null when they backed out of the store's sheet.
 */
export async function purchase(plan) {
  try {
    await plugin().purchasePackage({ aPackage: plan.pkg })
  } catch (error) {
    // The bridge's raw rejection carries the code as a string ("1" = purchase cancelled) and the flag under `data`.
    if (error?.userCancelled || error?.data?.userCancelled || String(error?.code) === '1') return null
    throw error
  }
  return config.billing.sync()
}

/** "Restore purchases": the store's receipts for this Apple ID / Google account, then the server confirms. */
export async function restore() {
  await setup()
  await plugin().restorePurchases()
  return config.billing.sync()
}
