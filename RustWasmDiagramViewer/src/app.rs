use egui::*;
use emath::*;

#[cfg(target_arch = "wasm32")]
use wasm_bindgen::prelude::*;

#[cfg(target_arch = "wasm32")]
#[wasm_bindgen]
extern "C" {
    #[wasm_bindgen(js_namespace = window)]
    fn saveDiagramState(json_data: &str);
}
// --- Estruturas ---

/// We derive Deserialize/Serialize so we can persist app state on shutdown.
//Default: Preenche com os valores por defeito se não receber do laravel, se for necessário valores diferentes dos default, implementar o Default com as traits
#[derive(serde::Deserialize, serde::Serialize)]
#[serde(default)]
pub struct TemplateApp {
    pub tables: Vec<Table>,
    pub relations: Vec<Relation>,
    #[serde(skip)] // Não presistir, não serializar para guardar no laravel
    pub schema_loaded: bool,
    #[serde(skip)]
    pub selected: Vec<Selected>,
}
#[derive(PartialEq)]
pub enum Selected {
    Table { table: usize, column: Option<usize> },
    Relation { relation: usize, segment: Option<usize> },
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
                            description: String::new()
                        },
                        Column {
                            name: String::from("name"),
                            column_type: String::from("VARCHAR2"),
                            nullable: false,
                            unique: false,
                            key_type: String::new(),
                            description: String::new()
                        }
                    ],
                    description: String::new()
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
                            description: String::new()
                        },
                        Column {
                            name: String::from("primeira_id"),
                            column_type: String::from("NUMBER"),
                            nullable: true,
                            unique: true,
                            key_type: String::from("FK"),
                            description: String::new()
                        },
                        Column {
                            name: String::from("terceira_id"),
                            column_type: String::from("NUMBER"),
                            nullable: false,
                            unique: false,
                            key_type: String::from("FK"),
                            description: String::new()
                        }
                    ],
                    description: String::new()
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
                            description: String::new()
                        },
                        Column {
                            name: String::from("idade"),
                            column_type: String::from("NUMBER"),
                            nullable: true,
                            unique: false,
                            key_type: String::new(),
                            description: String::new()
                        }
                    ],
                    description: String::new()
                }
            ],
            relations: vec![
                Relation {
                    name: String::new(),
                    relation_segments: vec![450.0],
                    tables: [1, 0],
                    columns: [1, 0],
                    description: String::new()
                },
                Relation {
                    name: String::new(),
                    relation_segments: vec![],
                    tables: [1, 2],
                    columns: [2, 0],
                    description: String::new()
                }
            ],
            schema_loaded: false,
            selected: Vec::new()
        }
    }
}
#[derive(serde::Deserialize, serde::Serialize, Default)]
#[serde(default)]
pub struct Table {
    pub name: String,
    pub pos: Pos2,
    pub columns: Vec<Column>,
    pub description: String,
}
#[derive(serde::Deserialize, serde::Serialize, Default)]
#[serde(default)]
pub struct Column {
    pub name: String,
    pub column_type: String,
    pub nullable: bool,
    pub unique: bool,
    pub description: String,
    pub key_type: String,
}

#[derive(serde::Deserialize, serde::Serialize ,Default)]
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
const TABLE_BG:     Color32 = Color32::from_rgb(22, 22, 25);
const TABLE_BORDER: Color32 = Color32::from_gray(55);
const HEADER_BG:    Color32 = Color32::from_rgb(28, 28, 33);
const HEADER_HOVER: Color32 = Color32::from_rgb(38, 38, 46);
const HEADER_TEXT:  Color32 = Color32::from_rgb(240, 240, 240);
const COL_NAME:     Color32 = Color32::from_rgb(228, 228, 228);
const COL_TYPE:     Color32 = Color32::from_gray(140);
const NULL_TEXT:    Color32 = Color32::from_rgb(155, 155, 175);
const NULL_BG:      Color32 = Color32::from_rgb(40, 40, 52);
const POPUP_BG:     Color32 = Color32::from_rgb(24, 24, 28);
const POPUP_BORDER: Color32 = Color32::from_gray(52);

// Constantes para definir tamanhos dos elementos das tabelas
const HEADER_SIZE: f32 = 32.0;
const COL_SIZE: f32 = 26.0;

// --- Implementações das estruturas ---

