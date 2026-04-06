#![warn(clippy::all, rust_2018_idioms)]

mod app;
pub use app::TemplateApp;

#[cfg(target_arch = "wasm32")]
mod wasm {
    use super::TemplateApp;
    use wasm_bindgen::{self, prelude::*};
    use std::sync::{Arc, Mutex};

    #[wasm_bindgen]
    pub struct WebHandle {
        runner: eframe::WebRunner,
        shared_json_state: Arc<Mutex<String>>,
    }

    #[wasm_bindgen]
    impl WebHandle {
        #[wasm_bindgen(constructor)]
        pub fn new() -> Self {
            Self {
                runner: eframe::WebRunner::new(),
                shared_json_state: Arc::new(Mutex::new(String::from("{}"))),
            }
        }

        #[wasm_bindgen]
        pub async fn start(&self, canvas: web_sys::HtmlCanvasElement) -> Result<(), wasm_bindgen::JsValue> {
            eframe::WebLogger::init(log::LevelFilter::Debug).ok();

            let web_options = eframe::WebOptions::default();

            let state_clone = Arc::clone(&self.shared_json_state);

            self.runner
                .start(
                    canvas,
                    web_options,
                    Box::new(move |cc| {
                        let json_data = state_clone.lock().unwrap().clone();
                        Ok(Box::new(TemplateApp::new(cc, json_data)))
                    }),
                )
                .await
        }

        // Carregar dados com o Laravel
        #[wasm_bindgen]
        pub fn load_data(&self, json_data: &str) {
            if let Ok(mut state) = self.shared_json_state.lock() {
                *state = json_data.to_string();
            }
        }
    }
}