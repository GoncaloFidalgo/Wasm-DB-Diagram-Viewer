use egui::{util::undoer::Undoer, *};
use emath::*;
use std::sync::{Arc, Mutex};

#[cfg(target_arch = "wasm32")]
use wasm_bindgen::prelude::*;

#[cfg(target_arch = "wasm32")]
#[wasm_bindgen]
extern "C" {
    #[wasm_bindgen(js_namespace = window)]
    fn saveDiagramState(json_data: &str);

    #[wasm_bindgen(js_namespace = window)]
    fn savePixelsAsPng(width: u32, height: u32, pixels: &[u8]);

    #[wasm_bindgen(js_namespace = window)]
    fn openSyncModal(json_data: &str);

    #[wasm_bindgen(js_namespace = window)]
    fn downloadTextFile(content: &str);

}

// --- Estruturas ---

/// We derive Deserialize/Serialize so we can persist app state on shutdown.
//Default: Preenche com os valores por defeito se não receber do laravel, se for necessário valores diferentes dos default, implementar o Default com as traits
#[derive(serde::Deserialize, serde::Serialize)]
#[serde(default)]
pub struct TemplateApp {
    pub tables: Vec<Table>,
    pub relations: Vec<Relation>,
    #[serde(skip)] // Ignora este campo ao guardar/ler o JSON
    pub schema_loaded: bool,
    #[serde(skip)]
    pub save_trigger: Arc<Mutex<bool>>,
    #[serde(skip)]
    pub read_only: bool,
    #[serde(skip)]
    pub update_json: Arc<Mutex<Option<String>>>,
    #[serde(skip)]
    pub update_read_only: Arc<Mutex<Option<bool>>>,
    #[serde(skip)]
    pub selected: Vec<Selected>,
    #[serde(skip)]
    pub export_trigger: Arc<Mutex<bool>>,
    #[serde(skip)]
    pub exporting: bool,
    #[serde(skip)]
    pub sync_trigger: Arc<Mutex<bool>>,
    #[serde(skip)]
    pub undoer: Undoer<AppState>,
    #[serde(skip)]
    pub app_state: AppState,
    #[serde(skip)]
    pub options_menu: OptionsMenu,
    pub scene_transform: TSTransform,
    #[serde(skip)]
    pub txt_export_trigger: Arc<Mutex<bool>>,
}
#[derive(PartialEq, Clone)]
pub struct AppState {
    pub tables: Vec<Table>,
    pub relations: Vec<Relation>,
}
pub struct OptionsMenu {
    pub cardinality_display: CardinalityDisplay,
    pub description_indicator: DescriptionIndicator,
    pub search_table: String,
}
#[derive(PartialEq, Clone, Copy)]
pub enum CardinalityDisplay {
    Never,
    SelectedOnly,
    Always,
}
#[derive(PartialEq, Clone, Copy)]
pub enum DescriptionIndicator {
    None,
    Missing,
    Existing,
}
#[derive(PartialEq)]
pub enum Selected {
    Table { table: usize, column: Option<usize> },
    Relation { relation: usize, segment: Option<usize> },
}
fn toggle_selected(selected: &mut Vec<Selected>, item: Selected, rela_len: usize, _read_only: bool) {
    match item {
        Selected::Relation { relation, segment } => {
            let rela_idx = relation;
            match segment {
                None => {
                    if selected.contains(&item) {
                        selected.retain(|s| s != &item);
                    } else {
                        selected.retain(|s| {
                            !matches!(s,
                                Selected::Relation { relation, segment: Some(_) }
                                if *relation == rela_idx
                            )
                        });

                        selected.push(item);

                    }
                },
                Some(seg_idx) => {
                    if selected.contains(&Selected::Relation { relation: rela_idx, segment: None }) {
                        selected.retain(|s| !(s == &Selected::Relation { relation: rela_idx, segment: None }));
                        if rela_len == 0 {selected.push(Selected::Relation { relation: rela_idx, segment: Some(seg_idx) });} else {
                            for selected_segment_idx in 0..rela_len {
                                selected.push(Selected::Relation { relation: rela_idx, segment: Some(selected_segment_idx) });
                            }
                        }
                    }

                    if selected.contains(&item) {
                        selected.retain(|s| s != &item);
                    } else {
                        selected.push(item);

                    }

                    let selected_segments = selected.iter().filter(|s| {
                        matches!(s,
                            Selected::Relation { relation, segment: Some(_) }
                            if *relation == rela_idx
                        )
                    }).count();

                    if selected_segments >= 1 && selected_segments >= rela_len {
                        selected.retain(|s| {
                            !matches!(s,
                                Selected::Relation { relation, segment: Some(_) }
                                if *relation == rela_idx
                            )
                        });

                        selected.push(Selected::Relation { relation: rela_idx, segment: None });
                    }
                }
            }
        },
        Selected::Table { table, .. } => {
            for select in selected.iter_mut() {
                match select {
                    Selected::Relation { .. } => {}
                    Selected::Table { column, .. } => {
                        *column = None;
                    }
                }
            }
            if selected.contains(&item) {
                selected.retain(|s| s != &item);
            } else {
                selected.retain(|s| s != &Selected::Table { table, column: None });
                selected.push(item);

            }
        }
    }

    #[cfg(target_arch = "wasm32")]
    {
        if let Some(window) = web_sys::window() && !_read_only {
            let _ = js_sys::Reflect::set(
                &window,
                &wasm_bindgen::JsValue::from_str("hasUnsavedChanges"),
                &wasm_bindgen::JsValue::from_bool(true),
            );
        }
    }
}

impl Default for TemplateApp {
    fn default() -> Self {
        Self {
            tables: vec![
                Table {
                    name: String::from("primeira"),
                    pos: pos2(200.0, 200.0),
                    columns: vec![
                        Column {
                            name: String::from("id"),
                            column_type: String::from("NUMBER"),
                            nullable: false,
                            unique: false,
                            key_type: String::from("PK"),
                            description: String::new(),
                        },
                        Column {
                            name: String::from("name"),
                            column_type: String::from("VARCHAR2"),
                            nullable: false,
                            unique: false,
                            key_type: String::new(),
                            description: String::new(),
                        },
                    ],
                    description: String::new(),
                },
                Table {
                    name: String::from("segunda"),
                    pos: pos2(700.0, 300.0),
                    columns: vec![
                        Column {
                            name: String::from("id"),
                            column_type: String::from("NUMBER"),
                            nullable: false,
                            unique: false,
                            key_type: String::from("PK"),
                            description: String::new(),
                        },
                        Column {
                            name: String::from("primeira_id"),
                            column_type: String::from("NUMBER"),
                            nullable: true,
                            unique: true,
                            key_type: String::from("FK"),
                            description: String::new(),
                        },
                        Column {
                            name: String::from("terceira_id"),
                            column_type: String::from("NUMBER"),
                            nullable: false,
                            unique: true,
                            key_type: String::from("FK"),
                            description: String::new(),
                        },
                    ],
                    description: String::new(),
                },
                Table {
                    name: String::from("terceira"),
                    pos: pos2(300.0, 400.0),
                    columns: vec![
                        Column {
                            name: String::from("id"),
                            column_type: String::from("NUMBER"),
                            nullable: false,
                            unique: false,
                            key_type: String::from("PK"),
                            description: String::new(),
                        },
                        Column {
                            name: String::from("idade"),
                            column_type: String::from("NUMBER"),
                            nullable: true,
                            unique: false,
                            key_type: String::new(),
                            description: String::new(),
                        },
                    ],
                    description: String::new(),
                },
                Table {
                    name: String::from("quarta"),
                    pos: pos2(500.0, 600.0),
                    columns: vec![
                        Column {
                            name: String::from("id"),
                            column_type: String::from("NUMBER"),
                            nullable: false,
                            unique: false,
                            key_type: String::from("PK"),
                            description: String::new(),
                        },
                        Column {
                            name: String::from("valor"),
                            column_type: String::from("NUMBER"),
                            nullable: true,
                            unique: false,
                            key_type: String::new(),
                            description: String::new(),
                        },
                    ],
                    description: String::new(),
                },
            ],
            relations: vec![
                Relation {
                    name: String::new(),
                    relation_segments: vec![450.0],
                    tables: [1, 0],
                    columns: [1, 0],
                    description: String::new(),
                },
                Relation {
                    name: String::new(),
                    relation_segments: vec![],
                    tables: [1, 2],
                    columns: [2, 0],
                    description: String::new(),
                },
                Relation {
                    name: String::new(),
                    relation_segments: vec![],
                    tables: [2, 3],
                    columns: [0, 0],
                    description: String::new(),
                },
                Relation {
                    name: String::new(),
                    relation_segments: vec![],
                    tables: [1, 2],
                    columns: [2, 1],
                    description: String::new(),
                },
                Relation {
                    name: String::new(),
                    relation_segments: vec![],
                    tables: [2, 3],
                    columns: [1, 0],
                    description: String::new(),
                },
                Relation {
                    name: String::new(),
                    relation_segments: vec![],
                    tables: [3, 2],
                    columns: [1, 1],
                    description: String::new(),
                },
            ],
            schema_loaded: false,
            selected: Vec::new(),
            save_trigger: Arc::new(Mutex::new(false)),
            read_only: false,
            update_json: Arc::new(Mutex::new(None)),
            update_read_only: Arc::new(Mutex::new(None)),
            export_trigger: Arc::new(Mutex::new(false)),
            exporting: false,
            sync_trigger: Arc::new(Mutex::new(false)),
            undoer: Undoer::default(),
            app_state: AppState {
                tables: Vec::new(),
                relations: Vec::new(),
            },
            options_menu: OptionsMenu {
                cardinality_display: CardinalityDisplay::SelectedOnly,
                description_indicator: DescriptionIndicator::None,
                search_table: String::new(),
            },
            scene_transform: TSTransform::IDENTITY,
            txt_export_trigger: Arc::new(Mutex::new(false)),
        }
    }
}
#[derive(serde::Deserialize, serde::Serialize, Default, PartialEq, Clone)]
#[serde(default)]
pub struct Table {
    pub name: String,
    pub pos: Pos2,
    pub columns: Vec<Column>,
    pub description: String,
}
#[derive(serde::Deserialize, serde::Serialize, Default, PartialEq, Clone)]
#[serde(default)]
pub struct Column {
    pub name: String,
    pub column_type: String,
    pub nullable: bool,
    pub unique: bool,
    pub description: String,
    pub key_type: String,
}

#[derive(serde::Deserialize, serde::Serialize, Default, PartialEq, Clone)]
#[serde(default)]
pub struct Relation {
    pub name: String,
    pub relation_segments: Vec<f32>, // If empty, auto align
    pub tables: [usize; 2],
    pub columns: [usize; 2],
    pub description: String,
}

// Constantes para definir cores

const PRIMARY_KEY: Color32 = Color32::from_rgb(220, 180, 50); // Dourado
const FOREIGN_KEY: Color32 = Color32::from_rgb(100, 150, 220); // Azul escuro
const TABLE_BG: Color32 = Color32::from_rgb(22, 22, 25);
const TABLE_BORDER: Color32 = Color32::from_gray(55);
const HEADER_BG: Color32 = Color32::from_rgb(28, 28, 33);
const HEADER_HOVER: Color32 = Color32::from_rgb(38, 38, 46);
const HEADER_TEXT: Color32 = Color32::from_rgb(240, 240, 240);
const COL_NAME: Color32 = Color32::from_rgb(228, 228, 228);
const COL_TYPE: Color32 = Color32::from_gray(140);
const NULL_TEXT: Color32 = Color32::from_rgb(155, 155, 175);
const NULL_BG: Color32 = Color32::from_rgb(40, 40, 52);

// Constantes para definir tamanhos dos elementos das tabelas
const HEADER_SIZE: f32 = 40.0;
const COL_SIZE: f32 = 36.0;