impl Table {
    pub fn ui(&mut self, ctx: &Context, id: usize, relations: &mut Vec<Relation>, scene_transform: TSTransform) {
        let table_width = ctx.fonts_mut(|f| {
            let header_width = f.layout_no_wrap(
                self.name.clone(),
                FontId::proportional(14.5),
                HEADER_TEXT,
            ).rect.width();

            let mut max_col_width: f32 = 0.0;

            for col in &self.columns {
                // Nome da coluna
                let name_w = f.layout_no_wrap(
                    col.name.clone(),
                    FontId::proportional(13.0),
                    COL_NAME,
                ).rect.width();

                // Tipo de dados
                let type_w = f.layout_no_wrap(
                    col.column_type.clone(),
                    FontId::proportional(11.5),
                    COL_TYPE,
                ).rect.width();

                // Adicionar +40 se o campo for nullable
                let null_w = if col.nullable {40.0} else {0.0};

                // Somar todas as widths +30 para ter um pouco de margem
                let total = name_w + null_w + type_w + 30.0;
                max_col_width = max_col_width.max(total);
            }

            300.0_f32.max(header_width.max(max_col_width))
        });

        let inner_window_response = Window::new(&self.name)
            .title_bar(false)
            .constrain(false)
            .frame(Frame::new().fill(TABLE_BG).stroke(Stroke::new(1.0 * scene_transform.scaling, TABLE_BORDER)).shadow(Shadow {offset: [0, 6], blur: 18, spread: 0, color: Color32::from_black_alpha(90)}))
            .fixed_rect(scene_transform.mul_rect(Rect::from_center_size(self.pos, vec2(table_width, 8.0 + HEADER_SIZE + COL_SIZE * self.columns.len() as f32))))
            .show(ctx, |ui| {
                ui.spacing_mut().item_spacing = Vec2::ZERO;
                let inner_response = Scene::new()
                    .zoom_range(Rangef::point(scene_transform.scaling))
                    .drag_pan_buttons(DragPanButtons::empty())
                    .show(ui, &mut Rect::from_pos((ui.available_size()/(2.0*scene_transform.scaling)).to_pos2()), |ui| {
                        self.header_ui(ui, table_width);
                        ui.add_space(2.0);
                        for column in self.columns.iter() {
                            column.ui(ui, table_width);
                        }
                        ui.add_space(6.0);
                    });

                if inner_response.response.dragged() {
                    self.pos += inner_response.response.drag_delta();
                    ctx.output_mut(|o| o.cursor_icon = CursorIcon::Grabbing);
                }
                if inner_response.response.drag_stopped() {
                    for relation in relations {
                        if relation.tables[0] == id || relation.tables[1] == id {
                            let (rect_a, rect_b) = ctx.data(|data| {
                                (
                                    data.get_temp::<Rect>(Id::new(("column_rect", relation.tables[0], relation.columns[0]))),
                                    data.get_temp::<Rect>(Id::new(("column_rect", relation.tables[1], relation.columns[1])))
                                )
                            });

                            let (Some(rect_a), Some(rect_b)) = (rect_a, rect_b) else {
                                continue;
                            };

                            verify_line_segment_joins(&mut relation.relation_segments, 0, scene_transform.inverse().mul_pos(rect_a.center()).y, scene_transform.inverse().mul_pos(rect_b.center()).y);
                            if relation.relation_segments.len() <= 1 && (rect_a.center().y - rect_b.center().y).abs() < 5.0 * scene_transform.scaling {
                                let adjust_y = scene_transform.inverse().mul_pos(rect_a.center()).y - scene_transform.inverse().mul_pos(rect_b.center()).y;
                                self.pos += if relation.tables[0] == id {vec2(0.0, -adjust_y)} else {vec2(0.0, adjust_y)};
                            }
                        }
                    }
                }
            });
        if let Some(inner_window_response) = inner_window_response {
            let table_rect = inner_window_response.response.rect;
            for (col_idx, _column) in self.columns.iter().enumerate() {
                let y = table_rect.top() + (HEADER_SIZE + 2.0 + (col_idx as f32 * COL_SIZE))*scene_transform.scaling;

                let col_rect = Rect::from_min_size(pos2(table_rect.left(), y), vec2(table_rect.width(), COL_SIZE*scene_transform.scaling));

                let column_rect_id = Id::new(("column_rect", id, col_idx));
                ctx.data_mut(|data| {
                    data.insert_temp(column_rect_id, col_rect);
                });
            }
        }
    }

