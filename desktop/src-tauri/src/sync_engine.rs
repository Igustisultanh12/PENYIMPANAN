use crate::conflict::{ConflictItem, ConflictStrategy};
use crate::db::SyncDatabase;
use crate::watcher::FileWatcher;
use reqwest::Client;
use serde::{Deserialize, Serialize};
use std::path::{Path, PathBuf};
use std::sync::atomic::{AtomicBool, Ordering};
use std::sync::Arc;
use tokio::sync::Mutex;
use tokio::time::{sleep, Duration};

#[derive(Debug, Serialize, Deserialize, Clone)]
pub struct SyncStatusState {
    pub status: String, // 'idle', 'syncing', 'paused', 'offline'
    pub files_remaining: u32,
    pub bytes_synced: u64,
    pub total_bytes: u64,
    pub last_synced_at: Option<String>,
    pub sync_dir: String,
    pub active_conflicts: Vec<ConflictItem>,
}

#[derive(Debug, Deserialize)]
struct SyncFeedResponse {
    success: bool,
    data: SyncFeedData,
}

#[derive(Debug, Deserialize)]
struct SyncFeedData {
    changes: Vec<SyncChangeItem>,
    cursor: i64,
    has_more: bool,
}

#[derive(Debug, Deserialize, Clone)]
pub struct SyncChangeItem {
    pub id: i64,
    pub item_type: String, // 'file' or 'folder'
    pub item_uuid: String,
    pub change_type: String, // 'created', 'updated', 'deleted', 'restored', 'moved'
    pub checksum: Option<String>,
    pub version: Option<u32>,
    pub metadata: Option<serde_json::Value>,
}

pub struct SyncEngine {
    pub api_base: String,
    pub token: String,
    pub device_id: String,
    pub sync_dir: PathBuf,
    pub db: Arc<Mutex<SyncDatabase>>,
    pub is_paused: AtomicBool,
    pub status: Arc<Mutex<SyncStatusState>>,
    client: Client,
}

impl SyncEngine {
    pub fn new(
        api_base: String,
        token: String,
        device_id: String,
        sync_dir: PathBuf,
        db: SyncDatabase,
    ) -> Self {
        let initial_status = SyncStatusState {
            status: "idle".to_string(),
            files_remaining: 0,
            bytes_synced: 0,
            total_bytes: 0,
            last_synced_at: None,
            sync_dir: sync_dir.to_string_lossy().to_string(),
            active_conflicts: Vec::new(),
        };

        Self {
            api_base,
            token,
            device_id,
            sync_dir,
            db: Arc::new(Mutex::new(db)),
            is_paused: AtomicBool::new(false),
            status: Arc::new(Mutex::new(initial_status)),
            client: Client::new(),
        }
    }

    /// Primary background sync task loop
    pub async fn start_background_loop(self: Arc<Self>) {
        // Ensure local sync directory exists
        let _ = std::fs::create_dir_all(&self.sync_dir);

        loop {
            if !self.is_paused.load(Ordering::Relaxed) {
                if let Err(e) = self.poll_and_process_changes().await {
                    eprintln!("[SyncEngine] Poll error: {:?}", e);
                    let mut st = self.status.lock().await;
                    st.status = "offline".to_string();
                }
            } else {
                let mut st = self.status.lock().await;
                st.status = "paused".to_string();
            }

            sleep(Duration::from_secs(3)).await;
        }
    }

    /// Fetch incremental change feed from server and apply updates
    pub async fn poll_and_process_changes(&self) -> Result<(), Box<dyn std::error::Error + Send + Sync>> {
        let cursor = {
            let db = self.db.lock().await;
            db.get_cursor().unwrap_or(0)
        };

        let url = format!(
            "{}/api/v1/sync/changes?cursor={}&device_id={}&exclude_self=true&limit=100",
            self.api_base, cursor, self.device_id
        );

        let res = self
            .client
            .get(&url)
            .bearer_auth(&self.token)
            .send()
            .await?;

        if !res.status().is_success() {
            return Ok(());
        }

        let feed: SyncFeedResponse = res.json().await?;
        if feed.data.changes.is_empty() {
            let mut st = self.status.lock().await;
            st.status = "idle".to_string();
            return Ok(());
        }

        {
            let mut st = self.status.lock().await;
            st.status = "syncing".to_string();
            st.files_remaining = feed.data.changes.len() as u32;
        }

        for change in &feed.data.changes {
            self.apply_server_change(change).await?;
        }

        // Commit new cursor
        {
            let db = self.db.lock().await;
            let _ = db.set_cursor(feed.data.cursor);
        }

        // Send checkpoint acknowledgment to server
        let _ = self
            .client
            .post(format!("{}/api/v1/sync/checkpoint", self.api_base))
            .bearer_auth(&self.token)
            .json(&serde_json::json!({
                "device_id": self.device_id,
                "cursor": feed.data.cursor
            }))
            .send()
            .await;

        {
            let mut st = self.status.lock().await;
            st.status = "idle".to_string();
            st.files_remaining = 0;
            st.last_synced_at = Some(chrono::Utc::now().to_rfc3339());
        }

        Ok(())
    }

    async fn apply_server_change(&self, change: &SyncChangeItem) -> Result<(), Box<dyn std::error::Error + Send + Sync>> {
        if change.item_type == "file" {
            let file_name = change
                .metadata
                .as_ref()
                .and_then(|m| m.get("name"))
                .and_then(|v| v.as_str())
                .unwrap_or("unnamed_file");

            let target_path = self.sync_dir.join(file_name);

            match change.change_type.as_str() {
                "deleted" => {
                    if target_path.exists() {
                        let _ = std::fs::remove_file(&target_path);
                    }
                    let db = self.db.lock().await;
                    let _ = db.delete_file_record(file_name);
                }
                "created" | "updated" | "restored" => {
                    // Download file content from server
                    let download_url = format!("{}/api/v1/files/{}/download", self.api_base, change.item_uuid);
                    if let Ok(resp) = self.client.get(&download_url).bearer_auth(&self.token).send().await {
                        if resp.status().is_success() {
                            if let Ok(bytes) = resp.bytes().await {
                                if let Ok(_) = std::fs::write(&target_path, &bytes) {
                                    let checksum = change.checksum.clone().unwrap_or_default();
                                    let size = bytes.len() as i64;
                                    let mtime = chrono::Utc::now().timestamp();
                                    let db = self.db.lock().await;
                                    let _ = db.upsert_file(file_name, Some(&change.item_uuid), &checksum, mtime, size, "synced");
                                }
                            }
                        }
                    }
                }
                _ => {}
            }
        }
        Ok(())
    }

    pub fn pause(&self) {
        self.is_paused.store(true, Ordering::Relaxed);
    }

    pub fn resume(&self) {
        self.is_paused.store(false, Ordering::Relaxed);
    }
}
