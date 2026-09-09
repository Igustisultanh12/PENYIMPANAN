use serde::{Deserialize, Serialize};

#[derive(Debug, Serialize, Deserialize, Clone)]
pub struct ConflictItem {
    pub file_uuid: String,
    pub relative_path: String,
    pub local_size: u64,
    pub local_checksum: String,
    pub cloud_size: u64,
    pub cloud_checksum: String,
    pub cloud_updated_at: String,
}

#[derive(Debug, Serialize, Deserialize, Clone)]
pub enum ConflictStrategy {
    KeepLocal,
    KeepCloud,
    KeepBoth,
}

impl ConflictStrategy {
    pub fn as_str(&self) -> &'static str {
        match self {
            ConflictStrategy::KeepLocal => "keep_local",
            ConflictStrategy::KeepCloud => "keep_cloud",
            ConflictStrategy::KeepBoth => "keep_both",
        }
    }
}