// Constantes para definir tamanhos dos elementos das relacoes
const NOTATION_SIZE: f32 = 6.0;
const TABLE_PROXIMITY_LIMIT: f32 = 20.0;

// --- Implementações das estruturas ---

impl Table {
    pub fn ui(
        &mut self,
        ctx: &Context,
        id: usize,
        mut scene_transform: &mut TSTransform,
        read_only: bool,
        selected: &mut Vec<Selected>,
        relations: &Vec<Relation>,
        description_indicator: DescriptionIndicator,
    ) -> (Vec2, Option<usize>) {
        let table_width = ctx.fonts_mut(|f| {
            let header_width = 30.0 + f
                .layout_no_wrap(self.name.clone(), FontId::proportional(18.0), HEADER_TEXT)
                .rect
                .width();

            let mut max_col_width: f32 = 0.0;

            for col in &self.columns {
                // Lado esquerdo primeiro
                let mut total = 12.0;
                // PK/FP
                if !col.key_type.is_empty() {
                    let key_w = f
                        .layout_no_wrap(col.key_type.clone(), FontId::proportional(11.0), Color32::BLACK)
                        .rect
                        .width();
                    total += key_w + 6.0;
                }
                // Nome da coluna
                let name_w = f
                    .layout_no_wrap(col.name.clone(), FontId::proportional(13.0), Color32::BLACK)
                    .rect
                    .width();
                total += name_w;

                // Deixar sempre espaço para a bola vermelha se necessario (diametro + spacing)
                total += 8.0 + 6.0;

                // Lado direito depois
                total += 10.0;
                // Tipo de dados
                let type_w = f
                    .layout_no_wrap(col.column_type.clone(), FontId::proportional(11.5), Color32::BLACK)
                    .rect
                    .width();
                total += type_w + 6.0;

                // NULLABLE
                if col.nullable {
                    // Pad + null size
                    let null_w = 8.0 + f
                        .layout_no_wrap("NULL".to_owned(), FontId::monospace(10.0), Color32::BLACK)
                        .rect
                        .width();
                    total += null_w + 6.0;
                }

                // UNIQUE
                if col.nullable {
                    // Pad + null size
                    let unique_w = 8.0 + f
                        .layout_no_wrap("UNIQUE".to_owned(), FontId::monospace(10.0), Color32::BLACK)
                        .rect
                        .width();
                    total += unique_w + 6.0;
                }

                max_col_width = max_col_width.max(total);
            }

            300.0_f32.max(header_width).max(max_col_width)
        });

        let mut delta_used = Vec2::ZERO;
        let mut drag_stopped_on = None;

        let mut table_selected = false;
        let mut column_selected = None;
        let mut highlight_table = false;
        let mut highlight_columns: Vec<usize> = Vec::new();
        for select in selected.iter() {
            match select {
                Selected::Relation { relation, .. } => {
                    if relations[*relation].tables[0] == id || relations[*relation].tables[1] == id {
                        highlight_table = true;
                        let col_idx = if relations[*relation].tables[0] == id {relations[*relation].columns[0]} else {relations[*relation].columns[1]};
                        if !highlight_columns.contains(&col_idx) {
                            highlight_columns.push(col_idx);
                        }
                    }
                }
                Selected::Table { table, column } => {
                    if let Some(column) = *column {
                        for relation in relations {
                            if relation.tables[0] == *table && relation.columns[0] == column {
                                if relation.tables[1] == id {
                                    highlight_table = true;
                                    let col_idx = relation.columns[1];
                                    if !highlight_columns.contains(&col_idx) {
                                        highlight_columns.push(col_idx);
                                    }
                                }
                            } else if relation.tables[1] == *table && relation.columns[1] == column {
                                if relation.tables[0] == id {
                                    highlight_table = true;
                                    let col_idx = relation.columns[0];
                                    if !highlight_columns.contains(&col_idx) {
                                        highlight_columns.push(col_idx);
                                    }
                                }
                            }
                        }
                    }
                    if *table == id {
                        table_selected = true;
                        column_selected = *column;
                    }
                }
            }
        }
        Area::new(Id::new((&self.name, id)))
            .pivot(Align2::CENTER_CENTER)
            .constrain(false)
            .fixed_pos(self.pos)
            .show(ctx, |ui| {
                ctx.set_transform_layer(ui.layer_id(), *scene_transform);
                ui.set_clip_rect(Rect::EVERYTHING);
                Frame::new()
                    .fill(TABLE_BG)
                    .stroke(Stroke::new(2.0, if table_selected {Color32::BLUE} else if highlight_table {Color32::GREEN} else {TABLE_BORDER}))
                    .shadow(Shadow {
                        offset: [0, 6],
                        blur: 18,
                        spread: 0,
                        color: Color32::from_black_alpha(90),
                    }).show(ui, |ui| {
                        let mut area_response = ui.allocate_ui(Vec2::ZERO, |ui| {
                            ui.spacing_mut().item_spacing = Vec2::ZERO;
                            if self.header_ui(ui, table_width, description_indicator).clicked() {
                                if !ctx.input(|i| {i.modifiers.command_only()}) || read_only {selected.clear();}
                                toggle_selected(selected, Selected::Table { table: id, column: None }, 0, read_only);
                            }
                            ui.add_space(2.0);
                            for (col_idx, column) in self.columns.iter().enumerate() {
                                if column.ui(ui, table_width, id, col_idx, match column_selected {None => {false} Some(idx) => {idx == col_idx}}, highlight_columns.contains(&col_idx), description_indicator).clicked()  {
                                    if !ctx.input(|i| {i.modifiers.command_only()}) || read_only {
                                        selected.clear();
                                        toggle_selected(selected, Selected::Table { table: id, column: Some(col_idx) }, 0, read_only);
                                    } else {
                                        toggle_selected(selected, Selected::Table { table: id, column: None }, 0, read_only);
                                    }
                                }
                            }
                            ui.add_space(6.0);
                        }).response.interact(Sense::click_and_drag());

                        if area_response.clicked() {
                            if !ctx.input(|i| {i.modifiers.command_only()}) || read_only {selected.clear();}
                            toggle_selected(selected, Selected::Table { table: id, column: None }, 0, read_only);
                        }

                        if !read_only {
                            if area_response.drag_started() {
                                for select in selected.iter_mut() {
                                    match select {
                                        Selected::Relation { .. } => {}
                                        Selected::Table { column, .. } => {
                                            *column = None;
                                        }
                                    }
                                }
                                let item = Selected::Table { table: id, column: None };
                                if !selected.contains(&item) {
                                    if !ui.input(|i| {i.modifiers.command_only()}) {selected.clear();}
                                    toggle_selected(selected, item, 0, read_only);
                                } else {
                                    toggle_selected(selected, item, 0, read_only);
                                    toggle_selected(selected, Selected::Table { table: id, column: None }, 0, read_only);
                                }
                            }
                            if area_response.dragged() {
                                delta_used = area_response.drag_delta();
                                ctx.output_mut(|o| o.cursor_icon = CursorIcon::Grabbing);
                            }
                            if area_response.drag_stopped() {
                                drag_stopped_on = Some(id);
                            }
                        }
                        Scene::new()
                            .drag_pan_buttons(if read_only {DragPanButtons::all()} else {DragPanButtons::empty()})
                            .zoom_range(Rangef::new(0.1, 2.0))
                            .register_pan_and_zoom(ui, &mut area_response, &mut scene_transform);
                    });
            });

        return (delta_used, drag_stopped_on);
    }

    fn header_ui(&mut self, ui: &mut Ui, table_width: f32, description_indicator: DescriptionIndicator) -> Response {
        let (rect, response) = ui.allocate_exact_size(
            vec2(table_width, HEADER_SIZE),
            Sense::click(),
        );

        if response.hovered() { ui.output_mut(|o| o.cursor_icon = CursorIcon::PointingHand); }

        let bg = if response.hovered() {
            HEADER_HOVER
        } else {
            HEADER_BG
        };
        ui.painter().rect_filled(rect, CornerRadius::ZERO, bg);
        ui.painter().text(
            rect.center(),
            Align2::CENTER_CENTER,
            &self.name,
            FontId::proportional(18.0),
            HEADER_TEXT,
        );
        match description_indicator {
            DescriptionIndicator::None => {}
            DescriptionIndicator::Missing => {
                if self.description.is_empty() {
                    ui.painter().circle_filled(pos2(rect.right() - 10.0, rect.center().y), 4.0, Color32::RED);
                }
            }
            DescriptionIndicator::Existing => {
                if !self.description.is_empty() {
                    ui.painter().circle_filled(pos2(rect.right() - 10.0, rect.center().y), 4.0, Color32::RED);
                }
            }
        }
        response
    }
}

