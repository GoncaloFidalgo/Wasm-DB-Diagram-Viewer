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
                            key_type: String::from("PK"),
                            description: String::new()
                        },
                        Column {
                            name: String::from("name"),
                            column_type: String::from("VARCHAR2"),
                            nullable: false,
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
                            key_type: String::from("PK"),
                            description: String::new()
                        },
                        Column {
                            name: String::from("primeira_id"),
                            column_type: String::from("NUMBER"),
                            nullable: false,
                            key_type: String::from("FK"),
                            description: String::new()
                        },
                        Column {
                            name: String::from("terceira_id"),
                            column_type: String::from("NUMBER"),
                            nullable: false,
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
                            key_type: String::from("PK"),
                            description: String::new()
                        },
                        Column {
                            name: String::from("idade"),
                            column_type: String::from("NUMBER"),
                            nullable: true,
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
            schema_loaded: false
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
const DIVIDER:      Color32 = Color32::from_gray(48);
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
        let area_response = Area::new(Id::new(("table", id)))
            .constrain(false)
            .pivot(Align2::CENTER_CENTER)
            .current_pos(scene_transform.mul_pos(self.pos))
            .show(ctx, |ui| {
                let table_width = ui.fonts_mut(|f| {
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
                Frame::new()
                    .fill(TABLE_BG)
                    .stroke(Stroke::new(1.0, TABLE_BORDER))
                    .corner_radius(8.0)
                    .inner_margin(Margin::same(0))
                    .shadow(Shadow {
                        offset: [0, 6],
                        blur: 18,
                        spread: 0,
                        color: Color32::from_black_alpha(90),
                    })
                    .show(ui, |ui| {
                        ui.set_width(table_width);
                        self.header_ui(ui);
                        ui.add_space(2.0);
                        for (col_idx, column) in self.columns.iter().enumerate() {
                            column.ui(ui, id, col_idx);
                        }
                        ui.add_space(6.0);
                    });
            });
        
        if area_response.response.dragged() {
            self.pos += area_response.response.drag_delta()/scene_transform.scaling;
            ctx.output_mut(|o| o.cursor_icon = CursorIcon::Grabbing);
        }
        if area_response.response.drag_stopped() {
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

                    verify_line_segment_joins(&mut relation.relation_segments, 0, rect_a.center().y, rect_b.center().y);
                }
            }
        }
    }

    fn header_ui(&mut self, ui: &mut Ui) {
        let (rect, response) = ui.allocate_exact_size(
            vec2(ui.available_width(), HEADER_SIZE),
            Sense::click(),
        );

        if response.hovered() {
            ui.output_mut(|o| o.cursor_icon = CursorIcon::PointingHand);
        }

        let bg = if response.hovered() { HEADER_HOVER } else { HEADER_BG };
        ui.painter().rect_filled(
            rect,
            CornerRadius { nw: 8, ne: 8, sw: 0, se: 0 },
            bg,
        );

        ui.painter().text(
            rect.center(),
            Align2::CENTER_CENTER,
            &self.name,
            FontId::proportional(14.0),
            HEADER_TEXT,
        );

        ui.painter().line_segment(
            [pos2(rect.left(), rect.bottom()), pos2(rect.right(), rect.bottom())],
            Stroke::new(1.0, DIVIDER),
        );

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
    fn ui(&self, ui: &mut Ui, table_id: usize, col_id: usize) {
        let (rect, response) = ui.allocate_exact_size(
            vec2(ui.available_width(), COL_SIZE),
            Sense::click(),
        );

        let id = Id::new(("column_rect", table_id, col_id));
        ui.ctx().data_mut(|data| {
            data.insert_temp(id, rect);
        });

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
            let mut x = 100.0;
            let mut y = 100.0;
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
    fn draw_relations(&mut self, ui: &mut Ui, painter: &Painter) {
        const LINE_WIDTH: f32 = 2.5;
        const TABLE_PROXIMITY_LIMIT: f32 = 20.0;
        const NOTATION_SIZE: f32 = 8.0;
        const INTERACT_HITBOX_SIZE: f32 = 14.0;

        let line_stroke = Stroke::new(LINE_WIDTH, Color32::from_gray(80));


        for (rela_idx, relation) in self.relations.iter_mut().enumerate() {
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
            let auto_align = relation.relation_segments.is_empty();

            let start = rect_a.center();
            let start_offset = rect_a.width() / 2.0;

            let end = rect_b.center();
            let end_offset = rect_b.width() / 2.0;

            let x_align = (start.x + end.x) / 2.0;

            // Criar pontos inicias para o caminho da relação
            let mut pts = Vec::from([start]);

            if auto_align {
                pts.push(pos2(x_align, start.y));
                pts.push(pos2(x_align, end.y));
            } else {
                pts.push(pos2(relation.relation_segments[0], start.y));
                for (i, seg) in relation.relation_segments.windows(2).enumerate() {
                    pts.push(if i % 2 == 0 {
                        pos2(seg[0], seg[1])
                    } else {
                        pos2(seg[1], seg[0])
                    });
                }
                pts.push(pos2(*relation.relation_segments.last().unwrap(), end.y));
            }
            pts.push(end);

            // Ajustar posição da linha com base no limite dos retangulos das tabelas e desenhar as notações

            let last_idx = pts.len() - 1;
            let enough_space = (start.x - end.x).abs() > start_offset + end_offset + TABLE_PROXIMITY_LIMIT * 2.0;

            // --- Primeira Tabela FK ---
            let start_goes_left = (if enough_space { pts[0].x } else { x_align }) > pts[1].x;

            let start_dir = if start_goes_left { -1.0 } else { 1.0 };

            pts[0].x += start_dir * start_offset;
            let new_start_x = if start_goes_left {
                pts[1].x.min(pts[0].x - TABLE_PROXIMITY_LIMIT)
            } else {
                pts[1].x.max(pts[0].x + TABLE_PROXIMITY_LIMIT)
            };
            pts[1].x = new_start_x;
            pts[2].x = new_start_x;

            // Desenhar a notação Many
            let crow_base = pts[0] + vec2(start_dir * TABLE_PROXIMITY_LIMIT / 1.5, 0.0);
            painter.line_segment([crow_base, pts[0] + vec2(0.0, NOTATION_SIZE)], line_stroke);
            painter.line_segment([crow_base, pts[0] + vec2(0.0, - NOTATION_SIZE)], line_stroke);

            // --- Segunda Tabela PK --
            let end_goes_left = if enough_space {pts[last_idx].x} else {x_align} > pts[last_idx-1].x;
            let end_dir = if end_goes_left { -1.0 } else { 1.0 };

            pts[last_idx].x += end_dir * end_offset;
            let new_end_x = if end_goes_left {
                pts[last_idx - 1].x.min(pts[last_idx].x - TABLE_PROXIMITY_LIMIT)
            } else {
                pts[last_idx - 1].x.max(pts[last_idx].x + TABLE_PROXIMITY_LIMIT)
            };
            pts[last_idx - 1].x = new_end_x;
            pts[last_idx - 2].x = new_end_x;

            // Desenhar notação One
            let pk_base = pts[last_idx] + vec2(end_dir * TABLE_PROXIMITY_LIMIT / 3.0, NOTATION_SIZE);
            painter.line_segment([pk_base, pts[last_idx] + vec2(end_dir * TABLE_PROXIMITY_LIMIT / 3.0, - NOTATION_SIZE)], line_stroke);

            // Desenhar a linha completa
            painter.line(pts.clone(), line_stroke);

            // Segmentos
            for (seg_idx, pair) in pts[1..last_idx].windows(2).enumerate() {
                let (p1, p2) = (pair[0], pair[1]);
                let is_vertical = seg_idx % 2 == 0;

                let seg_id = ui.id().with(("seg", rela_idx, seg_idx));

                // Area visual, largura da linha
                let visual_rect = Rect::from_two_pos(p1, p2).expand(LINE_WIDTH / 2.0);

                // Expandir a area para clicar
                let hit_padding = if is_vertical { vec2(3.0, 0.0) } else { vec2(0.0, 3.0) };
                let interact_rect = visual_rect.expand2(hit_padding);

                let seg_response = ui.interact(interact_rect, seg_id, Sense::click_and_drag());
                let popup_id = ui.id().with(("popup", rela_idx, seg_idx));

                if seg_response.hovered() {
                    painter.rect_filled(visual_rect, CornerRadius::ZERO, Color32::from_gray(160));
                }

                Popup::menu(&seg_response).id(popup_id).show(|ui| {
                    if ui.button("⟳").clicked() {
                        relation.relation_segments.clear();
                    }
                });

                // --- Mudanças de estado (Start / End Drag / Right Click) ---
                if seg_response.drag_started() || seg_response.secondary_clicked() || seg_response.drag_stopped() {
                    if auto_align {
                        relation.relation_segments.push(interact_rect.center().x);
                    }
                    relation.relation_segments[seg_idx] = if is_vertical { interact_rect.center().x } else { interact_rect.center().y };

                    Popup::open_id(ui.ctx(), popup_id);
                }

                // --- Arrastar ---
                if seg_response.dragged() {
                    let delta = if is_vertical { seg_response.drag_delta().x } else { seg_response.drag_delta().y };
                    relation.relation_segments[seg_idx] += delta;
                }

                // --- Dividir linha ---
                if seg_response.secondary_clicked() {
                    let mid = if is_vertical { (p1.y + p2.y) / 2.0 } else { (p1.x + p2.x) / 2.0 };
                    let next = if is_vertical { (p2.x + pts[seg_idx + 3].x) / 2.0 } else { (p2.y + pts[seg_idx + 3].y) / 2.0 };
                    relation.relation_segments.insert(seg_idx + 1, mid);
                    relation.relation_segments.insert(seg_idx + 2, next);
                }

                if seg_response.drag_stopped() {
                    verify_line_segment_joins(&mut relation.relation_segments, seg_idx, start.y, end.y);
                }

                if seg_idx != 0 {
                    let pt_id = ui.id().with(("pt", rela_idx, seg_idx));
                    let pt_rect = Rect::from_center_size(p1, vec2(INTERACT_HITBOX_SIZE, INTERACT_HITBOX_SIZE));
                    let pt_response = ui.interact(pt_rect, pt_id, Sense::click_and_drag());
                    let pt_popup_id = ui.id().with(("popup_pt", rela_idx, seg_idx));

                    Popup::menu(&pt_response).id(pt_popup_id).show(|ui| {
                        if ui.button("⟳").clicked() {
                            relation.relation_segments.clear();
                        }
                    });

                    if pt_response.drag_started() || pt_response.secondary_clicked() || pt_response.drag_stopped() {
                        relation.relation_segments[seg_idx - 1] = if is_vertical { pt_rect.center().y } else { pt_rect.center().x };
                        relation.relation_segments[seg_idx]     = if is_vertical { pt_rect.center().x } else { pt_rect.center().y };
                        Popup::open_id(ui.ctx(), pt_popup_id);
                    }

                    if pt_response.hovered() {
                        painter.circle_filled(p1, 4.5, Color32::from_gray(130));

                        if pt_response.dragged() {
                            let delta_prev = if is_vertical { pt_response.drag_delta().y } else { pt_response.drag_delta().x };
                            let delta_curr = if is_vertical { pt_response.drag_delta().x } else { pt_response.drag_delta().y };

                            relation.relation_segments[seg_idx - 1] += delta_prev;
                            relation.relation_segments[seg_idx]     += delta_curr;
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

            Scene::new().drag_pan_buttons(DragPanButtons::all().difference(DragPanButtons::PRIMARY))
                .zoom_range(Rangef::new(0.5, 2.0))
                .register_pan_and_zoom(ui, &mut bg_response, &mut scene_transform);

            // Desenhar as tabelas
            for (i, table) in self.tables.iter_mut().enumerate() {
                table.ui(ctx, i, &mut self.relations, scene_transform);
            }

            /* Window::new("testeee")
                .title_bar(false)
                .constrain(false)
                .pivot(Align2::CENTER_CENTER)
                .frame(Frame::new().fill(Color32::BLUE))
                .fixed_rect(scene_transform.mul_rect(Rect::from_center_size(pos2(1000.0, 500.0), vec2(200.0, 200.0))))
                .show(ctx, |ui| {
                    ui.spacing_mut().item_spacing = Vec2::ZERO;
                    let inner_response = Scene::new()
                        .zoom_range(Rangef::point(scene_transform.scaling))
                        .drag_pan_buttons(DragPanButtons::empty())
                        .show(ui, &mut Rect::from_pos((ui.available_size()/(2.0*scene_transform.scaling)).to_pos2()), |ui| {
                            ui.button("atoms");
                            ui.label("alou");
                            ui.label("variable pointer");
                    });
                    if inner_response.response.dragged() {
                        self.tables[0].pos += inner_response.response.drag_delta();
                    }
                }); */

            // Desenhar as linhas das relações
            self.draw_relations(ui, &painter);

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