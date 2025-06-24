<?php
/**
 * Plugin Name: GuruX BTX Sync
 * Plugin URI: https://guruxglobal.com/es/nosotros/
 * Description: Two-Way WooCommerce and Bitrix24 Synchronization + Universal Form Capture
 * Version: 1.8.9
 * Author: GuruX
 * Author URI: https://guruxglobal.com/es/nosotros/
 * Text Domain: gurux-enterprice-plugin-bitrix
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit('No direct script access allowed');
}

// Definir constantes del plugin
define('YEISON_BTX_VERSION', '1.0.0');
define('YEISON_BTX_PLUGIN_FILE', __FILE__);
define('YEISON_BTX_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('YEISON_BTX_PLUGIN_URL', plugin_dir_url(__FILE__));
define('YEISON_BTX_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Clase principal del plugin
 */
class YeisonBTX {
    
    /**
     * Instancia única del plugin (Singleton)
     */
    private static $instance = null;
    
    /**
     * Constructor privado (Singleton)
     */
    private function __construct() {
        // Cargar funciones primero
        $this->load_functions();
        
        // Inicializar hooks básicos
        $this->init_hooks();
    }
    
    /**
     * Obtener instancia única
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Cargar archivo de funciones y todas las clases
     */
    private function load_functions() {
        // Funciones básicas
        $functions_file = YEISON_BTX_PLUGIN_DIR . 'includes/functions.php';
        if (file_exists($functions_file)) {
            require_once $functions_file;
        }
        
        // API de Bitrix24
        $api_file = YEISON_BTX_PLUGIN_DIR . 'includes/class-bitrix-api.php';
        if (file_exists($api_file)) {
            require_once $api_file;
        }
        
        // Manejador de formularios
        $forms_file = YEISON_BTX_PLUGIN_DIR . 'includes/class-forms-handler.php';
        if (file_exists($forms_file)) {
            require_once $forms_file;
        }
        
        // Sincronización WooCommerce
        $woo_file = YEISON_BTX_PLUGIN_DIR . 'includes/class-woo-sync.php';
        if (file_exists($woo_file)) {
            require_once $woo_file;
        }
        
        // Manejador de webhooks
        $webhooks_file = YEISON_BTX_PLUGIN_DIR . 'includes/class-webhook-handler.php';
        if (file_exists($webhooks_file)) {
            require_once $webhooks_file;
        }
        
        // Sincronización bidireccional
        $bidirectional_file = YEISON_BTX_PLUGIN_DIR . 'includes/class-bidirectional-sync.php';
        if (file_exists($bidirectional_file)) {
            require_once $bidirectional_file;
        }
        
        // Sistema anti-loop avanzado
        $anti_loop_file = YEISON_BTX_PLUGIN_DIR . 'includes/class-anti-loop.php';
        if (file_exists($anti_loop_file)) {
            require_once $anti_loop_file;
        }
        
        // Sistema de mapeo de datos
        $data_mapping_file = YEISON_BTX_PLUGIN_DIR . 'includes/class-data-mapping.php';
        if (file_exists($data_mapping_file)) {
            require_once $data_mapping_file;
        }
    }

    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Hooks de activación/desactivación
        register_activation_hook(YEISON_BTX_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(YEISON_BTX_PLUGIN_FILE, array($this, 'deactivate'));
        
        // Verificar requisitos
        add_action('admin_init', array($this, 'check_requirements'));
        add_action('admin_init', array($this, 'handle_oauth_callback'));
        
        // Cargar textdomain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Solo en admin
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        }
        // Verificar tablas en cada carga del admin

        add_action('admin_init', array($this, 'ensure_tables_exist'), 1);

