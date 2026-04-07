use egui::*;

// --- Estruturas ---

/// We derive Deserialize/Serialize so we can persist app state on shutdown.
//Default: Preenche com os valores por defeito se não receber do laravel, se for necessário valores diferentes dos default, implementar o Default com as traits
#[derive(serde::Deserialize, serde::Serialize, Default)]
#[serde(default)]
pub struct TemplateApp {
    pub tables: Vec<Table>,
    pub relations: Vec<Connection>,
    #[serde(skip)] // Não presistir, não serializar para guardar no laravel
    pub schema_loaded: bool,
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
pub struct Connection {
    pub name: String,
    pub connection_segments: Vec<f32>,
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
    pub fn ui(&mut self, ctx: &Context, id: usize) {
        let area_response = Area::new(Id::new(("table", id)))
            .constrain(false)
            .movable(true)
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
                        self.header_ui(ui, id);
                        ui.add_space(2.0);
                        for (col_idx, column) in self.columns.iter().enumerate() {
                            column.ui(ui, id, col_idx);
                        }
                        ui.add_space(6.0);
                    });
            });

        self.pos = area_response.response.rect.min;
    }

    fn header_ui(&mut self, ui: &mut Ui, id: usize) {
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
            .id(ui.id().with(("table_popup", id)))
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
            .id(ui.id().with(("column_popup", table_id, col_id)))
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
            let mut x = 50.0;
            let mut y = 50.0;
            for table in &mut self.tables {
                table.pos = pos2(x, y);
                x += 350.0;
                if x > 1200.0 { x = 50.0; y += 350.0; }
            }
        }

        for connection in &mut self.relations {
            // Verificar se existem segmentos para nao crashar
            if connection.connection_segments.is_empty() {
                let t1_idx = connection.tables[0];
                let t2_idx = connection.tables[1];

                // Cuidado com out of bounds
                //panicked at src\app.rs:466:61:
                //index out of bounds: the len is 0 but the index is 0
                if t1_idx < self.tables.len() && t2_idx < self.tables.len() {
                    let mid_x = (self.tables[t1_idx].pos.x + self.tables[t2_idx].pos.x) / 2.0;
                    connection.connection_segments.push(mid_x);
                } else {
                    connection.connection_segments.push(100.0);
                }
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
    fn draw_connections(&mut self, ui: &mut Ui, painter: &Painter) {
        // Desenhar as linhas
        let line_width = 2.5;
        let line_stroke = Stroke::new(line_width, Color32::from_gray(80));
        for (conn_idx, connection) in self.relations.iter_mut().enumerate() {
            let (rect_a, rect_b) = ui.ctx().data(|data| {
                (
                    data.get_temp::<Rect>(Id::new(("column_rect", connection.tables[0], connection.columns[0]))),
                    data.get_temp::<Rect>(Id::new(("column_rect", connection.tables[1], connection.columns[1])))
                )
            });

            let (Some(rect_a), Some(rect_b)) = (rect_a, rect_b) else {
                continue;
            };

            let start = rect_a.center();
            let end = rect_b.center();

            let mut pts = Vec::new();
            pts.push(start);
            if !connection.connection_segments.is_empty() {
                pts.push(pos2(connection.connection_segments[0], start.y));

                for (i, seg) in connection.connection_segments.windows(2).enumerate() {
                    pts.push(if i % 2 == 0 {
                        pos2(seg[0], seg[1]) }
                    else {
                        pos2(seg[1], seg[0])
                    });
                }

                pts.push(pos2(*connection.connection_segments.last().unwrap(), end.y));
            }

            pts.push(end);
            painter.line(pts.clone(), line_stroke);

            for (seg_idx, pair) in pts[1..pts.len() - 1].windows(2).enumerate() {
                let (p1, p2) = (pair[0], pair[1]);
                let vertical = seg_idx % 2 == 0;
                let expand = if vertical { vec2(line_width + 3.0, 0.0) } else { vec2(0.0, line_width + 3.0) };

                let seg_id  = ui.id().with(("seg",   conn_idx, seg_idx));
                let seg_response = ui.interact(Rect::from_two_pos(p1, p2).expand2(expand), seg_id, Sense::click_and_drag());

                if seg_response.dragged() {
                    connection.connection_segments[seg_idx] += if vertical { seg_response.drag_delta().x } else { seg_response.drag_delta().y };
                }
                if seg_response.hovered() {
                    painter.line_segment([p1, p2], Stroke::new(line_width, Color32::from_gray(160)));
                }
                if seg_response.clicked_by(PointerButton::Secondary) {
                    let mid = if vertical { (p1.y + p2.y) / 2.0 } else { (p1.x + p2.x) / 2.0 };
                    let next = if vertical { (p2.x + pts[seg_idx + 3].x) / 2.0 } else { (p2.y + pts[seg_idx + 3].y) / 2.0 };
                    connection.connection_segments.insert(seg_idx + 1, mid);
                    connection.connection_segments.insert(seg_idx + 2, next);
                }

                if seg_idx != 0 {
                    let pt_id = ui.id().with(("pt", conn_idx, seg_idx));
                    let pt_response = ui.interact(Rect::from_center_size(p1, vec2(14.0, 14.0)), pt_id, Sense::click_and_drag());

                    let dot_color = if pt_response.dragged() {
                        connection.connection_segments[seg_idx - 1] += if vertical { pt_response.drag_delta().y } else { pt_response.drag_delta().x };
                        connection.connection_segments[seg_idx]     += if vertical { pt_response.drag_delta().x } else { pt_response.drag_delta().y };
                        Color32::from_gray(140)
                    } else if pt_response.hovered() {
                        Color32::from_gray(170)
                    } else {
                        Color32::from_gray(75)
                    };

                    if pt_response.clicked_by(PointerButton::Secondary) {
                        connection.connection_segments.remove(seg_idx);
                        connection.connection_segments.remove(seg_idx - 1);
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
                table.ui(ctx, i);
            }

            // Desenhar as linhas das conexões
            self.draw_connections(ui, &painter);
        });
    }

    /// Guarda o estado da app antes de ser terminada
    fn save(&mut self, storage: &mut dyn eframe::Storage) {
        eframe::set_value(storage, eframe::APP_KEY, self);
    }
}