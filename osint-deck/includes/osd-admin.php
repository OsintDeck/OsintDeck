<?php
/**
 * OSINT Deck - Panel de administracion
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Opciones de rate limit (alias a las constantes del core).
if ( ! defined( 'OSD_OPTION_RATE_QPM' ) ) {
    define( 'OSD_OPTION_RATE_QPM', OSD_Rate_Limit::OPTION_QPM );
}
if ( ! defined( 'OSD_OPTION_RATE_QPD' ) ) {
    define( 'OSD_OPTION_RATE_QPD', OSD_Rate_Limit::OPTION_QPD );
}
if ( ! defined( 'OSD_OPTION_RATE_COOLDOWN' ) ) {
    define( 'OSD_OPTION_RATE_COOLDOWN', OSD_Rate_Limit::OPTION_COOLD );
}
if ( ! defined( 'OSD_OPTION_RATE_REPORTS_DAY' ) ) {
    define( 'OSD_OPTION_RATE_REPORTS_DAY', OSD_Rate_Limit::OPTION_REPORT );
}

// Menu admin.
add_action( 'admin_menu', 'osd_admin_menu' );
function osd_admin_menu() {
    add_menu_page(
        'OSINT Deck',
        'OSINT Deck',
        'manage_options',
        'osint-deck',
        'osd_admin_page',
        'dashicons-shield-alt',
        58
    );
}

// Assets fallback (OSD_Admin_UI ya encola, esto es solo compatibilidad).
add_action( 'admin_enqueue_scripts', 'osd_admin_assets' );
function osd_admin_assets( $hook ) {
    if ( $hook !== 'toplevel_page_osint-deck' ) {
        return;
    }
    if ( ! wp_script_is( 'osd-admin-js', 'enqueued' ) ) {
        wp_enqueue_style( 'osd-admin-css', OSD_PLUGIN_URL . 'assets/admin/osd-admin.css', [], OSD_VERSION );
        wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', [], '4.4.1', true );
        wp_enqueue_script( 'osd-admin-js', OSD_PLUGIN_URL . 'assets/admin/osd-admin.js', [ 'jquery', 'chartjs' ], OSD_VERSION, true );
        wp_localize_script( 'osd-admin-js', 'OSDAdmin', [ 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'osd_admin_ajax' ), 'version' => OSD_VERSION ] );
    }
}

// Pagina principal admin.
function osd_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $msg = '';
    $err = '';

    $color_defaults = [
        'dark'  => [ 'bg' => '#0a0c0f','card' => '#16181d','border' => '#23252a','ink' => '#f2f4f8','ink_sub' => '#a9b0bb','accent' => '#00ffe0','muted' => '#9ca3af','btn_bg' => '#00ffe0','btn_text' => '#0a0c0f' ],
        'light' => [ 'bg' => '#f8f9fb','card' => '#ffffff','border' => '#d7dbe1','ink' => '#111111','ink_sub' => '#5f6672','accent' => '#111111','muted' => '#9aa1ac','btn_bg' => '#111111','btn_text' => '#ffffff' ],
    ];

    // POST handlers
    if ( isset( $_POST['osd_action'] ) ) {
        check_admin_referer( 'osd_admin' );
        $action = sanitize_text_field( wp_unslash( $_POST['osd_action'] ) );

        if ( $action === 'save_theme' ) {
            update_option( OSD_OPTION_THEME_MODE, sanitize_text_field( wp_unslash( $_POST['osd_theme_mode'] ?? 'auto' ) ) );
            update_option( OSD_OPTION_THEME_SELECTOR, sanitize_text_field( wp_unslash( $_POST['osd_theme_selector'] ?? '[data-site-skin]' ) ) );
            update_option( OSD_OPTION_THEME_TOKEN_LIGHT, sanitize_text_field( wp_unslash( $_POST['osd_theme_token_light'] ?? 'light' ) ) );
            update_option( OSD_OPTION_THEME_TOKEN_DARK, sanitize_text_field( wp_unslash( $_POST['osd_theme_token_dark'] ?? 'dark' ) ) );
            $msg = 'Preferencias de tema guardadas.';
        }

        if ( $action === 'save_colors' ) {
            $modes  = [ 'dark', 'light' ];
            $fields = [ 'bg','card','border','ink','ink_sub','accent','muted','btn_bg','btn_text' ];
            $saved  = [];
            foreach ( $modes as $mode ) {
                foreach ( $fields as $field ) {
                    $key = 'osd_color_' . $mode . '_' . $field;
                    $val = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) );
                    if ( ! preg_match( '/^#?[0-9a-fA-F]{3,8}$/', $val ) ) {
                        $val = $color_defaults[ $mode ][ $field ];
                    }
                    if ( strpos( $val, '#' ) !== 0 ) {
                        $val = '#' . $val;
                    }
                    $saved[ $mode ][ $field ] = $val;
                }
            }
            update_option( OSD_OPTION_THEME_COLORS, $saved );
            $msg = 'Colores guardados.';
        }

        if ( $action === 'save_security' ) {
            $qpm      = max( 1, intval( $_POST['osd_rate_qpm'] ?? 60 ) );
            $qpd      = max( 1, intval( $_POST['osd_rate_qpd'] ?? 1000 ) );
            $cooldown = max( 0, intval( $_POST['osd_rate_cooldown'] ?? 60 ) );
            $rep_day  = max( 1, intval( $_POST['osd_rate_reports_day'] ?? 1 ) );
            $popular  = max( 1, intval( $_POST['osd_metric_popular_threshold'] ?? 100 ) );
            $new_days = max( 1, intval( $_POST['osd_metric_new_days'] ?? 30 ) );

            update_option( OSD_OPTION_RATE_QPM, $qpm );
            update_option( OSD_OPTION_RATE_QPD, $qpd );
            update_option( OSD_OPTION_RATE_COOLDOWN, $cooldown );
            update_option( OSD_OPTION_RATE_REPORTS_DAY, $rep_day );
            update_option( OSD_Metrics::OPTION_POPULAR_THRESHOLD, $popular );
            update_option( OSD_Metrics::OPTION_NEW_DAYS, $new_days );
            $msg = 'Configuracion de seguridad guardada.';
        }

        if ( $action === 'save_json' ) {
            $raw  = wp_unslash( $_POST['osd_json'] ?? '' );
            $test = json_decode( $raw, true );
            if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $test ) ) {
                $err = 'JSON invalido: ' . json_last_error_msg();
            } else {
                $normalized = array_values( array_filter( $test, static function( $t ) { return is_array( $t ) && ! empty( $t['name'] ); } ) );
                update_option( OSD_OPTION_TOOLS, wp_json_encode( $normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
                $msg = 'JSON de herramientas guardado correctamente.';
            }
        }
    }

    // Valores actuales
    $themeMode      = (string) get_option( OSD_OPTION_THEME_MODE, 'auto' );
    $themeSelector  = (string) get_option( OSD_OPTION_THEME_SELECTOR, '[data-site-skin]' );
    $tokenLight     = (string) get_option( OSD_OPTION_THEME_TOKEN_LIGHT, 'light' );
    $tokenDark      = (string) get_option( OSD_OPTION_THEME_TOKEN_DARK,  'dark' );
    $jsonRaw        = (string) get_option( OSD_OPTION_TOOLS, '[]' );

    $colors_raw     = get_option( OSD_OPTION_THEME_COLORS, [] );
    $colors         = [
        'dark'  => wp_parse_args( is_array( $colors_raw ) && isset( $colors_raw['dark'] ) ? $colors_raw['dark'] : [], $color_defaults['dark'] ),
        'light' => wp_parse_args( is_array( $colors_raw ) && isset( $colors_raw['light'] ) ? $colors_raw['light'] : [], $color_defaults['light'] ),
    ];

    $rate_qpm        = (int) get_option( OSD_OPTION_RATE_QPM, 60 );
    $rate_qpd        = (int) get_option( OSD_OPTION_RATE_QPD, 1000 );
    $rate_cooldown   = (int) get_option( OSD_OPTION_RATE_COOLDOWN, 60 );
    $rate_rep_day    = (int) get_option( OSD_OPTION_RATE_REPORTS_DAY, 1 );
    $metric_popular  = (int) get_option( OSD_Metrics::OPTION_POPULAR_THRESHOLD, 100 );
    $metric_new_days = (int) get_option( OSD_Metrics::OPTION_NEW_DAYS, 30 );

    ?>
    <div class="wrap osd-admin">
        <div class="osd-admin-header">
            <h1>OSINT Deck</h1>
            <p>Panel de administracion — tema, seguridad, herramientas, logs y ayuda.</p>
        </div>

        <?php if ( $msg ) : ?>
            <div class="notice notice-success"><p><?php echo esc_html( $msg ); ?></p></div>
        <?php endif; ?>

        <?php if ( $err ) : ?>
            <div class="notice notice-error"><p><?php echo esc_html( $err ); ?></p></div>
        <?php endif; ?>

        <div class="osd-tabs">
            <button type="button" class="osd-tab" data-tab="config">Configuracion</button>
            <button type="button" class="osd-tab" data-tab="tools">Herramientas</button>
            <button type="button" class="osd-tab" data-tab="logs">Logs y metricas</button>
            <button type="button" class="osd-tab" data-tab="help">Ayuda</button>
        </div>

        <div class="osd-section" id="osd-tab-config">
            <div class="osd-subtabs">
                <button type="button" class="osd-subtab" data-group="config" data-sub="config-theme">Tema</button>
                <button type="button" class="osd-subtab" data-group="config" data-sub="config-colors">Colores</button>
                <button type="button" class="osd-subtab" data-group="config" data-sub="config-security">Seguridad</button>
            </div>

            <div class="osd-subsection" data-group="config" id="osd-sub-config-theme">
                <div class="osd-card">
                    <h2>Tema (frontend)</h2>
                    <p class="description">Define como OSINT Deck sigue el modo claro/oscuro de tu sitio o fuerza un modo.</p>
                    <form method="post">
                        <?php wp_nonce_field( 'osd_admin' ); ?>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="osd_theme_mode">Modo</label></th>
                                <td>
                                    <select id="osd_theme_mode" name="osd_theme_mode">
                                        <option value="auto"  <?php selected( $themeMode, 'auto' ); ?>>Auto</option>
                                        <option value="light" <?php selected( $themeMode, 'light' ); ?>>Claro</option>
                                        <option value="dark"  <?php selected( $themeMode, 'dark' ); ?>>Oscuro</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="osd_theme_selector">Selector del atributo</label></th>
                                <td>
                                    <input type="text" id="osd_theme_selector" name="osd_theme_selector" value="<?php echo esc_attr( $themeSelector ); ?>" class="regular-text" />
                                    <p class="description">Ej: [data-site-skin]</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="osd_theme_token_light">Token Light</label></th>
                                <td><input type="text" id="osd_theme_token_light" name="osd_theme_token_light" value="<?php echo esc_attr( $tokenLight ); ?>" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="osd_theme_token_dark">Token Dark</label></th>
                                <td><input type="text" id="osd_theme_token_dark" name="osd_theme_token_dark" value="<?php echo esc_attr( $tokenDark ); ?>" /></td>
                            </tr>
                        </table>
                        <p class="osd-actions"><button class="osd-btn osd-btn-primary" name="osd_action" value="save_theme">Guardar tema</button></p>
                    </form>
                </div>
            </div>

            <div class="osd-subsection" data-group="config" id="osd-sub-config-colors">
                <div class="osd-card">
                    <h2>Colores base (claro y oscuro)</h2>
                    <p class="description">Ajusta las variables :root usadas en el deck para ambos modos.</p>
                    <form method="post">
                        <?php wp_nonce_field( 'osd_admin' ); ?>
                        <div class="osd-preview-grid osd-preview-grid-inline">
                            <div class="osd-preview-col">
                                <div class="osd-preview-head">
                                    <strong>Modo claro</strong>
                                    <button type="button" class="osd-btn osd-btn-secondary osd-reset-colors" data-mode="light">Restablecer</button>
                                </div>
                                <table class="form-table" role="presentation">
                                    <?php $light_fields = [ 'bg' => 'Fondo (--osint-bg)','card' => 'Tarjetas (--osint-card)','border' => 'Bordes (--osint-border)','ink' => 'Texto principal (--osint-ink)','ink_sub' => 'Texto secundario (--osint-ink-sub)','accent' => 'Acento (--osint-accent)','muted' => 'Muted (--osint-muted)','btn_bg' => 'Boton fondo (--osint-btn-bg)','btn_text' => 'Boton texto (--osint-btn-text)' ]; foreach ( $light_fields as $key => $label ) : ?>
                                        <tr>
                                            <th scope="row"><label for="osd_color_light_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
                                            <td><input type="color" id="osd_color_light_<?php echo esc_attr( $key ); ?>" name="osd_color_light_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $colors['light'][ $key ] ); ?>" data-default="<?php echo esc_attr( $color_defaults['light'][ $key ] ); ?>" /></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                                <div class="osd-preview" id="osd-preview-light" data-mode="light">
                                    <div class="osd-preview-surface">
                                        <div class="osd-preview-search">
                                            <span class="osd-preview-icon">🔍</span>
                                            <input type="text" value="Buscar o pegar una entrada..." disabled />
                                            <span class="osd-preview-pill">Light</span>
                                        </div>
                                        <div class="osd-preview-card">
                                            <div class="osd-preview-card-hdr">
                                                <div class="osd-preview-title">MxToolBox</div>
                                                <div class="osd-preview-badges">
                                                    <span class="osd-preview-badge badge-new">Nueva</span>
                                                    <span class="osd-preview-badge badge-pop">Popular</span>
                                                    <span class="osd-preview-badge badge-tip">Recomendada</span>
                                                </div>
                                            </div>
                                            <div class="osd-preview-meta">Dominios / DNS</div>
                                            <div class="osd-preview-tags">
                                                <span class="osd-preview-chip">10 cartas</span>
                                                <span class="osd-preview-chip">Libre</span>
                                                <span class="osd-preview-chip">⚠ Reportada</span>
                                            </div>
                                            <div class="osd-preview-desc">Acceso general a MxToolbox con badges dinámicos.</div>
                                            <div class="osd-preview-actions">
                                                <button type="button" class="osd-preview-btn primary">Analizar</button>
                                                <button type="button" class="osd-preview-btn ghost">Copiar URL</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="osd-preview-col">
                                <div class="osd-preview-head">
                                    <strong>Modo oscuro</strong>
                                    <button type="button" class="osd-btn osd-btn-secondary osd-reset-colors" data-mode="dark">Restablecer</button>
                                </div>
                                <table class="form-table" role="presentation">
                                    <?php foreach ( $light_fields as $key => $label ) : ?>
                                        <tr>
                                            <th scope="row"><label for="osd_color_dark_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
                                            <td><input type="color" id="osd_color_dark_<?php echo esc_attr( $key ); ?>" name="osd_color_dark_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $colors['dark'][ $key ] ); ?>" data-default="<?php echo esc_attr( $color_defaults['dark'][ $key ] ); ?>" /></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                                <div class="osd-preview" id="osd-preview-dark" data-mode="dark">
                                    <div class="osd-preview-surface">
                                        <div class="osd-preview-search">
                                            <span class="osd-preview-icon">🔍</span>
                                            <input type="text" value="Buscar o pegar una entrada..." disabled />
                                            <span class="osd-preview-pill">Dark</span>
                                        </div>
                                        <div class="osd-preview-card">
                                            <div class="osd-preview-card-hdr">
                                                <div class="osd-preview-title">MxToolBox</div>
                                                <div class="osd-preview-badges">
                                                    <span class="osd-preview-badge badge-new">Nueva</span>
                                                    <span class="osd-preview-badge badge-pop">Popular</span>
                                                    <span class="osd-preview-badge badge-tip">Recomendada</span>
                                                </div>
                                            </div>
                                            <div class="osd-preview-meta">Dominios / DNS</div>
                                            <div class="osd-preview-tags">
                                                <span class="osd-preview-chip">10 cartas</span>
                                                <span class="osd-preview-chip">Libre</span>
                                                <span class="osd-preview-chip">⚠ Reportada</span>
                                            </div>
                                            <div class="osd-preview-desc">Colores aplicados inmediatamente, sin ir al frontend.</div>
                                            <div class="osd-preview-actions">
                                                <button type="button" class="osd-preview-btn primary">Analizar</button>
                                                <button type="button" class="osd-preview-btn ghost">Copiar URL</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <p class="osd-actions"><button class="osd-btn osd-btn-primary" name="osd_action" value="save_colors">Guardar colores</button></p>
                </form>
            </div>
        </div>

            <div class="osd-subsection" data-group="config" id="osd-sub-config-security">
                <div class="osd-card">
                    <h2>Seguridad y limites</h2>
                    <p class="description">Limites por IP y umbrales de badges.</p>
                    <form method="post">
                        <?php wp_nonce_field( 'osd_admin' ); ?>
                        <table class="form-table" role="presentation">
                            <tr><th scope="row"><label for="osd_rate_qpm">Consultas por minuto</label></th><td><input type="number" min="1" id="osd_rate_qpm" name="osd_rate_qpm" value="<?php echo esc_attr( $rate_qpm ); ?>" /></td></tr>
                            <tr><th scope="row"><label for="osd_rate_qpd">Consultas por dia</label></th><td><input type="number" min="1" id="osd_rate_qpd" name="osd_rate_qpd" value="<?php echo esc_attr( $rate_qpd ); ?>" /></td></tr>
                            <tr><th scope="row"><label for="osd_rate_cooldown">Cooldown (segundos)</label></th><td><input type="number" min="0" id="osd_rate_cooldown" name="osd_rate_cooldown" value="<?php echo esc_attr( $rate_cooldown ); ?>" /></td></tr>
                            <tr><th scope="row"><label for="osd_rate_reports_day">Reportes por dia/herramienta</label></th><td><input type="number" min="1" id="osd_rate_reports_day" name="osd_rate_reports_day" value="<?php echo esc_attr( $rate_rep_day ); ?>" /></td></tr>
                            <tr><th scope="row"><label for="osd_metric_popular_threshold">Umbral Popular (clics 7d)</label></th><td><input type="number" min="1" id="osd_metric_popular_threshold" name="osd_metric_popular_threshold" value="<?php echo esc_attr( $metric_popular ); ?>" /></td></tr>
                            <tr><th scope="row"><label for="osd_metric_new_days">Badge Nueva (dias)</label></th><td><input type="number" min="1" id="osd_metric_new_days" name="osd_metric_new_days" value="<?php echo esc_attr( $metric_new_days ); ?>" /></td></tr>
                        </table>
                        <p class="osd-actions"><button class="osd-btn osd-btn-primary" name="osd_action" value="save_security">Guardar seguridad</button></p>
                    </form>
                </div>
            </div>
        </div>

        <div class="osd-section" id="osd-tab-tools">
            <div class="osd-card">
                <h2>Herramientas</h2>
                <p class="description">Gestiona tus herramientas como mazos OSINT. CRUD via AJAX o edicion masiva en JSON.</p>
                <div class="osd-subtabs">
                    <button type="button" class="osd-subtab" data-group="tools" data-sub="list">Listado</button>
                    <button type="button" class="osd-subtab" data-group="tools" data-sub="editor">Editor individual</button>
                    <button type="button" class="osd-subtab" data-group="tools" data-sub="backup">Backup JSON completo</button>
                </div>
                <div class="osd-subsection" data-group="tools" id="osd-sub-list">
                    <table class="osd-table">
                        <thead><tr><th>Nombre</th><th>Categoria</th><th>Acceso</th><th>Color</th><th>Tags</th><th>Descripcion</th><th>Badges</th><th>Principal</th><th>Acciones</th></tr></thead>
                        <tbody id="osd-tools-tbody"></tbody>
                    </table>
                </div>
                <div class="osd-subsection" data-group="tools" id="osd-sub-editor">
                    <span class="osd-label-small">Pega el JSON de una sola herramienta.</span>
                    <textarea id="osd-editor-text" class="osd-textarea" spellcheck="false" wrap="off"></textarea>
                    <div class="osd-actions">
                        <button type="button" class="osd-btn" id="osd-validate-tool">Validar JSON</button>
                        <button type="button" class="osd-btn osd-btn-primary" id="osd-save-tool">Guardar / Actualizar</button>
                        <button type="button" class="osd-btn osd-btn-secondary" id="osd-clear-tool">Limpiar</button>
                        <button type="button" class="osd-btn osd-btn-primary" id="osd-add-new">Nueva herramienta</button>
                    </div>
                </div>
                <div class="osd-subsection" data-group="tools" id="osd-sub-backup">
                    <span class="osd-label-small">JSON completo del plugin.</span>
                    <form method="post">
                        <?php wp_nonce_field( 'osd_admin' ); ?>
                        <div class="osd-json-tools">
                            <div class="osd-json-toolbar">
                                <input type="text" id="osd-json-search" class="osd-input osd-input-small" placeholder="Buscar en JSON y Enter" autocomplete="off" />
                                <span class="osd-json-status" id="osd-json-status">Ln 1, Col 1</span>
                            </div>
                            <textarea id="osd-backup-text" name="osd_json" class="osd-textarea" spellcheck="false" wrap="off"><?php echo esc_textarea( $jsonRaw ); ?></textarea>
                        </div>
                        <input type="file" id="osd-json-file" accept="application/json" style="display:none" />
                        <div class="osd-actions">
                            <button type="submit" class="osd-btn osd-btn-primary" name="osd_action" value="save_json">Guardar JSON completo</button>
                            <button type="button" class="osd-btn osd-btn-secondary" id="osd-export-json">Descargar JSON</button>
                            <button type="button" class="osd-btn osd-btn-secondary" id="osd-import-json">Importar JSON (archivo)</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="osd-section" id="osd-tab-logs">
            <div class="osd-card">
                <h2>Logs y metricas</h2>
                <div class="osd-subtabs">
                    <button type="button" class="osd-subtab" data-group="logs" data-sub="logs-user">Logs de usuarios</button>
                    <button type="button" class="osd-subtab" data-group="logs" data-sub="logs-admin">Logs de administrador</button>
                    <button type="button" class="osd-subtab" data-group="logs" data-sub="logs-metrics">Metricas</button>
                </div>
                <div class="osd-subsection" data-group="logs" id="osd-sub-logs-user">
                    <table class="osd-logs-table">
                        <thead><tr><th>Fecha</th><th>IP</th><th>Tipo</th><th>Input</th><th>Herramienta</th><th>Carta</th><th>Accion</th></tr></thead>
                        <tbody id="osd-logs-user-body"><tr><td colspan="7">No hay registros cargados.</td></tr></tbody>
                    </table>
                </div>
                <div class="osd-subsection" data-group="logs" id="osd-sub-logs-admin">
                    <table class="osd-logs-table">
                        <thead><tr><th>Fecha</th><th>Admin</th><th>Accion</th><th>Herramienta</th><th>Severidad</th><th>Detalles</th></tr></thead>
                        <tbody id="osd-logs-admin-body"><tr><td colspan="6">No hay registros cargados.</td></tr></tbody>
                    </table>
                </div>
                <div class="osd-subsection" data-group="logs" id="osd-sub-logs-metrics">
                    <div class="osd-chart-row">
                        <div class="osd-chart-card"><h4>Clicks (7d)</h4><canvas id="osd-chart-clicks" class="osd-chart"></canvas></div>
                        <div class="osd-chart-card"><h4>Reportes (7d)</h4><canvas id="osd-chart-reports" class="osd-chart"></canvas></div>
                    </div>
                    <table class="osd-logs-table">
                        <thead><tr><th>Herramienta</th><th>Clicks (7d)</th><th>Reportes (7d)</th><th>Badges</th><th>Creada</th></tr></thead>
                        <tbody id="osd-logs-metrics-body"><tr><td colspan="5">No hay metricas cargadas.</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="osd-section" id="osd-tab-help">
            <div class="osd-card">
                <h2>Uso del shortcode</h2>
                <ul>
                    <li><code>[osint_deck]</code> — todas las herramientas.</li>
                    <li><code>[osint_deck category="Dominios / DNS"]</code> — filtra por categoria.</li>
                    <li><code>[osint_deck access="gratis"]</code> — filtra por tipo de acceso.</li>
                    <li><code>[osint_deck limit="20"]</code> — limita la cantidad mostrada.</li>
                </ul>
            </div>
        </div>
    </div>
    <?php
}

// AJAX – CRUD herramientas
add_action( 'wp_ajax_osd_tools_get', 'osd_ajax_tools_get' );
function osd_ajax_tools_get() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'msg' => 'Unauthorized' ], 403 );
    }
    check_ajax_referer( 'osd_admin_ajax' );

    $data = class_exists( 'OSD_Tools' ) ? OSD_Tools::raw_list() : null;
    if ( ! is_array( $data ) ) {
        $txt  = (string) get_option( OSD_OPTION_TOOLS, '[]' );
        $data = json_decode( $txt, true );
    }
    if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
        wp_send_json( [ 'ok' => false, 'msg' => 'JSON principal invalido' ] );
    }
    wp_send_json( [ 'ok' => true, 'data' => $data ] );
}

add_action( 'wp_ajax_osd_tool_upsert', 'osd_ajax_tool_upsert' );
function osd_ajax_tool_upsert() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'ok' => false, 'msg' => 'Unauthorized' ], 403 );
    }
    check_ajax_referer( 'osd_admin_ajax' );

    $raw = wp_unslash( $_POST['tool_json'] ?? '' );
    if ( $raw === '' ) {
        wp_send_json( [ 'ok' => false, 'msg' => 'Falta tool_json' ] );
    }
    $obj = json_decode( $raw, true );
    if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $obj ) ) {
        wp_send_json( [ 'ok' => false, 'msg' => 'JSON invalido: ' . json_last_error_msg() ] );
    }
    $name = trim( (string) ( $obj['name'] ?? '' ) );
    if ( $name === '' ) {
        wp_send_json( [ 'ok' => false, 'msg' => 'El campo "name" es obligatorio.' ] );
    }
    $cards = isset( $obj['cards'] ) && is_array( $obj['cards'] ) ? $obj['cards'] : [];
    if ( count( $cards ) < 1 ) {
        wp_send_json( [ 'ok' => false, 'msg' => 'Debe incluir al menos 1 item en "cards".' ] );
    }
    $first = $cards[0];
    if ( empty( $first['title'] ) || empty( $first['url'] ) ) {
        wp_send_json( [ 'ok' => false, 'msg' => 'La primera card debe incluir "title" y "url".' ] );
    }

    $obj['name']     = sanitize_text_field( $obj['name'] ?? '' );
    $obj['category'] = sanitize_text_field( $obj['category'] ?? '' );
    $obj['access']   = sanitize_text_field( $obj['access'] ?? '' );
    $obj['color']    = sanitize_text_field( $obj['color'] ?? '' );
    $obj['favicon']  = esc_url_raw( $obj['favicon'] ?? '' );
    if ( isset( $obj['tags'] ) && is_array( $obj['tags'] ) ) {
        $obj['tags'] = array_values( array_map( 'sanitize_text_field', $obj['tags'] ) );
    }
    if ( isset( $obj['cards'] ) && is_array( $obj['cards'] ) ) {
        $obj['cards'] = array_values( array_map( function( $c ) {
            $card = [ 'title' => sanitize_text_field( $c['title'] ?? '' ), 'desc' => sanitize_text_field( $c['desc'] ?? '' ), 'url' => esc_url_raw( $c['url'] ?? '' ) ];
            if ( isset( $c['category'] ) ) { $card['category'] = sanitize_text_field( $c['category'] ); }
            if ( isset( $c['tags'] ) && is_array( $c['tags'] ) ) { $card['tags'] = array_values( array_map( 'sanitize_text_field', $c['tags'] ) ); }
            return $card;
        }, $obj['cards'] ) );
    }

    if ( class_exists( 'OSD_Tools' ) && method_exists( 'OSD_Tools', 'upsert_raw' ) ) {
        OSD_Tools::upsert_raw( $obj );
        OSD_Tools::sync_option_from_table();
    } else {
        $txt  = (string) get_option( OSD_OPTION_TOOLS, '[]' );
        $data = json_decode( $txt, true );
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) { $data = []; }
        $idx = -1;
        foreach ( $data as $i => $t ) {
            if ( is_array( $t ) && strtolower( (string) ( $t['name'] ?? '' ) ) === strtolower( $name ) ) { $idx = $i; break; }
        }
        if ( $idx >= 0 ) { $data[ $idx ] = $obj; } else { $data[] = $obj; }
        $data = array_values( array_filter( $data, static function( $t ) { return is_array( $t ) && ! empty( $t['name'] ); } ) );
        update_option( OSD_OPTION_TOOLS, wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
    }
    wp_send_json( [ 'ok' => true, 'msg' => 'Guardado' ] );
}

add_action( 'wp_ajax_osd_tool_delete', 'osd_ajax_tool_delete' );
function osd_ajax_tool_delete() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'ok' => false, 'msg' => 'Unauthorized' ], 403 );
    }
    check_ajax_referer( 'osd_admin_ajax' );

    $name = trim( (string) wp_unslash( $_POST['name'] ?? '' ) );
    if ( $name === '' ) {
        wp_send_json( [ 'ok' => false, 'msg' => 'Falta "name"' ] );
    }

    if ( class_exists( 'OSD_Tools' ) && method_exists( 'OSD_Tools', 'delete_raw' ) ) {
        OSD_Tools::delete_raw( $name );
        OSD_Tools::sync_option_from_table();
    } else {
        $txt  = (string) get_option( OSD_OPTION_TOOLS, '[]' );
        $data = json_decode( $txt, true );
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) { $data = []; }
        $out = [];
        foreach ( $data as $t ) {
            if ( ! is_array( $t ) ) { continue; }
            if ( strtolower( (string) ( $t['name'] ?? '' ) ) === strtolower( $name ) ) { continue; }
            $out[] = $t;
        }
        update_option( OSD_OPTION_TOOLS, wp_json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
    }
    wp_send_json( [ 'ok' => true, 'msg' => 'Eliminado' ] );
}

add_action( 'wp_ajax_osd_export_json', 'osd_ajax_export_json' );
function osd_ajax_export_json() {
    check_ajax_referer( 'osd_admin_ajax' );

    $data = class_exists( 'OSD_Tools' ) ? OSD_Tools::raw_list() : [];
    if ( ! is_array( $data ) ) { $data = []; }
    $filename = 'osint-deck-tools-' . date( 'Ymd-His' ) . '.json';
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
    exit;
}

// AJAX logs
add_action( 'wp_ajax_osd_logs_user', 'osd_ajax_logs_user' );
function osd_ajax_logs_user() {
    check_ajax_referer( 'osd_admin_ajax' );
    global $wpdb;
    $table = $wpdb->prefix . ( defined( 'OSD_LOG_TABLE' ) ? OSD_LOG_TABLE : 'osd_logs' );
    $rows = [];
    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    if ( $exists === $table ) {
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, created_at, actor_type, actor_id, ip, action, tool, input_type, input_value, meta FROM {$table} WHERE actor_type = %s ORDER BY created_at DESC LIMIT 200", 'user' ), ARRAY_A );
    }
    wp_send_json( [ 'ok' => true, 'data' => $rows ] );
}

add_action( 'wp_ajax_osd_logs_admin', 'osd_ajax_logs_admin' );
function osd_ajax_logs_admin() {
    check_ajax_referer( 'osd_admin_ajax' );
    global $wpdb;
    $table = $wpdb->prefix . ( defined( 'OSD_LOG_TABLE' ) ? OSD_LOG_TABLE : 'osd_logs' );
    $rows = [];
    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    if ( $exists === $table ) {
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, created_at, actor_type, actor_id, ip, action, tool, input_type, input_value, meta FROM {$table} WHERE actor_type = %s ORDER BY created_at DESC LIMIT 200", 'admin' ), ARRAY_A );
    }
    wp_send_json( [ 'ok' => true, 'data' => $rows ] );
}

add_action( 'wp_ajax_osd_export_logs_csv', 'osd_ajax_export_logs_csv' );
function osd_ajax_export_logs_csv() {
    check_ajax_referer( 'osd_admin_ajax' );
    global $wpdb;
    $table = $wpdb->prefix . ( defined( 'OSD_LOG_TABLE' ) ? OSD_LOG_TABLE : 'osd_logs' );
    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    if ( $exists !== $table ) { wp_die( 'No logs', 404 ); }
    $rows = $wpdb->get_results( "SELECT created_at, actor_type, actor_id, ip, action, tool, input_type, input_value FROM {$table} ORDER BY created_at DESC LIMIT 1000", ARRAY_A );
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="osd-logs.csv"' );
    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, [ 'created_at','actor_type','actor_id','ip','action','tool','input_type','input_value' ] );
    foreach ( $rows as $r ) { fputcsv( $out, $r ); }
    fclose( $out );
    exit;
}

// AJAX metrics
add_action( 'wp_ajax_osd_metrics_summary', 'osd_ajax_metrics_summary' );
function osd_ajax_metrics_summary() {
    check_ajax_referer( 'osd_admin_ajax' );
    $data = class_exists( 'OSD_Metrics' ) ? OSD_Metrics::all() : [];
    $out  = [];
    foreach ( $data as $tool_id => $row ) {
        $meta = class_exists( 'OSD_Metrics' ) ? OSD_Metrics::meta_for( $tool_id ) : [];
        $out[] = [
            'tool_id'        => $tool_id,
            'clicks_7d'      => isset( $meta['clicks_7d'] ) ? intval( $meta['clicks_7d'] ) : 0,
            'reports_7d'     => isset( $meta['reports_7d'] ) ? intval( $meta['reports_7d'] ) : 0,
            'badges'         => isset( $meta['badges'] ) ? $meta['badges'] : [],
            'created_at'     => isset( $meta['created_at'] ) ? intval( $meta['created_at'] ) : 0,
            'last_input_type'=> isset( $meta['last_input_type'] ) ? $meta['last_input_type'] : '',
        ];
    }
    usort( $out, static function( $a, $b ) { return $b['clicks_7d'] <=> $a['clicks_7d']; } );
    wp_send_json( [ 'ok' => true, 'data' => $out ] );
}

?>

