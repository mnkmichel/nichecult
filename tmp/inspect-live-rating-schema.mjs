import mysql from 'mysql2/promise'

const conn = await mysql.createConnection({
  host: 'database-5020340144.webspace-host.com',
  port: 3306,
  user: 'dbu5132826',
  password: 'Nicecult2026!',
  database: 'dbs15619002',
})

const [tables] = await conn.query("SHOW TABLES LIKE 'sample_set_perfume_%'")
console.log('TABLES')
console.log(JSON.stringify(tables, null, 2))

const [cols] = await conn.query(`
  SELECT TABLE_NAME, COLUMN_NAME
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('sample_set_perfume_ratings', 'sample_set_perfume_rating_answers')
  ORDER BY TABLE_NAME, ORDINAL_POSITION
`)
console.log('COLS')
console.log(JSON.stringify(cols, null, 2))

await conn.end()
