export default defineEventHandler(async () => {
  try {
    const pool = getDbPool()
    const [rows] = await pool.query('SELECT 1 AS ok, NOW() AS server_time')
    const row = Array.isArray(rows) ? rows[0] : rows

    return {
      ok: true,
      db: row,
    }
  }
  catch (error) {
    throw createError({
      statusCode: 500,
      statusMessage: 'Database connection failed',
      data: error,
    })
  }
})