impl Column {
    fn ui(&self, ui: &mut Ui, table_width: f32, table_id: usize, col_id: usize, col_selected: bool, highlight_for_relation: bool, description_indicator: DescriptionIndicator) -> Response {
        let (rect, response) = ui.allocate_exact_size(
            vec2(table_width, COL_SIZE),
            Sense::click(),
        );

        let column_rect_id = Id::new(("column_rect", table_id, col_id));
        let column_rect_relation_types_new_left_id = Id::new(("column_relation_types_new", true, table_id, col_id));
        let column_rect_relation_types_new_right_id = Id::new(("column_relation_types_new", false, table_id, col_id));
        ui.ctx().data_mut(|data| {
            data.insert_temp(column_rect_id, rect);
            data.insert_temp(column_rect_relation_types_new_left_id, [false, false, false]);
            data.insert_temp(column_rect_relation_types_new_right_id, [false, false, false]);
        });

        if col_selected {
            ui.painter().rect_filled(rect, CornerRadius::ZERO, Color32::from_rgb_additive(0, 0, 60));
        } else if highlight_for_relation {
            ui.painter().rect_filled(rect, CornerRadius::ZERO, Color32::from_rgb(0, 60, 0));
        }

        if response.hovered() {
            ui.painter().rect_filled(
                rect.shrink2(vec2(6.0, 1.0)),
                CornerRadius::same(4),
                Color32::from_rgba_unmultiplied(255, 255, 255, 7),
            );
            ui.output_mut(|o| o.cursor_icon = CursorIcon::PointingHand);
        }

        let painter = ui.painter();

        // Desenhar a chave ao lado do nome da coluna
        let mut left_x = rect.left() + 12.0;

        if !self.key_type.is_empty() {
            let key_color = if self.key_type == "PK" {
                PRIMARY_KEY
            } else {
                FOREIGN_KEY
            };

            let key_galley = painter.layout_no_wrap(
                self.key_type.clone(),
                FontId::proportional(11.0),
                key_color,
            );

            painter.galley(
                pos2(left_x, rect.center().y - key_galley.rect.height() * 0.5),
                key_galley.clone(),
                key_color,
            );

            // Ajustar o nome da coluna para a direita
            left_x += key_galley.rect.width() + 6.0;
        }

        // Nome do campo
        let name_galley = painter.layout_no_wrap(
            self.name.clone(),
            FontId::proportional(13.0),
            COL_NAME,
        );

        painter.galley(
            pos2(left_x, rect.center().y - name_galley.rect.height() * 0.5),
            name_galley.clone(),
            COL_NAME,
        );
        left_x += name_galley.rect.width() + 6.0;

        // Desenha bola vermelha se necessario
        match description_indicator {
            DescriptionIndicator::None => {}
            DescriptionIndicator::Missing => {
                if self.description.is_empty() {
                    painter.circle_filled(pos2(4.0 + left_x, rect.center().y), 4.0, Color32::RED);
                }
            }
            DescriptionIndicator::Existing => {
                if !self.description.is_empty() {
                    painter.circle_filled(pos2(4.0 + left_x, rect.center().y), 4.0, Color32::RED);
                }
            }
        }

        let mut right_x = rect.right() - 10.0;

        // Tipo do campo
        let type_galley = painter.layout_no_wrap(
            self.column_type.clone(),
            FontId::proportional(11.5),
            COL_TYPE,
        );

        let type_y = rect.center().y - type_galley.rect.height() * 0.5;
        painter.galley(
            pos2(right_x - type_galley.rect.width(), type_y),
            type_galley.clone(),
            COL_TYPE,
        );
        right_x -= type_galley.rect.width() + 6.0;

        if self.nullable {
            let null_galley =
                painter.layout_no_wrap("NULL".to_owned(), FontId::monospace(10.0), NULL_TEXT);
            let pad = vec2(4.0, 2.0);
            let badge_size = null_galley.rect.size() + pad * 2.0;
            let badge_rect = Rect::from_min_size(
                pos2(right_x - badge_size.x, rect.center().y - badge_size.y * 0.5),
                badge_size,
            );
            painter.rect_filled(badge_rect, CornerRadius::same(3), NULL_BG);
            painter.galley(badge_rect.min + pad, null_galley, NULL_TEXT);
            right_x -= badge_size.x + 6.0;
        }

        if self.unique {
            let unique_galley =
                painter.layout_no_wrap("UNIQUE".to_owned(), FontId::monospace(10.0), NULL_TEXT);
            let pad = vec2(4.0, 2.0);
            let badge_size = unique_galley.rect.size() + pad * 2.0;
            let badge_rect = Rect::from_min_size(
                pos2(right_x - badge_size.x, rect.center().y - badge_size.y * 0.5),
                badge_size,
            );
            painter.rect_filled(badge_rect, CornerRadius::same(3), NULL_BG);
            painter.galley(badge_rect.min + pad, unique_galley, NULL_TEXT);
        }
        response
    }
}
impl TemplateApp {
    /// Called once before the first frame.
    pub fn new(
        cc: &eframe::CreationContext<'_>,
        json_data: String,
        save_trigger_clone: Arc<Mutex<bool>>,
        read_only: bool,
        update_json: Arc<Mutex<Option<String>>>,
        update_read_only: Arc<Mutex<Option<bool>>>,
        export_trigger_clone: Arc<Mutex<bool>>,
        sync_trigger_clone: Arc<Mutex<bool>>,
        txt_export_trigger_clone: Arc<Mutex<bool>>,
    ) -> Self {
        let mut app: TemplateApp = if let Some(storage) = cc.storage {
            eframe::get_value(storage, eframe::APP_KEY).unwrap_or_default()
        } else {
            Self::default()
        };

        if !json_data.is_empty() && json_data.as_str() != "{}" {
            match serde_json::from_str::<TemplateApp>(&json_data) {
                Ok(mut app_state) => {
                    app_state.apply_auto_layout();
                    app_state.schema_loaded = true;
                    app_state.save_trigger = save_trigger_clone.clone();
                    app_state.read_only = read_only;
                    app_state.update_json = update_json.clone();
                    app_state.update_read_only = update_read_only.clone();
                    app_state.export_trigger = export_trigger_clone.clone();
                    app_state.sync_trigger = sync_trigger_clone.clone();
                    app_state.txt_export_trigger = txt_export_trigger_clone.clone();
                    app_state.undoer = Undoer::default();
                    app_state.app_state = AppState { tables: app_state.tables.clone(), relations: app_state.relations.clone() };
                    return app_state;
                }
                Err(e) => log::error!("Failed to parse JSON: {}", e),
            }
        }

        app.apply_auto_layout();
        app.save_trigger = save_trigger_clone;
        app.export_trigger = export_trigger_clone;
        app.sync_trigger = sync_trigger_clone;
        app.txt_export_trigger = txt_export_trigger_clone;
        app.undoer = Undoer::default();
        app.app_state = AppState { tables: app.tables.clone(), relations: app.relations.clone() };
        app
    }

    // Cria o layout inicial
    fn apply_auto_layout(&mut self) {
        // Criar apenas se estiverem na posicao default (0.0)
        let needs_layout = self
            .tables
            .first()
            .map_or(false, |t| t.pos == pos2(0.0, 0.0));

        if needs_layout {
            let mut x = 200.0;
            let mut y = 200.0;
            for table in &mut self.tables {
                table.pos = pos2(x, y);
                x += 350.0;
                if x > 1200.0 {
                    x = 50.0;
                    y += 350.0;
                }
            }
        }
    }

    fn draw_zoom_area(&mut self, ctx: &Context, screen_rect: Rect, bg_response: Response) {
        Area::new(Id::new("DragValue_zoom"))
            .anchor(Align2::CENTER_TOP, vec2(0.0, 20.0))
            .order(Order::Foreground)
            .show(ctx, |ui| {
                Frame::default()
                    .fill(Color32::WHITE)
                    .stroke(Stroke::new(1.0, Color32::BLACK))
                    .corner_radius(5.0)
                    .inner_margin(8.0)
                    .show(ui, |ui| {

                        ui.visuals_mut().override_text_color = Some(Color32::BLACK);

                        let center_vec = bg_response.rect.center().to_vec2();
                        let old_scale = self.scene_transform.scaling;
                        let mut new_scale = old_scale;

                        ui.horizontal(|ui| {
                            if ui.button(" - ").clicked() {
                                new_scale = (new_scale - 0.1).max(0.1);
                            }

                            ui.add(Slider::new(&mut new_scale, 0.1 ..= 2.0)
                                .show_value(true)
                                .step_by(0.01));

                            if ui.button(" + ").clicked() {
                                new_scale = (new_scale + 0.1).min(5.0);
                            }
                        });

                        if old_scale != new_scale {
                            self.scene_transform.scaling = new_scale;
                            let world_vec = (center_vec - self.scene_transform.translation) / old_scale;
                            self.scene_transform.translation += world_vec * (old_scale - self.scene_transform.scaling);
                        }
                    });
            });
        
        Area::new(Id::new("CenterScreen_button"))
            .anchor(Align2::CENTER_TOP, vec2(-130.0, 20.0))
            .order(Order::Foreground)
            .show(ctx, |ui| {
                Frame::default()
                    .fill(Color32::WHITE)
                    .stroke(Stroke::new(1.0, Color32::BLACK))
                    .corner_radius(5.0)
                    .inner_margin(8.0)
                    .show(ui, |ui| {

                        ui.visuals_mut().override_text_color = Some(Color32::BLACK);

                        if ui.button("◎").clicked() {
                            center_screen(ctx, screen_rect, &mut self.scene_transform, &self.tables, &self.relations, false);
                        }
                    });
            });
    }

    fn draw_edit_window(&mut self, ctx: &Context, screen_rect: Rect) {
        Window::new("Inspector")
            .order(Order::Tooltip)
            .default_size(vec2(300.0, 400.0))
            .min_width(250.0)
            .show(ctx, |ui| {
                ui.collapsing(RichText::new("Diagram Options").strong().size(30.0), |ui| {
                    ui.label(RichText::new("Cardinality Display").strong().size(15.0));
                    ui.horizontal(|ui| {
                        ui.radio_value(&mut self.options_menu.cardinality_display, CardinalityDisplay::Always, "Always");
                        ui.radio_value(&mut self.options_menu.cardinality_display, CardinalityDisplay::SelectedOnly, "SelectedOnly");
                        ui.radio_value(&mut self.options_menu.cardinality_display, CardinalityDisplay::Never, "Never");
                    });

                    ui.label(RichText::new("Description Indicator").strong().size(15.0));
                    ui.horizontal(|ui| {
                        ui.radio_value(&mut self.options_menu.description_indicator, DescriptionIndicator::None, "None");
                        ui.radio_value(&mut self.options_menu.description_indicator, DescriptionIndicator::Missing, "Missing");
                        ui.radio_value(&mut self.options_menu.description_indicator, DescriptionIndicator::Existing, "Existing");
                    });

                    if !self.read_only {
                        ui.horizontal(|ui| {
                            ui.label(RichText::new("Undo Redo").strong().size(15.0));
                            ui.label(RichText::new("(CTRL + Z/CTRL + Y)").size(10.0));
                        });
                        let can_undo = self.undoer.has_undo(&self.app_state);
                        let can_redo = self.undoer.has_redo(&self.app_state);
                        ui.horizontal(|ui| {
                            let undo = ui.add_enabled(can_undo, Button::new("⟲ Undo")).clicked();
                            let redo = ui.add_enabled(can_redo, Button::new("⟳ Redo")).clicked();

                            if undo && let Some(undo_text) = self.undoer.undo(&self.app_state) {
                                self.app_state = undo_text.clone();
                            }
                            if redo && let Some(redo_text) = self.undoer.redo(&self.app_state) {
                                self.app_state = redo_text.clone();
                            }
                            if undo || redo {
                                self.tables = self.app_state.tables.clone();
                                self.relations = self.app_state.relations.clone();
                            }
                        });
                    }

                    ui.horizontal(|ui| {
                        ui.label(RichText::new("Search Table").strong().size(15.0));
                        ui.label(RichText::new("(Case Sensitive)").size(10.0));
                    });
                    ui.horizontal(|ui| {
                        let lost_focus = ui.text_edit_singleline(&mut self.options_menu.search_table).lost_focus() && ui.input(|i| i.key_pressed(egui::Key::Enter));
                        if ui.button(RichText::new("Search").strong()).clicked() || lost_focus {
                            for table in self.tables.iter() {
                                if table.name == self.options_menu.search_table {
                                    self.scene_transform.translation = screen_rect.center().to_vec2() - (table.pos.to_vec2() * self.scene_transform.scaling);
                                }
                            }
                        }
                    });
                });

                ui.separator();
                
                ui.collapsing(RichText::new("Statistics").strong().size(30.0), |ui| {
                    let total_tables = self.tables.len();
                    let total_relations = self.relations.len();
                    let mut total_columns = 0;
                    let mut tables_with_desc = 0;
                    let mut relations_with_desc = 0;
                    let mut columns_with_desc = 0;
                    for table in self.tables.iter() {
                        total_columns += table.columns.len();
                        if !table.description.is_empty() {
                            tables_with_desc += 1;
                        }
                        for column in table.columns.iter() {
                            if !column.description.is_empty() {
                                columns_with_desc += 1;
                            }
                        }
                    }
                    for relation in self.relations.iter() {
                        if !relation.description.is_empty() {
                            relations_with_desc += 1;
                        }
                    }
                    Grid::new("table_statistics_grid")
                        .num_columns(2)
                        .spacing([40.0, 8.0])
                        .show(ui, |ui| {
                            ui.label(RichText::new("Tables:").strong().size(16.5));
                            ui.label(RichText::new(total_tables.to_string()).size(16.5));
                            ui.end_row();

                            ui.label(RichText::new("Columns:").strong().size(16.5));
                            ui.label(RichText::new(total_columns.to_string()).size(16.5));
                            ui.end_row();

                            ui.label(RichText::new("Relations:").strong().size(16.5));
                            ui.label(RichText::new(total_relations.to_string()).size(16.5));
                            ui.end_row();

                            ui.end_row();

                            ui.label(RichText::new("Description:").strong().size(16.5));
                            ui.end_row();

                            ui.label(RichText::new("Tables:").strong().size(16.5));
                            ui.label(RichText::new(tables_with_desc.to_string()+"/"+&total_tables.to_string()).size(16.5));
                            ui.end_row();

                            ui.label(RichText::new("Columns:").strong().size(16.5));
                            ui.label(RichText::new(columns_with_desc.to_string()+"/"+&total_columns.to_string()).size(16.5));
                            ui.end_row();

                            ui.label(RichText::new("Relations:").strong().size(16.5));
                            ui.label(RichText::new(relations_with_desc.to_string()+"/"+&total_relations.to_string()).size(16.5));
                            ui.end_row();

                            ui.label(RichText::new("Documentation:").strong().size(16.5));
                            ui.label(RichText::new((100.0 * (tables_with_desc + columns_with_desc + relations_with_desc) as f32/(total_tables + total_columns + total_relations) as f32).floor().to_string()+"%").size(16.5));
                            ui.end_row();
                        });
                });

                ui.separator();

                CollapsingHeader::new(RichText::new("Selected Object").strong().size(30.0)).default_open(true).show(ui, |ui| {
                    ui.separator();
                    if let Some(selected) = self.selected.last() {
                        match selected {
                            Selected::Table { table, column } => {
                                match column {
                                    None => {
                                        let t = &mut self.tables[*table];

                                        // --- Table grid ---
                                        Grid::new("table_grid")
                                            .num_columns(2)
                                            .spacing([40.0, 8.0])
                                            .show(ui, |ui| {
                                                ui.label(RichText::new("Type").strong().size(16.5));
                                                ui.label(RichText::new("Table").size(16.5));
                                                ui.end_row();

                                                ui.label(RichText::new("Name").strong().size(16.5));
                                                ui.label(RichText::new(&t.name).size(16.5));
                                                ui.end_row();

                                                ui.label(RichText::new("Columns").strong().size(16.5));
                                                ui.label(RichText::new(t.columns.len().to_string()).size(16.5));
                                                ui.end_row();
                                            });

                                        ui.add_space(10.0);
                                        ui.separator();
                                        ui.add_space(5.0);

                                        // --- Description ---
                                        ui.label(RichText::new("Description:").size(19.0));
                                        ui.add_enabled_ui(!self.read_only, |ui| {
                                            ui.add_sized(
                                                ui.available_size(),
                                                TextEdit::multiline(&mut t.description)
                                                    .font(FontId::proportional(19.0))
                                            );
                                        });
                                    },
                                    Some(column_idx) => {
                                        // Save table name by clonning
                                        let table_name = self.tables[*table].name.clone();

                                        // Get the column without memory conflicts
                                        let c = &mut self.tables[*table].columns[*column_idx];

                                        // --- Column grid ---
                                        Grid::new("column_grid")
                                            .num_columns(2)
                                            .spacing([40.0, 8.0])
                                            .show(ui, |ui| {
                                                ui.label(RichText::new("Type").strong().size(16.5));
                                                ui.label(RichText::new("Column").size(16.5));
                                                ui.end_row();

                                                ui.label(RichText::new("Table").strong().size(16.5));
                                                ui.label(RichText::new(table_name).size(16.5));
                                                ui.end_row();

                                                ui.label(RichText::new("Name").strong().size(16.5));
                                                ui.label(RichText::new(&c.name).size(16.5));
                                                ui.end_row();

                                                ui.label(RichText::new("Data Type").strong().size(16.5));
                                                ui.label(RichText::new(&c.column_type).monospace().size(16.5).color(Color32::from_gray(120)));
                                                ui.end_row();

                                                ui.label(RichText::new("Key").strong().size(16.5));
                                                let (key_text, key_color) = match c.key_type.as_str() {
                                                    "PK" => ("Primary Key", Color32::from_rgb(255, 170, 0)),
                                                    "FK" => ("Foreign Key", Color32::from_rgb(100, 150, 255)),
                                                    _ => ("No key", Color32::from_gray(130)),
                                                };
                                                ui.label(RichText::new(key_text).color(key_color).size(16.5));
                                                ui.end_row();

                                                ui.label(RichText::new("Nullable").strong().size(16.5));
                                                let (null_text, null_color) = if c.nullable {
                                                    ("Yes", Color32::from_rgb(100, 160, 100))
                                                } else {
                                                    ("No", Color32::from_rgb(180, 85, 85))
                                                };
                                                ui.label(RichText::new(null_text).color(null_color).size(16.5));
                                                ui.end_row();

                                                ui.label(RichText::new("Unique").strong().size(16.5));
                                                let (null_text, null_color) = if c.unique {
                                                    ("Yes", Color32::from_rgb(100, 160, 100))
                                                } else {
                                                    ("No", Color32::from_rgb(180, 85, 85))
                                                };
                                                ui.label(RichText::new(null_text).color(null_color).size(16.5));
                                                ui.end_row();

                                            });

                                        ui.add_space(10.0);
                                        ui.separator();
                                        ui.add_space(5.0);

                                        // --- Description ---
                                        ui.label(RichText::new("Description:").size(19.0));
                                        ui.add_enabled_ui(!self.read_only, |ui| {
                                            ui.add_sized(
                                                ui.available_size(),
                                                TextEdit::multiline(&mut c.description)
                                                    .font(FontId::proportional(19.0))
                                            );
                                        });
                                    }
                                }
                            },
                            Selected::Relation { relation, .. } => {
                                let r = &mut self.relations[*relation];

                                // --- Relation grid ---
                                Grid::new("relation_grid")
                                    .num_columns(2)
                                    .spacing([40.0, 8.0])
                                    .show(ui, |ui| {
                                        ui.label(RichText::new("Type").strong().size(16.5));
                                        ui.label(RichText::new("Relation").size(16.5));
                                        ui.end_row();

                                        ui.label(RichText::new("Name").strong().size(16.5));
                                        ui.label(RichText::new(&r.name).size(16.5));
                                        ui.end_row();
                                    });

                                ui.add_space(10.0);
                                ui.separator();
                                ui.add_space(5.0);

                                // --- Description ---
                                ui.label(RichText::new("Description:").size(19.0));
                                ui.add_enabled_ui(!self.read_only, |ui| {
                                    ui.add_sized(
                                        ui.available_size(),
                                        TextEdit::multiline(&mut r.description)
                                            .font(FontId::proportional(19.0))
                                    );
                                });
                            }
                        }
                    } else {
                        // --- Empty state ---
                        ui.label(
                            RichText::new("No object selected.")
                                .size(20.0)
                                .strong()
                        );

                        ui.allocate_space(ui.available_size());
                    }
                });
            });
    }