    fn header_ui(&mut self, ui: &mut Ui, table_width: f32) {
        let (rect, response) = ui.allocate_exact_size(
            vec2(table_width, HEADER_SIZE),
            Sense::click(),
        );

        if response.hovered() { ui.output_mut(|o| o.cursor_icon = CursorIcon::PointingHand); }

        let bg = if response.hovered() { HEADER_HOVER } else { HEADER_BG };
        ui.painter().rect_filled(rect, CornerRadius::ZERO, bg);
        ui.painter().text(rect.center(), Align2::CENTER_CENTER, &self.name, FontId::proportional(14.0), HEADER_TEXT);

        Popup::menu(&response)
            .frame(popup_frame())
            .width(260.0)
            .show(|ui| {
                ui.spacing_mut().item_spacing.y = 3.0;
                ui.label(RichText::new(&self.name).size(15.0).strong().color(HEADER_TEXT));
                ui.label(
                    RichText::new(format!("{} columns", self.columns.len()))
                        .size(11.5)
                        .color(Color32::from_gray(105)),
                );
                popup_divider(ui);
                popup_description(ui, &self.description);
            });
    }
}

impl Column {
    fn ui(&self, ui: &mut Ui, table_width: f32) {
        let (rect, response) = ui.allocate_exact_size(
            vec2(table_width, COL_SIZE),
            Sense::click(),
        );

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
                key_color
            );

            // Ajustar o nome da coluna para a direita
            left_x += key_galley.rect.width() + 6.0;
        }

        // Nome do campo
        painter.text(
            pos2(left_x, rect.center().y),
            Align2::LEFT_CENTER,
            &self.name,
            FontId::proportional(13.0),
            COL_NAME,
        );

        let mut right_x = rect.right() - 10.0;

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
            let null_galley = painter.layout_no_wrap(
                "NULL".to_owned(),
                FontId::monospace(10.0),
                NULL_TEXT,
            );
            let pad = vec2(4.0, 2.0);
            let badge_size = null_galley.rect.size() + pad * 2.0;
            let badge_rect = Rect::from_min_size(
                pos2(right_x - badge_size.x, rect.center().y - badge_size.y * 0.5),
                badge_size,
            );
            painter.rect_filled(badge_rect, CornerRadius::same(3), NULL_BG);
            painter.galley(badge_rect.min + pad, null_galley, NULL_TEXT);
        }
        
        Popup::menu(&response)
            .frame(popup_frame())
            .width(280.0)
            .show(|ui| {
                ui.spacing_mut().item_spacing.y = 3.0;

                ui.horizontal(|ui| {
                    ui.label(RichText::new(&self.name).size(14.5).strong().color(HEADER_TEXT));
                    ui.with_layout(Layout::right_to_left(Align::Center), |ui| {
                        ui.label(
                            RichText::new(&self.column_type)
                                .size(11.5)
                                .monospace()
                                .color(Color32::from_gray(120)));
                    });
                });

                ui.horizontal(|ui| {
                    ui.add_space(1.0);
                    let (dot_rect, _) = ui.allocate_exact_size(vec2(10.0, 14.0), Sense::hover());
                    let dot_color = if self.nullable {
                        Color32::from_rgb(100, 160, 100)
                    } else {
                        Color32::from_rgb(180, 85, 85)
                    };
                    ui.painter().circle_filled(dot_rect.center(), 3.0, dot_color);
                    ui.label(
                        RichText::new(if self.nullable { "nullable" } else { "not null" })
                            .size(11.5)
                            .color(Color32::from_gray(130)));
                });

                popup_divider(ui);
                popup_description(ui, &self.description);
            });
    }
}
fn popup_frame() -> Frame {
    Frame::new()
        .fill(POPUP_BG)
        .stroke(Stroke::new(1.0, POPUP_BORDER))
        .corner_radius(8.0)
        .inner_margin(Margin::same(14))
        .shadow(Shadow {
            offset: [0, 6],
            blur: 18,
            spread: 0,
            color: Color32::from_black_alpha(90),
        })
}

/// Standardized description renderer
fn popup_description(ui: &mut Ui, description: &str) {
    if description.is_empty() {
        ui.label(
            RichText::new("No description.")
                .size(12.5)
                .italics()
                .color(Color32::from_gray(95))
        );
    } else {
        ui.label(
            RichText::new(description)
                .size(12.5)
                .color(Color32::from_gray(185))
        );
    }
}
fn popup_divider(ui: &mut Ui) {
    ui.add_space(7.0);
    let (rect, _) = ui.allocate_exact_size(vec2(ui.available_width(), 1.0), Sense::hover());
    ui.painter().rect_filled(rect, CornerRadius::ZERO, Color32::from_gray(42));
    ui.add_space(7.0);
}


