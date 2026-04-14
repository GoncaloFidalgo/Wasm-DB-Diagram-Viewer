use egui::*;

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
                            name: String::from("segunda_id"),
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
    pub fn ui(&mut self, ctx: &Context, id: usize, relations: &mut Vec<Relation>) {
        let area_response = Area::new(Id::new(("table", id)))
            .constrain(false)
            .movable(true)
            .pivot(Align2::CENTER_CENTER)
            .default_pos(self.pos)
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

        self.pos = area_response.response.rect.min;
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
        });
    }
    
    fn draw_relations(&mut self, ui: &mut Ui, painter: &Painter) {
        // Desenhar as linhas
        const LINE_WIDTH: f32 = 2.5;
        const TABLE_PROXIMITY_LIMIT: f32 = 20.0;
        let line_stroke: Stroke = Stroke::new(LINE_WIDTH, Color32::from_gray(80));
        for (rela_idx, relation) in self.relations.iter_mut().enumerate() {
            let (rect_a, rect_b) = ui.ctx().data(|data| {
                (
                    data.get_temp::<Rect>(Id::new(("column_rect", relation.tables[0], relation.columns[0]))),
                    data.get_temp::<Rect>(Id::new(("column_rect", relation.tables[1], relation.columns[1])))
                )
            });

            let (Some(rect_a), Some(rect_b)) = (rect_a, rect_b) else {
                continue;
            };

            let auto_align = relation.relation_segments.is_empty();

            let start = rect_a.center();
            let start_offset = rect_a.width()/2.0;
            let end = rect_b.center();
            let end_offset = rect_b.width()/2.0;

            let x_align = (start.x + end.x)/2.0;

            let mut pts = Vec::new();
            pts.push(start);
            if !auto_align {
                pts.push(pos2(relation.relation_segments[0], start.y));

                for (i, seg) in relation.relation_segments.windows(2).enumerate() {
                    pts.push(if i % 2 == 0 {
                        pos2(seg[0], seg[1]) }
                    else {
                        pos2(seg[1], seg[0])
                    });
                }

                pts.push(pos2(*relation.relation_segments.last().unwrap(), end.y));
            } else {
                pts.push(pos2(x_align, start.y));
                pts.push(pos2(x_align, end.y));
            }
            pts.push(end);

            // Change line position based on tables x and draw notation
            let last_idx = pts.len()-1;
            let enough_space = (start.x-end.x).abs() > start_offset + end_offset + TABLE_PROXIMITY_LIMIT*2.0;

            // First table
            let left_line = if enough_space {pts[0].x} else {x_align} > pts[1].x;
            pts[0].x += if left_line {-start_offset} else {start_offset};
            let new_x = if left_line {pts[1].x.min(pts[0].x-TABLE_PROXIMITY_LIMIT)}
                else {pts[1].x.max(pts[0].x+TABLE_PROXIMITY_LIMIT)};
            pts[1].x = new_x;
            pts[2].x = new_x;
            // Draw many (FK) notation
            painter.line_segment([pts[0] + vec2(if left_line {-1.0} else {1.0} * TABLE_PROXIMITY_LIMIT/2.0, 0.0),
                pts[0] + vec2(0.0, 5.0)], line_stroke);
            painter.line_segment([pts[0] + vec2(if left_line {-1.0} else {1.0} * TABLE_PROXIMITY_LIMIT/2.0, 0.0),
                pts[0] + vec2(0.0, -5.0)], line_stroke);

            // Second table
            let left_line = if enough_space {pts[last_idx].x} else {x_align} > pts[last_idx-1].x;
            pts[last_idx].x += if left_line {-end_offset} else {end_offset};
            let new_x = if left_line {pts[last_idx-1].x.min(pts[last_idx].x-TABLE_PROXIMITY_LIMIT)}
                else {pts[last_idx-1].x.max(pts[last_idx].x+TABLE_PROXIMITY_LIMIT)};
            pts[last_idx-1].x = new_x;
            pts[last_idx-2].x = new_x;
            // Draw one (PK) notation
            painter.line_segment([pts[last_idx] + vec2(if left_line {-1.0} else {1.0} * TABLE_PROXIMITY_LIMIT/3.0, 5.0),
                pts[last_idx] + vec2(if left_line {-1.0} else {1.0} * TABLE_PROXIMITY_LIMIT/3.0, -5.0)], line_stroke);

            // Draw hole path relation
            painter.line(pts.clone(), line_stroke);

            for (seg_idx, pair) in pts[1..pts.len() - 1].windows(2).enumerate() {
                let (p1, p2) = (pair[0], pair[1]);
                let vertical = seg_idx % 2 == 0;
                let expand = if vertical { vec2(LINE_WIDTH + 3.0, 0.0) } else { vec2(0.0, LINE_WIDTH + 3.0) };

                let seg_id  = ui.id().with(("seg",   rela_idx, seg_idx));
                let seg_response = ui.interact(Rect::from_two_pos(p1, p2).expand2(expand), seg_id, Sense::click_and_drag());
                
                let popup_id = ui.id().with(("popup", rela_idx, seg_idx));
                Popup::menu(&seg_response).id(popup_id).show(|ui| {
                    if ui.button("⟳").clicked() {
                        relation.relation_segments.clear();
                    }
                });
                if seg_response.drag_started() || seg_response.secondary_clicked() || seg_response.drag_stopped() {
                    if auto_align {
                        relation.relation_segments.push(seg_response.rect.center().x);
                    }
                    relation.relation_segments[seg_idx] = if vertical {seg_response.rect.center().x} else {seg_response.rect.center().y};
                    Popup::open_id(ui.ctx(), popup_id);
                }

                if seg_response.dragged() {
                    relation.relation_segments[seg_idx] += if vertical { seg_response.drag_delta().x } else { seg_response.drag_delta().y };
                }
                if seg_response.hovered() {
                    painter.line_segment([p1, p2], Stroke::new(LINE_WIDTH, Color32::from_gray(160)));
                }
                if seg_response.secondary_clicked() {
                    let mid = if vertical { (p1.y + p2.y) / 2.0 } else { (p1.x + p2.x) / 2.0 };
                    let next = if vertical { (p2.x + pts[seg_idx + 3].x) / 2.0 } else { (p2.y + pts[seg_idx + 3].y) / 2.0 };
                    relation.relation_segments.insert(seg_idx + 1, mid);
                    relation.relation_segments.insert(seg_idx + 2, next);
                }
                if seg_response.drag_stopped() {
                    verify_line_segment_joins(&mut relation.relation_segments, seg_idx, start.y, end.y);
                }

                if seg_idx != 0 {
                    let pt_id = ui.id().with(("pt", rela_idx, seg_idx));
                    let pt_response = ui.interact(Rect::from_center_size(p1, vec2(14.0, 14.0)), pt_id, Sense::click_and_drag());

                    let popup_id = ui.id().with(("popup_pt", rela_idx, seg_idx));
                    Popup::menu(&pt_response).id(popup_id).show(|ui| {
                        if ui.button("⟳").clicked() {
                            relation.relation_segments.clear();
                        }
                    });
                    if pt_response.drag_started() || pt_response.secondary_clicked() || pt_response.drag_stopped() {
                        relation.relation_segments[seg_idx - 1] = if vertical {pt_response.rect.center().y} else {pt_response.rect.center().x};
                        relation.relation_segments[seg_idx]     = if vertical {pt_response.rect.center().x} else {pt_response.rect.center().y};
                        Popup::open_id(ui.ctx(), popup_id);
                    }

                    let dot_color = if pt_response.dragged() {
                        relation.relation_segments[seg_idx - 1] += if vertical { pt_response.drag_delta().y } else { pt_response.drag_delta().x };
                        relation.relation_segments[seg_idx]     += if vertical { pt_response.drag_delta().x } else { pt_response.drag_delta().y };
                        Color32::from_gray(140)
                    } else if pt_response.hovered() {
                        Color32::from_gray(170)
                    } else {
                        Color32::from_gray(75)
                    };

                    if pt_response.secondary_clicked() {
                        relation.relation_segments.remove(seg_idx);
                        relation.relation_segments.remove(seg_idx - 1);
                    }

                    if pt_response.drag_stopped() {
                        verify_line_segment_joins(&mut relation.relation_segments, seg_idx, start.y, end.y);
                        verify_line_segment_joins(&mut relation.relation_segments, seg_idx - 1, start.y, end.y);
                    }

                    painter.circle_filled(p1, 4.5, dot_color);
                }
            }

            painter.circle_filled(pts[1],              4.5, Color32::from_gray(75));
            painter.circle_filled(pts[pts.len() - 2],  4.5, Color32::from_gray(75));
        }
    }
}

impl eframe::App for TemplateApp {
    /// Chamada sempre que o UI necessita de ser desenhado outra vez (60x por segundo)
    fn update(&mut self, ctx: &Context, _frame: &mut eframe::Frame) {
        CentralPanel::default().show(ctx, |ui| {
            self.ui_top_bar(ctx, ui);

            ui.separator();

            let (_response, painter) = ui.allocate_painter(ui.available_size(), Sense::click());

            // Desenhar as tabelas
            for (i, table) in self.tables.iter_mut().enumerate() {
                table.ui(ctx, i, &mut self.relations);
            }

            // Desenhar as linhas das relações
            self.draw_relations(ui, &painter);
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