import mysql from 'mysql2/promise'

let pool: mysql.Pool | null = null

export function getDbPool() {
  if (pool) {
    return pool
  }

  const config = useRuntimeConfig()

  pool = mysql.createPool({
    host: config.dbHost,
    port: Number(config.dbPort || 3306),
    user: config.dbUser,
    password: config.dbPassword,
    database: config.dbName,
    connectionLimit: 10,
    waitForConnections: true,
    queueLimit: 0,
  })

  return pool
}
