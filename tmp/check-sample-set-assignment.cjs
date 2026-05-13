const mysql = require('mysql2/promise')

async function main() {
  const conn = await mysql.createConnection({
    host: process.env.DB_HOST || 'database-5020340144.webspace-host.com',
    port: Number(process.env.DB_PORT || 3306),
    user: process.env.DB_USER || 'dbu5132826',
    password: process.env.DB_PASSWORD || 'Nicecult2026!',
    database: process.env.DB_NAME || 'dbs15619002',
  })

  const emails = [
    'final.assign.20260513.0347@nichecult-test.local',
    'proxy.assign.20260513.0329@nichecult-test.local',
    'auto.assign.20260513.0126@nichecult-test.local',
  ]

  const [sets] = await conn.query('SELECT id, title, status, rating_deadline_at FROM sample_sets ORDER BY id ASC')
  const [users] = await conn.query(
    'SELECT id, email FROM users WHERE email IN (?, ?, ?) ORDER BY id ASC',
    emails,
  )
  const [assignments] = await conn.query(
    `SELECT uss.id, uss.user_id, uss.sample_set_id, uss.set_status, uss.assigned_at
     FROM user_sample_sets uss
     INNER JOIN users u ON u.id = uss.user_id
     WHERE u.email IN (?, ?, ?)
     ORDER BY uss.id ASC`,
    emails,
  )

  console.log(JSON.stringify({ sets, users, assignments }, null, 2))
  await conn.end()
}

main().catch((error) => {
  console.error(error)
  process.exit(1)
})
