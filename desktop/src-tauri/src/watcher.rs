use notify::{Config, Event, EventKind, RecommendedWatcher, RecursiveMode, Watcher};
use sha2::{Digest, Sha256};
use std::fs::File;
use std::io::Read;
use std::path::{Path, PathBuf};
use std::sync::mpsc::{channel, Receiver};
use std::time::Duration;

#[derive(Debug, Clone)]
pub enum FsChangeEvent {
    Created(PathBuf),
    Modified(PathBuf),
    Deleted(PathBuf),
    Renamed { from: PathBuf, to: PathBuf },
}

pub struct FileWatcher {
    _watcher: RecommendedWatcher,
    pub rx: Receiver<FsChangeEvent>,
}

impl FileWatcher {
    pub fn new<P: AsRef<Path>>(watch_dir: P) -> Result<Self, Box<dyn std::error::Error>> {
        let (event_tx, rx) = channel();
        let (raw_tx, raw_rx) = channel();

        let mut watcher = RecommendedWatcher::new(
            move |res| {
                if let Ok(event) = res {
                    let _ = raw_tx.send(event);
                }
            },
            Config::default().with_poll_interval(Duration::from_millis(500)),
        )?;

        watcher.watch(watch_dir.as_ref(), RecursiveMode::Recursive)?;

        // Background thread to filter and normalize raw notify events
        std::thread::spawn(move || {
            while let Ok(event) = raw_rx.recv() {
                match event.kind {
                    EventKind::Create(_) => {
                        for path in event.paths {
                            if path.is_file() {
                                let _ = event_tx.send(FsChangeEvent::Created(path));
                            }
                        }
                    }
                    EventKind::Modify(_) => {
                        for path in event.paths {
                            if path.is_file() {
                                let _ = event_tx.send(FsChangeEvent::Modified(path));
                            }
                        }
                    }
                    EventKind::Remove(_) => {
                        for path in event.paths {
                            let _ = event_tx.send(FsChangeEvent::Deleted(path));
                        }
                    }
                    _ => {}
                }
            }
        });

        Ok(Self {
            _watcher: watcher,
            rx,
        })
    }

    /// Calculate SHA-256 hash of a local file safely in chunks
    pub fn compute_sha256<P: AsRef<Path>>(path: P) -> Result<String, std::io::Error> {
        let mut file = File::open(path)?;
        let mut hasher = Sha256::new();
        let mut buffer = [0u8; 65536]; // 64KB buffer

        loop {
            let bytes_read = file.read(&mut buffer)?;
            if bytes_read == 0 {
                break;
            }
            hasher.update(&buffer[..bytes_read]);
        }

        Ok(format!("{:x}", hasher.finalize()))
    }
}