impl TemplateApp {
    /// Called once before the first frame.
    pub fn new(cc: &eframe::CreationContext<'_>, json_data: String) -> Self {
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
                    return app_state;
                }
                Err(e) => log::error!("Failed to parse JSON: {}", e),
            }
        }

        app.apply_auto_layout();
        app
    }

    // Cria o layout inicial
    fn apply_auto_layout(&mut self) {
        // Criar apenas se estiverem na posicao default (0.0)
        let needs_layout = self.tables.first().map_or(false, |t| t.pos == pos2(0.0, 0.0));

        if needs_layout {
            let mut x = 200.0;
            let mut y = 200.0;
            for table in &mut self.tables {
                table.pos = pos2(x, y);
                x += 350.0;
                if x > 1200.0 { x = 50.0; y += 350.0; }
            }
        }
    }

    fn ui_top_bar(&mut self, ctx: &Context, ui: &mut Ui) {
        ui.horizontal(|ui| {
            if ui.add(Button::new(RichText::new("Reset Canvas").color(Color32::RED))).clicked() {
                *self = Default::default();
                ctx.memory_mut(|mem| *mem = Default::default());
            }

            if !self.schema_loaded {
                ui.label(RichText::new("Nenhum diagrama carregado.").color(Color32::from_rgb(180, 80, 80)));
            } else {
                ui.label(RichText::new(format!("{} tabelas", self.tables.len())).color(Color32::from_rgb(80, 180, 80)));
            }

            // Botão para guardar diagrama
            ui.with_layout(Layout::right_to_left(Align::Center), |ui| {
                if ui.button("💾 Guardar Diagrama").clicked() {
                    match serde_json::to_string(self) {
                        Ok(json_string) => {
                            #[cfg(target_arch = "wasm32")]
                            saveDiagramState(&json_string);
                        }
                        Err(e) => {
                            tracing::error!("Erro ao serializar o diagrama: {}", e);
                        }
                    }
                }
            });
        });
    }
    fn draw_relations(&mut self, ui: &mut Ui, painter: &Painter, scene_transform: TSTransform) {
        let line_width: f32 = 2.5 * scene_transform.scaling;
        let table_proximity_limit: f32 = 20.0 * scene_transform.scaling;
        let notation_size: f32 = 8.0 * scene_transform.scaling;
        let interact_hitbox_size: f32 = 14.0 * scene_transform.scaling;

        let mut relation_segments_to_change: Vec<[usize; 3]> = Vec::new();//relationID/fullRelationBinary/segmentID
        let mut delta_used = Vec2::ZERO;

        for (rela_idx, relation) in self.relations.iter_mut().enumerate() {
            let line_stroke = if self.selected.contains(&Selected::Relation { relation: rela_idx, segment: None }) {
                    Stroke::new(line_width, Color32::BLUE)
                } else {
                    Stroke::new(line_width, Color32::from_gray(80))
                };

            // Obter os retângulos para ligar a relação
            let (rect_a, rect_b) = ui.ctx().data(|data| {
                (
                    data.get_temp::<Rect>(
                        Id::new(("column_rect", relation.tables[0], relation.columns[0]))
                    ),
                    data.get_temp::<Rect>(
                        Id::new(("column_rect", relation.tables[1], relation.columns[1]))
                    )
                )
            });

            let (Some(rect_a), Some(rect_b)) = (rect_a, rect_b) else {
                continue;
            };

            // Cálculos base para as posições
            let start = rect_a.center();
            let start_offset = rect_a.width() / 2.0;

            let end = rect_b.center();
            let end_offset = rect_b.width() / 2.0;

            let front_line = relation.relation_segments.len() <= 1 && (start.y - end.y).abs() < 5.0 * scene_transform.scaling;
            let auto_align = relation.relation_segments.is_empty();
            let x_align = (start.x + end.x) / 2.0;

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
            let enough_space = (start.x - end.x).abs() > start_offset + end_offset + table_proximity_limit * 2.0;

            // --- Primeira Tabela FK ---
            let start_goes_left = (if enough_space { pts[0].x } else { x_align }) > pts[1].x;

            let start_dir = if start_goes_left { -1.0 } else { 1.0 };

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

            if self.tables[relation.tables[0]].columns[relation.columns[0]].unique {
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

            // --- Segunda Tabela PK --
            let end_goes_left = if enough_space {pts[last_idx].x} else {x_align} > pts[last_idx-1].x;
            let end_dir = if end_goes_left { -1.0 } else { 1.0 };

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

            if self.tables[relation.tables[0]].columns[relation.columns[0]].nullable {
                // Desenhar a notação Zero
                let crow_base_start = pts[last_idx] + vec2(end_dir * (table_proximity_limit / 2.0 - notation_size/2.0), 0.0);
                let crow_base_end = pts[last_idx] + vec2(end_dir * (table_proximity_limit / 2.0 + notation_size/2.0), 0.0);
                painter.line_segment([pts[last_idx], crow_base_start], line_stroke);
                painter.line_segment([crow_base_end, pts[last_idx-1] + vec2(end_dir * line_width/2.0, 0.0)], line_stroke);
                painter.circle_stroke(pts[last_idx] + vec2(end_dir * table_proximity_limit / 2.0, 0.0), notation_size/2.0, line_stroke);
            } else {
                // Desenhar a notação One
                painter.line_segment([pts[last_idx], pts[last_idx-1] + vec2(end_dir * line_width/2.0, 0.0)], line_stroke);
                let crow_up_base = pts[last_idx] + vec2(end_dir * table_proximity_limit / 3.0, notation_size);
                let down_up_base = pts[last_idx] + vec2(end_dir * table_proximity_limit / 3.0, - notation_size);
                painter.line_segment([crow_up_base, down_up_base], line_stroke);
            }

            // Desenhar a linha completa
            if !front_line {painter.line(pts[0..last_idx].to_vec(), line_stroke);}

            // Segmentos
            for (seg_idx, pair) in pts[1..last_idx].windows(2).enumerate() {
                let (p1, p2) = (pair[0], pair[1]);
                let is_vertical = seg_idx % 2 == 0;

                let seg_id = ui.id().with(("seg", rela_idx, seg_idx));

                // Area visual, largura da linha
                let visual_rect = Rect::from_two_pos(p1, p2).expand(line_width / 2.0);

                // Expandir a area para clicar
                let hit_padding = if is_vertical { vec2(3.0, 0.0) } else { vec2(0.0, 3.0) };
                let interact_rect = visual_rect.expand2(hit_padding);

                let seg_response = ui.interact(interact_rect, seg_id, Sense::click_and_drag());
                let popup_id = ui.id().with(("popup", rela_idx, seg_idx));

                if seg_response.clicked() {
                    if !ui.input(|i| {i.modifiers.command_only()}) {self.selected.clear();}

                    if self.selected.contains(&Selected::Relation { relation: rela_idx, segment: None }) {
                        self.selected.retain(|s| {
                            !matches!(s,
                                Selected::Relation { relation, segment: None }
                                if *relation == rela_idx
                            )
                        });
                        if auto_align { self.selected.push(Selected::Relation { relation: rela_idx, segment: Some(seg_idx) }); } else {
                            for (selected_segment_idx, _) in relation.relation_segments.iter().enumerate() {
                                self.selected.push(Selected::Relation { relation: rela_idx, segment: Some(selected_segment_idx) });
                            }
                        }
                    }

                    let item = Selected::Relation { relation: rela_idx, segment: Some(seg_idx) };
                    if self.selected.contains(&item) {
                        self.selected.retain(|s| s != &item);
                    } else {
                        self.selected.push(item);
                    }

                    let selected_segments = self.selected.iter().filter(|s| {
                        matches!(s,
                            Selected::Relation { relation, segment: Some(_) }
                            if *relation == rela_idx
                        )
                    }).count();

                    if selected_segments == last_idx-2 {
                        self.selected.retain(|s| {
                            !matches!(s,
                                Selected::Relation { relation, segment: Some(_) }
                                if *relation == rela_idx
                            )
                        });

                        self.selected.push(Selected::Relation { relation: rela_idx, segment: None });
                    }
                }

                if self.selected.contains(&Selected::Relation { relation: rela_idx, segment: Some(seg_idx) }) || self.selected.contains(&Selected::Relation { relation: rela_idx, segment: None }) {
                    painter.rect_filled(visual_rect, CornerRadius::ZERO, Color32::BLUE);
                } else if seg_response.hovered() {
                    painter.rect_filled(visual_rect, CornerRadius::ZERO, Color32::from_gray(160));
                }

                Popup::menu(&seg_response).id(popup_id).show(|ui| {
                    if ui.button("⟳").clicked() {
                        relation.relation_segments.clear();
                        self.selected.clear();
                    }
                });

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
                    let delta = seg_response.drag_delta() / scene_transform.scaling;
                    delta_used = delta;
                    for selected in self.selected.iter() {
                        match selected {
                            Selected::Table { table, .. } => {
                                self.tables[*table].pos += delta;
                            },
                            Selected::Relation { relation, segment } => {
                                match segment {
                                    None => {
                                        relation_segments_to_change.push([*relation, 1, if *relation == rela_idx {seg_idx} else {usize::MAX}]);
                                    },
                                    Some(selected_seg_idx) => {
                                        if *relation == rela_idx && *selected_seg_idx == seg_idx {continue;}
                                        relation_segments_to_change.push([*relation, 0, *selected_seg_idx]);
                                    }
                                }
                            }
                        }
                    }
                    relation.relation_segments[seg_idx] += if is_vertical { delta.x } else { delta.y };
                }

                // --- Dividir linha ---
                if seg_response.secondary_clicked() {
                    let mid = (if is_vertical { (p1.y + p2.y) / 2.0 - scene_transform.translation.y } else { (p1.x + p2.x) / 2.0 - scene_transform.translation.x }) / scene_transform.scaling;
                    let next = (if is_vertical { (p2.x + pts[seg_idx + 3].x) / 2.0 - scene_transform.translation.x } else { (p2.y + pts[seg_idx + 3].y) / 2.0 - scene_transform.translation.y }) / scene_transform.scaling;
                    relation.relation_segments.insert(seg_idx + 1, mid);
                    relation.relation_segments.insert(seg_idx + 2, next);
                }

                let (start, end) = (scene_transform.inverse().mul_pos(start), scene_transform.inverse().mul_pos(end));

                if seg_response.drag_stopped() {
                    verify_line_segment_joins(&mut relation.relation_segments, seg_idx, start.y, end.y);
                }

                if seg_idx != 0 {
                    let pt_id = ui.id().with(("pt", rela_idx, seg_idx));
                    let pt_rect = Rect::from_center_size(p1, vec2(interact_hitbox_size, interact_hitbox_size));
                    let pt_response = ui.interact(pt_rect, pt_id, Sense::click_and_drag());
                    let pt_popup_id = ui.id().with(("popup_pt", rela_idx, seg_idx));

                    Popup::menu(&pt_response).id(pt_popup_id).show(|ui| {
                        if ui.button("⟳").clicked() {
                            relation.relation_segments.clear();
                        }
                    });

                    if pt_response.drag_started() || pt_response.secondary_clicked() || pt_response.drag_stopped() {
                        let pt_real_center = scene_transform.inverse().mul_pos(pt_rect.center());
                        relation.relation_segments[seg_idx - 1] = if is_vertical { pt_real_center.y } else { pt_real_center.x };
                        relation.relation_segments[seg_idx]     = if is_vertical { pt_real_center.x } else { pt_real_center.y };
                        Popup::open_id(ui.ctx(), pt_popup_id);
                    }

                    if pt_response.hovered() {
                        painter.circle_filled(p1, 4.5 * scene_transform.scaling, Color32::from_gray(130));

                        if pt_response.dragged() {
                            let delta_prev = if is_vertical { pt_response.drag_delta().y } else { pt_response.drag_delta().x };
                            let delta_curr = if is_vertical { pt_response.drag_delta().x } else { pt_response.drag_delta().y };

                            relation.relation_segments[seg_idx - 1] += delta_prev / scene_transform.scaling;
                            relation.relation_segments[seg_idx]     += delta_curr / scene_transform.scaling;
                        }
                    }

                    if pt_response.secondary_clicked() {
                        relation.relation_segments.remove(seg_idx);
                        relation.relation_segments.remove(seg_idx - 1);
                    }

                    if pt_response.drag_stopped() {
                        verify_line_segment_joins(&mut relation.relation_segments, seg_idx, start.y, end.y);
                        verify_line_segment_joins(&mut relation.relation_segments, seg_idx - 1, start.y, end.y);
                    }
                }
            }
        }
        
        for relation_segment in relation_segments_to_change.iter() {
            if relation_segment[1] == 1 {
                for (segment_idx, segment) in self.relations[relation_segment[0]].relation_segments.iter_mut().enumerate() {
                    if segment_idx == relation_segment[2] {continue;}
                    *segment += if segment_idx % 2 == 0 {delta_used.x} else {delta_used.y};
                }
            } else {
                self.relations[relation_segment[0]].relation_segments[relation_segment[2]] += if relation_segment[2] % 2 == 0 {delta_used.x} else {delta_used.y};
            }
        }
    }
}