    fn draw_relations(&mut self, ui: &mut Ui, painter: &Painter, scene_transform: TSTransform) {
        let line_width: f32 = 2.5 * scene_transform.scaling;
        let table_proximity_limit: f32 = TABLE_PROXIMITY_LIMIT * scene_transform.scaling;
        let notation_size: f32 = NOTATION_SIZE * scene_transform.scaling;
        let interact_hitbox_size: f32 = 14.0 * scene_transform.scaling;

        let mut diagram_interacted = false;
        let mut delta_used = Vec2::ZERO;
        let mut drag_stopped = false;

        for (rela_idx, relation) in self.relations.iter_mut().enumerate() {
            if self.selected.iter().filter(|s| {
                matches!(s,
                    Selected::Relation { relation, .. }
                    if *relation == rela_idx
                )
            }).count() >= 1 {
                Area::new(Id::new((rela_idx, "area")))
                    .default_size(ui.clip_rect().size())
                    .show(ui.ctx(), |ui| {
                        draw_interact_relation(ui, ui.painter(), scene_transform, &mut self.selected, self.tables[relation.tables[0]].columns[relation.columns[0]].unique, self.tables[relation.tables[0]].columns[relation.columns[0]].nullable, self.read_only, line_width, table_proximity_limit, notation_size, interact_hitbox_size, &mut delta_used, &mut drag_stopped, rela_idx, relation, &mut diagram_interacted, self.options_menu.cardinality_display);
                    });
            } else {
                draw_interact_relation(ui, painter, scene_transform, &mut self.selected, self.tables[relation.tables[0]].columns[relation.columns[0]].unique, self.tables[relation.tables[0]].columns[relation.columns[0]].nullable, self.read_only, line_width, table_proximity_limit, notation_size, interact_hitbox_size, &mut delta_used, &mut drag_stopped, rela_idx, relation, &mut diagram_interacted, self.options_menu.cardinality_display);
            }
        }

        if diagram_interacted {
            self.app_state.tables = self.tables.clone();
            self.app_state.relations = self.relations.clone();
        }

        if delta_used != Vec2::ZERO {
            move_all_selected(delta_used, &mut self.selected, &mut self.relations, &mut self.tables);
        }

        if drag_stopped {
            use_verify_in_selected(ui.ctx(), &mut self.selected, &mut self.relations, &self.tables, &mut self.app_state);
        }

        for (table_idx, table) in self.tables.iter().enumerate() {
            for (col_idx, _) in table.columns.iter().enumerate() {
                ui.ctx().data_mut(|data| {
                    data.insert_temp(Id::new(("column_relation_types_old", true, table_idx, col_idx)),
                    data.get_temp::<[bool; 3]>(Id::new(("column_relation_types_new", true, table_idx, col_idx))).unwrap());
                    data.insert_temp(Id::new(("column_relation_types_old", false, table_idx, col_idx)),
                    data.get_temp::<[bool; 3]>(Id::new(("column_relation_types_new", false, table_idx, col_idx))).unwrap());
                })
            }
        }
    }

    pub fn generate_text_export(&self) -> String {
        let mut report = String::from("");

        report.push_str("== TABELAS E RELAÇÕES ==\n");

        for (table_idx, table) in self.tables.iter().enumerate() {
            report.push_str(&format!("\nTabela: {}\n", table.name));

            let t_desc = if table.description.is_empty() { "" } else { &table.description };
            if !t_desc.is_empty() {
                report.push_str(&format!("Descrição: {}\n", t_desc));
            }

            report.push_str("Colunas:\n");

            for col in &table.columns {
                let null_str = if col.nullable { "NULL" } else { "NOT NULL" };

                let mut col_line = format!("  - {} | Tipo: {} | Restrição: {}",
                                           col.name, col.column_type, null_str
                );

                if !col.key_type.is_empty() {
                    col_line.push_str(&format!(" | Chave: {}", col.key_type));
                }

                if !col.description.is_empty() {
                    col_line.push_str(&format!(" | Descrição: {}", col.description));
                }

                col_line.push('\n');
                report.push_str(&col_line);
            }

            let mut table_relations = String::new();

            for rel in &self.relations {
                if rel.tables[0] == table_idx {
                    let target_table = &self.tables[rel.tables[1]];

                    let origin_col = &table.columns[rel.columns[0]];
                    let target_col = &target_table.columns[rel.columns[1]];

                    let is_nullable = origin_col.nullable;
                    let is_unique = origin_col.unique;

                    let min_card = if is_nullable { "0" } else { "1" };
                    let max_card = if is_unique { "1" } else { "N" };
                    let cardinalidade = format!("{}:{}", min_card, max_card);

                    let r_desc = if rel.description.is_empty() { "" } else { &rel.description };

                    table_relations.push_str(&format!(
                        "  - Relação: {}\n    Ligação: {}  ->  {}.{}\n    Cardinalidade: {}\n",
                        rel.name,
                        origin_col.name,
                        target_table.name, target_col.name,
                        cardinalidade,
                    ));

                    if !r_desc.is_empty() {
                        table_relations.push_str(&format!("    Descrição: {}\n", r_desc));
                    }
                    table_relations.push_str("\n");
                }
            }

            if !table_relations.is_empty() {
                report.push_str("Relações:\n");
                report.push_str(&table_relations);
            }
        }

        report
    }
}

