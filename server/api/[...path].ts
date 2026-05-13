import { defineEventHandler, getProxyRequestHeaders, getRequestURL, proxyRequest } from 'h3'

export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig(event)
  const path = event.context.params?.path

  if (!path) {
    throw createError({ statusCode: 404, statusMessage: 'API path missing' })
  }

  const normalizedPath = Array.isArray(path) ? path.join('/') : path
  const targetBase = String(config.apiProxyTarget || 'https://api.nichecult.de').replace(/\/+$/, '')
  const requestUrl = getRequestURL(event)
  const targetUrl = new URL(`${targetBase}/${normalizedPath}`)

  targetUrl.search = requestUrl.search

  return proxyRequest(event, targetUrl.toString(), {
    headers: getProxyRequestHeaders(event),
  })
})