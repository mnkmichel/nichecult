const crypto = require('crypto')

const API_BASE = 'https://api.nichecult.de'
const SECRET = '5a2782cc9bcdde81a1ac79c61eb5692796ef9fec76f7879f6f5c51c37a1be411eebe32342ad8ade6780e909fbbc9560e62d58437df4f4325346c770861579ba1'

const b64url = (obj) => Buffer.from(JSON.stringify(obj)).toString('base64url')

function createAdminToken() {
  const now = Math.floor(Date.now() / 1000)
  const header = b64url({ alg: 'HS256', typ: 'JWT' })
  const payload = b64url({ sub: 1, admin: 1, exp: now + 3600 })
  const signature = crypto.createHmac('sha256', SECRET).update(`${header}.${payload}`).digest('base64url')
  return `${header}.${payload}.${signature}`
}

function pickDefaultSet(sampleSets) {
  const titleScore = (title) => {
    const t = String(title || '').trim().toLowerCase()
    if (t === 'erstes set') return 0
    if (t === 'erste duftselektion') return 1
    if (t.startsWith('erstes set')) return 2
    if (t.startsWith('erste')) return 3
    return 9
  }

  return [...sampleSets].sort((a, b) => {
    const scoreDiff = titleScore(a.title) - titleScore(b.title)
    if (scoreDiff !== 0) return scoreDiff

    const statusDiff = (String(a.status || '').toLowerCase() === 'active' ? 0 : 1) - (String(b.status || '').toLowerCase() === 'active' ? 0 : 1)
    if (statusDiff !== 0) return statusDiff

    const perfumeDiff = (Number(b.perfume_count || 0) > 0 ? 0 : 1) - (Number(a.perfume_count || 0) > 0 ? 0 : 1)
    if (perfumeDiff !== 0) return perfumeDiff

    return Number(a.id || 0) - Number(b.id || 0)
  })[0]
}

async function run() {
  const token = createAdminToken()
  const headers = { Authorization: `Bearer ${token}` }

  const listRes = await fetch(`${API_BASE}/admin-list-sample-sets.php`, { headers })
  const listText = await listRes.text()
  let listJson
  try {
    listJson = JSON.parse(listText)
  } catch {
    throw new Error(`admin-list-sample-sets non-JSON response (status ${listRes.status}): ${listText.slice(0, 300)}`)
  }
  if (!listJson.ok || !Array.isArray(listJson.sampleSets)) {
    throw new Error(`admin-list-sample-sets failed: ${JSON.stringify(listJson)}`)
  }

  const targetSet = pickDefaultSet(listJson.sampleSets)
  if (!targetSet || !targetSet.id) {
    throw new Error('Kein passendes erstes Set gefunden')
  }

  const form = new FormData()
  form.set('sample_set_id', String(targetSet.id))

  const assignRes = await fetch(`${API_BASE}/admin-set-default-sample-set.php`, {
    method: 'POST',
    headers,
    body: form,
  })
  const assignText = await assignRes.text()
  let assignJson
  try {
    assignJson = JSON.parse(assignText)
  } catch {
    assignJson = null
  }

  if (assignRes.status === 404 || !assignJson) {
    const usersRes = await fetch(`${API_BASE}/admin-users.php`, { headers })
    const usersText = await usersRes.text()
    let usersJson
    try {
      usersJson = JSON.parse(usersText)
    } catch {
      throw new Error(`admin-users non-JSON response (status ${usersRes.status}): ${usersText.slice(0, 300)}`)
    }

    if (!usersJson.ok || !Array.isArray(usersJson.users)) {
      throw new Error(`admin-users failed: ${JSON.stringify(usersJson)}`)
    }

    let assignedCount = 0
    const failed = []

    for (const user of usersJson.users) {
      const userId = Number(user.id || 0)
      if (!userId) continue

      const userForm = new FormData()
      userForm.set('sample_set_id', String(targetSet.id))
      userForm.set('user_id', String(userId))
      userForm.set('set_status', 'delivered')
      userForm.set('rating_deadline_at', '')

      const r = await fetch(`${API_BASE}/admin-assign-sample-set.php`, {
        method: 'POST',
        headers,
        body: userForm,
      })
      const rt = await r.text()
      let rj
      try {
        rj = JSON.parse(rt)
      } catch {
        failed.push({ user_id: userId, reason: `non-json status ${r.status}` })
        continue
      }

      if (rj.ok) {
        assignedCount++
      } else {
        failed.push({ user_id: userId, reason: rj.error || 'assignment failed' })
      }
    }

    console.log(JSON.stringify({
      picked_set: {
        id: targetSet.id,
        title: targetSet.title,
        status: targetSet.status,
        perfume_count: targetSet.perfume_count,
      },
      fallback_mode: 'admin-assign-sample-set-loop',
      result: {
        ok: true,
        assigned_count: assignedCount,
        failed_count: failed.length,
        failed,
      },
    }, null, 2))
    return
  }

  console.log(JSON.stringify({
    picked_set: {
      id: targetSet.id,
      title: targetSet.title,
      status: targetSet.status,
      perfume_count: targetSet.perfume_count,
    },
    result: assignJson,
  }, null, 2))
}

run().catch((error) => {
  console.error(error.message || error)
  process.exit(1)
})