fn draw_interact_relation(ui: &Ui, painter: &Painter, scene_transform: TSTransform, selected: &mut Vec<Selected>, unique: bool, nullable: bool, read_only: bool, line_width: f32, table_proximity_limit: f32, notation_size: f32, interact_hitbox_size: f32, delta_used: &mut Vec2, drag_stopped: &mut bool, rela_idx: usize, relation: &mut Relation, diagram_interacted: &mut bool, cardinality_display: CardinalityDisplay) {
    let mut highlight_relation = false;
    for select in selected.iter() {
        match select {
            Selected::Relation { .. } => {}
            Selected::Table { table, column } => {
                if let Some(column) = *column {
                    if relation.tables[0] == *table && relation.columns[0] == column {
                        highlight_relation = true;
                    } else if relation.tables[1] == *table && relation.columns[1] == column {
                        highlight_relation = true;
                    }
                }
            }
        }
    }
    
    let line_stroke = if selected.contains(&Selected::Relation { relation: rela_idx, segment: None }) {
        Stroke::new(line_width, Color32::BLUE)
    } else if highlight_relation {
        Stroke::new(line_width, Color32::GREEN)
    } else {
        Stroke::new(line_width, Color32::from_gray(80))
    };

    // Obter os retângulos para ligar a relação
    let (rect_a, rect_b) = ui.ctx().data(|data| {
        (
            data.get_temp::<Rect>(Id::new(("column_rect", relation.tables[0], relation.columns[0]))),
            data.get_temp::<Rect>(Id::new(("column_rect", relation.tables[1], relation.columns[1])))
        )
    });

    let (Some(rect_a), Some(rect_b)) = (rect_a, rect_b) else {
        return;
    };
    let rect_a = scene_transform.mul_rect(rect_a);
    let rect_b = scene_transform.mul_rect(rect_b);

    // Cálculos base para as posições
    let mut start = rect_a.center();
    let start_offset = rect_a.width() / 2.0;

    let mut end = rect_b.center();
    let end_offset = rect_b.width() / 2.0;

    let enough_space = (start.x - end.x).abs() > start_offset + end_offset + table_proximity_limit * 2.0;
    let auto_align = relation.relation_segments.is_empty();
    let x_align = (start.x + end.x) / 2.0;

    let start_goes_left = if auto_align {
        if enough_space {start.x > end.x} else {false}
    } else {
        (if enough_space {start.x} else {x_align}) > *relation.relation_segments.first().unwrap() * scene_transform.scaling + scene_transform.translation.x
    };
    let start_dir = if start_goes_left { -1.0 } else { 1.0 };

    let end_goes_left = if auto_align {
        if enough_space {end.x > start.x} else {false}
    } else {
        (if enough_space {end.x} else {x_align}) > *relation.relation_segments.last().unwrap() * scene_transform.scaling + scene_transform.translation.x
    };
    let end_dir = if end_goes_left { -1.0 } else { 1.0 };

    // Tipos existentes nas colunas [bool; 3] Multi, One, Zero
    let start_column_relation_types_new_id = Id::new(("column_relation_types_new", start_goes_left, relation.tables[0], relation.columns[0]));
    let end_column_relation_types_new_id = Id::new(("column_relation_types_new", end_goes_left, relation.tables[1], relation.columns[1]));
    let (start_relation_types_new, end_relation_types_new, start_relation_types_old, end_relation_types_old) = ui.ctx().data(|data| {
        (
            data.get_temp::<[bool; 3]>(start_column_relation_types_new_id),
            data.get_temp::<[bool; 3]>(end_column_relation_types_new_id),
            data.get_temp::<[bool; 3]>(Id::new(("column_relation_types_old", start_goes_left, relation.tables[0], relation.columns[0]))),
            data.get_temp::<[bool; 3]>(Id::new(("column_relation_types_old", end_goes_left, relation.tables[1], relation.columns[1])))
        )
    });

    let (Some(mut start_relation_types_new), Some(mut end_relation_types_new)) = (start_relation_types_new, end_relation_types_new) else {
        return;
    };

    let start_relation_types_old = match start_relation_types_old {
        None => [false, false, false],
        Some(relation_types) => relation_types
    };
    let end_relation_types_old = match end_relation_types_old {
        None => [false, false, false],
        Some(relation_types) => relation_types
    };

    let adjust_start_y = if unique {
        start_relation_types_new[1] = true;
        notation_size *
        -start_dir *
        if start_relation_types_old[0] && start_relation_types_old[2] {2.0}
        else if start_relation_types_old[0] || start_relation_types_old[2] {1.0}
        else {0.0}
    } else {
        start_relation_types_new[0] = true;
        notation_size *
        start_dir *
        if start_relation_types_old[1] && start_relation_types_old[2] {2.0}
        else if start_relation_types_old[1] || start_relation_types_old[2] {1.0}
        else {0.0}
    };
    let adjust_end_y = if nullable {
        end_relation_types_new[2] = true;
        notation_size *
        end_dir *
        if end_relation_types_old[1] {1.0} else {-1.0} *
        if end_relation_types_old[0] && end_relation_types_old[1] {0.0}
        else if end_relation_types_old[0] || end_relation_types_old[1] {1.0}
        else {0.0}
    } else {
        end_relation_types_new[1] = true;
        notation_size *
        -end_dir *
        if end_relation_types_old[0] && end_relation_types_old[2] {2.0}
        else if end_relation_types_old[0] || end_relation_types_old[2] {1.0}
        else {0.0}
    };

    start.y += adjust_start_y;
    end.y += adjust_end_y;

    ui.ctx().data_mut(|data| {
        data.insert_temp(start_column_relation_types_new_id, start_relation_types_new);
        data.insert_temp(end_column_relation_types_new_id, end_relation_types_new);
    });

    let front_line = start_dir != end_dir && relation.relation_segments.len() <= 1 && (start.y - end.y).abs() < 3.0 * scene_transform.scaling;

    // Criar pontos inicias para o caminho da relação
    let mut pts = Vec::from([start]);

    if !front_line {
        if auto_align {
            pts.push(pos2(x_align, start.y));
            pts.push(pos2(x_align, end.y));
        } else {
            pts.push(pos2(relation.relation_segments[0] * scene_transform.scaling + scene_transform.translation.x, start.y));
            for (i, seg) in relation.relation_segments.windows(2).enumerate() {
                pts.push(scene_transform.mul_pos(if i % 2 == 0 {
                    pos2(seg[0], seg[1])
                } else {
                    pos2(seg[1], seg[0])
                }));
            }
            pts.push(pos2(*relation.relation_segments.last().unwrap() * scene_transform.scaling + scene_transform.translation.x, end.y));
        }
    }
    pts.push(end);

    // Ajustar posição da linha com base no limite dos retangulos das tabelas e desenhar as notações
    let last_idx = pts.len() - 1;

    // --- Primeira Tabela FK ---
    pts[0].x += start_dir * start_offset;
    if !front_line {
        let new_start_x = if start_goes_left {
            pts[1].x.min(pts[0].x - table_proximity_limit)
        } else {
            pts[1].x.max(pts[0].x + table_proximity_limit)
        };
        pts[1].x = new_start_x;
        pts[2].x = new_start_x;
    }

    // --- Segunda Tabela PK --
    pts[last_idx].x += end_dir * end_offset;
    if !front_line {
        let new_end_x = if end_goes_left {
            pts[last_idx - 1].x.min(pts[last_idx].x - table_proximity_limit)
        } else {
            pts[last_idx - 1].x.max(pts[last_idx].x + table_proximity_limit)
        };
        pts[last_idx - 1].x = new_end_x;
        pts[last_idx - 2].x = new_end_x;
    }

    draw_visual_relation(painter, &pts, selected.contains(&Selected::Relation { relation: rela_idx, segment: None }), line_stroke, table_proximity_limit, notation_size, start_dir, end_dir, last_idx, unique, nullable, cardinality_display);

    let rel_first_response = ui.interact(Rect::from_two_pos(pts[0], pts[1]).expand(line_width / 2.0).expand2(vec2(0.0, 3.0)), Id::new(("rel", rela_idx, "first")), Sense::click_and_drag());
    let rel_second_response = ui.interact(Rect::from_two_pos(pts[last_idx], pts[last_idx-1]).expand(line_width / 2.0).expand2(vec2(0.0, 3.0)), Id::new(("rel", rela_idx, "second")), Sense::click_and_drag());
    if rel_first_response.clicked() || rel_second_response.clicked() {
        if !ui.input(|i| {i.modifiers.command_only()}) || read_only {selected.clear();}
        toggle_selected(selected, Selected::Relation { relation: rela_idx, segment: None }, relation.relation_segments.len(), read_only);
    }
    if rel_first_response.hovered() || rel_second_response.hovered() {
        ui.output_mut(|o| o.cursor_icon = CursorIcon::PointingHand);
    }
    let popup_first_id = Id::new(("popup", rela_idx, "first"));
    let popup_second_id = Id::new(("popup", rela_idx, "second"));
    if !read_only {
        popup_relation_create(&rel_first_response, popup_first_id, relation, selected, diagram_interacted);
        popup_relation_create(&rel_second_response, popup_second_id, relation, selected, diagram_interacted);
        if rel_first_response.drag_started() || rel_second_response.drag_started() {
            let item_rela = Selected::Relation { relation: rela_idx, segment: None };
            if !selected.contains(&item_rela) {
                if !ui.input(|i| {i.modifiers.command_only()}) {selected.clear();}
                toggle_selected(selected, item_rela, relation.relation_segments.len(), read_only);
            } else {
                toggle_selected(selected, item_rela, relation.relation_segments.len(),read_only);
                toggle_selected(selected, Selected::Relation { relation: rela_idx, segment: None }, relation.relation_segments.len(), read_only);
            }
        }
        if rel_first_response.dragged() || rel_second_response.dragged() {
            Popup::close_all(ui.ctx());
            let delta = (rel_first_response.drag_delta() / scene_transform.scaling) + (rel_second_response.drag_delta() / scene_transform.scaling);
            *delta_used = delta;
            ui.output_mut(|o| o.cursor_icon = CursorIcon::Grabbing);
        }
        if rel_first_response.drag_stopped() {
            Popup::open_id(ui.ctx(), popup_first_id);
            *drag_stopped = true;
        }
        if rel_second_response.drag_stopped() {
            Popup::open_id(ui.ctx(), popup_second_id);
            *drag_stopped = true;
        }
        
        if !front_line && rel_first_response.secondary_clicked() {
            selected.clear();
            if auto_align {relation.relation_segments.push((x_align - scene_transform.translation.x) / scene_transform.scaling);}
            let mid = ((pts[0].x + pts[1].x) / 2.0 - scene_transform.translation.x) / scene_transform.scaling;
            let next = ((pts[1].y + pts[2].y) / 2.0 - scene_transform.translation.y) / scene_transform.scaling;
            relation.relation_segments.insert(0, mid);
            relation.relation_segments.insert(1, next);
            Popup::open_id(ui.ctx(), popup_first_id);
            *diagram_interacted = true;
        }
        if rel_second_response.secondary_clicked() {
            selected.clear();
            if front_line {
                relation.relation_segments.clear();
                let mid_x = (pts[0].x + pts[1].x)/2.0;
                let first = ((pts[0].x + mid_x)/2.0 - scene_transform.translation.x) / scene_transform.scaling;
                let second = (pts[0].y + 20.0 - scene_transform.translation.y) / scene_transform.scaling;
                let third = ((pts[1].x + mid_x)/2.0 - scene_transform.translation.x) / scene_transform.scaling;
                relation.relation_segments.push(first);
                relation.relation_segments.push(second);
                relation.relation_segments.push(third);
            } else {
                if auto_align {relation.relation_segments.push((x_align - scene_transform.translation.x) / scene_transform.scaling);}
                let mid = ((pts[last_idx].x + pts[last_idx - 1].x) / 2.0 - scene_transform.translation.x) / scene_transform.scaling;
                let next = ((pts[last_idx - 1].y + pts[last_idx - 2].y) / 2.0 - scene_transform.translation.y) / scene_transform.scaling;
                relation.relation_segments.push(next);
                relation.relation_segments.push(mid);
            }
            Popup::open_id(ui.ctx(), popup_second_id);
            *diagram_interacted = true;
        }
    }

    // Segmentos
    for (seg_idx, pair) in pts[1..last_idx].windows(2).enumerate() {
        let (p1, p2) = (pair[0], pair[1]);
        let is_vertical = seg_idx % 2 == 0;

        let seg_id = Id::new(("seg", rela_idx, seg_idx));

        // Area visual, largura da linha
        let visual_rect = Rect::from_two_pos(p1, p2).expand(line_width / 2.0);

        // Expandir a area para clicar
        let hit_padding = if is_vertical { vec2(3.0, 0.0) } else { vec2(0.0, 3.0) };
        let interact_rect = visual_rect.expand2(hit_padding);

        let seg_response = ui.interact(interact_rect, seg_id, Sense::click_and_drag());
        let popup_id = Id::new(("popup", rela_idx, seg_idx));

        if seg_response.clicked() {
            if read_only {
                selected.clear();
                toggle_selected(selected, Selected::Relation { relation: rela_idx, segment: None }, relation.relation_segments.len(), read_only);
            } else {
                if !ui.input(|i| {i.modifiers.command_only()}) {selected.clear();}
                toggle_selected(selected, Selected::Relation { relation: rela_idx, segment: Some(seg_idx) }, relation.relation_segments.len(), read_only);
            }
        }
        if seg_response.hovered() {
            ui.output_mut(|o| o.cursor_icon = CursorIcon::PointingHand);
        }
        if !read_only {
            popup_relation_create(&seg_response, popup_id, relation, selected, diagram_interacted);

            if seg_response.drag_started() {
                let item_seg = Selected::Relation { relation: rela_idx, segment: Some(seg_idx) };
                let item_rela = Selected::Relation { relation: rela_idx, segment: None };
                if !selected.contains(&item_seg) && !selected.contains(&item_rela) {
                    if !ui.input(|i| {i.modifiers.command_only()}) {selected.clear();}
                    toggle_selected(selected, item_seg, relation.relation_segments.len(), read_only);
                } else {
                    toggle_selected(selected, item_seg, relation.relation_segments.len(),read_only);
                    toggle_selected(selected, Selected::Relation { relation: rela_idx, segment: Some(seg_idx) }, relation.relation_segments.len(), read_only);
                }
            }

            if selected.contains(&Selected::Relation { relation: rela_idx, segment: Some(seg_idx) }) {
                painter.rect_filled(visual_rect, CornerRadius::ZERO, Color32::BLUE);
            }

            // --- Mudanças de estado (Start / End Drag / Right Click) ---
            if seg_response.drag_started() || seg_response.secondary_clicked() || seg_response.drag_stopped() {
                let interact_real_center = scene_transform.inverse().mul_pos(interact_rect.center());
                if auto_align {
                    relation.relation_segments.push(interact_real_center.x);
                }
                relation.relation_segments[seg_idx] = if is_vertical { interact_real_center.x } else { interact_real_center.y };

                Popup::open_id(ui.ctx(), popup_id);
            }

            // --- Arrastar ---
            if seg_response.dragged() {
                Popup::close_all(ui.ctx());
                let delta = seg_response.drag_delta() / scene_transform.scaling;
                *delta_used = delta;
                ui.output_mut(|o| o.cursor_icon = CursorIcon::Grabbing);
            }

            // --- Dividir linha ---
            if seg_response.secondary_clicked() {
                selected.clear();
                let mut mid = (if is_vertical { (p1.y + p2.y) / 2.0 - scene_transform.translation.y } else { (p1.x + p2.x) / 2.0 - scene_transform.translation.x }) / scene_transform.scaling;
                let next = (if is_vertical { (p2.x + pts[seg_idx + 3].x) / 2.0 - scene_transform.translation.x } else { (p2.y + pts[seg_idx + 3].y) / 2.0 - scene_transform.translation.y }) / scene_transform.scaling;
                if let Some(mouse_pos) = ui.input(|i| {i.pointer.latest_pos()}) {
                    mid = if is_vertical {mouse_pos.y - scene_transform.translation.y} else {mouse_pos.x - scene_transform.translation.x} / scene_transform.scaling;
                }
                relation.relation_segments.insert(seg_idx + 1, mid);
                relation.relation_segments.insert(seg_idx + 2, next);
                *diagram_interacted = true;
            }

            let (start, end) = (scene_transform.inverse().mul_pos(pts[0]), scene_transform.inverse().mul_pos(pts[last_idx]));

            if seg_response.drag_stopped() {
                *drag_stopped = true;
            }

            if seg_idx != 0 {
                let pt_id = Id::new(("pt", rela_idx, seg_idx));
                let pt_rect = Rect::from_center_size(p1, vec2(interact_hitbox_size, interact_hitbox_size));
                let pt_response = ui.interact(pt_rect, pt_id, Sense::click_and_drag());
                let pt_popup_id = Id::new(("popup_pt", rela_idx, seg_idx));

                popup_relation_create(&pt_response, pt_popup_id, relation, selected, diagram_interacted);

                if pt_response.drag_started() || pt_response.secondary_clicked() || pt_response.drag_stopped() {
                    let pt_real_center = scene_transform.inverse().mul_pos(pt_rect.center());
                    relation.relation_segments[seg_idx - 1] = if is_vertical { pt_real_center.y } else { pt_real_center.x };
                    relation.relation_segments[seg_idx]     = if is_vertical { pt_real_center.x } else { pt_real_center.y };
                    Popup::open_id(ui.ctx(), pt_popup_id);
                }

                if pt_response.hovered() {
                    painter.circle_filled(p1, 4.5 * scene_transform.scaling, Color32::from_gray(130));
                    ui.output_mut(|o| o.cursor_icon = CursorIcon::PointingHand);

                    if pt_response.dragged() {
                        let delta_prev = if is_vertical { pt_response.drag_delta().y } else { pt_response.drag_delta().x };
                        let delta_curr = if is_vertical { pt_response.drag_delta().x } else { pt_response.drag_delta().y };

                        relation.relation_segments[seg_idx - 1] += delta_prev / scene_transform.scaling;
                        relation.relation_segments[seg_idx]     += delta_curr / scene_transform.scaling;

                        ui.output_mut(|o| o.cursor_icon = CursorIcon::Grabbing);
                    }
                }

                if pt_response.secondary_clicked() {
                    selected.clear();
                    relation.relation_segments.remove(seg_idx);
                    relation.relation_segments.remove(seg_idx - 1);
                    Popup::open_id(ui.ctx(), popup_second_id);
                    *diagram_interacted = true;
                }

                if pt_response.drag_stopped() {
                    verify_line_segment_joins(&mut relation.relation_segments, seg_idx, start.y, end.y, selected, rela_idx);
                    verify_line_segment_joins(&mut relation.relation_segments, seg_idx - 1, start.y, end.y, selected, rela_idx);
                    *diagram_interacted = true;
                }
            }
        }
    }
}

