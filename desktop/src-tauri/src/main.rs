#![cfg_attr(not(debug_assertions), windows_subsystem = "windows")]

mod conflict;
mod db;
mod sync_engine;
mod watcher;

use db::SyncDatabase;
use sync_engine::{SyncEngine, SyncStatusState};
use std::path::PathBuf;
use std::sync::Arc;
use tauri::{AppHandle, Manager, State};
use tokio::sync::Mutex;

struct AppState {
    engine: Arc<SyncEngine>,
}

#[tauri::command]
async fn get_sync_status(state: State<'_, AppState>) -> Result<SyncStatusState, String> {
    let status = state.engine.status.lock().await;
    Ok(status.clone())
}

#[tauri::command]
async fn pause_sync(state: State<'_, AppState>) -> Result<bool, String> {
    state.engine.pause();
    Ok(true)
}

#[tauri::command]
async fn resume_sync(state: State<'_, AppState>) -> Result<bool, String> {
    state.engine.resume();
    Ok(true)
}

#[tauri::command]
async fn open_local_folder(state: State<'_, AppState>) -> Result<(), String> {
    let path = &state.engine.sync_dir;
    #[cfg(target_os = "windows")]
    {
        std::process::Command::new("explorer")
            .arg(path)
            .spawn()
            .map_err(|e| e.to_string())?;
    }
    #[cfg(target_os = "macos")]
    {
        std::process::Command::new("open")
            .arg(path)
            .spawn()
            .map_err(|e| e.to_string())?;
    }
    #[cfg(target_os = "linux")]
    {
        std::process::Command::new("xdg-open")
            .arg(path)
            .spawn()
            .map_err(|e| e.to_string())?;
    }
    Ok(())
}

#[tauri::command]
async fn trigger_manual_sync(state: State<'_, AppState>) -> Result<bool, String> {
    state
        .engine
        .poll_and_process_changes()
        .await
        .map_err(|e| e.to_string())?;
    Ok(true)
}

fn main() {
    let home_dir = dirs::home_dir().unwrap_or_else(|| PathBuf::from("."));
    let sync_dir = home_dir.join("MyStorage");
    let app_data_dir = home_dir.join(".mystorage");
    let _ = std::fs::create_dir_all(&app_data_dir);
    let _ = std::fs::create_dir_all(&sync_dir);

    let db_path = app_data_dir.join("mystorage.db");
    let db = SyncDatabase::new(&db_path).expect("Failed to initialize sync database");

    let engine = Arc::new(SyncEngine::new(
        "http://localhost:8000".to_string(),
        "".to_string(),
        "desktop-pc-client".to_string(),
        sync_dir,
        db,
    ));

    // Spawn background sync runtime
    let engine_clone = engine.clone();
    tokio::spawn(async move {
        engine_clone.start_background_loop().await;
    });

    tauri::Builder::default()
        .manage(AppState { engine })
        .invoke_handler(tauri::generate_handler![
            get_sync_status,
            pause_sync,
            resume_sync,
            open_local_folder,
            trigger_manual_sync
        ])
        .run(tauri::generate_context!())
        .expect("error while running MyStorage desktop client");
}
