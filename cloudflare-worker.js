/**
 * Cloudflare Worker for Beszéd API
 * Handles edge caching, routing, and security
 */

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // Security headers
    const securityHeaders = {
      'X-Content-Type-Options': 'nosniff',
      'X-Frame-Options': 'SAMEORIGIN',
      'X-XSS-Protection': '1; mode=block',
      'Strict-Transport-Security': 'max-age=31536000; includeSubDomains',
      'Content-Security-Policy': "default-src 'self'; script-src 'self' 'unsafe-inline' 'wasm-unsafe-eval'; style-src 'self' 'unsafe-inline'",
    };

    // Handle OPTIONS requests (CORS preflight)
    if (request.method === 'OPTIONS') {
      return new Response(null, {
        headers: {
          'Access-Control-Allow-Origin': '*',
          'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
          'Access-Control-Allow-Headers': 'Content-Type, Authorization',
          'Access-Control-Max-Age': '86400',
        },
      });
    }

    // Cache key configuration
    const cacheKey = new Request(url.toString(), { method: 'GET' });
    const cache = caches.default;

    // Check cache for GET requests
    if (request.method === 'GET') {
      const cachedResponse = await cache.match(cacheKey);
      if (cachedResponse) {
        return new Response(cachedResponse.body, {
          status: cachedResponse.status,
          statusText: cachedResponse.statusText,
          headers: {
            ...Object.fromEntries(cachedResponse.headers),
            'X-Cache': 'HIT',
            ...securityHeaders,
          },
        });
      }
    }

    // Route API requests
    const apiOrigin = env.API_ORIGIN || 'http://localhost:8000';

    // Forward request to origin
    const modifiedRequest = new Request(url.toString().replace(/^https?:\/\/[^\/]+/, apiOrigin), {
      method: request.method,
      headers: {
        ...Object.fromEntries(request.headers),
        'X-Forwarded-For': request.headers.get('CF-Connecting-IP'),
        'X-Forwarded-Proto': 'https',
      },
      body: request.body,
    });

    let response = await fetch(modifiedRequest);

    // Cache GET responses
    if (request.method === 'GET' && response.status === 200) {
      const cacheControl = response.headers.get('Cache-Control') || 'public, max-age=3600';
      response = new Response(response.body, response);
      response.headers.set('Cache-Control', cacheControl);

      ctx.waitUntil(cache.put(cacheKey, response.clone()));
    }

    // Add security headers to response
    const finalResponse = new Response(response.body, {
      status: response.status,
      statusText: response.statusText,
      headers: {
        ...Object.fromEntries(response.headers),
        ...securityHeaders,
        'Access-Control-Allow-Origin': '*',
      },
    });

    return finalResponse;
  },
};
