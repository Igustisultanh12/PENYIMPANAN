use rusqlite::{params, Connection, Result};
use serde::{Deserialize, Serialize};
use std::path::Path;

#[derive(Debug, Serialize, Deserialize, Clone)]
pub struct LocalFileRecord {
    pub id: i64,
    pub relative_path: String,
    pub cloud_uuid: Option<String>,
    pub checksum: String,
    pub mtime: i64,
    pub size: i64,
    pub status: String, // 'synced', 'modified', 'conflict'
}

pub struct SyncDatabase {
    conn: Connection,
}

impl SyncDatabase {
    pub fn new<P: AsRef<Path>>(path: P) -> Result<Self> {
        let conn = Connection::open(path)?;
        let db = Self { conn };
        db.init_schema()?;
        Ok(db)
    }

    fn init_schema(&self) -> Result<()> {
        self.conn.execute_batch(
            "
            CREATE TABLE IF NOT EXISTS local_files (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                relative_path TEXT UNIQUE NOT NULL,
                cloud_uuid TEXT,
                checksum TEXT NOT NULL,
                mtime INTEGER NOT NULL,
                size INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'synced',
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS sync_state (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS sync_queue (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                action TEXT NOT NULL, -- 'upload', 'download', 'delete'
                relative_path TEXT NOT NULL,
                cloud_uuid TEXT,
                retries INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS selective_sync (
                folder_uuid TEXT PRIMARY KEY,
                folder_name TEXT NOT NULL,
                is_synced INTEGER DEFAULT 1
            );
            ",
        )?;
        Ok(())
    }

    pub fn get_cursor(&self) -> Result<i64> {
        let mut stmt = self.conn.prepare("SELECT value FROM sync_state WHERE key = 'cursor'")?;
        let mut rows = stmt.query([])?;
        if let Some(row) = rows.next()? {
            let val: String = row.get(0)?;
            Ok(val.parse::<i64>().unwrap_or(0))
        } else {
            Ok(0)
        }
    }

    pub fn set_cursor(&self, cursor: i64) -> Result<()> {
        self.conn.execute(
            "INSERT OR REPLACE INTO sync_state (key, value) VALUES ('cursor', ?1)",
            params![cursor.to_string()],
        )?;
        Ok(())
    }

    pub fn upsert_file(&self, relative_path: &str, cloud_uuid: Option<&str>, checksum: &str, mtime: i64, size: i64, status: &str) -> Result<()> {
        self.conn.execute(
            "INSERT INTO local_files (relative_path, cloud_uuid, checksum, mtime, size, status, updated_at)
             VALUES (?1, ?2, ?3, ?4, ?5, ?6, CURRENT_TIMESTAMP)
             ON CONFLICT(relative_path) DO UPDATE SET
                cloud_uuid = excluded.cloud_uuid,
                checksum = excluded.checksum,
                mtime = excluded.mtime,
                size = excluded.size,
                status = excluded.status,
                updated_at = CURRENT_TIMESTAMP",
            params![relative_path, cloud_uuid, checksum, mtime, size, status],
        )?;
        Ok(())
    }

    pub fn get_file_by_path(&self, relative_path: &str) -> Result<Option<LocalFileRecord>> {
        let mut stmt = self.conn.prepare(
            "SELECT id, relative_path, cloud_uuid, checksum, mtime, size, status FROM local_files WHERE relative_path = ?1"
        )?;
        let mut rows = stmt.query(params![relative_path])?;
        if let Some(row) = rows.next()? {
            Ok(Some(LocalFileRecord {
                id: row.get(0)?,
                relative_path: row.get(1)?,
                cloud_uuid: row.get(2)?,
                checksum: row.get(3)?,
                mtime: row.get(4)?,
                size: row.get(5)?,
                status: row.get(6)?,
            }))
        } else {
            Ok(None)
        }
    }

    pub fn delete_file_record(&self, relative_path: &str) -> Result<()> {
        self.conn.execute("DELETE FROM local_files WHERE relative_path = ?1", params![relative_path])?;
        Ok(())
    }
}