fn draw_visual_relation(painter: &Painter, pts: &Vec<Pos2>, selected: bool, line_stroke: Stroke, table_proximity_limit: f32, notation_size: f32, start_dir: f32, end_dir: f32, last_idx: usize, unique: bool, nullable: bool, cardinality_display: CardinalityDisplay) {
    let mut pts = pts.clone();
    if unique {
        // Desenhar a notação One
        let crow_up_base = pts[0] + vec2(start_dir * table_proximity_limit / 3.0, notation_size);
        let down_up_base = pts[0] + vec2(start_dir * table_proximity_limit / 3.0, - notation_size);
        painter.line_segment([crow_up_base, down_up_base], line_stroke);
    } else {
        // Desenhar a notação Many
        let crow_base = pts[0] + vec2(start_dir * table_proximity_limit / 1.5, 0.0);
        painter.line_segment([crow_base, pts[0] + vec2(0.0, notation_size)], line_stroke);
        painter.line_segment([crow_base, pts[0] + vec2(0.0, - notation_size)], line_stroke);
    }

    if nullable {
        // Desenhar a notação Zero
        let crow_base_start = pts[last_idx] + vec2(end_dir * (table_proximity_limit / 2.0 - notation_size/2.0), 0.0);
        let crow_base_end = pts[last_idx] + vec2(end_dir * (table_proximity_limit / 2.0 + notation_size/2.0), 0.0);
        painter.line_segment([pts[last_idx], crow_base_start], line_stroke);
        painter.circle_stroke(pts[last_idx] + vec2(end_dir * table_proximity_limit / 2.0, 0.0), notation_size/2.0, line_stroke);
        // Fazer o ultimo ponto passar a ser depois do circulo
        pts.pop();
        pts.push(crow_base_end);
    } else {
        // Desenhar a notação One
        let crow_up_base = pts[last_idx] + vec2(end_dir * table_proximity_limit / 3.0, notation_size);
        let down_up_base = pts[last_idx] + vec2(end_dir * table_proximity_limit / 3.0, - notation_size);
        painter.line_segment([crow_up_base, down_up_base], line_stroke);
    }

    let start_text_pos = pts[0] + vec2(start_dir * table_proximity_limit / 3.0, -notation_size*2.0);
    let end_text_pos = pts[last_idx] + vec2(end_dir * table_proximity_limit / 3.0, -notation_size*2.0);

    // Desenhar a linha completa
    painter.line(pts, line_stroke);
    if match cardinality_display {
        CardinalityDisplay::Never => {false}
        CardinalityDisplay::SelectedOnly => {selected}
        CardinalityDisplay::Always => {true}
    } {
        painter.text(start_text_pos, Align2::CENTER_CENTER, if unique {"1"} else {"*"}, FontId::monospace(notation_size*2.0), Color32::BLACK);
        painter.text(end_text_pos, Align2::CENTER_CENTER, if nullable {"0..1"} else {"1"}, FontId::monospace(notation_size*2.0), Color32::BLACK);
    }
}

fn popup_relation_create(seg_response: &Response, popup_id: Id, relation: &mut Relation, selected: &mut Vec<Selected>, diagram_interacted: &mut bool) {
    Popup::menu(seg_response).id(popup_id).show(|ui| {
        if ui.button("⟳ Reset").clicked() {
            relation.relation_segments.clear();
            selected.clear();
            *diagram_interacted = true;
        }
    });
}