        // Inicializar todas las clases del sistema
        $this->init_all_components();




    }
    
    /**
     * Inicializar todos los componentes del sistema
     */
    private function init_all_components() {
        // API de Bitrix24
        if (class_exists('YeisonBTX_Bitrix_API')) {
            yeison_btx_api();
        }
        
        // Manejador de formularios
        if (class_exists('YeisonBTX_Forms_Handler')) {
            yeison_btx_forms();
        }
        
        // Sincronización WooCommerce
        if (class_exists('YeisonBTX_WooCommerce_Sync')) {
            yeison_btx_woo_sync();
        }
        
        // Manejador de webhooks
        if (class_exists('YeisonBTX_Webhook_Handler')) {
            yeison_btx_webhooks();
        }
        
        // Sincronización bidireccional
        if (class_exists('YeisonBTX_Bidirectional_Sync')) {
            yeison_btx_bidirectional_sync();
        }
        
        // Sistema anti-loop avanzado
        if (class_exists('YeisonBTX_Anti_Loop')) {
            yeison_btx_anti_loop();
        }
        
        // Sistema de mapeo de datos
        if (class_exists('YeisonBTX_Data_Mapping')) {
            yeison_btx_data_mapping();
        }
    }

    /**
     * Manejar callback OAuth
     */
    public function handle_oauth_callback() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'yeison-btx') {
            return;
        }
        
        if (!isset($_GET['action']) || $_GET['action'] !== 'oauth') {
            return;
        }
        
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            return;
        }
        
        $api = yeison_btx_api();
        $result = $api->exchange_code_for_tokens($_GET['code'], $_GET['state']);
        
        if ($result) {
            wp_redirect(admin_url('admin.php?page=yeison-btx&oauth=success'));
            do_action('yeison_btx_oauth_success');
        } else {
            wp_redirect(admin_url('admin.php?page=yeison-btx&oauth=error'));
        }
        exit;
    }
    
    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        // Menú principal
        add_menu_page(
            'BTX Sync',
            'BTX Sync',
            'manage_options',
            'yeison-btx',
            array($this, 'admin_page'),
            'dashicons-admin-site',
            30
        );
        
        // Submenú - Configuración (renombrar el principal para evitar duplicación)
        add_submenu_page(
            'yeison-btx',
            'Servykc',
            'Admin',
            'manage_options',
            'yeison-btx',
            array($this, 'admin_page')
        );
        
        // Submenú - Configuración Avanzada
        add_submenu_page(
            'yeison-btx',
            'Ajustes',
            'Ajustes',
            'manage_options',
            'yeison-btx-advanced',
            array($this, 'advanced_config_page')
        );
        
        // Submenú - Logs y Monitoreo
        add_submenu_page(
            'yeison-btx',
            'Logs y Monitoreo',
            'Logs',
            'manage_options',
            'yeison-btx-logs',
            array($this, 'logs_page_dos')
        );
    }
    

    /**
     * Página principal de administración con diseño mejorado y responsive
     * Mantiene toda la funcionalidad original pero con mejor layout
     */
    
    public function admin_page() {
        $api = yeison_btx_api();

        // Procesar limpieza de tokens
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['yeison_btx_clear_tokens'])) {
            check_admin_referer('yeison_btx_clear_tokens');
            
            $api->clear_tokens();
            
            yeison_btx_log('Tokens limpiados manualmente desde admin', 'info', array(
                'user_id' => get_current_user_id()
            ));
            
            $tokens_cleared = true;
        }

        // Procesar formulario de configuración
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['yeison_btx_config'])) {
            check_admin_referer('yeison_btx_config');
            
            $domain = sanitize_text_field($_POST['bitrix_domain']);
            $client_id = sanitize_text_field($_POST['client_id']);
            $client_secret = sanitize_text_field($_POST['client_secret']);
            $pipeline_id = intval($_POST['woocommerce_pipeline_id'] ?? 0);

            // Asegurar que se capture y guarde el pipeline
            $pipeline_id = isset($_POST['woocommerce_pipeline_id']) ? intval($_POST['woocommerce_pipeline_id']) : 0;
            
            // Si cambió el dominio, limpiar tokens automáticamente
            $old_domain = yeison_btx_get_option('bitrix_domain');
            if ($old_domain && $old_domain !== $domain) {
                $api->clear_tokens();
                yeison_btx_log('Tokens limpiados por cambio de dominio', 'info', array(
                    'old_domain' => $old_domain,
                    'new_domain' => $domain
                ));
                $domain_changed = true;
            }

            // Guardar configuración
            yeison_btx_update_option('bitrix_domain', $domain);
            yeison_btx_update_option('client_id', $client_id);
            yeison_btx_update_option('client_secret', $client_secret);
            yeison_btx_update_option('woocommerce_pipeline_id', $pipeline_id);
            
            yeison_btx_log('Configuración guardada', 'info', array(
                'domain' => $domain,
                'client_id' => $client_id
            ));
            
            $config_saved = true;
        }
        
        // Test automático de tokens al cargar la página
        $connection_status = null;
        if ($api->is_configured() && !empty(yeison_btx_get_option('access_token'))) {
            $connection_status = $api->test_connection();
        }
        
        ?>

            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>GuruX BTX Sync - Dashboard</title>
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            </head>
            <body>
                <div class="yeison-admin-container">
                    <style>
                        /* =============================================================================
                        ESTILOS EMPRESARIALES MEJORADOS - DISEÑO COMPACTO Y PROFESIONAL
                        ============================================================================= */
                        
                        /* Reset y base */
                        * {
                            box-sizing: border-box;
                        }

                        body {
                            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                            line-height: 1.6;
                            margin: 0;
                            padding: 0;
                        }
                        
                        /* Indicadores de pipeline empresariales */
                        .pipeline-indicator {
                            display: inline-block;
                            padding: 6px 12px;
                            border-radius: 8px;
                            font-size: 12px;
                            font-weight: 600;
                            margin-left: 12px;
                            letter-spacing: 0.5px;
                            border: 1px solid;
                        }

                        .pipeline-indicator.pipeline-0 { background: #E8F2FF; color: #1E40AF; border-color: #93C5FD; }
                        .pipeline-indicator.pipeline-1 { background: #F0F9FF; color: #0369A1; border-color: #7DD3FC; }
                        .pipeline-indicator.pipeline-2 { background: #F8FAFC; color: #1E293B; border-color: #CBD5E0; }
                        .pipeline-indicator.pipeline-3 { background: #F1F5F9; color: #334155; border-color: #E2E8F0; }

                        .mapping-pipeline-badge {
                            position: absolute;
                            top: 12px;
                            right: 12px;
                            background: linear-gradient(135deg, #4F46E5 0%, #3730A3 100%);
                            color: white;
                            padding: 6px 14px;
                            border-radius: 20px;
                            font-size: 11px;
                            font-weight: 600;
                            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
                            border: 1px solid rgba(255, 255, 255, 0.2);
                        }

                        /* Contenedor principal empresarial */
                        .yeison-admin-container {
                            max-width: 1600px;
                            margin: 0 auto;
                            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                            background: linear-gradient(135deg, #F8FAFC 0%, #E8F2FF 50%, #F0F4F8 100%);
                            min-height: 100vh;
                            padding: 20px;
                            color: #1E293B;
                        }
                        
                        /* Header empresarial elegante */
                        .yeison-admin-header {
                            background: linear-gradient(135deg, #FFFFFF 0%, #F8FAFC 100%);
                            padding: 32px 40px;
                            border-radius: 16px;
                            box-shadow: 0 4px 24px rgba(79, 70, 229, 0.08);
                            margin-bottom: 24px;
                            text-align: center;
                            border: 1px solid #E2E8F0;
                            position: relative;
                            overflow: hidden;
                        }

                        .yeison-admin-header::before {
                            content: '';
                            position: absolute;
                            top: 0;
                            left: 0;
                            right: 0;
                            height: 4px;
                            background: linear-gradient(90deg, #A5B4FC 0%, #ddc2ef 50%, #A5B4FC 100%);
                        }
                        
                        .yeison-admin-title {
                            margin: 0;
                            font-size: 32px;
                            color: #1E293B;
                            font-weight: 600;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            gap: 16px;
                            flex-wrap: wrap;
                            letter-spacing: -0.5px;
                        }
                        
                        .yeison-admin-subtitle {
                            margin: 12px 0 0 0;
                            color: #64748B;
                            font-size: 16px;
                            font-weight: 400;
                            opacity: 0.9;
                        }
                        
                        .yeison-admin-version {
                            background: linear-gradient(135deg, #A5B4FC 0%, #ddc2ef 100%);
                            color: white;
                            padding: 8px 16px;
                            border-radius: 20px;
                            font-size: 13px;
                            font-weight: 600;
                            margin-left: 12px;
                            box-shadow: 0 4px 16px rgba(139, 92, 246, 0.25);
                            border: 2px solid rgba(255, 255, 255, 0.2);
                        }
                        
                        /* Layout empresarial responsive - MÁS COMPACTO */
                        .yeison-admin-content {
                            display: grid;
                            grid-template-columns: 1fr 380px;
                            gap: 20px;
                            align-items: start;
                        }
                        
                        .yeison-admin-main {
                            display: flex;
                            flex-direction: column;
                            gap: 20px;
                        }
                        
                        .yeison-admin-sidebar {
                            display: flex;
                            flex-direction: column;
                            gap: 16px;
                            position: sticky;
                            top: 20px;
                        }
                        
                        /* Cards empresariales MÁS COMPACTAS */
                        .yeison-card {
                            background: linear-gradient(135deg, #FFFFFF 0%, #F8FAFC 100%);
                            border-radius: 12px;
                            overflow: hidden;
                            box-shadow: 0 2px 16px rgba(79, 70, 229, 0.06);
                            transition: all 0.3s ease;
                            border: 1px solid #E2E8F0;
                        }
                        
                        .yeison-card:hover {
                            transform: translateY(-2px);
                            box-shadow: 0 6px 24px rgba(79, 70, 229, 0.12);
                            border-color: #CBD5E0;
                        }
                        
                        .yeison-card-header {
                            padding: 20px 24px;
                            background: linear-gradient(135deg, #A5B4FC 0%, #ddc2ef 100%);
                            color: white;
                            border-bottom: none;
                            position: relative;
                        }

                        .yeison-card-header::after {
                            content: '';
                            position: absolute;
                            bottom: 0;
                            left: 0;
                            right: 0;
                            height: 1px;
                            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
                        }
                        
                        .yeison-card-title {
                            margin: 0;
                            font-size: 18px;
                            font-weight: 600;
                            display: flex;
                            align-items: center;
                            gap: 10px;
                            letter-spacing: -0.2px;
                        }
                        
                        .yeison-card-content {
                            padding: 24px;
                            background: #FFFFFF;
                        }

                        /* NUEVA SECCIÓN COMPACTA UNIFICADA */
                        .yeison-unified-controls {
                            background: linear-gradient(135deg, #FFFFFF 0%, #F8FAFC 100%);
                            border-radius: 16px;
                            overflow: hidden;
                            box-shadow: 0 4px 20px rgba(79, 70, 229, 0.08);
                            border: 1px solid #E2E8F0;
                            transition: all 0.3s ease;
                        }

                        .yeison-unified-controls:hover {
                            transform: translateY(-1px);
                            box-shadow: 0 6px 28px rgba(79, 70, 229, 0.12);
                        }

                        .yeison-unified-header {
                            background: linear-gradient(135deg, #86EFAC 0%, #22D3EE 100%);
                            color: white;
                            padding: 16px 20px;
                            text-align: center;
                            position: relative;
                        }

                        .yeison-unified-header h3 {
                            margin: 0;
                            font-size: 18px;
                            font-weight: 600;
                            letter-spacing: -0.2px;
                        }

                        .yeison-unified-content {
                            padding: 20px;
                            background: #FFFFFF;
                        }

                        /* Grid de estadísticas más compacto */
                        .yeison-compact-stats {
                            display: grid;
                            grid-template-columns: repeat(2, 1fr);
                            gap: 12px;
                            margin-bottom: 20px;
                        }

                        .yeison-compact-stat {
                            text-align: center;
                            padding: 16px 12px;
                            background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%);
                            border-radius: 10px;
                            border: 1px solid #E2E8F0;
                            transition: all 0.3s ease;
                            position: relative;
                            overflow: hidden;
                        }

                        .yeison-compact-stat::before {
                            content: '';
                            position: absolute;
                            top: 0;
                            left: 0;
                            right: 0;
                            height: 3px;
                            background: linear-gradient(90deg, #A5B4FC, #ddc2ef);
                        }

                        .yeison-compact-stat:hover {
                            transform: translateY(-2px);
                            box-shadow: 0 4px 16px rgba(79, 70, 229, 0.15);
                            background: linear-gradient(135deg, #E8F2FF 0%, #DBEAFE 100%);
                        }

                        .yeison-compact-stat-number {
                            font-size: 24px;
                            font-weight: 700;
                            color: #1E293B;
                            margin-bottom: 4px;
                            letter-spacing: -0.5px;
                        }

                        .yeison-compact-stat-label {
                            color: #64748B;
                            font-size: 11px;
                            font-weight: 600;
                            text-transform: uppercase;
                            letter-spacing: 0.5px;
                        }

                        /* Autorización compacta */
                        .yeison-compact-auth {
                            margin: 20px 0;
                            padding: 20px;
                            background: linear-gradient(135deg, #F0F9FF 0%, #E0F2FE 100%);
                            border-radius: 12px;
                            border: 2px solid #BFDBFE;
                            text-align: center;
                            box-shadow: 0 2px 12px rgba(14, 165, 233, 0.1);
                            transition: all 0.3s ease;
                        }

                        .yeison-compact-auth:hover {
                            transform: translateY(-1px);
                            box-shadow: 0 4px 16px rgba(14, 165, 233, 0.15);
                        }

                        .yeison-compact-auth.warning {
                            background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
                            border-color: #FDE68A;
                            box-shadow: 0 2px 12px rgba(251, 191, 36, 0.1);
                        }

                        .yeison-compact-auth.warning:hover {
                            box-shadow: 0 4px 16px rgba(251, 191, 36, 0.15);
                        }

                        .yeison-compact-auth.error {
                            background: linear-gradient(135deg, #FEF7FF 0%, #FAE8FF 100%);
                            border-color: #F3E8FF;
                            box-shadow: 0 2px 12px rgba(168, 85, 247, 0.1);
                        }

                        .yeison-compact-auth.error:hover {
                            box-shadow: 0 4px 16px rgba(168, 85, 247, 0.15);
                        }

                        .yeison-compact-auth-status {
                            font-size: 15px;
                            font-weight: 600;
                            margin-bottom: 12px;
                            letter-spacing: 0.2px;
                        }

                        /* Herramientas compactas */
                        .yeison-compact-tools {
                            display: grid;
                            grid-template-columns: repeat(2, 1fr);
                            gap: 8px;
                            margin-top: 16px;
                        }

                        .yeison-compact-tool {
                            display: flex;
                            align-items: center;
                            gap: 8px;
                            padding: 10px 12px;
                            border: none;
                            border-radius: 8px;
                            font-size: 12px;
                            font-weight: 600;
                            cursor: pointer;
                            transition: all 0.3s ease;
                            text-decoration: none;
                            position: relative;
                            overflow: hidden;
                            min-height: 40px;
                            box-sizing: border-box;
                            font-family: inherit;
                            letter-spacing: 0.2px;
                        }

                        .yeison-compact-tool::before {
                            content: '';
                            position: absolute;
                            top: 0;
                            left: -100%;
                            width: 100%;
                            height: 100%;
                            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
                            transition: left 0.5s;
                        }

                        .yeison-compact-tool:hover::before {
                            left: 100%;
                        }

                        .yeison-compact-tool:hover {
                            transform: translateY(-1px);
                            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                        }

                        .yeison-compact-tool-icon {
                            font-size: 14px;
                            flex-shrink: 0;
                            width: 16px;
                            text-align: center;
                        }

                        .yeison-compact-tool-text {
                            flex: 1;
                            text-align: left;
                            line-height: 1.2;
                            font-size: 11px;
                        }

                        .yeison-compact-tool-primary {
                            background: linear-gradient(135deg, #A5B4FC 0%, #8B5CF6 100%);
                            color: white;
                            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.25);
                        }

                        .yeison-compact-tool-primary:hover {
                            background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
                            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.35);
                        }

                        .yeison-compact-tool-secondary {
                            background: linear-gradient(135deg, #F1F5F9, #E2E8F0);
                            color: #475569;
                            border: 1px solid #CBD5E1;
                        }

                        .yeison-compact-tool-secondary:hover {
                            background: linear-gradient(135deg, #E2E8F0, #CBD5E1);
                            border-color: #94A3B8;
                            box-shadow: 0 3px 10px rgba(71, 85, 105, 0.15);
                        }

                        .yeison-compact-tool-warning {
                            background: linear-gradient(135deg, #FCD34D 0%, #F59E0B 100%);
                            color: white;
                            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.25);
                        }

                        .yeison-compact-tool-warning:hover {
                            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
                            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35);
                        }

                        .yeison-compact-tool-danger {
                            background: linear-gradient(135deg, #FCA5A5 0%, #F87171 100%);
                            color: white;
                            box-shadow: 0 2px 8px rgba(248, 113, 113, 0.25);
                        }

                        .yeison-compact-tool-danger:hover {
                            background: linear-gradient(135deg, #F87171 0%, #EF4444 100%);
                            box-shadow: 0 4px 12px rgba(248, 113, 113, 0.35);
                        }
                        
                        /* Salud del sistema empresarial COMPACTO */
                        .yeison-health-container {
                            background: linear-gradient(135deg, #FFFFFF 0%, #F8FAFC 100%);
                            border-radius: 12px;
                            overflow: hidden;
                            box-shadow: 0 2px 16px rgba(79, 70, 229, 0.06);
                            transition: all 0.3s ease;
                            border: 1px solid #E2E8F0;
                            margin-bottom: 16px;
                        }
                        
                        .yeison-health-score {
                            text-align: center;
                            padding: 24px 20px;
                            background: linear-gradient(135deg, #3730A3 0%, #4F46E5 100%);
                            color: white;
                            position: relative;
                            overflow: hidden;
                        }
                        
                        .yeison-health-score::before {
                            content: '';
                            position: absolute;
                            top: -50%;
                            left: -50%;
                            width: 200%;
                            height: 200%;
                            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
                            animation: pulse 6s ease-in-out infinite;
                        }
                        
                        @keyframes pulse {
                            0%, 100% { transform: scale(1); opacity: 0.8; }
                            50% { transform: scale(1.05); opacity: 0.4; }
                        }
                        
                        .yeison-health-number {
                            font-size: 40px;
                            font-weight: 700;
                            margin-bottom: 8px;
                            position: relative;
                            z-index: 2;
                            letter-spacing: -1px;
                        }
                        
                        .yeison-health-label {
                            font-size: 13px;
                            opacity: 0.95;
                            text-transform: uppercase;
                            letter-spacing: 1px;
                            position: relative;
                            z-index: 2;
                            font-weight: 500;
                        }
                        
                        /* Grid de componentes empresarial COMPACTO */
                        .yeison-components-grid {
                            display: grid;
                            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
                            gap: 10px;
                            padding: 16px;
                            background: linear-gradient(135deg, #F8FAFC 0%, #E8F2FF 100%);
                        }
                        
                        .yeison-component {
                            text-align: center;
                            padding: 16px 12px;
                            border-radius: 8px;
                            transition: all 0.3s ease;
                            border: 2px solid transparent;
                            background: white;
                        }
                        
                        .yeison-component:hover {
                            transform: translateY(-1px);
                            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.15);
                        }
                        
                        .yeison-component.active {
                            background: linear-gradient(135deg, #DBEAFE, #93C5FD);
                            color: #1E40AF;
                            border-color: #4F46E5;
                            box-shadow: 0 2px 12px rgba(79, 70, 229, 0.2);
                        }
                        
                        .yeison-component.inactive {
                            background: linear-gradient(135deg, #FEF2F2, #FECACA);
                            color: #B91C1C;
                            border-color: #EF4444;
                            box-shadow: 0 2px 12px rgba(239, 68, 68, 0.2);
                        }
                        
                        .yeison-component-icon {
                            font-size: 20px;
                            margin-bottom: 8px;
                            display: block;
                        }
                        
                        .yeison-component-name {
                            font-size: 11px;
                            font-weight: 600;
                            letter-spacing: 0.3px;
                        }
                        
                        /* Formularios empresariales */
                        .yeison-form-table {
                            width: 100%;
                        }
                        
                        .yeison-form-row {
                            display: grid;
                            grid-template-columns: 200px 1fr;
                            gap: 20px;
                            align-items: start;
                            padding: 20px 0;
                            border-bottom: 1px solid #E2E8F0;
                        }
                        
                        .yeison-form-row:last-child {
                            border-bottom: none;
                        }
                        
                        .yeison-form-label {
                            font-weight: 600;
                            color: #374151;
                            font-size: 14px;
                            padding-top: 12px;
                            letter-spacing: 0.2px;
                        }
                        
                        .yeison-form-input {
                            width: 100%;
                            padding: 14px 16px;
                            border: 2px solid #E2E8F0;
                            border-radius: 8px;
                            font-size: 14px;
                            transition: all 0.3s ease;
                            box-sizing: border-box;
                            background: #FFFFFF;
                            font-family: inherit;
                        }
                        
                        .yeison-form-input:focus {
                            outline: none;
                            border-color: #4F46E5;
                            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
                            background: #FEFEFE;
                        }
                        
                        .yeison-form-description {
                            color: #64748B;
                            font-size: 12px;
                            margin-top: 6px;
                            font-style: italic;
                            line-height: 1.4;
                        }
                        
                        /* Botones empresariales */
                        .yeison-btn {
                            padding: 14px 24px;
                            border: none;
                            border-radius: 8px;
                            font-size: 14px;
                            font-weight: 600;
                            cursor: pointer;
                            transition: all 0.3s ease;
                            text-decoration: none;
                            display: inline-block;
                            text-align: center;
                            position: relative;
                            overflow: hidden;
                            font-family: inherit;
                            letter-spacing: 0.3px;
                        }
                        
                        .yeison-btn::before {
                            content: '';
                            position: absolute;
                            top: 0;
                            left: -100%;
                            width: 100%;
                            height: 100%;
                            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
                            transition: left 0.5s;
                        }
                        
                        .yeison-btn:hover::before {
                            left: 100%;
                        }
                        
                        .yeison-btn-primary {
                            background: linear-gradient(135deg, #A5B4FC 0%, #8B5CF6 100%);
                            color: white;
                            box-shadow: 0 4px 16px rgba(139, 92, 246, 0.25);
                            border: 2px solid transparent;
                        }
                        
                        .yeison-btn-primary:hover {
                            transform: translateY(-2px);
                            background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
                            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.35);
                        }
                        
                        .yeison-btn-success {
                            background: linear-gradient(135deg, #86EFAC 0%, #22D3EE 100%);
                            color: white;
                            box-shadow: 0 4px 16px rgba(34, 211, 238, 0.25);
                        }

                        .yeison-btn-success:hover {
                            transform: translateY(-2px);
                            background: linear-gradient(135deg, #22D3EE 0%, #0EA5E9 100%);
                            box-shadow: 0 6px 20px rgba(34, 211, 238, 0.35);
                        }
                        
                        .yeison-btn-secondary {
                            background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%);
                            color: #475569;
                            border: 2px solid #CBD5E1;
                        }
                        
                        .yeison-btn-secondary:hover {
                            background: linear-gradient(135deg, #E2E8F0 0%, #CBD5E1 100%);
                            transform: translateY(-1px);
                            border-color: #94A3B8;
                            box-shadow: 0 4px 12px rgba(71, 85, 105, 0.15);
                        }
                        
                        .yeison-btn-danger {
                            background: linear-gradient(135deg, #FCA5A5 0%, #F87171 100%);
                            color: white;
                            box-shadow: 0 4px 16px rgba(248, 113, 113, 0.25);
                        }

                        .yeison-btn-danger:hover {
                            transform: translateY(-2px);
                            background: linear-gradient(135deg, #F87171 0%, #EF4444 100%);
                            box-shadow: 0 6px 20px rgba(248, 113, 113, 0.35);
                        }

                        .yeison-btn-warning {
                            background: linear-gradient(135deg, #FCD34D 0%, #F59E0B 100%);
                            color: white;
                            box-shadow: 0 4px 16px rgba(245, 158, 11, 0.25);
                        }

                        .yeison-btn-warning:hover {
                            transform: translateY(-2px);
                            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
                            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.35);
                        }
                        
                        /* Estados y notificaciones empresariales */
                        .yeison-status {
                            display: flex;
                            align-items: center;
                            gap: 12px;
                            padding: 16px 20px;
                            border-radius: 10px;
                            font-weight: 500;
                            margin: 16px 0;
                            border: 2px solid transparent;
                            font-size: 14px;
                        }
                        
                        .yeison-status.success {
                            background: linear-gradient(135deg, #DBEAFE, #93C5FD);
                            color: #1E40AF;
                            border-color: #4F46E5;
                        }
                        
                        .yeison-status.error {
                            background: linear-gradient(135deg, #FEF2F2, #FECACA);
                            color: #B91C1C;
                            border-color: #EF4444;
                        }
                        
                        .yeison-status.warning {
                            background: linear-gradient(135deg, #FFFBEB, #FDE68A);
                            color: #92400E;
                            border-color: #F59E0B;
                        }
                        
                        .yeison-status.info {
                            background: linear-gradient(135deg, #E8F2FF, #DBEAFE);
                            color: #3730A3;
                            border-color: #4F46E5;
                        }
                        
                        /* Logs empresariales */
                        .yeison-recent-logs {
                            max-height: 300px;
                            overflow-y: auto;
                            border: 2px solid #E2E8F0;
                            border-radius: 10px;
                            background: #F8FAFC;
                        }
                        
                        .yeison-log-item {
                            padding: 12px 16px;
                            border-bottom: 1px solid #E2E8F0;
                            font-size: 12px;
                            transition: background 0.2s ease;
                            line-height: 1.4;
                        }
                        
                        .yeison-log-item:hover {
                            background: #E8F2FF;
                        }
                        
                        .yeison-log-item:last-child {
                            border-bottom: none;
                        }
                        
                        .yeison-log-type {
                            display: inline-block;
                            padding: 3px 8px;
                            border-radius: 6px;
                            font-size: 9px;
                            font-weight: 600;
                            text-transform: uppercase;
                            margin-right: 10px;
                            letter-spacing: 0.5px;
                        }
                        
                        .yeison-log-type.success { background: #DBEAFE; color: #1E40AF; }
                        .yeison-log-type.error { background: #FEF2F2; color: #B91C1C; }
                        .yeison-log-type.warning { background: #FFFBEB; color: #92400E; }
                        .yeison-log-type.info { background: #E8F2FF; color: #3730A3; }
                        
                        .yeison-log-time {
                            float: right;
                            color: #64748B;
                            font-size: 10px;
                            font-weight: 500;
                        }
                        
                        /* Notificaciones empresariales */
                        .yeison-notice {
                            padding: 18px 24px;
                            border-radius: 10px;
                            margin: 20px 0;
                            font-weight: 500;
                            border: 2px solid transparent;
                            font-size: 14px;
                        }
                        
                        .yeison-notice.success {
                            background: linear-gradient(135deg, #DBEAFE, #93C5FD);
                            color: #1E40AF;
                            border-left: 6px solid #4F46E5;
                        }
                        
                        .yeison-notice.error {
                            background: linear-gradient(135deg, #FEF2F2, #FECACA);
                            color: #B91C1C;
                            border-left: 6px solid #EF4444;
                        }

                        /* Estilos para estados de carga mejorados */
                        .yeison-loading-spinner {
                            display: inline-block;
                            width: 20px;
                            height: 20px;
                            border: 3px solid rgba(79, 70, 229, 0.3);
                            border-radius: 50%;
                            border-top-color: #4F46E5;
                            animation: spin 1s ease-in-out infinite;
                        }

                        @keyframes spin {
                            to { transform: rotate(360deg); }
                        }

                        .yeison-error-details {
                            margin-top: 10px;
                            padding: 10px;
                            background: #FEF2F2;
                            border: 1px solid #FECACA;
                            border-radius: 6px;
                            font-size: 12px;
                            color: #B91C1C;
                        }

                        /* =============================================================================
                        RESPONSIVE DESIGN EMPRESARIAL MÁS COMPACTO
                        ============================================================================= */
                        
                        @media (max-width: 1400px) {
                            .yeison-admin-content {
                                grid-template-columns: 1fr 340px;
                                gap: 16px;
                            }
                        }
                        
                        @media (max-width: 1200px) {
                            .yeison-admin-container {
                                padding: 16px;
                            }
                            
                            .yeison-admin-content {
                                grid-template-columns: 1fr;
                                gap: 20px;
                            }
                            
                            .yeison-admin-sidebar {
                                order: -1;
                                position: static;
                            }
                            
                            .yeison-admin-header {
                                padding: 24px 20px;
                            }
                            
                            .yeison-admin-title {
                                font-size: 28px;
                            }

                            .yeison-compact-stats {
                                grid-template-columns: repeat(4, 1fr);
                            }

                            .yeison-compact-tools {
                                grid-template-columns: repeat(4, 1fr);
                            }
                        }
                        
                        @media (max-width: 768px) {
                            .yeison-admin-container {
                                padding: 12px;
                            }
                            
                            .yeison-admin-header {
                                padding: 20px 16px;
                                margin-bottom: 16px;
                            }
                            
                            .yeison-admin-title {
                                font-size: 24px;
                                flex-direction: column;
                                gap: 8px;
                            }
                            
                            .yeison-admin-version {
                                margin-left: 0;
                            }
                            
                            .yeison-admin-subtitle {
                                font-size: 14px;
                            }
                            
                            .yeison-card-content {
                                padding: 20px;
                            }
                            
                            .yeison-form-row {
                                grid-template-columns: 1fr;
                                gap: 12px;
                                padding: 16px 0;
                            }
                            
                            .yeison-form-label {
                                padding-top: 0;
                            }
                            
                            .yeison-compact-stats {
                                grid-template-columns: repeat(2, 1fr);
                                gap: 10px;
                            }
                            
                            .yeison-compact-tools {
                                grid-template-columns: repeat(2, 1fr);
                                gap: 8px;
                            }
                            
                            .yeison-components-grid {
                                grid-template-columns: repeat(2, 1fr);
                                padding: 16px;
                            }
                            
                            .yeison-health-score {
                                padding: 20px 16px;
                            }
                            
                            .yeison-health-number {
                                font-size: 32px;
                            }
                            
                            .yeison-btn {
                                padding: 12px 20px;
                                width: 100%;
                                margin-bottom: 10px;
                            }
                        }
                        
                        @media (max-width: 480px) {
                            .yeison-admin-title {
                                font-size: 20px;
                            }
                            
                            .yeison-compact-stats {
                                grid-template-columns: 1fr;
                            }
                            
                            .yeison-compact-tools {
                                grid-template-columns: 1fr;
                            }
                            
                            .yeison-components-grid {
                                grid-template-columns: 1fr;
                            }

                            .yeison-compact-tool {
                                padding: 12px 14px;
                                gap: 10px;
                                font-size: 13px;
                                min-height: 44px;
                            }
                            
                            .yeison-compact-tool-icon {
                                font-size: 16px;
                                width: 20px;
                            }
                            
                            .yeison-compact-tool-text {
                                font-size: 12px;
                                line-height: 1.3;
                            }
                        }
                    </style>
                    
                    
                    <!-- Mensajes de notificación -->
                    <?php if (isset($config_saved)): ?>
                    <div class="yeison-notice success">
                        ✅ <strong>Configuración guardada correctamente</strong>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['oauth'])): ?>
                        <?php if ($_GET['oauth'] === 'success'): ?>
                        <div class="yeison-notice success">
                            🎉 <strong>¡Autorización exitosa!</strong> Tu sitio está conectado con Bitrix24.
                        </div>
                        <?php else: ?>
                        <div class="yeison-notice error">
                            ❌ <strong>Error en autorización.</strong> Revisa los logs para más detalles.
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Contenido Principal mejorado -->
                    <div class="yeison-admin-content">
                        <!-- Columna Principal -->
                        <div class="yeison-admin-main">
                            <!-- Configuración de Bitrix24 -->
                            <div class="yeison-card">
                                <div class="yeison-card-header">
                                    <h2 class="yeison-card-title">⚙️ Configuración de Bitrix24</h2>
                                </div>
                                <div class="yeison-card-content">
                                    <form method="post">
                                        <?php wp_nonce_field('yeison_btx_config'); ?>
                                        <input type="hidden" name="yeison_btx_config" value="1">
                                        
                                        <div class="yeison-form-table">
                                            <div class="yeison-form-row">
                                                <label class="yeison-form-label">Dominio de Bitrix24</label>
                                                <div>
                                                    <input type="text" 
                                                        name="bitrix_domain" 
                                                        value="<?php echo esc_attr(yeison_btx_get_option('bitrix_domain')); ?>" 
                                                        class="yeison-form-input" 
                                                        placeholder="miempresa.bitrix24.com">
                                                    <div class="yeison-form-description">Sin https://, solo el dominio</div>
                                                </div>
                                            </div>
                                            
                                            <div class="yeison-form-row">
                                                <label class="yeison-form-label">Client ID</label>
                                                <div>
                                                    <input type="text" 
                                                        name="client_id" 
                                                        value="<?php echo esc_attr(yeison_btx_get_option('client_id')); ?>" 
                                                        class="yeison-form-input"
                                                        placeholder="local.xxxxxxx.xxxxxxx">
                                                </div>
                                            </div>
                                            
                                            <div class="yeison-form-row">
                                                <label class="yeison-form-label">Client Secret</label>
                                                <div>
                                                    <input type="password" 
                                                        name="client_secret" 
                                                        value="<?php echo esc_attr(yeison_btx_get_option('client_secret')); ?>" 
                                                        class="yeison-form-input"
                                                        placeholder="••••••••••••••••••••••">
                                                </div>
                                            </div>

                                            <div class="yeison-form-row">
                                                <label class="yeison-form-label">A donde van los pedidos</label>
                                                <div>
                                                    <select name="woocommerce_pipeline_id" 
                                                            id="pipeline-selector" 
                                                            class="yeison-form-input" 
                                                            onchange="loadPipelineStages()">
                                                        <?php 
                                                        // Obtener pipeline actualmente seleccionado
                                                        $selected_pipeline = yeison_btx_get_option('woocommerce_pipeline_id', 0);
                                                        
                                                        // Opción por defecto
                                                        echo '<option value="0"' . selected($selected_pipeline, 0, false) . '>General (por defecto)</option>';
                                                        
                                                        // Obtener y mostrar pipelines disponibles
                                                        if (function_exists('yeison_btx_get_bitrix24_categories')) {
                                                            $categories = yeison_btx_get_bitrix24_categories();
                                                            foreach ($categories as $cat_id => $cat_name) {
                                                                if ($cat_id != 0) { // No duplicar General
                                                                    echo '<option value="' . esc_attr($cat_id) . '"' . selected($selected_pipeline, $cat_id, false) . '>' . esc_html($cat_name) . '</option>';
                                                                }
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                    <div class="yeison-form-description">Pipeline donde se crearán los deals de WooCommerce</div>
                                                    <div id="pipeline-status" style="margin-top: 10px;"></div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div style="margin-top: 24px;">
                                            <button type="submit" class="yeison-btn yeison-btn-primary">
                                                💾 Guardar Configuración
                                            </button>
                                        </div>
                                    </form>

                                </div>
                            </div>

                            <!-- Header mejorado -->
                            <div class="yeison-admin-header">
                                <!-- Autorización Integrada -->
                                <?php if ($api->is_configured()): ?>
                                    <?php if ($api->is_authorized()): ?>
                                        <div class="yeison-compact-auth">
                                            <div class="yeison-compact-auth-status">✅ Autorizado correctamente</div>
                                            <button onclick="testConnection()" class="yeison-btn yeison-btn-secondary" style="font-size: 13px; padding: 10px 18px;">
                                                🔍 Probar Conexión
                                            </button>
                                            <div id="connection-result" style="margin-top: 12px;"></div>
                                        </div>
                                    <?php else: ?>
                                        <div class="yeison-compact-auth warning">
                                            <div class="yeison-compact-auth-status">⚠️ Listo para autorizar</div>
                                            <a href="<?php echo esc_url($api->get_auth_url()); ?>" 
                                            class="yeison-btn yeison-btn-warning" style="font-size: 13px; padding: 10px 18px;">
                                                🚀 Autorizar con Bitrix24
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="yeison-compact-auth error">
                                        <div class="yeison-compact-auth-status">ℹ️ Completa la configuración primero</div>
                                    </div>
                                <?php endif; ?>
                                            
                                <p class="yeison-admin-subtitle">Sistema de sincronización bidireccional WooCommerce ↔ Bitrix24</p>
                            </div>

                            <!-- Mapeo de Estados CORREGIDO -->
                            <div class="yeison-card">
                                <div class="yeison-card-header">
                                    <h3 class="yeison-card-title">🔄 Mapeo de Estados</h3>
                                </div>
                                <div class="yeison-card-content">
                                    <div id="status-mapping-container">
                                        <div style="background: linear-gradient(135deg, #F0F9FF 0%, #E0F2FE 100%); padding: 20px; border-radius: 12px; border: 2px solid #BFDBFE; margin-bottom: 20px; box-shadow: 0 4px 16px rgba(14, 165, 233, 0.08);">
                                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #60A5FA 0%, #3B82F6 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);">
                                                    🎯
                                                </div>
                                                <div>
                                                    <h4 style="margin: 0; color: #0C4A6E; font-weight: 600; font-size: 16px;">Configuración de Mapeo</h4>
                                                    <p style="margin: 4px 0 0 0; color: #0369A1; font-size: 13px; opacity: 0.9;">Mapea los estados entre WooCommerce y el pipeline seleccionado</p>
                                                </div>
                                            </div>
                                            
                                            <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
                                                <!-- Verificación de autorización MEJORADA -->
                                                <?php if ($api->is_authorized()): ?>
                                                    <button onclick="toggleStatusMapping()" 
                                                            id="load-mapping-btn" 
                                                            class="yeison-btn yeison-btn-primary"
                                                            style="font-size: 14px; padding: 12px 20px; font-weight: 600;">
                                                        🔍 Cargar Estados del Pipeline
                                                    </button>
                                                <?php else: ?>
                                                    <div style="text-align: center; padding: 20px; background: #FEF3C7; border: 2px solid #FDE68A; border-radius: 8px;">
                                                        <p style="margin: 0; color: #92400E; font-weight: 600;">⚠️ Debes autorizar con Bitrix24 primero</p>
                                                        <p style="margin: 8px 0 0 0; color: #78350F; font-size: 12px;">El mapeo de estados requiere conexión activa</p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div id="mapping-status" style="margin-top: 12px;"></div>
                                        </div>
                                    </div>
                                    
                                    <!-- Contenedor expandible para mapeo MEJORADO -->
                                    <div id="mapping-content" style="display: none; margin-top: 24px; border-top: 3px solid #E0F2FE; padding-top: 24px;">
                                        <div id="mapping-loading" style="text-align: center; padding: 60px 40px; background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%); border-radius: 16px; border: 2px dashed #CBD5E1;">
                                            <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #A5B4FC 0%, #ddc2ef 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 8px 24px rgba(139, 92, 246, 0.2);">
                                                <div class="yeison-loading-spinner" style="border-color: rgba(255,255,255,0.3); border-top-color: white;"></div>
                                            </div>
                                            <h3 style="margin: 0 0 8px 0; color: #475569; font-weight: 600; font-size: 18px;">Cargando Estados del Pipeline</h3>
                                            <p style="margin: 0; color: #64748B; font-size: 14px; opacity: 0.8;">Preparando la interfaz de mapeo...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sidebar COMPACTO UNIFICADO -->
                        <div class="yeison-admin-sidebar">
                            <!-- Estado del Sistema compacto -->
                            <div class="yeison-health-container">
                                <?php
                                $system_health = $this->get_system_health();
                                $health_color = $system_health['score'] >= 90 ? '#10b981' : 
                                            ($system_health['score'] >= 70 ? '#f59e0b' : '#ef4444');
                                ?>
                                <div class="yeison-health-score" style="background: linear-gradient(135deg, <?php echo $health_color; ?> 0%, <?php echo $health_color; ?>dd 100%);">
                                    <div class="yeison-health-number"><?php echo $system_health['score']; ?>%</div>
                                    <div class="yeison-health-label">Salud del Sistema</div>
                                </div>
                                
                                <div class="yeison-components-grid">
                                    <?php foreach ($system_health['components'] as $component => $status): ?>
                                    <div class="yeison-component <?php echo $status ? 'active' : 'inactive'; ?>">
                                        <div class="yeison-component-icon"><?php echo $status ? '✅' : '❌'; ?></div>
                                        <div class="yeison-component-name"><?php echo $component; ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- PANEL UNIFICADO COMPACTO -->
                            <div class="yeison-unified-controls">
                                <div class="yeison-unified-header">
                                    <h3>🎛️ Panel de Control</h3>
                                </div>
                                <div class="yeison-unified-content">
                                    
                                    <!-- Estadísticas Compactas -->
                                    <?php if (function_exists('yeison_btx_get_stats')): ?>
                                    <?php $stats = yeison_btx_get_stats(); ?>
                                    <div class="yeison-compact-stats">
                                        <div class="yeison-compact-stat">
                                            <div class="yeison-compact-stat-number"><?php echo number_format($stats['total_logs']); ?></div>
                                            <div class="yeison-compact-stat-label">Total Logs</div>
                                        </div>
                                        <div class="yeison-compact-stat">
                                            <div class="yeison-compact-stat-number"><?php echo number_format($stats['total_synced']); ?></div>
                                            <div class="yeison-compact-stat-label">Sincronizados</div>
                                        </div>
                                        <div class="yeison-compact-stat">
                                            <div class="yeison-compact-stat-number" style="color: <?php echo $stats['pending_queue'] > 10 ? '#dc2626' : '#059669'; ?>;">
                                                <?php echo number_format($stats['pending_queue']); ?>
                                            </div>
                                            <div class="yeison-compact-stat-label">Cola Pendiente</div>
                                        </div>
                                        <div class="yeison-compact-stat">
                                            <div class="yeison-compact-stat-number" style="color: <?php echo $stats['errors_today'] > 5 ? '#dc2626' : '#059669'; ?>;">
                                                <?php echo number_format($stats['errors_today']); ?>
                                            </div>
                                            <div class="yeison-compact-stat-label">Errores Hoy</div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Herramientas Compactas -->
                                    <div class="yeison-compact-tools">
                                        <a href="admin-ajax.php?action=yeison_btx_repair_tables" class="yeison-compact-tool yeison-compact-tool-secondary" target="_blank">
                                            <span class="yeison-compact-tool-icon">🛠️</span>
                                            <span class="yeison-compact-tool-text">Verificar Tablas</span>
                                        </a>

                                        <a href="admin-ajax.php?action=yeison_btx_register_all_webhooks" class="yeison-compact-tool yeison-compact-tool-secondary" target="_blank">
                                            <span class="yeison-compact-tool-icon">📡</span>
                                            <span class="yeison-compact-tool-text">Verificar Webhooks</span>
                                        </a>

                                        <a href="admin-ajax.php?action=yeison_btx_nuclear_cleanup" class="yeison-compact-tool yeison-compact-tool-danger" target="_blank" onclick="return confirm('⚠️ ADVERTENCIA: Esto eliminará TODOS los datos del plugin.\n¿Estás completamente seguro?');">
                                            <span class="yeison-compact-tool-icon">💣</span>
                                            <span class="yeison-compact-tool-text">Limpieza Total</span>
                                        </a>
                                        
                                        <a href="admin.php?page=yeison-btx-logs" class="yeison-compact-tool yeison-compact-tool-primary">
                                            <span class="yeison-compact-tool-icon">📄</span>
                                            <span class="yeison-compact-tool-text">Ver Logs</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <script>
                    /* =============================================================================
                    JAVASCRIPT CORREGIDO PARA FUNCIONALIDAD DEL DASHBOARD
                    ============================================================================= */
                    
                    // Variables globales mejoradas
                    let statusData = {};
                    let mappingExpanded = false;
                    let currentPipelineId = 0;
                    let pipelineMappings = {};
                    let ajaxTimeout = 30000; // 30 segundos timeout
                    let retryCount = 0;
                    let maxRetries = 3;

                    // Test de conexión con feedback visual mejorado
                    function testConnection() {
                        const resultEl = document.getElementById('connection-result');
                        resultEl.innerHTML = '<div class="yeison-status info"><span class="yeison-loading-spinner"></span> Probando conexión...</div>';
                        
                        const controller = new AbortController();
                        const timeoutId = setTimeout(() => controller.abort(), ajaxTimeout);
                        
                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=yeison_btx_test_connection&nonce=<?php echo wp_create_nonce('yeison_btx_test'); ?>',
                            signal: controller.signal
                        })
                        .then(response => {
                            clearTimeout(timeoutId);
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                resultEl.innerHTML = '<div class="yeison-status success">✅ ' + data.data.message + '</div>';
                            } else {
                                let html = '<div class="yeison-status error">❌ ' + (data.data.message || data.data || 'Error en la conexión') + '</div>';
                                
                                // Si necesita reautorización, mostrar botón
                                if (data.data && data.data.needs_reauth) {
                                    html += '<div style="margin-top: 15px;">';
                                    html += '<button onclick="clearTokensAndReauth()" class="yeison-btn yeison-btn-warning">🧹 Limpiar y Reautorizar</button>';
                                    html += '</div>';
                                }
                                
                                resultEl.innerHTML = html;
                            }
                        })
                        .catch(error => {
                            clearTimeout(timeoutId);
                            console.error('Error en test de conexión:', error);
                            let errorMsg = 'Error de conexión';
                            if (error.name === 'AbortError') {
                                errorMsg = 'Timeout - Conexión muy lenta';
                            }
                            resultEl.innerHTML = '<div class="yeison-status error">❌ ' + errorMsg + '</div>';
                        });
                    }
                    
                    // Limpiar tokens y reautorizar
                    function clearTokensAndReauth() {
                        if (confirm('¿Limpiar tokens y reautorizar con Bitrix24?')) {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.innerHTML = `
                                <?php wp_nonce_field('yeison_btx_clear_tokens'); ?>
                                <input type="hidden" name="yeison_btx_clear_tokens" value="1">
                            `;
                            document.body.appendChild(form);
                            form.submit();
                        }
                    }

                    /* =============================================================================
                    FUNCIONALIDAD MAPEO DE ESTADOS CORREGIDA
                    ============================================================================= */

                    // Cargar pipelines cuando la API esté autorizada
                    document.addEventListener('DOMContentLoaded', function() {
                        console.log('🚀 DOM cargado, iniciando validaciones...');
                        
                        // Verificar autorización antes de cargar pipelines
                        <?php if ($api->is_authorized()): ?>
                            console.log('✅ API autorizada, cargando opciones de pipeline...');
                            loadPipelineOptions();
                        <?php else: ?>
                            console.log('⚠️ API no autorizada, saltando carga de pipelines');
                        <?php endif; ?>
                    });

                    // Cargar opciones de pipeline con manejo de errores mejorado
                    function loadPipelineOptions() {
                        const selector = document.getElementById('pipeline-selector');
                        if (!selector) {
                            console.error('❌ No se encontró selector de pipeline');
                            return;
                        }
                        
                        console.log('📡 Cargando opciones de pipeline...');
                        
                        const controller = new AbortController();
                        const timeoutId = setTimeout(() => controller.abort(), ajaxTimeout);
                        
                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=yeison_btx_get_all_statuses&nonce=<?php echo wp_create_nonce('yeison_btx_status_nonce'); ?>',
                            signal: controller.signal
                        })
                        .then(response => {
                            clearTimeout(timeoutId);
                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('📦 Respuesta recibida:', data);
                            
                            if (data.success && data.data && data.data.categories) {
                                const currentValue = '<?php echo yeison_btx_get_option('woocommerce_pipeline_id', 0); ?>';
                                
                                // Limpiar opciones excepto la primera
                                selector.innerHTML = '<option value="0">General (por defecto)</option>';
                                
                                // Agregar opciones de pipelines
                                Object.keys(data.data.categories).forEach(catId => {
                                    if (catId != 0) {
                                        const catName = data.data.categories[catId];
                                        const option = document.createElement('option');
                                        option.value = catId;
                                        option.textContent = catName;
                                        
                                        if (catId == currentValue) {
                                            option.selected = true;
                                        }
                                        
                                        selector.appendChild(option);
                                    }
                                });
                                
                                updatePipelineStatus();
                                console.log('✅ Pipelines cargados exitosamente');
                                
                                if (currentValue != '0') {
                                    console.log('📌 Pipeline guardado: ' + currentValue);
                                }
                            } else {
                                throw new Error(data.data || 'Respuesta inválida del servidor');
                            }
                        })
                        .catch(error => {
                            clearTimeout(timeoutId);
                            console.error('❌ Error cargando pipelines:', error);
                            
                            // Mostrar error en la interfaz
                            const statusDiv = document.getElementById('pipeline-status');
                            if (statusDiv) {
                                statusDiv.innerHTML = '<div style="color: #dc2626; font-size: 12px;">❌ Error cargando pipelines: ' + error.message + '</div>';
                            }
                        });
                    }

                    // Cargar stages del pipeline con validación mejorada
                    function loadPipelineStages() {
                        const selector = document.getElementById('pipeline-selector');
                        if (!selector) {
                            console.error('❌ Selector de pipeline no encontrado');
                            return;
                        }
                        
                        const newPipelineId = selector.value;
                        const pipelineName = selector.options[selector.selectedIndex].text;
                        
                        console.log('🔄 Cambiando pipeline a:', newPipelineId, '-', pipelineName);
                        
                        currentPipelineId = newPipelineId;
                        updatePipelineStatus();
                        
                        // Guardar el cambio via AJAX con timeout
                        const controller = new AbortController();
                        const timeoutId = setTimeout(() => controller.abort(), ajaxTimeout);
                        
                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=yeison_btx_update_pipeline&nonce=<?php echo wp_create_nonce('yeison_btx_status_nonce'); ?>&pipeline_id=' + encodeURIComponent(newPipelineId),
                            signal: controller.signal
                        })
                        .then(response => {
                            clearTimeout(timeoutId);
                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                console.log('✅ Pipeline actualizado:', data.data.pipeline_name);
                                
                                const statusDiv = document.getElementById('pipeline-status');
                                if (statusDiv) {
                                    statusDiv.innerHTML = '<div style="color: #28a745; font-weight: bold;">✅ Pipeline guardado: ' + data.data.pipeline_name + '</div>';
                                }
                                
                                // Si el mapeo está expandido, recargar con el nuevo pipeline
                                if (mappingExpanded) {
                                    console.log('🔄 Recargando mapeo para pipeline:', newPipelineId);
                                    loadStatusMappingForPipeline(newPipelineId);
                                }
                            } else {
                                throw new Error(data.data || 'Error desconocido');
                            }
                        })
                        .catch(error => {
                            clearTimeout(timeoutId);
                            console.error('❌ Error actualizando pipeline:', error);
                            
                            const statusDiv = document.getElementById('pipeline-status');
                            if (statusDiv) {
                                statusDiv.innerHTML = '<div style="color: #dc2626; font-weight: bold;">❌ Error: ' + error.message + '</div>';
                            }
                        });
                    }

                    // Actualizar estado del pipeline
                    function updatePipelineStatus() {
                        const selector = document.getElementById('pipeline-selector');
                        const statusDiv = document.getElementById('pipeline-status');
                        
                        if (selector && statusDiv) {
                            const pipelineName = selector.options[selector.selectedIndex].text;
                            statusDiv.innerHTML = '<small style="color: #28a745;">✅ Pipeline activo: <strong>' + pipelineName + '</strong></small>';
                        }
                    }
                    
                    // Toggle mapeo de estados CORREGIDO
                    function toggleStatusMapping() {
                        const content = document.getElementById('mapping-content');
                        const btn = document.getElementById('load-mapping-btn');
                        
                        if (!content || !btn) {
                            console.error('❌ Elementos de mapeo no encontrados');
                            return;
                        }
                        
                        if (!mappingExpanded) {
                            console.log('🔍 Expandiendo mapeo de estados...');
                            
                            content.style.display = 'block';
                            btn.textContent = '🔼 Ocultar Estados';
                            btn.className = 'yeison-btn yeison-btn-secondary';
                            mappingExpanded = true;
                            
                            // Resetear contador de reintentos
                            retryCount = 0;
                            
                            const selector = document.getElementById('pipeline-selector');
                            currentPipelineId = selector ? selector.value : '0';
                            
                            console.log('📊 Cargando mapeo para pipeline:', currentPipelineId);
                            loadStatusMappingForPipeline(currentPipelineId);
                        } else {
                            console.log('📁 Ocultando mapeo de estados...');
                            
                            content.style.display = 'none';
                            btn.textContent = '🔍 Cargar Estados del Pipeline';
                            btn.className = 'yeison-btn yeison-btn-primary';
                            mappingExpanded = false;
                        }
                    }

                    // Cargar mapeo para pipeline específico CORREGIDO
                    function loadStatusMappingForPipeline(pipelineId) {
                        const content = document.getElementById('mapping-content');
                        if (!content) {
                            console.error('❌ Contenedor de mapeo no encontrado');
                            return;
                        }
                        
                        console.log('📡 Iniciando carga de estados para pipeline:', pipelineId);
                        
                        // Mostrar loading mejorado
                        const loadingDiv = content.querySelector('#mapping-loading');
                        if (loadingDiv) {
                            loadingDiv.querySelector('h3').textContent = 'Cargando Estados del Pipeline...';
                            loadingDiv.querySelector('p').textContent = 'Preparando la interfaz de mapeo...';
                        }
                        
                        const controller = new AbortController();
                        const timeoutId = setTimeout(() => {
                            controller.abort();
                            console.error('⏱️ Timeout en carga de estados');
                        }, ajaxTimeout);
                        
                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=yeison_btx_get_pipeline_statuses&nonce=<?php echo wp_create_nonce('yeison_btx_status_nonce'); ?>&pipeline_id=' + encodeURIComponent(pipelineId),
                            signal: controller.signal
                        })
                        .then(response => {
                            clearTimeout(timeoutId);
                            console.log('📦 Respuesta recibida, status:', response.status);
                            
                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('📊 Datos de mapeo recibidos:', data);
                            
                            if (data.success && data.data) {
                                console.log('✅ Estados cargados exitosamente para pipeline:', pipelineId);
                                console.log('🎯 Etapas Bitrix encontradas:', Object.keys(data.data.bitrix_stages || {}).length);
                                console.log('🛒 Estados WooCommerce:', Object.keys(data.data.wc_statuses || {}).length);
                                
                                statusData = data.data;
                                renderMappingInterface(data.data);
                                retryCount = 0; // Reset retry counter on success
                            } else {
                                throw new Error(data.data || 'Datos de mapeo inválidos');
                            }
                        })
                        .catch(error => {
                            clearTimeout(timeoutId);
                            console.error('❌ Error cargando estados:', error);
                            
                            let errorMessage = error.message;
                            if (error.name === 'AbortError') {
                                errorMessage = 'Timeout - La conexión tardó demasiado';
                            }
                            
                            // Retry logic
                            if (retryCount < maxRetries && error.name !== 'AbortError') {
                                retryCount++;
                                console.log(`🔄 Reintentando... (${retryCount}/${maxRetries})`);
                                
                                content.innerHTML = `
                                    <div style="text-align: center; color: orange; padding: 40px;">
                                        <div style="font-size: 40px; margin-bottom: 15px;">🔄</div>
                                        <p>Reintentando... (${retryCount}/${maxRetries})</p>
                                        <p style="font-size: 12px; opacity: 0.7;">${errorMessage}</p>
                                    </div>
                                `;
                                
                                setTimeout(() => loadStatusMappingForPipeline(pipelineId), 2000);
                                return;
                            }
                            
                            // Show error with details
                            content.innerHTML = `
                                <div style="text-align: center; color: red; padding: 40px;">
                                    <div style="font-size: 40px; margin-bottom: 15px;">❌</div>
                                    <h3>Error cargando estados</h3>
                                    <p>${errorMessage}</p>
                                    <div class="yeison-error-details">
                                        <strong>Detalles técnicos:</strong><br>
                                        Pipeline ID: ${pipelineId}<br>
                                        Reintentos: ${retryCount}/${maxRetries}<br>
                                        Timestamp: ${new Date().toLocaleString()}
                                    </div>
                                    <button onclick="loadStatusMappingForPipeline('${pipelineId}')" 
                                            class="yeison-btn yeison-btn-warning" 
                                            style="margin-top: 15px;">
                                        🔄 Reintentar
                                    </button>
                                </div>
                            `;
                        });
                    }

                    // Renderizar interfaz de mapeo MEJORADA
                    function renderMappingInterface(data) {
                        const content = document.getElementById('mapping-content');
                        if (!content) {
                            console.error('❌ Contenedor de contenido no encontrado');
                            return;
                        }
                        
                        const selectedPipeline = document.getElementById('pipeline-selector').value;
                        const pipelineName = document.getElementById('pipeline-selector').options[document.getElementById('pipeline-selector').selectedIndex].text;
                        
                        console.log('🎨 Renderizando interfaz para pipeline:', pipelineName);
                        
                        // Validar datos requeridos
                        if (!data.wc_statuses || !data.bitrix_stages) {
                            content.innerHTML = `
                                <div style="text-align: center; color: red; padding: 40px;">
                                    <div style="font-size: 40px; margin-bottom: 15px;">⚠️</div>
                                    <h3>Datos incompletos</h3>
                                    <p>No se pudieron cargar los estados necesarios</p>
                                    <div class="yeison-error-details">
                                        WooCommerce estados: ${data.wc_statuses ? Object.keys(data.wc_statuses).length : 'No disponible'}<br>
                                        Bitrix24 etapas: ${data.bitrix_stages ? Object.keys(data.bitrix_stages).length : 'No disponible'}
                                    </div>
                                </div>
                            `;
                            return;
                        }
                        
                        let html = '<div style="margin-bottom: 20px; padding: 15px; background: #e8f4f8; border-radius: 8px; text-align: center;">';
                        html += '<h3 style="margin: 0; color: #2c5aa0;">🎯 Configurando Mapeo para Pipeline: ' + pipelineName + '</h3>';
                        html += '<p style="margin: 5px 0 0 0; color: #666;">Este mapeo se aplicará solo a los deals de este pipeline</p>';
                        html += '</div>';
                        
                        html += '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">';
                        
                        // Columna WooCommerce → Bitrix24
                        html += '<div style="background: #f8f0ff; padding: 20px; border-radius: 10px;">';
                        html += '<h3 style="color: #8a2be2; margin-bottom: 20px; text-align: center;">🛒 WooCommerce → Bitrix24</h3>';
                        
                        Object.keys(data.wc_statuses).forEach(wcStatus => {
                            const wcName = data.wc_statuses[wcStatus];
                            const currentMapping = data.current_mapping && data.current_mapping.wc_to_bitrix ? data.current_mapping.wc_to_bitrix[wcStatus] || '' : '';
                            
                            html += '<div style="margin-bottom: 20px; padding: 15px; border: 2px solid #ddd; border-radius: 8px; background: white;">';
                            html += '<label style="display: block; font-weight: bold; margin-bottom: 10px; color: #333;">' + wcName + '</label>';
                            html += '<select id="wc_to_bitrix_' + wcStatus + '" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px;">';
                            html += '<option value="">-- Seleccionar Estado --</option>';
                            
                            Object.keys(data.bitrix_stages).forEach(stageId => {
                                const stage = data.bitrix_stages[stageId];
                                const selected = currentMapping === stageId ? 'selected' : '';
                                html += '<option value="' + stageId + '" ' + selected + '>' + stage.name + '</option>';
                            });
                            
                            html += '</select>';
                            html += '</div>';
                        });
                        
                        html += '</div>';
                        
                        // Columna Bitrix24 → WooCommerce  
                        html += '<div style="background: #fff0f5; padding: 20px; border-radius: 10px;">';
                        html += '<h3 style="color: #ff6b35; margin-bottom: 20px; text-align: center;">🔄 Bitrix24 → WooCommerce</h3>';
                        
                        Object.keys(data.bitrix_stages).forEach(stageId => {
                            const stage = data.bitrix_stages[stageId];
                            const currentMapping = data.current_mapping && data.current_mapping.bitrix_to_wc ? data.current_mapping.bitrix_to_wc[stageId] || '' : '';
                            
                            html += '<div style="margin-bottom: 20px; padding: 15px; border: 2px solid #ddd; border-radius: 8px; background: white;">';
                            html += '<label style="display: block; font-weight: bold; margin-bottom: 10px; color: #333;">' + stage.name + '</label>';
                            html += '<select id="bitrix_to_wc_' + stageId + '" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px;">';
                            html += '<option value="">-- Seleccionar Estado --</option>';
                            
                            Object.keys(data.wc_statuses).forEach(wcStatus => {
                                const wcName = data.wc_statuses[wcStatus];
                                const cleanWcStatus = wcStatus.replace('wc-', '');
                                const selected = currentMapping === cleanWcStatus ? 'selected' : '';
                                html += '<option value="' + cleanWcStatus + '" ' + selected + '>' + wcName + '</option>';
                            });
                            
                            html += '</select>';
                            html += '</div>';
                        });
                        
                        html += '</div>';
                        html += '</div>';
                        
                        // Botones de acción
                        html += '<div style="margin-top: 30px; text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">';
                        html += '<button onclick="savePipelineMappingChanges()" class="yeison-btn yeison-btn-primary" style="margin-right: 15px; font-size: 16px; padding: 12px 24px;">💾 Guardar Mapeo para ' + pipelineName + '</button>';
                        html += '</div>';
                        
                        content.innerHTML = html;
                        console.log('✅ Interfaz de mapeo renderizada exitosamente');
                    }

                    // Guardar cambios de mapeo del pipeline MEJORADO
                    function savePipelineMappingChanges() {
                        console.log('💾 Iniciando guardado de mapeo...');
                        
                        if (!statusData || !statusData.wc_statuses || !statusData.bitrix_stages) {
                            alert('❌ Error: Datos de estados no disponibles. Recarga la página e intenta de nuevo.');
                            return;
                        }
                        
                        const wcToBitrix = {};
                        const bitrixToWc = {};
                        
                        // Recopilar mapeos WooCommerce → Bitrix24
                        Object.keys(statusData.wc_statuses).forEach(wcStatus => {
                            const select = document.getElementById('wc_to_bitrix_' + wcStatus);
                            if (select && select.value) {
                                wcToBitrix[wcStatus] = select.value;
                            }
                        });
                        
                        // Recopilar mapeos Bitrix24 → WooCommerce
                        Object.keys(statusData.bitrix_stages).forEach(stageId => {
                            const select = document.getElementById('bitrix_to_wc_' + stageId);
                            if (select && select.value) {
                                bitrixToWc[stageId] = select.value;
                            }
                        });
                        
                        const pipelineName = document.getElementById('pipeline-selector').options[document.getElementById('pipeline-selector').selectedIndex].text;
                        
                        console.log('📤 Enviando mapeos:', {
                            pipeline: currentPipelineId,
                            wcToBitrix: Object.keys(wcToBitrix).length,
                            bitrixToWc: Object.keys(bitrixToWc).length
                        });
                        
                        // Guardar via AJAX con timeout
                        const controller = new AbortController();
                        const timeoutId = setTimeout(() => controller.abort(), ajaxTimeout);
                        
                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=yeison_btx_save_pipeline_mapping&nonce=<?php echo wp_create_nonce('yeison_btx_status_nonce'); ?>&' +
                                'pipeline_id=' + encodeURIComponent(currentPipelineId) + '&' +
                                'wc_to_bitrix=' + encodeURIComponent(JSON.stringify(wcToBitrix)) + '&' +
                                'bitrix_to_wc=' + encodeURIComponent(JSON.stringify(bitrixToWc)),
                            signal: controller.signal
                        })
                        .then(response => {
                            clearTimeout(timeoutId);
                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                console.log('✅ Mapeo guardado exitosamente');
                                alert('✅ Mapeo guardado exitosamente para el pipeline: ' + pipelineName + '\n\n' + 
                                    'WooCommerce → Bitrix24: ' + data.data.wc_count + ' mapeos\n' +
                                    'Bitrix24 → WooCommerce: ' + data.data.bitrix_count + ' mapeos');
                                updateMappingStatus('success');
                            } else {
                                throw new Error(data.data || 'Error desconocido al guardar');
                            }
                        })
                        .catch(error => {
                            clearTimeout(timeoutId);
                            console.error('❌ Error guardando mapeo:', error);
                            alert('❌ Error guardando: ' + error.message);
                        });
                    }
                    
                    // Actualizar estado del mapeo
                    function updateMappingStatus(type = 'info') {
                        const colors = {
                            success: '#28a745',
                            error: '#dc3545',
                            info: '#17a2b8'
                        };
                        
                        const statusDiv = document.getElementById('mapping-status');
                        if (statusDiv) {
                            statusDiv.innerHTML = 
                                '<div style="color: ' + colors[type] + '; font-size: 12px;">✅ Estados actualizados - ' + new Date().toLocaleTimeString() + '</div>';
                        }
                    }

                    // Funciones de utilidad para debug
                    function debugMappingState() {
                        console.log('🔍 Estado actual del mapeo:', {
                            mappingExpanded: mappingExpanded,
                            currentPipelineId: currentPipelineId,
                            statusDataAvailable: !!statusData,
                            retryCount: retryCount
                        });
                    }

                    // Event listeners para debug
                    window.addEventListener('error', function(e) {
                        console.error('💥 Error global capturado:', e.error);
                    });

                    // Agregar debug al objeto window para testing
                    window.yeisonDebug = {
                        statusData,
                        mappingExpanded,
                        currentPipelineId,
                        retryCount,
                        debugMappingState,
                        loadPipelineOptions,
                        loadStatusMappingForPipeline,
                        toggleStatusMapping
                    };

                    console.log('🎉 Sistema de mapeo inicializado correctamente');
                </script>
            </body>
            </html>

        <?php
    }

    /**
     * Obtener salud del sistema
     * Método helper para mostrar el estado de los componentes
     */


    /**
     * Obtener salud del sistema
     */
    private function get_system_health() {
        $components = array(
            'API Bitrix24' => false,
            'WooCommerce' => false,
            'Formularios' => false,
            'Webhooks' => false,
            'Anti-Loop' => false,
            'Mapeo' => false
        );
        
        // Verificar API
        if (class_exists('YeisonBTX_Bitrix_API')) {
            $api = yeison_btx_api();
            $components['API Bitrix24'] = $api->is_authorized();
        }
        
        // Verificar WooCommerce
        if (class_exists('YeisonBTX_WooCommerce_Sync')) {
            $components['WooCommerce'] = class_exists('WooCommerce');
        }
        
        // Verificar otros componentes
        $components['Formularios'] = class_exists('YeisonBTX_Forms_Handler');
        $components['Webhooks'] = class_exists('YeisonBTX_Webhook_Handler');
        $components['Anti-Loop'] = class_exists('YeisonBTX_Anti_Loop');
        $components['Mapeo'] = class_exists('YeisonBTX_Data_Mapping');

        $active_count = count(array_filter($components));
        $total_count = count($components);
        $score = round(($active_count / $total_count) * 100);
        
        return array(
            'score' => $score,
            'components' => $components,
            'active_count' => $active_count,
            'total_count' => $total_count
        );
    }







    /**
     * Verificar estado de las tablas de la base de datos
     */
    private function check_database_tables() {
        global $wpdb;
        
        // Verificar tablas principales del plugin
        $required_tables = array(
            $wpdb->prefix . 'yeison_btx_logs',
            $wpdb->prefix . 'yeison_btx_mappings',
            $wpdb->prefix . 'yeison_btx_queue'
        );
        
        foreach ($required_tables as $table) {
            $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if ($result !== $table) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Verificar estado de los webhooks
     */
    private function check_webhooks_status() {
        // Verificar si los webhooks están registrados
        $webhook_option = yeison_btx_get_option('registered_webhooks', array());
        
        // Al menos debería tener el webhook de deals
        return is_array($webhook_option) && !empty($webhook_option);
    }



















    /**
     * Página de configuración avanzada
     */
    public function advanced_config_page() {
        $api = yeison_btx_api();
        
        // Procesar formularios
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->process_advanced_settings();
        }
        
        // Obtener datos del sistema
        $system_stats = $this->get_system_health();
        $sync_stats = class_exists('YeisonBTX_WooCommerce_Sync') ? yeison_btx_woo_sync()->get_sync_stats() : array();
        $webhook_stats = $this->get_webhook_status();
        
        ?>
        <div class="yeison-advanced-container">
            <style>
                /* =============================================================================
                ESTILOS AVANZADOS PARA PÁGINA DE AJUSTES
                ============================================================================= */
                
                .yeison-advanced-container {
                    max-width: 1600px;
                    margin: 0 auto;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    padding: 0;
                }
                
                .yeison-advanced-header {
                    background: rgba(255, 255, 255, 0.95);
                    padding: 30px;
                    text-align: center;
                    backdrop-filter: blur(10px);
                    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                }
                
                .yeison-advanced-title {
                    margin: 0;
                    font-size: 32px;
                    color: #2d3748;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 12px;
                }
                
                .yeison-advanced-subtitle {
                    margin: 12px 0 0 0;
                    color: #718096;
                    font-size: 16px;
                }
                
                .yeison-advanced-content {
                    padding: 30px;
                }
                
                .yeison-sections-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
                    gap: 25px;
                    margin-bottom: 30px;
                }
                
                .yeison-section {
                    background: rgba(255, 255, 255, 0.98);
                    border-radius: 16px;
                    overflow: hidden;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
                    transition: all 0.3s ease;
                }
                
                .yeison-section:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 12px 40px rgba(0,0,0,0.15);
                }
                
                .yeison-section-header {
                    padding: 20px 25px;
                    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                    border-bottom: 1px solid rgba(0,0,0,0.05);
                }
                
                .yeison-section-title {
                    margin: 0;
                    font-size: 18px;
                    font-weight: 600;
                    color: #2d3748;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                
                .yeison-section-content {
                    padding: 25px;
                }
                
                /* Herramientas Grid */
                .yeison-tools-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                    gap: 12px;
                }
                
                .yeison-tool-item {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    padding: 16px 12px;
                    border: 2px solid transparent;
                    border-radius: 12px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    text-decoration: none;
                    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                    min-height: 80px;
                    justify-content: center;
                }
                
                .yeison-tool-item:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
                    text-decoration: none;
                }
                
                .yeison-tool-item.diagnostic { border-color: #3b82f6; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); }
                .yeison-tool-item.monitoring { border-color: #10b981; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); }
                .yeison-tool-item.webhook { border-color: #8b5cf6; background: linear-gradient(135deg, #ede9fe 0%, #c4b5fd 100%); }
                .yeison-tool-item.maintenance { border-color: #f59e0b; background: linear-gradient(135deg, #fef3c7 0%, #fcd34d 100%); }
                .yeison-tool-item.danger { border-color: #ef4444; background: linear-gradient(135deg, #fee2e2 0%, #fca5a5 100%); }
                
                .yeison-tool-icon {
                    font-size: 24px;
                    margin-bottom: 8px;
                }
                
                .yeison-tool-name {
                    font-size: 12px;
                    font-weight: 600;
                    text-align: center;
                    line-height: 1.2;
                    color: #374151;
                }
                
                /* Configuración Forms */
                .yeison-config-form {
                    display: grid;
                    gap: 20px;
                }
                
                .yeison-config-group {
                    background: #f8fafc;
                    padding: 20px;
                    border-radius: 12px;
                    border: 1px solid #e2e8f0;
                }
                
                .yeison-config-group h4 {
                    margin: 0 0 15px 0;
                    font-size: 16px;
                    color: #374151;
                    font-weight: 600;
                }
                
                .yeison-config-row {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 12px 0;
                    border-bottom: 1px solid #e5e7eb;
                }
                
                .yeison-config-row:last-child {
                    border-bottom: none;
                }
                
                .yeison-config-label {
                    font-weight: 500;
                    color: #374151;
                    flex: 1;
                }
                
                .yeison-config-control {
                    margin-left: 20px;
                }
                
                .yeison-toggle {
                    position: relative;
                    display: inline-block;
                    width: 50px;
                    height: 24px;
                }
                
                .yeison-toggle input {
                    opacity: 0;
                    width: 0;
                    height: 0;
                }
                
                .yeison-slider {
                    position: absolute;
                    cursor: pointer;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: #cbd5e0;
                    transition: .4s;
                    border-radius: 24px;
                }
                
                .yeison-slider:before {
                    position: absolute;
                    content: "";
                    height: 18px;
                    width: 18px;
                    left: 3px;
                    bottom: 3px;
                    background-color: white;
                    transition: .4s;
                    border-radius: 50%;
                }
                
                input:checked + .yeison-slider {
                    background-color: #10b981;
                }
                
                input:checked + .yeison-slider:before {
                    transform: translateX(26px);
                }
                
                .yeison-select {
                    padding: 8px 12px;
                    border: 1px solid #d1d5db;
                    border-radius: 6px;
                    background: white;
                    font-size: 14px;
                    min-width: 120px;
                }
                
                /* Stats Cards */
                .yeison-stats-row {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                    gap: 15px;
                    margin: 20px 0;
                }
                
                .yeison-stat-card {
                    text-align: center;
                    padding: 16px;
                    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
                    border-radius: 10px;
                    border: 1px solid #e2e8f0;
                }
                
                .yeison-stat-number {
                    font-size: 20px;
                    font-weight: 700;
                    color: #1e293b;
                    margin-bottom: 4px;
                }
                
                .yeison-stat-label {
                    font-size: 11px;
                    color: #64748b;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                /* Botones */
                .yeison-btn-advanced {
                    padding: 10px 20px;
                    border: none;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    text-decoration: none;
                    display: inline-block;
                }
                
                .yeison-btn-primary { background: #3b82f6; color: white; }
                .yeison-btn-success { background: #10b981; color: white; }
                .yeison-btn-warning { background: #f59e0b; color: white; }
                .yeison-btn-danger { background: #ef4444; color: white; }
                
                .yeison-btn-advanced:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    color: white;
                    text-decoration: none;
                }
                
                /* Status Indicators */
                .yeison-status-indicator {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 4px 8px;
                    border-radius: 6px;
                    font-size: 12px;
                    font-weight: 600;
                }
                
                .yeison-status-indicator.active {
                    background: #d1fae5;
                    color: #065f46;
                }
                
                .yeison-status-indicator.inactive {
                    background: #fee2e2;
                    color: #991b1b;
                }
                
                .yeison-status-indicator.warning {
                    background: #fef3c7;
                    color: #92400e;
                }
                
                /* Responsive */
                @media (max-width: 768px) {
                    .yeison-advanced-content {
                        padding: 20px 15px;
                    }
                    
                    .yeison-sections-grid {
                        grid-template-columns: 1fr;
                        gap: 20px;
                    }
                    
                    .yeison-tools-grid {
                        grid-template-columns: repeat(2, 1fr);
                    }
                    
                    .yeison-config-row {
                        flex-direction: column;
                        align-items: flex-start;
                        gap: 10px;
                    }
                    
                    .yeison-config-control {
                        margin-left: 0;
                    }
                }
                
                @media (max-width: 480px) {
                    .yeison-tools-grid {
                        grid-template-columns: 1fr;
                    }
                    
                    .yeison-advanced-title {
                        font-size: 24px;
                        flex-direction: column;
                        gap: 8px;
                    }
                }
            </style>
            
            <!-- Header -->
            <div class="yeison-advanced-header">
                <h1 class="yeison-advanced-title">
                    ⚙️ Configuración Avanzada
                    <span style="background: linear-gradient(45deg, #667eea 0%, #764ba2 100%); color: white; padding: 6px 12px; border-radius: 15px; font-size: 12px;">PRO</span>
                </h1>
                <p class="yeison-advanced-subtitle">Panel de control completo para administradores</p>
            </div>
            
            <!-- Contenido -->
            <div class="yeison-advanced-content">
                <div class="yeison-sections-grid">
                    
                    <!-- SECCIÓN: Herramientas de Diagnóstico -->
                    <div class="yeison-section">
                        <div class="yeison-section-header">
                            <h3 class="yeison-section-title">🔍 Diagnóstico del Sistema</h3>
                        </div>
                        <div class="yeison-section-content">
                            <div class="yeison-tools-grid">
                                <a href="<?php echo admin_url('admin-ajax.php?action=yeison_btx_test_optimized'); ?>" 
                                target="_blank" class="yeison-tool-item diagnostic">
                                    <div class="yeison-tool-icon">🧪</div>
                                    <div class="yeison-tool-name">Test Completo</div>
                                </a>
                                
                                <a href="<?php echo admin_url('admin-ajax.php?action=yeison_btx_diagnostic'); ?>" 
                                target="_blank" class="yeison-tool-item diagnostic">
                                    <div class="yeison-tool-icon">🔬</div>
                                    <div class="yeison-tool-name">Diagnóstico Avanzado</div>
                                </a>
                                
                                <a href="#" onclick="testConnection()" class="yeison-tool-item diagnostic">
                                    <div class="yeison-tool-icon">📡</div>
                                    <div class="yeison-tool-name">Test Conexión</div>
                                </a>
                                
                                <a href="<?php echo admin_url('admin-ajax.php?action=yeison_btx_debug_pipeline_stages'); ?>" 
                                target="_blank" class="yeison-tool-item diagnostic">
                                    <div class="yeison-tool-icon">🔍</div>
                                    <div class="yeison-tool-name">Debug Pipelines</div>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SECCIÓN: Monitoreo y Estadísticas -->
                    <div class="yeison-section">
                        <div class="yeison-section-header">
                            <h3 class="yeison-section-title">📊 Monitoreo del Sistema</h3>
                        </div>
                        <div class="yeison-section-content">
                            <div class="yeison-stats-row">
                                <div class="yeison-stat-card">
                                    <div class="yeison-stat-number"><?php echo $system_stats['score']; ?>%</div>
                                    <div class="yeison-stat-label">Salud Sistema</div>
                                </div>
                                <div class="yeison-stat-card">
                                    <div class="yeison-stat-number"><?php echo $sync_stats['total_orders_synced'] ?? 0; ?></div>
                                    <div class="yeison-stat-label">Pedidos Sync</div>
                                </div>
                                <div class="yeison-stat-card">
                                    <div class="yeison-stat-number"><?php echo count($webhook_stats); ?></div>
                                    <div class="yeison-stat-label">Webhooks</div>
                                </div>
                            </div>
                            
                            <div class="yeison-tools-grid">
                                <a href="<?php echo admin_url('admin.php?page=yeison-btx-logs'); ?>" 
                                class="yeison-tool-item monitoring">
                                    <div class="yeison-tool-icon">📄</div>
                                    <div class="yeison-tool-name">Ver Logs</div>
                                </a>
                                
                                <a href="<?php echo admin_url('admin-ajax.php?action=yeison_btx_debug_queue'); ?>" 
                                target="_blank" class="yeison-tool-item monitoring">
                                    <div class="yeison-tool-icon">📋</div>
                                    <div class="yeison-tool-name">Debug Cola</div>
                                </a>
                                
                                <a href="#" onclick="showSystemStats()" class="yeison-tool-item monitoring">
                                    <div class="yeison-tool-icon">📈</div>
                                    <div class="yeison-tool-name">Estadísticas</div>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SECCIÓN: Gestión de Webhooks -->
                    <div class="yeison-section">
                        <div class="yeison-section-header">
                            <h3 class="yeison-section-title">🔗 Gestión de Webhooks</h3>
                        </div>
                        <div class="yeison-section-content">
                            <div class="yeison-tools-grid">
                                <a href="<?php echo admin_url('admin-ajax.php?action=yeison_btx_check_webhooks_status'); ?>" 
                                target="_blank" class="yeison-tool-item webhook">
                                    <div class="yeison-tool-icon">📊</div>
                                    <div class="yeison-tool-name">Estado Webhooks</div>
                                </a>
                                
                                <a href="<?php echo admin_url('admin-ajax.php?action=yeison_btx_register_all_webhooks'); ?>" 
                                target="_blank" class="yeison-tool-item webhook">
                                    <div class="yeison-tool-icon">📡</div>
                                    <div class="yeison-tool-name">Registrar Todos</div>
                                </a>
                                
                                <a href="<?php echo admin_url('admin-ajax.php?action=yeison_btx_clean_and_register_webhooks'); ?>" 
                                target="_blank" class="yeison-tool-item webhook"
                                onclick="return confirm('¿Limpiar y re-registrar todos los webhooks?')">
                                    <div class="yeison-tool-icon">🧹</div>
                                    <div class="yeison-tool-name">Limpiar Webhooks</div>
                                </a>
                                
                                <a href="<?php echo admin_url('admin-ajax.php?action=yeison_btx_debug_webhook_registration'); ?>" 
                                target="_blank" class="yeison-tool-item webhook">
                                    <div class="yeison-tool-icon">🔧</div>
                                    <div class="yeison-tool-name">Debug Webhooks</div>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SECCIÓN: Mantenimiento -->
                    <div class="yeison-section">
                        <div class="yeison-section-header">
                            <h3 class="yeison-section-title">🛠️ Herramientas de Mantenimiento</h3>
                        </div>
                        <div class="yeison-section-content">
                            <div class="yeison-tools-grid">
                                <a href="<?php echo admin_url('admin-ajax.php?action=yeison_btx_repair_tables'); ?>" 
                                target="_blank" class="yeison-tool-item maintenance">
                                    <div class="yeison-tool-icon">🔧</div>
                                    <div class="yeison-tool-name">Reparar Tablas</div>
                                </a>
                                
                                <a href="<?php echo admin_url('admin-ajax.php?action=yeison_btx_clear_queue'); ?>" 
                                target="_blank" class="yeison-tool-item maintenance"
                                onclick="return confirm('¿Vaciar la cola pendiente?')">
                                    <div class="yeison-tool-icon">🗑️</div>
                                    <div class="yeison-tool-name">Vaciar Cola</div>
                                </a>
                                
                                <a href="#" onclick="clearTokens()" class="yeison-tool-item maintenance">
                                    <div class="yeison-tool-icon">🔑</div>
                                    <div class="yeison-tool-name">Limpiar Tokens</div>
                                </a>
                                
                                <a href="<?php echo admin_url('admin-ajax.php?action=yeison_btx_nuclear_cleanup'); ?>" 
                                target="_blank" class="yeison-tool-item danger"
                                onclick="return confirm('⚠️ ADVERTENCIA: Esto eliminará TODOS los datos del plugin.\n¿Estás completamente seguro?')">
                                    <div class="yeison-tool-icon">💣</div>
                                    <div class="yeison-tool-name">Limpieza Nuclear</div>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SECCIÓN: Configuración del Sistema -->
                    <div class="yeison-section">
                        <div class="yeison-section-header">
                            <h3 class="yeison-section-title">⚙️ Configuración del Sistema</h3>
                        </div>
                        <div class="yeison-section-content">
                            <form method="post" class="yeison-config-form">
                                <?php wp_nonce_field('yeison_btx_advanced_settings'); ?>
                                
                                <div class="yeison-config-group">
                                    <h4>🔄 Sincronización</h4>
                                    
                                    <div class="yeison-config-row">
                                        <label class="yeison-config-label">Sincronización Bidireccional</label>
                                        <div class="yeison-config-control">
                                            <label class="yeison-toggle">
                                                <input type="checkbox" name="bidirectional_sync_enabled" value="1" 
                                                    <?php checked(yeison_btx_get_option('bidirectional_sync_enabled', true)); ?>>
                                                <span class="yeison-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="yeison-config-row">
                                        <label class="yeison-config-label">Sincronizar Deals → Pedidos</label>
                                        <div class="yeison-config-control">
                                            <label class="yeison-toggle">
                                                <input type="checkbox" name="sync_deal_to_order" value="1" 
                                                    <?php checked(yeison_btx_get_option('sync_deal_to_order', true)); ?>>
                                                <span class="yeison-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="yeison-config-row">
                                        <label class="yeison-config-label">Sincronizar Contactos → Clientes</label>
                                        <div class="yeison-config-control">
                                            <label class="yeison-toggle">
                                                <input type="checkbox" name="sync_contact_to_customer" value="1" 
                                                    <?php checked(yeison_btx_get_option('sync_contact_to_customer', true)); ?>>
                                                <span class="yeison-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="yeison-config-group">
                                    <h4>📝 Formularios</h4>
                                    
                                    <div class="yeison-config-row">
                                        <label class="yeison-config-label">Captura Universal de Formularios</label>
                                        <div class="yeison-config-control">
                                            <label class="yeison-toggle">
                                                <input type="checkbox" name="forms_capture_enabled" value="1" 
                                                    <?php checked(yeison_btx_get_option('forms_capture_enabled', true)); ?>>
                                                <span class="yeison-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="yeison-config-row">
                                        <label class="yeison-config-label">Procesamiento Automático</label>
                                        <div class="yeison-config-control">
                                            <label class="yeison-toggle">
                                                <input type="checkbox" name="forms_auto_process" value="1" 
                                                    <?php checked(yeison_btx_get_option('forms_auto_process', true)); ?>>
                                                <span class="yeison-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="yeison-config-group">
                                    <h4>🔗 Webhooks</h4>
                                    
                                    <div class="yeison-config-row">
                                        <label class="yeison-config-label">Sistema de Webhooks</label>
                                        <div class="yeison-config-control">
                                            <label class="yeison-toggle">
                                                <input type="checkbox" name="webhooks_enabled" value="1" 
                                                    <?php checked(yeison_btx_get_option('webhooks_enabled', true)); ?>>
                                                <span class="yeison-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="yeison-config-row">
                                        <label class="yeison-config-label">Auto-registro de Webhooks</label>
                                        <div class="yeison-config-control">
                                            <label class="yeison-toggle">
                                                <input type="checkbox" name="webhook_auto_register" value="1" 
                                                    <?php checked(yeison_btx_get_option('webhook_auto_register', true)); ?>>
                                                <span class="yeison-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="yeison-config-group">
                                    <h4>🛡️ Sistema Anti-Loop</h4>
                                    
                                    <div class="yeison-config-row">
                                        <label class="yeison-config-label">Prevención de Loops</label>
                                        <div class="yeison-config-control">
                                            <label class="yeison-toggle">
                                                <input type="checkbox" name="anti_loop_enabled" value="1" 
                                                    <?php checked(yeison_btx_get_option('anti_loop_enabled', true)); ?>>
                                                <span class="yeison-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="yeison-config-row">
                                        <label class="yeison-config-label">Timeout de Actualización (segundos)</label>
                                        <div class="yeison-config-control">
                                            <select name="sync_update_timeout" class="yeison-select">
                                                <option value="30" <?php selected(yeison_btx_get_option('sync_update_timeout', 30), 30); ?>>30 segundos</option>
                                                <option value="60" <?php selected(yeison_btx_get_option('sync_update_timeout', 30), 60); ?>>1 minuto</option>
                                                <option value="300" <?php selected(yeison_btx_get_option('sync_update_timeout', 30), 300); ?>>5 minutos</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="text-align: center; margin-top: 25px;">
                                    <button type="submit" class="yeison-btn-advanced yeison-btn-primary">
                                        💾 Guardar Configuración Avanzada
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- SECCIÓN: Estado de Componentes -->
                    <div class="yeison-section">
                        <div class="yeison-section-header">
                            <h3 class="yeison-section-title">🔧 Estado de Componentes</h3>
                        </div>
                        <div class="yeison-section-content">
                            <div style="display: grid; gap: 12px;">
                                <?php
                                $components_status = array(
                                    'API Bitrix24' => $api->is_authorized(),
                                    'WooCommerce' => class_exists('WooCommerce'),
                                    'Formularios' => class_exists('YeisonBTX_Forms_Handler'),
                                    'Webhooks' => class_exists('YeisonBTX_Webhook_Handler'),
                                    'Anti-Loop' => class_exists('YeisonBTX_Anti_Loop'),
                                    'Sync Bidireccional' => class_exists('YeisonBTX_Bidirectional_Sync'),
                                    'Mapeo de Datos' => class_exists('YeisonBTX_Data_Mapping')
                                );
                                
                                foreach ($components_status as $component => $status): ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0;">
                                        <span><?php echo esc_html($component); ?></span>
                                        <span class="yeison-status-indicator <?php echo $status ? 'active' : 'inactive'; ?>">
                                            <?php echo $status ? '✅ Activo' : '❌ Inactivo'; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Botones de Navegación -->
                <div style="text-align: center; margin-top: 30px;">
                    <a href="<?php echo admin_url('admin.php?page=yeison-btx'); ?>" class="yeison-btn-advanced yeison-btn-success">
                        🏠 Volver al Dashboard
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=yeison-btx-logs'); ?>" class="yeison-btn-advanced yeison-btn-primary">
                        📝 Ver Logs del Sistema
                    </a>
                </div>
            </div>
        </div>
        
        <script>
            // Test de conexión
            function testConnection() {
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=yeison_btx_test_connection&nonce=<?php echo wp_create_nonce('yeison_btx_test'); ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Conexión exitosa: ' + data.data.message);
                    } else {
                        alert('❌ Error de conexión: ' + data.data.message);
                    }
                });
            }
            
            // Limpiar tokens
            function clearTokens() {
                if (confirm('¿Limpiar todos los tokens de acceso?')) {
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=yeison_btx_clear_tokens&nonce=<?php echo wp_create_nonce('yeison_btx_clear_tokens'); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('✅ Tokens limpiados correctamente');
                            window.location.reload();
                        } else {
                            alert('❌ Error: ' + data.data);
                        }
                    });
                }
            }
            
            // Mostrar estadísticas del sistema
            function showSystemStats() {
                const stats = {
                    'Salud del Sistema': '<?php echo $system_stats['score']; ?>%',
                    'Componentes Activos': '<?php echo $system_stats['active_count']; ?>/<?php echo $system_stats['total_count']; ?>',
                    'API Autorizada': '<?php echo $api->is_authorized() ? 'Sí' : 'No'; ?>',
                    'Pedidos Sincronizados': '<?php echo $sync_stats['total_orders_synced'] ?? 0; ?>',
                    'Clientes Sincronizados': '<?php echo $sync_stats['total_customers_synced'] ?? 0; ?>',
                    'Webhooks Registrados': '<?php echo count($webhook_stats); ?>'
                };
                
                let message = 'ESTADÍSTICAS DEL SISTEMA:\n\n';
                for (const [key, value] of Object.entries(stats)) {
                    message += `${key}: ${value}\n`;
                }
                
                alert(message);
            }
        </script>
        
        <?php
    }

    /**
     * Procesar formulario de configuración avanzada
     */
    private function process_advanced_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'yeison_btx_advanced_settings')) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Configuración de sincronización
        yeison_btx_update_option('bidirectional_sync_enabled', isset($_POST['bidirectional_sync_enabled']));
        yeison_btx_update_option('sync_deal_to_order', isset($_POST['sync_deal_to_order']));
        yeison_btx_update_option('sync_contact_to_customer', isset($_POST['sync_contact_to_customer']));
        
        // Configuración de formularios
        yeison_btx_update_option('forms_capture_enabled', isset($_POST['forms_capture_enabled']));
        yeison_btx_update_option('forms_auto_process', isset($_POST['forms_auto_process']));
        
        // Configuración de webhooks
        yeison_btx_update_option('webhooks_enabled', isset($_POST['webhooks_enabled']));
        yeison_btx_update_option('webhook_auto_register', isset($_POST['webhook_auto_register']));
        
        // Configuración anti-loop
        yeison_btx_update_option('anti_loop_enabled', isset($_POST['anti_loop_enabled']));
        yeison_btx_update_option('sync_update_timeout', intval($_POST['sync_update_timeout'] ?? 30));
        
        yeison_btx_log('Configuración avanzada actualizada', 'success', array(
            'user_id' => get_current_user_id()
        ));
        
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>✅ Configuración avanzada guardada correctamente.</strong></p>
            </div>
            <?php
        });
    }

    /**
     * Obtener estado de webhooks
     */
    private function get_webhook_status() {
        $api = yeison_btx_api();
        
        if (!$api->is_authorized()) {
            return array();
        }
        
        $response = $api->api_call('event.get');
        $webhooks = array();
        
        if ($response && isset($response['result'])) {
            $site_domain = parse_url(home_url(), PHP_URL_HOST);
            
            foreach ($response['result'] as $webhook) {
                $handler = $webhook['handler'] ?? '';
                if (strpos($handler, $site_domain) !== false || 
                    strpos($handler, 'yeison-bitrix/v1/webhook') !== false) {
                    $webhooks[] = $webhook['event'];
                }
            }
        }
        
        return $webhooks;
    }



    /**
     * Página de logs simplificada con diseño en tabla y colores pastel
     */
    public function logs_page_dos() {
        global $wpdb;
        
        // Parámetros de filtrado
        $type_filter = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        
        // Construir query
        $where_clause = '';
        if (!empty($type_filter) && in_array($type_filter, ['info', 'error', 'warning', 'success'])) {
            $where_clause = $wpdb->prepare("WHERE type = %s", $type_filter);
        }
        
        // Obtener logs
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT id, type, message, created_at, user_id, ip_address, data 
            FROM {$wpdb->prefix}yeison_btx_logs 
            {$where_clause}
            ORDER BY created_at DESC 
            LIMIT %d",
            $limit
        ));
        
        // Estadísticas
        $stats = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_logs"),
            'errors' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_logs WHERE type = 'error'"),
            'warnings' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_logs WHERE type = 'warning'"),
            'success' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_logs WHERE type = 'success'"),
            'today' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_logs WHERE DATE(created_at) = %s",
                current_time('Y-m-d')
            ))
        );
        
        ?>
        <div class="yeison-logs-wrapper">
            <style>
                /* Contenedor principal */
                .yeison-logs-wrapper {
                    max-width: 1200px;
                    margin: 20px auto;
                    padding: 20px;
                    background: #fefefe;
                    border-radius: 12px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
                }
                
                /* Título */
                .yeison-page-title {
                    color: #5a5a5a;
                    font-size: 24px;
                    margin-bottom: 20px;
                    text-align: center;
                    border-bottom: 2px solid #e8f4f8;
                    padding-bottom: 15px;
                }
                
                /* Panel de estadísticas */
                .yeison-stats-panel {
                    background: linear-gradient(135deg, #f8fbff 0%, #e8f4f8 100%);
                    padding: 20px;
                    border-radius: 8px;
                    margin-bottom: 25px;
                    border: 1px solid #d6e9f0;
                }
                
                .yeison-stats-title {
                    color: #4a5568;
                    font-size: 16px;
                    margin-bottom: 15px;
                    font-weight: 600;
                }
                
                .yeison-stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                    gap: 15px;
                }
                
                .yeison-stat-box {
                    background: white;
                    padding: 15px;
                    border-radius: 6px;
                    text-align: center;
                    border: 1px solid #e2e8f0;
                }
                
                .yeison-stat-number {
                    font-size: 22px;
                    font-weight: bold;
                    color: #2d3748;
                    margin-bottom: 5px;
                }
                
                .yeison-stat-label {
                    font-size: 12px;
                    color: #718096;
                    text-transform: uppercase;
                }
                
                /* Panel de filtros */
                .yeison-filters-panel {
                    background: #fff8f0;
                    padding: 20px;
                    border-radius: 8px;
                    margin-bottom: 25px;
                    border: 1px solid #f7e6d3;
                }
                
                .yeison-filters-title {
                    color: #744210;
                    font-size: 16px;
                    margin-bottom: 15px;
                    font-weight: 600;
                }
                
                .yeison-filters-form {
                    display: flex;
                    gap: 15px;
                    align-items: center;
                    flex-wrap: wrap;
                }
                
                .yeison-filter-input {
                    padding: 8px 12px;
                    border: 1px solid #d69e2e;
                    border-radius: 6px;
                    background: white;
                    font-size: 14px;
                }
                
                .yeison-filter-btn {
                    padding: 8px 16px;
                    background: #ed8936;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 14px;
                    text-decoration: none;
                }
                
                .yeison-filter-btn:hover {
                    background: #dd6b20;
                }
                
                .yeison-filter-btn.reset {
                    background: #a0aec0;
                }
                
                .yeison-filter-btn.reset:hover {
                    background: #718096;
                }
                
                /* Tabla de logs */
                .yeison-logs-table {
                    width: 100%;
                    border-collapse: collapse;
                    background: white;
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
                }
                
                .yeison-logs-table th {
                    background: linear-gradient(135deg, #f0f8ff 0%, #e6f3ff 100%);
                    padding: 15px 12px;
                    text-align: left;
                    font-weight: 600;
                    color: #2d3748;
                    border-bottom: 2px solid #bee3f8;
                    font-size: 14px;
                }
                
                .yeison-logs-table td {
                    padding: 12px;
                    border-bottom: 1px solid #e2e8f0;
                    vertical-align: top;
                    font-size: 14px;
                }
                
                .yeison-logs-table tr:hover {
                    background: #f7fafc;
                }
                
                /* Tipos de log */
                .yeison-log-badge {
                    display: inline-block;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                }
                
                .yeison-log-badge.success {
                    background: #c6f6d5;
                    color: #22543d;
                }
                
                .yeison-log-badge.error {
                    background: #fed7d7;
                    color: #742a2a;
                }
                
                .yeison-log-badge.warning {
                    background: #fef5e7;
                    color: #975a16;
                }
                
                .yeison-log-badge.info {
                    background: #dbeafe;
                    color: #1e3a8a;
                }
                
                /* Datos adicionales */
                .yeison-log-data {
                    font-family: monospace;
                    font-size: 12px;
                    background: #f8f9fa;
                    padding: 8px;
                    border-radius: 4px;
                    max-width: 200px;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }
                
                /* Estado vacío */
                .yeison-empty-logs {
                    text-align: center;
                    padding: 40px;
                    color: #718096;
                    background: #f7fafc;
                    border-radius: 8px;
                }
                
                /* Responsive */
                @media (max-width: 768px) {
                    .yeison-filters-form {
                        flex-direction: column;
                        align-items: stretch;
                    }
                    
                    .yeison-logs-table {
                        font-size: 12px;
                    }
                    
                    .yeison-logs-table th,
                    .yeison-logs-table td {
                        padding: 8px 6px;
                    }
                }
            </style>
            
            <!-- Título de la página -->
            <h1 class="yeison-page-title">📊 Sistema de Logs y Monitoreo</h1>
            
            <!-- Panel de estadísticas -->
            <div class="yeison-stats-panel">
                <h3 class="yeison-stats-title">📈 Resumen de Actividad</h3>
                <div class="yeison-stats-grid">
                    <div class="yeison-stat-box">
                        <div class="yeison-stat-number"><?php echo number_format($stats['total']); ?></div>
                        <div class="yeison-stat-label">Total</div>
                    </div>
                    <div class="yeison-stat-box">
                        <div class="yeison-stat-number"><?php echo number_format($stats['success']); ?></div>
                        <div class="yeison-stat-label">Éxitos</div>
                    </div>
                    <div class="yeison-stat-box">
                        <div class="yeison-stat-number"><?php echo number_format($stats['warnings']); ?></div>
                        <div class="yeison-stat-label">Advertencias</div>
                    </div>
                    <div class="yeison-stat-box">
                        <div class="yeison-stat-number"><?php echo number_format($stats['errors']); ?></div>
                        <div class="yeison-stat-label">Errores</div>
                    </div>
                    <div class="yeison-stat-box">
                        <div class="yeison-stat-number"><?php echo number_format($stats['today']); ?></div>
                        <div class="yeison-stat-label">Hoy</div>
                    </div>
                </div>
            </div>
            
            <!-- Panel de filtros -->
            <div class="yeison-filters-panel">
                <h3 class="yeison-filters-title">🔍 Filtros de Búsqueda</h3>
                <form method="get" class="yeison-filters-form">
                    <input type="hidden" name="page" value="yeison-btx-logs">
                    
                    <label>
                        <strong>Tipo:</strong>
                        <select name="type" class="yeison-filter-input">
                            <option value="">Todos</option>
                            <option value="info" <?php selected($type_filter, 'info'); ?>>Info</option>
                            <option value="success" <?php selected($type_filter, 'success'); ?>>Success</option>
                            <option value="warning" <?php selected($type_filter, 'warning'); ?>>Warning</option>
                            <option value="error" <?php selected($type_filter, 'error'); ?>>Error</option>
                        </select>
                    </label>
                    
                    <label>
                        <strong>Límite:</strong>
                        <select name="limit" class="yeison-filter-input">
                            <option value="25" <?php selected($limit, 25); ?>>25</option>
                            <option value="50" <?php selected($limit, 50); ?>>50</option>
                            <option value="100" <?php selected($limit, 100); ?>>100</option>
                            <option value="200" <?php selected($limit, 200); ?>>200</option>
                        </select>
                    </label>
                    
                    <button type="submit" class="yeison-filter-btn">Aplicar Filtros</button>
                    <a href="?page=yeison-btx-logs" class="yeison-filter-btn reset">Limpiar</a>
                </form>
            </div>
            
            <!-- Tabla de logs -->
            <?php if (empty($logs)): ?>
                <div class="yeison-empty-logs">
                    <h3>📝 No hay logs disponibles</h3>
                    <p>No se encontraron registros con los filtros aplicados.<br>
                    Intenta cambiar los parámetros de búsqueda o verifica que existan logs en el sistema.</p>
                </div>
            <?php else: ?>
                <table class="yeison-logs-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tipo</th>
                            <th>Mensaje</th>
                            <th>Fecha y Hora</th>
                            <th>Usuario</th>
                            <th>IP</th>
                            <th>Datos Extra</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <!-- ID del log -->
                            <td><strong>#<?php echo esc_html($log->id); ?></strong></td>
                            
                            <!-- Tipo con badge colorido -->
                            <td>
                                <span class="yeison-log-badge <?php echo esc_attr($log->type); ?>">
                                    <?php 
                                    $icons = [
                                        'success' => '✅',
                                        'error' => '❌',
                                        'warning' => '⚠️',
                                        'info' => 'ℹ️'
                                    ];
                                    echo $icons[$log->type] ?? '📝';
                                    echo ' ' . esc_html(ucfirst($log->type)); 
                                    ?>
                                </span>
                            </td>
                            
                            <!-- Mensaje completo -->
                            <td>
                                <strong><?php echo esc_html($log->message); ?></strong>
                            </td>
                            
                            <!-- Fecha formateada -->
                            <td>
                                <?php 
                                $date = new DateTime($log->created_at);
                                echo $date->format('d/m/Y'); ?><br>
                                <small style="color: #718096;">
                                    <?php echo $date->format('H:i:s'); ?>
                                </small>
                            </td>
                            
                            <!-- Usuario -->
                            <td>
                                <?php if ($log->user_id): ?>
                                    <?php 
                                    $user = get_user_by('ID', $log->user_id);
                                    echo $user ? esc_html($user->display_name) : 'Usuario #' . $log->user_id;
                                    ?>
                                <?php else: ?>
                                    <em style="color: #a0aec0;">Sistema</em>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Dirección IP -->
                            <td>
                                <?php if ($log->ip_address): ?>
                                    <code><?php echo esc_html($log->ip_address); ?></code>
                                <?php else: ?>
                                    <em style="color: #a0aec0;">No disponible</em>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Datos adicionales -->
                            <td>
                                <?php if ($log->data): ?>
                                    <div class="yeison-log-data" title="<?php echo esc_attr($log->data); ?>">
                                        <?php echo esc_html(substr($log->data, 0, 50)); ?>
                                        <?php if (strlen($log->data) > 50): ?>...<?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <em style="color: #a0aec0;">Sin datos</em>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <!-- Información adicional -->
            <div style="margin-top: 20px; padding: 15px; background: #f0f8ff; border-radius: 6px; font-size: 13px; color: #4a5568;">
                <strong>💡 Información:</strong>
                Mostrando <?php echo count($logs); ?> de <?php echo $stats['total']; ?> registros totales.
                <?php if ($type_filter): ?>
                Filtrado por tipo: <strong><?php echo esc_html($type_filter); ?></strong>.
                <?php endif; ?>
                Los logs se ordenan por fecha (más recientes primero).
            </div>
        </div>
        <?php
    }

    
    /**
     * Cargar scripts de admin
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'yeison-btx') !== false) {
            wp_enqueue_style(
                'yeison-btx-admin',
                YEISON_BTX_PLUGIN_URL . 'assets/admin.css',
                array(),
                YEISON_BTX_VERSION
            );
        }
    }
    
    /**
     * Verificar requisitos
     */
    public function check_requirements() {
        $errors = array();
        
        // PHP Version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $errors[] = sprintf(
                'Yeison BTX requiere PHP 7.4 o superior. Tu versión: %s',
                PHP_VERSION
            );
        }
        
        // cURL
        if (!extension_loaded('curl')) {
            $errors[] = 'Yeison BTX requiere la extensión cURL de PHP.';
        }
        
        // JSON
        if (!extension_loaded('json')) {
            $errors[] = 'Yeison BTX requiere la extensión JSON de PHP.';
        }
        
        // Mostrar errores
        foreach ($errors as $error) {
            add_action('admin_notices', function() use ($error) {
                ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html($error); ?></p>
                </div>
                <?php
            });
        }
    }
    
    /**
     * Cargar textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'yeison-btx',
            false,
            dirname(YEISON_BTX_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Activar plugin
     */
    public function activate() {
        // Crear tablas
        $this->create_tables();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Marcar versión
        update_option('yeison_btx_version', YEISON_BTX_VERSION);
        
        // Log si la función existe
        if (function_exists('yeison_btx_log')) {
            yeison_btx_log('Plugin activado', 'info', array(
                'version' => YEISON_BTX_VERSION
            ));
        }
        
        // Programar eventos cron
        if (!wp_next_scheduled('yeison_btx_process_queue')) {
            wp_schedule_event(time(), 'hourly', 'yeison_btx_process_queue');
        }
        
        if (!wp_next_scheduled('yeison_btx_cleanup_patterns')) {
            wp_schedule_event(time(), 'daily', 'yeison_btx_cleanup_patterns');
        }
    }
    
    /**
     * Desactivar plugin
     */
    public function deactivate() {
        // Limpiar tareas programadas
        wp_clear_scheduled_hook('yeison_btx_process_queue');
        wp_clear_scheduled_hook('yeison_btx_cleanup_patterns');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log de desactivación
        if (function_exists('yeison_btx_log')) {
            yeison_btx_log('Plugin desactivado', 'info');
        }
    }
    

    /**
     * Crear tablas de la base de datos (versión mejorada)
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabla de logs
        $table_logs = $wpdb->prefix . 'yeison_btx_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            type varchar(50) NOT NULL DEFAULT 'info',
            action varchar(100) NOT NULL,
            message text,
            data longtext,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(100) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type_index (type),
            KEY created_at_index (created_at)
        ) $charset_collate;";
        
        // Tabla de sincronización
        $table_sync = $wpdb->prefix . 'yeison_btx_sync';
        $sql_sync = "CREATE TABLE $table_sync (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            entity_type varchar(50) NOT NULL,
            local_id varchar(100) NOT NULL,
            remote_id varchar(100) NOT NULL,
            sync_status varchar(20) DEFAULT 'synced',
            last_sync datetime DEFAULT CURRENT_TIMESTAMP,
            sync_data longtext,
            PRIMARY KEY (id),
            UNIQUE KEY entity_mapping (entity_type, local_id),
            KEY remote_lookup (entity_type, remote_id)
        ) $charset_collate;";
        
        // Tabla de queue
        $table_queue = $wpdb->prefix . 'yeison_btx_queue';
        $sql_queue = "CREATE TABLE $table_queue (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_type varchar(50) NOT NULL,
            form_data longtext NOT NULL,
            status varchar(20) DEFAULT 'pending',
            attempts int(11) DEFAULT 0,
            processed_at datetime DEFAULT NULL,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status_index (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Usar dbDelta para crear/actualizar tablas
        $result_logs = dbDelta($sql_logs);
        $result_sync = dbDelta($sql_sync);
        $result_queue = dbDelta($sql_queue);
        
        // Verificar que las tablas se crearon correctamente
        $tables_created = array();
        $tables_failed = array();
        
        $tables_to_check = array(
            'logs' => $table_logs,
            'sync' => $table_sync,
            'queue' => $table_queue
        );
        
        foreach ($tables_to_check as $name => $table_name) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
                $tables_created[] = $name;
            } else {
                $tables_failed[] = $name;
            }
        }
        
        // Log del resultado
        if (function_exists('error_log')) {
            if (!empty($tables_failed)) {
                error_log('[Yeison BTX] Error creando tablas: ' . implode(', ', $tables_failed));
            } else {
                error_log('[Yeison BTX] Todas las tablas creadas exitosamente: ' . implode(', ', $tables_created));
            }
        }
        
        // Forzar creación si dbDelta falló
        if (!empty($tables_failed)) {
            foreach ($tables_failed as $failed_table) {
                switch ($failed_table) {
                    case 'logs':
                        $wpdb->query($sql_logs);
                        break;
                    case 'sync':
                        $wpdb->query($sql_sync);
                        break;
                    case 'queue':
                        $wpdb->query($sql_queue);
                        break;
                }
            }
        }
        
        // Marcar como creadas
        update_option('yeison_btx_tables_created', current_time('mysql'));
    }

    /**
     * Verificar y crear tablas faltantes
     */
    public function ensure_tables_exist() {
        global $wpdb;
        
        $required_tables = array(
            $wpdb->prefix . 'yeison_btx_logs',
            $wpdb->prefix . 'yeison_btx_sync', 
            $wpdb->prefix . 'yeison_btx_queue'
        );
        
        $missing_tables = array();
        
        foreach ($required_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
                $missing_tables[] = $table;
            }
        }
        
        if (!empty($missing_tables)) {
            error_log('[Yeison BTX] Tablas faltantes detectadas: ' . implode(', ', $missing_tables));
            $this->create_tables();
            
            // Verificar de nuevo
            $still_missing = array();
            foreach ($missing_tables as $table) {
                if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
                    $still_missing[] = $table;
                }
            }
            
            if (!empty($still_missing)) {
                error_log('[Yeison BTX] ERROR: Tablas aún faltantes después de crear: ' . implode(', ', $still_missing));
            }
            
            return empty($still_missing);
        }
        
        return true;
    }



}

// Función global para obtener la instancia
function yeison_btx() {
    return YeisonBTX::get_instance();
}

// Inicializar solo si no estamos en proceso de activación
if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
    add_action('plugins_loaded', 'yeison_btx', 1);
}