impl eframe::App for TemplateApp {
    /// Chamada sempre que o UI necessita de ser desenhado outra vez (60x por segundo)
    fn update(&mut self, ctx: &Context, _frame: &mut eframe::Frame) {
        TopBottomPanel::top("top_panel").show(ctx, |ui| {
            self.ui_top_bar(ctx, ui);

            //ui.separator();
        });
        CentralPanel::default().show(ctx, |ui| {

            let mut scene_transform = ui.ctx().data(|d| {
                match d.get_temp(Id::new("scene_transform")) {
                    Some(scene_transform) => scene_transform,
                    None => TSTransform::default(),
                }
            });

            let (mut bg_response, painter) = ui.allocate_painter(ui.available_size(), Sense::click_and_drag());

            // Desativar scrolling fora do background
            if !bg_response.hovered() {
                ctx.input_mut(|i| {
                    i.smooth_scroll_delta = Vec2::ZERO;
                });
            }

            // Remover todos os objetos selecionados da lista
            if bg_response.clicked() {
                self.selected.clear();
            }

            Window::new("Inspector")
                .show(ctx, |ui| {

                });

            // Colocar o background a controlar a Scene (PanAndDrag)
            Scene::new().drag_pan_buttons(DragPanButtons::all().difference(DragPanButtons::PRIMARY))
                .zoom_range(Rangef::new(0.5, 2.0))
                .register_pan_and_zoom(ui, &mut bg_response, &mut scene_transform);

            // Controlar zoom com uma barra lateral
            Area::new(Id::new("DragValue_zoom")).anchor(Align2::RIGHT_CENTER, vec2(-100.0, 0.0)).show(ctx, |ui|{
                let center_vec = bg_response.rect.center().to_vec2();
                let old_scale = scene_transform.scaling;

                ui.add(Slider::new(&mut scene_transform.scaling, 0.5..=2.0).vertical());
                if old_scale != scene_transform.scaling {
                    let world_vec = (center_vec - scene_transform.translation) / old_scale;
                    scene_transform.translation += world_vec * (old_scale-scene_transform.scaling);
                }
            });

            // Desenhar as tabelas
            for (i, table) in self.tables.iter_mut().enumerate() {
                table.ui(ctx, i, &mut self.relations, scene_transform);
            }

            // Desenhar as linhas das relações
            self.draw_relations(ui, &painter, scene_transform);

            ui.ctx().data_mut(|d| {
                d.insert_temp(Id::new("scene_transform"), scene_transform);
            })
        });
    }