impl eframe::App for TemplateApp {
    /// Chamada sempre que o UI necessita de ser desenhado outra vez (60x por segundo)
    fn update(&mut self, ctx: &Context, _frame: &mut eframe::Frame) {

        // Tira a mensagem do Read Only, fecha a porta do lock imediatamente
        let new_read_only = if let Ok(mut update) = self.update_read_only.lock() {
            update.take() // Tira o valor (Option<bool>) e deixa None lá dentro
        } else {
            None
        };

        // Aplica a mudança se houver alguma
        if let Some(ro) = new_read_only {
            self.read_only = ro;
        }

        // Tira a mensagem do JSON, fecha a porta do lock imediatamente
        let new_json = if let Ok(mut update) = self.update_json.lock() {
            update.take() // Tira a String (Option<String>) e deixa None
        } else {
            None
        };

        // Agora o `self` está sem locks e pode ser substituido
        if let Some(json) = new_json {
            match serde_json::from_str::<TemplateApp>(&json) {
                Ok(mut new_app) => {
                    // Passar as funções para a nova app
                    new_app.save_trigger = self.save_trigger.clone();
                    new_app.update_json = self.update_json.clone();
                    new_app.update_read_only = self.update_read_only.clone();
                    new_app.export_trigger = self.export_trigger.clone();
                    new_app.sync_trigger = self.sync_trigger.clone();
                    new_app.txt_export_trigger = self.txt_export_trigger.clone();
                    new_app.read_only = self.read_only;
                    new_app.apply_auto_layout();

                    // Substitui a aplicação inteira para aplicar o novo estado do diagrama
                    *self = new_app;
                }
                Err(e) => log::error!("Falha ao aplicar novo JSON: {}", e),
            }
        }
        // Exportar txt
        #[cfg(target_arch = "wasm32")]
        if let Ok(mut flag) = self.txt_export_trigger.lock() {
            if *flag {
                *flag = false;
                let content = self.generate_text_export();
                downloadTextFile(&content);
            }
        }
        // Verifica se o JS pediu para gravar
        #[cfg(target_arch = "wasm32")]
        if let Ok(mut flag) = self.save_trigger.lock() {
            if *flag {
                *flag = false; // Desliga a flag
                if let Some(window) = web_sys::window() {
                    let _ = js_sys::Reflect::set(
                        &window,
                        &wasm_bindgen::JsValue::from_str("hasUnsavedChanges"),
                        &wasm_bindgen::JsValue::from_bool(false),
                    );
                }
                // Gera o JSON e envia para o Laravel
                match serde_json::to_string(self) {
                    Ok(json_string) => {
                        saveDiagramState(&json_string);
                    }
                    Err(e) => tracing::error!("Erro: {}", e),
                }

            }
        }
        #[cfg(target_arch = "wasm32")]
        if let Ok(mut sync_flag) = self.sync_trigger.lock() {
            if *sync_flag {
                *sync_flag = false; // Reseta a flag
                match serde_json::to_string(self) {
                    Ok(json_string) => openSyncModal(&json_string),
                    Err(e) => tracing::error!("Erro ao gerar JSON para Sync: {}", e),
                }
            }
        }
        let mut frame = egui::Frame::default();

        if self.exporting {
            frame = frame.fill(egui::Color32::TRANSPARENT).stroke(egui::Stroke::NONE);
        } else {
            frame = frame.fill(ctx.style().visuals.panel_fill);
        }

        CentralPanel::default().frame(frame).show(ctx, |ui| {
            let (mut bg_response, painter) =
                ui.allocate_painter(ui.available_size(), Sense::click_and_drag());

            // --- 1. PREPARAR A FOTO: AUTO-FRAMING E SCREENSHOT ---
            #[cfg(target_arch = "wasm32")]
            if let Ok(mut flag) = self.export_trigger.lock() {
                if *flag {
                    *flag = false;
                    self.exporting = true;

                    ui.ctx().data_mut(|d| d.insert_temp(Id::new("saved_transform"), self.scene_transform));

                    center_screen(ctx, ui.clip_rect(), &mut self.scene_transform, &self.tables, &self.relations, true);

                    // Faz screenshot
                    ctx.send_viewport_cmd(ViewportCommand::Screenshot(UserData::default()));
                    ctx.request_repaint();
                }
            }

            // --- 2. RECUPERAR DEPOIS DA FOTO E ENVIAR PARA JS ---
            #[cfg(target_arch = "wasm32")]
            if self.exporting {
                //Mantém o motor acordado à espera do evento da foto
                ctx.request_repaint();

                for event in ctx.input(|i| i.events.clone()) {
                    if let Event::Screenshot { image, .. } = event {
                        self.exporting = false;

                        if let Some(saved) = ui.ctx().data_mut(|d| d.get_temp::<TSTransform>(Id::new("saved_transform"))) {
                            self.scene_transform = saved;
                        }

                        let pixels_rgba: Vec<u8> = image.pixels.iter().flat_map(|color| color.to_array()).collect();
                        savePixelsAsPng(image.size[0] as u32, image.size[1] as u32, &pixels_rgba);
                    }
                }
            }

            if !self.exporting {

                // Remover todos os objetos selecionados da lista
                if bg_response.clicked() && !ctx.input(|i| {i.modifiers.command_only()}) {
                    self.selected.clear();
                }

                // Drag and Drop area para selecionar objetos
                if !self.read_only {
                    let start_drag_id = Id::new("start_background_drag_pos");
                    if bg_response.drag_started_by(PointerButton::Primary) {
                        ctx.data_mut(|data| {
                            data.insert_temp(start_drag_id, bg_response.interact_pointer_pos().unwrap());
                        });
                    }

                    if let Some(start_drag_pos) = ctx.data(|data| {
                        data.get_temp::<Pos2>(start_drag_id)
                    }) {
                        if let Some(end_drag_pos) = bg_response.interact_pointer_pos() {
                            let selection_area_rect = Rect::from_two_pos(start_drag_pos, end_drag_pos);
                            self.selected.clear();

                            // Adicionar todas as tabelas dentro da area aos selecionados
                            for (table_idx, table) in self.tables.iter().enumerate() {
                                if selection_area_rect.contains(self.scene_transform.mul_pos(table.pos)) {
                                    toggle_selected(&mut self.selected, Selected::Table { table: table_idx, column: None }, 0, self.read_only);
                                }
                            }

                            // Adicionar todas as relações dentro da area aos selecionados
                            for (rela_idx, relation) in self.relations.iter().enumerate() {
                                let (rect_a, rect_b) = ctx.data(|data| {
                                    (
                                        data.get_temp::<Rect>(Id::new(("column_rect", relation.tables[0], relation.columns[0]))),
                                        data.get_temp::<Rect>(Id::new(("column_rect", relation.tables[1], relation.columns[1])))
                                    )
                                });

                                let (Some(rect_a), Some(rect_b)) = (rect_a, rect_b) else {
                                    continue;
                                };

                                let start_relation_pos = rect_a.center();
                                let end_relation_pos = rect_b.center();

                                if relation.relation_segments.is_empty() {
                                    if selection_area_rect.contains(self.scene_transform.mul_pos(start_relation_pos.lerp(end_relation_pos, 0.5))) {
                                        toggle_selected(&mut self.selected, Selected::Relation { relation: rela_idx, segment: None }, relation.relation_segments.len(), self.read_only);
                                    }
                                } else {
                                    for (seg_idx, segment) in relation.relation_segments.iter().enumerate() {
                                        let segment_pos = if seg_idx % 2 == 0 {
                                            let mid_y = (if seg_idx == 0 {start_relation_pos.y} else {relation.relation_segments[seg_idx-1]} +
                                                if seg_idx == relation.relation_segments.len()-1 {end_relation_pos.y} else {relation.relation_segments[seg_idx+1]}) / 2.0;
                                            pos2(*segment, mid_y)
                                        } else {
                                            pos2((relation.relation_segments[seg_idx-1] + relation.relation_segments[seg_idx+1]) / 2.0, *segment)
                                        };
                                        if selection_area_rect.contains(self.scene_transform.mul_pos(segment_pos)) {
                                            toggle_selected(&mut self.selected, Selected::Relation { relation: rela_idx, segment: Some(seg_idx) }, relation.relation_segments.len(), self.read_only);
                                        }
                                    }
                                }
                            }

                            // Desenhar uma area de seleção
                            painter.rect(selection_area_rect, CornerRadius::ZERO, Color32::from_rgb(200, 200, 255), Stroke::new(1.0, Color32::BLUE), StrokeKind::Middle);
                        }
                    }

                    if bg_response.drag_stopped_by(PointerButton::Primary) {
                        ctx.data_mut(|data| {
                            data.remove_temp::<Pos2>(start_drag_id);
                        });
                    }
                }

                // Mover o selecionado entre tabelas/colunas
                if self.selected.len() == 1 {
                    match &mut self.selected[0] {
                        Selected::Relation { .. } => {}
                        Selected::Table { table, column } => {
                            if ctx.input(|i| {i.key_pressed(Key::ArrowRight)}) {
                                *table = (*table + 1) % self.tables.len();
                                *column = None;
                            }
                            if ctx.input(|i| {i.key_pressed(Key::ArrowLeft)}) {
                                *table = (*table + self.tables.len() - 1) % self.tables.len();
                                *column = None;
                            }
                            match column {
                                Some(col_idx) => {
                                    if ctx.input(|i| {i.key_pressed(Key::ArrowDown)}) {
                                        if *col_idx != self.tables[*table].columns.len()-1 {
                                            *col_idx += 1;
                                        } else {
                                            *column = None;
                                        }
                                    } else if ctx.input(|i| {i.key_pressed(Key::ArrowUp)}) {
                                        if *col_idx != 0 {
                                            *col_idx -= 1;
                                        } else {
                                            *column = None;
                                        }
                                    }
                                }
                                None => {
                                    if self.tables[*table].columns.len() != 0 {
                                        if ctx.input(|i| {i.key_pressed(Key::ArrowDown)}) {
                                            *column = Some(0);
                                        } else if ctx.input(|i| {i.key_pressed(Key::ArrowUp)}) {
                                            *column = Some(self.tables[*table].columns.len()-1);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // Correr logica do undoer (undo and redo)
                let mut state_changed = false;
                if ui.input(|i| {i.modifiers.command && i.key_pressed(Key::Z)}) && let Some(undo_text) = self.undoer.undo(&self.app_state) {
                    self.app_state = undo_text.clone();
                    state_changed = true;
                }
                if ui.input(|i| {i.modifiers.command && i.key_pressed(Key::Y)}) && let Some(redo_text) = self.undoer.redo(&self.app_state) {
                    self.app_state = redo_text.clone();
                    state_changed = true;
                }
                if state_changed {
                    self.tables = self.app_state.tables.clone();
                    self.relations = self.app_state.relations.clone();
                }
                self.undoer.feed_state(ui.input(|input| input.time), &self.app_state);

                // Colocar o background a controlar a Scene (PanAndDrag)
                Scene::new()
                    .drag_pan_buttons(if self.read_only {DragPanButtons::all()} else {DragPanButtons::all().difference(DragPanButtons::PRIMARY)})
                    .zoom_range(Rangef::new(0.1, 2.0))
                    .register_pan_and_zoom(ui, &mut bg_response, &mut self.scene_transform);

                // Criar window de edição no ecra
                self.draw_edit_window(ctx, ui.clip_rect());

                // Controlar zoom com uma barra superior horizontal
                self.draw_zoom_area(ctx, ui.clip_rect(), bg_response);
            }

            let mut delta_used = Vec2::ZERO;
            let mut drag_stopped_on: Option<usize> = None;
            let mut new_transform: Option<TSTransform> = None;
            // Desenhar as tabelas
            for (i, table) in self.tables.iter_mut().enumerate() {
                let old_transform = self.scene_transform;
                let (delta_received, drag_stopped_on_received) = table.ui(ctx, i, &mut self.scene_transform, self.read_only, &mut self.selected, &self.relations, self.options_menu.description_indicator);
                if delta_received != Vec2::ZERO {
                    delta_used = delta_received;
                }
                if drag_stopped_on_received != None {
                    drag_stopped_on = drag_stopped_on_received;
                }
                if old_transform != self.scene_transform {
                    new_transform = Some(self.scene_transform);
                    self.scene_transform = old_transform;
                }
            }

            if let Some(drag_stopped_on) = drag_stopped_on {
                drag_stopped_on_table(drag_stopped_on, ctx, &mut self.selected, &mut self.relations, &mut self.tables, &mut self.app_state);
            }

            // Desenhar as linhas das relações
            self.draw_relations(ui, &painter, self.scene_transform);

            if delta_used != Vec2::ZERO {
                move_all_selected(delta_used, &mut self.selected, &mut self.relations, &mut self.tables);
            }

            if let Some(new_transform) = new_transform {
                self.scene_transform = new_transform;
            }
        });
    }
    fn clear_color(&self, _visuals: &egui::Visuals) -> [f32; 4] {
        if self.exporting {
            // Se estiver a exportar, o fundo do ecrã fica 100% transparente [R, G, B, Alpha]
            [0.0, 0.0, 0.0, 0.0]
        } else {
            _visuals.panel_fill.to_normalized_gamma_f32()
        }
    }
    /// Guarda o estado da app antes de ser terminada
    fn save(&mut self, storage: &mut dyn eframe::Storage) {
        eframe::set_value(storage, eframe::APP_KEY, self);
    }
}

fn center_screen(ctx: &Context, screen_rect: Rect, scene_transform: &mut TSTransform, tables: &Vec<Table>, relations: &Vec<Relation>, is_print: bool) {
    if tables.is_empty() {
        *scene_transform = TSTransform::IDENTITY;
    } else {
        let mut diagram = Rect::NOTHING;

        for (table_idx, table) in tables.iter().enumerate() {
            // Como table.pos é o CENTRO da tabela, tem de se calcular as margens
            let table_height = 8.0 + HEADER_SIZE + (table.columns.len() as f32 * COL_SIZE);
            let table_width = if let Some(column_rect) = ctx.data(|data| {
                data.get_temp::<Rect>(Id::new(("column_rect", table_idx, 0)))
            }) {
                column_rect.width() //largura real encontrada
            } else {
                300.0 // Fallback largura média para os cálculos
            };

            // Descobrir as pontas verdadeiras do retângulo
            let left = table.pos.x - (table_width / 2.0);
            let top = table.pos.y - (table_height / 2.0);
            let right = table.pos.x + (table_width / 2.0);
            let bottom = table.pos.y + (table_height / 2.0);

            // Atualizar a Caixa global
            diagram.extend_with(pos2(left, top));
            diagram.extend_with(pos2(right, bottom));
        }

        for relation in relations.iter() {
            for (seg_idx, seg_pos) in relation.relation_segments.iter().enumerate() {
                if seg_idx % 2 == 0 {
                    diagram.extend_with_x(*seg_pos);
                } else {
                    diagram.extend_with_y(*seg_pos);
                }
            }
        }

        // Escala (mantendo uma margem de 90% do ecrã)
        let scale_x = (screen_rect.width() * 0.9) / diagram.width();
        let scale_y = (screen_rect.height() * 0.9) / diagram.height();
        scene_transform.scaling = if is_print {scale_x.min(scale_y)} else {scale_x.min(scale_y).min(1.0)};

        // Centra
        scene_transform.translation = screen_rect.center().to_vec2() - (diagram.center().to_vec2() * scene_transform.scaling);
    }
}

fn drag_stopped_on_table(table_idx: usize, ctx: &Context, selected: &mut Vec<Selected>, relations: &mut Vec<Relation>, tables: &mut Vec<Table>, app_state: &mut AppState) {
    let mut table_adjusted = false;
    for relation in relations.iter_mut() {
        if table_adjusted == false && relation.tables[0] == table_idx || relation.tables[1] == table_idx {
            let (rect_a, rect_b) = ctx.data(|data| {
                (
                    data.get_temp::<Rect>(Id::new(("column_rect", relation.tables[0], relation.columns[0]))),
                    data.get_temp::<Rect>(Id::new(("column_rect", relation.tables[1], relation.columns[1])))
                )
            });

            let (Some(rect_a), Some(rect_b)) = (rect_a, rect_b) else {
                continue;
            };

            let mut start = rect_a.center();
            let start_offset = rect_a.width() / 2.0;

            let mut end = rect_b.center();
            let end_offset = rect_b.width() / 2.0;

            let enough_space = (start.x - end.x).abs() > start_offset + end_offset + TABLE_PROXIMITY_LIMIT * 2.0;
            let auto_align = relation.relation_segments.is_empty();
            let x_align = (start.x + end.x) / 2.0;

            let start_goes_left = if auto_align {
                if enough_space {start.x > end.x} else {false}
            } else {
                (if enough_space {start.x} else {x_align}) > *relation.relation_segments.first().unwrap()
            };
            let start_dir = if start_goes_left { -1.0 } else { 1.0 };

            let end_goes_left = if auto_align {
                if enough_space {end.x > start.x} else {false}
            } else {
                (if enough_space {end.x} else {x_align}) > *relation.relation_segments.last().unwrap()
            };
            let end_dir = if end_goes_left { -1.0 } else { 1.0 };

            // Tipos existentes nas colunas [bool; 3] Multi, One, Zero
            let (start_relation_types_old, end_relation_types_old) = ctx.data(|data| {
                (
                    data.get_temp::<[bool; 3]>(Id::new(("column_relation_types_old", start_goes_left, relation.tables[0], relation.columns[0]))),
                    data.get_temp::<[bool; 3]>(Id::new(("column_relation_types_old", end_goes_left, relation.tables[1], relation.columns[1])))
                )
            });

            let start_relation_types_old = match start_relation_types_old {
                None => [false, false, false],
                Some(relation_types) => relation_types
            };
            let end_relation_types_old = match end_relation_types_old {
                None => [false, false, false],
                Some(relation_types) => relation_types
            };

            let adjust_start_y = if tables[relation.tables[0]].columns[relation.columns[0]].unique {
                NOTATION_SIZE *
                -start_dir *
                if start_relation_types_old[0] && start_relation_types_old[2] {2.0}
                else if start_relation_types_old[0] || start_relation_types_old[2] {1.0}
                else {0.0}
            } else {
                NOTATION_SIZE *
                start_dir *
                if start_relation_types_old[1] && start_relation_types_old[2] {2.0}
                else if start_relation_types_old[1] || start_relation_types_old[2] {1.0}
                else {0.0}
            };
            let adjust_end_y = if tables[relation.tables[0]].columns[relation.columns[0]].nullable {
                NOTATION_SIZE *
                end_dir *
                if end_relation_types_old[1] {1.0} else {-1.0} *
                if end_relation_types_old[0] && end_relation_types_old[1] {0.0}
                else if end_relation_types_old[0] || end_relation_types_old[1] {1.0}
                else {0.0}
            } else {
                NOTATION_SIZE *
                -end_dir *
                if end_relation_types_old[0] && end_relation_types_old[2] {2.0}
                else if end_relation_types_old[0] || end_relation_types_old[2] {1.0}
                else {0.0}
            };

            start.y += adjust_start_y;
            end.y += adjust_end_y;

            if relation.relation_segments.len() <= 1 && (start.y - end.y).abs() < 5.0 {
                let adjust_y = start.y - end.y;
                tables[table_idx].pos += if relation.tables[0] == table_idx {vec2(0.0, -adjust_y)} else {vec2(0.0, adjust_y)};
                table_adjusted = true;
            }
        }
    }
    
    use_verify_in_selected(ctx, selected, relations, tables, app_state);
}

fn move_all_selected(delta: Vec2, selected: &mut Vec<Selected>, relations: &mut Vec<Relation>, tables: &mut Vec<Table>) {
    for selected in selected.iter() {
        match selected {
            Selected::Table { table, .. } => {
                tables[*table].pos += delta;
            },
            Selected::Relation { relation, segment } => {
                let relation = &mut relations[*relation];
                let relation_size = relation.relation_segments.len();
                match segment {
                    None => {
                        if relation_size == 0 {
                            let first_table_idx = relation.tables[0];
                            let last_table_idx = relation.tables[1];
                            let x_mid = (tables[first_table_idx].pos.x + tables[last_table_idx].pos.x)/2.0;
                            relation.relation_segments.push(x_mid);
                        }
                        for (segment_idx, segment) in relation.relation_segments.iter_mut().enumerate() {
                            *segment += if segment_idx % 2 == 0 {delta.x} else {delta.y};
                        }
                    },
                    Some(selected_seg_idx) => {
                        if relation_size > *selected_seg_idx {
                            relation.relation_segments[*selected_seg_idx] += if *selected_seg_idx % 2 == 0 {delta.x} else {delta.y};
                        }
                    }
                }
            }
        }
    }
}

fn use_verify_in_selected(ctx: &Context, selected: &mut Vec<Selected>, relations: &mut Vec<Relation>, tables: &Vec<Table>, app_state: &mut AppState) {
    let mut relation_segments_drag_stopped_to_verify: Vec<[usize; 3]> = Vec::new();//relationID/fullRelationBinary/segmentID
    for selected in selected.iter() {
        match selected {
            Selected::Table { table, .. } => {
                for (rela_idx, relation) in relations.iter_mut().enumerate() {
                    if relation.tables[0] == *table || relation.tables[1] == *table {
                        relation_segments_drag_stopped_to_verify.push([rela_idx, 1, usize::MAX]);
                    }
                }
            },
            Selected::Relation { relation, segment } => {
                match segment {
                    None => {
                        relation_segments_drag_stopped_to_verify.push([*relation, 1, usize::MAX]);
                    },
                    Some(selected_seg_idx) => {
                        relation_segments_drag_stopped_to_verify.push([*relation, 0, *selected_seg_idx]);
                    }
                }
            }
        }
    }

    for relation_segment in relation_segments_drag_stopped_to_verify.iter() {
        let rela_idx = relation_segment[0];
        let relation = &mut relations[rela_idx];
        let (rect_a, rect_b) = ctx.data(|data| {
            (
                data.get_temp::<Rect>(Id::new(("column_rect", relation.tables[0], relation.columns[0]))),
                data.get_temp::<Rect>(Id::new(("column_rect", relation.tables[1], relation.columns[1])))
            )
        });

        let (Some(rect_a), Some(rect_b)) = (rect_a, rect_b) else {
            continue;
        };

        let start_height = rect_a.center().y;
        let end_height = rect_b.center().y;

        if relation_segment[1] == 1 {
            verify_line_segment_joins(&mut relation.relation_segments, 0, start_height, end_height, selected, rela_idx);
        } else {
            verify_line_segment_joins(&mut relation.relation_segments, relation_segment[2], start_height, end_height, selected, rela_idx);
        }
    }

    app_state.relations = relations.clone();
    app_state.tables = tables.clone();
}

fn verify_line_segment_joins(
    segments: &mut Vec<f32>,
    seg_idx: usize,
    start_height: f32,
    end_height: f32,
    selected: &mut Vec<Selected>,
    rela_idx: usize,
) {
    const LINE_JOIN_LIMIT: f32 = 10.0;

    if seg_idx + 2 < segments.len() {
        if (segments[seg_idx] - segments[seg_idx + 2]).abs() < LINE_JOIN_LIMIT {
            segments.remove(seg_idx + 2);
            segments.remove(seg_idx + 1);
            selected.retain(|s| {
                !matches!(s,
                    Selected::Relation { relation, segment: Some(idx) }
                    if *relation == rela_idx && (*idx == seg_idx + 2 || *idx == seg_idx + 1)
                )
            });
            for select in selected.iter_mut() {
                match select {
                    Selected::Relation { relation, segment: Some(idx) } => {
                        if *relation == rela_idx && *idx > seg_idx {
                            *idx -= 2;
                        }
                    }
                    Selected::Relation { .. } => {}
                    Selected::Table { .. } => {}
                }
            }
        }
    }
    if seg_idx >= 2 && seg_idx < segments.len() {
        if (segments[seg_idx] - segments[seg_idx - 2]).abs() < LINE_JOIN_LIMIT {
            segments.remove(seg_idx - 1);
            segments.remove(seg_idx - 2);
            selected.retain(|s| {
                !matches!(s,
                    Selected::Relation { relation, segment: Some(idx) }
                    if *relation == rela_idx && (*idx == seg_idx - 1 || *idx == seg_idx - 2)
                )
            });
            for select in selected.iter_mut() {
                match select {
                    Selected::Relation { relation, segment: Some(idx) } => {
                        if *relation == rela_idx && *idx >= seg_idx {
                            *idx -= 2;
                        }
                    }
                    Selected::Relation { .. } => {}
                    Selected::Table { .. } => {}
                }
            }
        }
    }

    if !(segments.len() < 3) {
        if (segments[segments.len() - 2] - end_height).abs() < LINE_JOIN_LIMIT {
            segments.pop();
            segments.pop();
            selected.retain(|s| {
                !matches!(s,
                    Selected::Relation { relation, segment: Some(idx) }
                    if *relation == rela_idx && (*idx == segments.len() + 1 || *idx == segments.len())
                )
            });
        }
    }
    if !(segments.len() < 3) {
        if (segments[1] - start_height).abs() < LINE_JOIN_LIMIT {
            segments.remove(1);
            segments.remove(0);
            selected.retain(|s| {
                !matches!(s,
                    Selected::Relation { relation, segment: Some(idx) }
                    if *relation == rela_idx && (*idx == 1 || *idx == 0)
                )
            });
            for select in selected.iter_mut() {
                match select {
                    Selected::Relation { relation, segment: Some(idx) } => {
                        if *relation == rela_idx {
                            *idx -= 2;
                        }
                    }
                    Selected::Relation { .. } => {}
                    Selected::Table { .. } => {}
                }
            }
        }
    }
    if segments.len() == 1 && (start_height - end_height).abs() < LINE_JOIN_LIMIT {
        segments.clear();
    }

    let selected_segments = selected.iter().filter(|s| {
        matches!(s,
            Selected::Relation { relation, segment: Some(_) }
            if *relation == rela_idx
        )
    }).count();
    if selected_segments >= 1 && selected_segments >= segments.len() {
        selected.retain(|s| {
            !matches!(s,
                Selected::Relation { relation, segment: Some(_) }
                if *relation == rela_idx
            )
        });

        selected.push(Selected::Relation { relation: rela_idx, segment: None });
    }
}