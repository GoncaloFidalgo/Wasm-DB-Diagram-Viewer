#![warn(clippy::all, rust_2018_idioms)]

mod app;
pub use app::TemplateApp;

#[cfg(target_arch = "wasm32")]
mod wasm {
    use super::TemplateApp;
    use wasm_bindgen::{self, prelude::*};
    use std::sync::{Arc, Mutex};

    #[wasm_bindgen(js_namespace = window)]
    extern "C" {
        fn saveDiagramState(json_data: &str);
        fn openSyncModal(json_data: &str);
    }

    #[wasm_bindgen]
    pub struct WebHandle {
        runner: eframe::WebRunner,
        shared_json_state: Arc<Mutex<String>>,
        save_trigger: Arc<Mutex<bool>>,
        update_json: Arc<Mutex<Option<String>>>,
        update_read_only: Arc<Mutex<Option<bool>>>,
        export_trigger: Arc<Mutex<bool>>,
        sync_trigger: Arc<Mutex<bool>>,
        egui_ctx: Arc<Mutex<Option<egui::Context>>>,
    }

    #[wasm_bindgen]
    impl WebHandle {
        #[wasm_bindgen(constructor)]
        pub fn new() -> Self {
            Self {
                runner: eframe::WebRunner::new(),
                shared_json_state: Arc::new(Mutex::new(String::from("{}"))),
                save_trigger: Arc::new(Mutex::new(false)),
                update_json: Arc::new(Mutex::new(None)),
                update_read_only: Arc::new(Mutex::new(None)),
                export_trigger: Arc::new(Mutex::new(false)),
                sync_trigger: Arc::new(Mutex::new(false)),
                egui_ctx: Arc::new(Mutex::new(None)),
            }
        }
        #[wasm_bindgen]
        pub fn trigger_save(&self) {
            if let Ok(mut flag) = self.save_trigger.lock() {
                *flag = true;
            }
        }
        #[wasm_bindgen]
        pub fn trigger_export(&self) {
            if let Ok(mut flag) = self.export_trigger.lock() {
                *flag = true;
            }
        }
        #[wasm_bindgen]
        pub fn trigger_sync(&self) {
            if let Ok(mut flag) = self.sync_trigger.lock() {
                *flag = true;
            }
        }

        #[wasm_bindgen]
        pub async fn start(&self, canvas: web_sys::HtmlCanvasElement, read_only: bool) -> Result<(), wasm_bindgen::JsValue> {
            eframe::WebLogger::init(log::LevelFilter::Debug).ok();

            let web_options = eframe::WebOptions::default();

            let state_clone = Arc::clone(&self.shared_json_state);

            let save_trigger_clone = Arc::clone(&self.save_trigger);
            let export_trigger_clone = Arc::clone(&self.export_trigger);
            let sync_trigger_clone = Arc::clone(&self.sync_trigger);

            let update_json_clone = Arc::clone(&self.update_json);
            let ctx_clone = Arc::clone(&self.egui_ctx);
            let update_read_only_clone = Arc::clone(&self.update_read_only);

            self.runner
                .start(
                    canvas,
                    web_options,
                    Box::new(move |cc| {
                        if let Ok(mut ctx_lock) = ctx_clone.lock() {
                            *ctx_lock = Some(cc.egui_ctx.clone());
                        }

                        let json_data = state_clone.lock().unwrap().clone();
                        Ok(Box::new(TemplateApp::new(
                            cc,
                            json_data,
                            save_trigger_clone,
                            read_only,
                            update_json_clone,
                            update_read_only_clone,
                            export_trigger_clone,
                            sync_trigger_clone,
                        )))
                    }),
                )
                .await
        }

        // Carregar dados com o Laravel
        #[wasm_bindgen]
        pub fn load_data(&self, json_data: &str) {
            // Atualiza o estado inicial (caso ainda não tenha arrancado)
            if let Ok(mut state) = self.shared_json_state.lock() {
                *state = json_data.to_string();
            }
            // Coloca o novo JSON no update ser atualizado na app que está a correr
            if let Ok(mut update) = self.update_json.lock() {
                *update = Some(json_data.to_string());
            }
            // Força a desenhar os novos dados
            if let Ok(ctx_lock) = self.egui_ctx.lock() {
                if let Some(ctx) = ctx_lock.as_ref() {
                    ctx.request_repaint();
                }
            }
        }
        // Alterar valor do read_only
        #[wasm_bindgen]
        pub fn set_read_only(&self, read_only: bool) {
            // Coloca o read_only no update para ser atualizado na app que está a correr
            if let Ok(mut update) = self.update_read_only.lock() {
                *update = Some(read_only);
            }
            // Força a desenhar os novos dados
            if let Ok(ctx_lock) = self.egui_ctx.lock() {
                if let Some(ctx) = ctx_lock.as_ref() {
                    ctx.request_repaint();
                }
            }
        }

    }
}