    /// Guarda o estado da app antes de ser terminada
    fn save(&mut self, storage: &mut dyn eframe::Storage) {
        eframe::set_value(storage, eframe::APP_KEY, self);
    }
}

fn verify_line_segment_joins(segments: &mut Vec<f32>, seg_idx: usize, start_height: f32, end_height: f32) {
    const LINE_JOIN_LIMIT: f32 = 10.0;

    if seg_idx + 2 < segments.len() {
        if (segments[seg_idx] - segments[seg_idx + 2]).abs() < LINE_JOIN_LIMIT {
            segments.remove(seg_idx + 2);
            segments.remove(seg_idx + 1);
        }
    }
    if seg_idx >= 2 && seg_idx < segments.len() {
        if (segments[seg_idx] - segments[seg_idx - 2]).abs() < LINE_JOIN_LIMIT {
            segments.remove(seg_idx - 1);
            segments.remove(seg_idx - 2);
        }
    }

    if segments.len() < 3 {
        return;
    }
    if (segments[segments.len()-2] - end_height).abs() < LINE_JOIN_LIMIT {
        segments.pop();
        segments.pop();
    }
    if segments.len() < 3 {
        return;
    }
    if (segments[1] - start_height).abs() < LINE_JOIN_LIMIT {
        segments.remove(1);
        segments.remove(0);
    }
}