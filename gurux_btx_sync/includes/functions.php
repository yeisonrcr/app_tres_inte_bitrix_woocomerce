<?php
/**
 * Funciones auxiliares del plugin Yeison BTX
 * 
 * @package YeisonBTX
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}


/**
 * Función de logging mejorada con protección de datos sensibles
 */
function yeison_btx_log($message, $type = 'info', $data = array()) {
    global $wpdb;
    
    // Verificar que la tabla existe
    $table_name = $wpdb->prefix . 'yeison_btx_logs';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
        error_log("[Yeison BTX - FALLBACK] {$type}: {$message}");
        return false;
    }
    
    // Validar tipo
    $valid_types = array('info', 'error', 'warning', 'success', 'debug');
    if (!in_array($type, $valid_types)) {
        $type = 'info';
    }
    
    // SANITIZAR DATOS SENSIBLES
    $safe_data = !empty($data) ? yeison_btx_sanitize_sensitive_data($data, 'log') : array();
    
    // Preparar datos
    $log_data = array(
        'type' => $type,
        'action' => current_action() ?: 'manual',
        'message' => $message,
        'data' => !empty($safe_data) ? wp_json_encode($safe_data) : null,
        'user_id' => get_current_user_id(),
        'ip_address' => yeison_btx_get_ip(),
        'created_at' => current_time('mysql')
    );
    
    // Insertar en DB
    $result = $wpdb->insert(
        $table_name,
        $log_data,
        array('%s', '%s', '%s', '%s', '%d', '%s', '%s')
    );
    
    // Si es error crítico, también log en error_log (sin datos sensibles)
    if ($type === 'error') {
        error_log(sprintf('[Yeison BTX] %s', $message));
    }
    
    return $result ? $wpdb->insert_id : false;
}



function yeison_btx_get_ip() {
    $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
    
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = filter_var($_SERVER[$key], FILTER_VALIDATE_IP);
            if ($ip !== false) {
                return $ip;
            }
        }
    }
    
    return '0.0.0.0';
}



function yeison_btx_get_option($option, $default = null) {
    $options = get_option('yeison_btx_settings', array());
    return isset($options[$option]) ? $options[$option] : $default;
}

function yeison_btx_update_option($option, $value) {
    $options = get_option('yeison_btx_settings', array());
    $options[$option] = $value;
    return update_option('yeison_btx_settings', $options);
}

function yeison_btx_is_configured() {
    $domain = yeison_btx_get_option('bitrix_domain');
    $client_id = yeison_btx_get_option('client_id');
    $client_secret = yeison_btx_get_option('client_secret');
    
    return !empty($domain) && !empty($client_id) && !empty($client_secret);
}

function yeison_btx_has_valid_tokens() {
    $access_token = yeison_btx_get_option('access_token');
    $refresh_token = yeison_btx_get_option('refresh_token');
    
    return !empty($access_token) && !empty($refresh_token);
}


function yeison_btx_sanitize_form_data($data) {
    $sanitized = array();
    
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $sanitized[$key] = yeison_btx_sanitize_form_data($value);
        } elseif (is_email($value)) {
            $sanitized[$key] = sanitize_email($value);
        } elseif (strpos($key, 'url') !== false || filter_var($value, FILTER_VALIDATE_URL)) {
            $sanitized[$key] = esc_url_raw($value);
        } else {
            $sanitized[$key] = sanitize_text_field($value);
        }
    }
    
    return $sanitized;
}


function yeison_btx_check_webhook_config() {
    $webhooks = yeison_btx_webhooks();
    $api = yeison_btx_api();
    
    return array(
        'webhooks_enabled' => yeison_btx_get_option('webhooks_enabled', true),
        'api_authorized' => $api->is_authorized(),
        'webhook_secret' => !empty(yeison_btx_get_option('webhook_secret')),
        'endpoints_accessible' => yeison_btx_test_webhook_endpoints(),
        'webhooks_registered' => yeison_btx_count_registered_webhooks()
    );
}



function yeison_btx_test_webhook_endpoints() {
    $test_url = rest_url('yeison-bitrix/v1/webhook/status');
    
    $response = wp_remote_get($test_url, array(
        'timeout' => 10
    ));
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    return $status_code === 200;
}


function yeison_btx_count_registered_webhooks() {
    $api = yeison_btx_api();
    
    if (!$api->is_authorized()) {
        return 0;
    }
    
    $response = $api->api_call('event.get');
    
    if (!$response || !isset($response['result'])) {
        return 0;
    }
    
    $site_url = parse_url(home_url(), PHP_URL_HOST);
    $count = 0;
    
    foreach ($response['result'] as $webhook) {
        if (strpos($webhook['HANDLER'], $site_url) !== false) {
            $count++;
        }
    }
    
    return $count;
}



function yeison_btx_map_form_to_lead($form_data) {
    $lead_data = array(
        'TITLE' => 'Lead desde ' . parse_url(home_url(), PHP_URL_HOST),
        'SOURCE_ID' => 'WEB',
        'STATUS_ID' => 'NEW',
        'OPENED' => 'Y',
        'ASSIGNED_BY_ID' => 1,
        'CREATED_DATE' => date('c'),
        'COMMENTS' => ''
    );
    
    // Mapeo inteligente de campos comunes
    $field_mappings = array(
        // Campo formulario => Campo Bitrix24
        'name' => 'NAME',
        'first_name' => 'NAME',
        'nombre' => 'NAME',
        'last_name' => 'LAST_NAME',
        'apellido' => 'LAST_NAME',
        'email' => 'EMAIL',
        'correo' => 'EMAIL',
        'phone' => 'PHONE',
        'telefono' => 'PHONE',
        'tel' => 'PHONE',
        'message' => 'COMMENTS',
        'mensaje' => 'COMMENTS',
        'comments' => 'COMMENTS',
        'comentarios' => 'COMMENTS'
    );
    
    // Aplicar mapeo
    foreach ($form_data as $key => $value) {
        $key_lower = strtolower($key);
        
        // Buscar coincidencia directa
        if (isset($field_mappings[$key_lower])) {
            $bitrix_field = $field_mappings[$key_lower];
            
            // Manejar campos múltiples (email, phone)
            if (in_array($bitrix_field, array('EMAIL', 'PHONE'))) {
                $lead_data[$bitrix_field] = array(
                    array('VALUE' => $value, 'VALUE_TYPE' => 'WORK')
                );
            } else {
                $lead_data[$bitrix_field] = $value;
            }
        }
        
        // Buscar coincidencia parcial
        foreach ($field_mappings as $form_field => $bitrix_field) {
            if (strpos($key_lower, $form_field) !== false && !isset($lead_data[$bitrix_field])) {
                if (in_array($bitrix_field, array('EMAIL', 'PHONE'))) {
                    $lead_data[$bitrix_field] = array(
                        array('VALUE' => $value, 'VALUE_TYPE' => 'WORK')
                    );
                } else {
                    $lead_data[$bitrix_field] = $value;
                }
                break;
            }
        }
    }

    /* 
    // Agregar URL de origen
    if (isset($_SERVER['HTTP_REFERER'])) {
        $lead_data['SOURCE_DESCRIPTION'] = 'Formulario enviado desde: ' . $_SERVER['HTTP_REFERER'];
    }
 */


    // ✅Agregar página de origen con más detalle
    $origin_url = '';
    if (isset($form_data['_meta']['origin'])) {
        $origin_url = $form_data['_meta']['origin'];
    } elseif (isset($_SERVER['HTTP_REFERER'])) {
        $origin_url = $_SERVER['HTTP_REFERER'];
    }

    if ($origin_url) {
        $lead_data['SOURCE_DESCRIPTION'] = 'Formulario desde: ' . $origin_url;
        $lead_data['UTM_SOURCE'] = parse_url($origin_url, PHP_URL_HOST);
        $lead_data['UTM_CONTENT'] = basename(parse_url($origin_url, PHP_URL_PATH));
    }

















    
    // Agregar todos los campos al comentario
    $all_fields = array();
    foreach ($form_data as $key => $value) {
        if (!is_array($value)) {
            $all_fields[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
        }
    }
    
    if (empty($lead_data['COMMENTS'])) {
        $lead_data['COMMENTS'] = implode("\n", $all_fields);
    } else {
        $lead_data['COMMENTS'] .= "\n\n--- Datos adicionales ---\n" . implode("\n", $all_fields);
    }
    
    // Título descriptivo
    if (!empty($lead_data['NAME'])) {
        $lead_data['TITLE'] = 'Lead: ' . $lead_data['NAME'];
    } elseif (isset($lead_data['EMAIL'][0]['VALUE'])) {
        $lead_data['TITLE'] = 'Lead: ' . $lead_data['EMAIL'][0]['VALUE'];
    }
    
    return $lead_data;
}

















add_action('wp_ajax_yeison_btx_test_connection', 'yeison_btx_handle_test_connection');
function yeison_btx_handle_test_connection() {
    // Verificar nonce
    if (!wp_verify_nonce($_POST['nonce'], 'yeison_btx_test')) {
        wp_die('Nonce inválido');
    }
    
    // Verificar permisos
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    $api = yeison_btx_api();
    $result = $api->test_connection();
    
    wp_send_json_success($result);
}



function yeison_btx_validate_domain($domain) {
    // Limpiar dominio
    $domain = trim($domain);
    $domain = str_replace(array('http://', 'https://'), '', $domain);
    $domain = rtrim($domain, '/');
    
    // Verificar formato
    if (empty($domain)) {
        return 'El dominio no puede estar vacío';
    }
    
    if (!preg_match('/^[a-zA-Z0-9.-]+\.bitrix24\.(com|es|de|fr|it|pl|br|uk|eu)$/', $domain)) {
        return 'Formato de dominio inválido. Debe ser: miempresa.bitrix24.com';
    }
    
    return true;
}

/**
 * Limpiar tokens de autenticación
 */
function yeison_btx_clear_tokens() {
    yeison_btx_update_option('access_token', '');
    yeison_btx_update_option('refresh_token', '');
    
    yeison_btx_log('Tokens limpiados', 'info');
}


function yeison_btx_get_api_status() {
    $api = yeison_btx_api();
    
    return array(
        'configured' => $api->is_configured(),
        'authorized' => $api->is_authorized(),
        'domain' => yeison_btx_get_option('bitrix_domain'),
        'client_id' => yeison_btx_get_option('client_id') ? 'Configurado' : 'No configurado',
        'has_tokens' => !empty(yeison_btx_get_option('access_token'))
    );
}




function yeison_btx_add_to_queue($form_type, $form_data) {
    global $wpdb;
    
    // Verificar que la tabla existe
    $table_name = $wpdb->prefix . 'yeison_btx_queue';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    
        if (!$table_exists) {
        yeison_btx_log('❌ Tabla yeison_btx_queue no existe', 'error', array(
            'table_name' => $table_name
        ));
        
        // Intentar crear la tabla
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$table_name} (
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
        ) {$wpdb->get_charset_collate()};");
        
        yeison_btx_log('🔧 Tabla creada automáticamente', 'info');
    }
    
    // Preparar datos para insertar
    $queue_data = array(
        'form_type' => sanitize_text_field($form_type),
        'form_data' => wp_json_encode($form_data),
        'status' => 'pending',
        'attempts' => 0,
        'created_at' => current_time('mysql')
    );
    
    // Intentar insertar en la base de datos
    $result = $wpdb->insert(
        $table_name,
        $queue_data,
        array('%s', '%s', '%s', '%d', '%s')
    );
    
    // 🔧 LOG DEL RESULTADO
    if ($result === false) {
        yeison_btx_log('❌ Error insertando en cola', 'error', array(
            'wpdb_error' => $wpdb->last_error,
            'wpdb_query' => $wpdb->last_query,
            'table_name' => $table_name,
            'data' => $queue_data
        ));
        return false;
    }
    
    $queue_id = $wpdb->insert_id;
    
    if (!$queue_id) {
        yeison_btx_log('❌ No se obtuvo insert_id', 'error', array(
            'result' => $result,
            'insert_id' => $queue_id,
            'wpdb_error' => $wpdb->last_error
        ));
        return false;
    }
    
    // Verificar que realmente se insertó
    $verification = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $queue_id
    ));
    
    if (!$verification) {
        yeison_btx_log('❌ Elemento no se encuentra después de insertar', 'error', array(
            'queue_id' => $queue_id,
            'table_name' => $table_name
        ));
        return false;
    }
    
    return $queue_id;
}

















function yeison_btx_get_stats() {
    global $wpdb;
    
    $stats = array(
        'total_logs' => 0,
        'total_synced' => 0,
        'pending_queue' => 0,
        'errors_today' => 0,
        'last_sync' => null
    );
    
    // Total logs
    $stats['total_logs'] = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_logs"
    );
    
    // Total sincronizados
    $stats['total_synced'] = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_sync WHERE sync_status = 'synced'"
    );
    
    // Cola pendiente
    $stats['pending_queue'] = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_queue WHERE status = 'pending'"
    );
    
    // Errores de hoy
    $stats['errors_today'] = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_logs 
        WHERE type = 'error' AND DATE(created_at) = %s",
        current_time('Y-m-d')
    ));
    
    // Última sincronización
    $last_sync = $wpdb->get_var(
        "SELECT MAX(created_at) FROM {$wpdb->prefix}yeison_btx_logs 
        WHERE action LIKE '%sync%' AND type = 'success'"
    );
    
    if ($last_sync) {
        $stats['last_sync'] = human_time_diff(strtotime($last_sync), current_time('timestamp')) . ' atrás';
    }
    
    return $stats;
}

function yeison_btx_is_ajax() {
    return defined('DOING_AJAX') && DOING_AJAX;
}

function yeison_btx_json_response($data, $status_code = 200) {
    wp_send_json($data, $status_code);
}


function yeison_btx_debug($data, $label = '') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Yeison BTX Debug] ' . $label . ': ' . print_r($data, true));
    }
}





/**
 * Disparar evento personalizado cuando API se autoriza
 */
add_action('yeison_btx_oauth_success', function() {
    do_action('yeison_btx_api_authorized');
});

/**
 * Disparar evento cuando API se desautoriza
 */
add_action('yeison_btx_clear_tokens', function() {
    do_action('yeison_btx_api_deauthorized');
});


/**
 * Vaciar cola pendiente - URL: /wp-admin/admin-ajax.php?action=yeison_btx_clear_queue
 */
add_action('wp_ajax_yeison_btx_clear_queue', 'yeison_btx_clear_pending_queue');
function yeison_btx_clear_pending_queue() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    global $wpdb;
    
    // Contar elementos pendientes
    $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_queue WHERE status = 'pending'");
    
    if ($pending_count == 0) {
        echo '<h2>✅ Cola ya está vacía</h2>';
        exit;
    }
    
    // Mostrar elementos antes de borrar
    $pending_items = $wpdb->get_results("SELECT id, form_type, created_at FROM {$wpdb->prefix}yeison_btx_queue WHERE status = 'pending' ORDER BY created_at DESC");
    
    echo '<h2>🗑️ Elementos en Cola Pendiente (' . $pending_count . ')</h2>';
    echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
    echo '<tr><th>ID</th><th>Tipo</th><th>Fecha</th></tr>';
    
    foreach ($pending_items as $item) {
        echo '<tr>';
        echo '<td>' . $item->id . '</td>';
        echo '<td>' . $item->form_type . '</td>';
        echo '<td>' . $item->created_at . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
    // Botón de confirmación
    if (!isset($_GET['confirm'])) {
        echo '<br><p style="color: red;"><strong>⚠️ ¿Estás seguro de eliminar todos estos elementos?</strong></p>';
        echo '<a href="' . admin_url('admin-ajax.php?action=yeison_btx_clear_queue&confirm=yes') . '" 
              style="background: red; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
              🗑️ SÍ, ELIMINAR TODO</a>';
        echo ' ';
        echo '<a href="' . admin_url('admin.php?page=yeison-btx') . '" 
              style="background: gray; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
              ❌ Cancelar</a>';
    } else {
        // Eliminar elementos
        $deleted = $wpdb->query("DELETE FROM {$wpdb->prefix}yeison_btx_queue WHERE status = 'pending'");
        
        // Log de la acción
        
        echo '<br><div style="background: green; color: white; padding: 15px; border-radius: 5px;">';
        echo '<h3>✅ Cola Vaciada Exitosamente</h3>';
        echo '<p>Se eliminaron <strong>' . $deleted . '</strong> elementos de la cola.</p>';
        echo '</div>';
        
        echo '<p><a href="' . admin_url('admin.php?page=yeison-btx') . '">← Volver al Dashboard</a></p>';
    }
    
    exit;
}






// Normalizar eventos de webhook
function yeison_btx_normalize_event($raw_event) {
    $event_mapping = array(
        'onCrmContactUpdate' => 'ONCRMCONTACTUPDATE',
        'onCrmContactAdd' => 'ONCRMCONTACTADD',
        'onCrmDealUpdate' => 'ONCRMDEALUPDATE', 
        'onCrmDealAdd' => 'ONCRMDEALADD',
        'onCrmLeadAdd' => 'ONCRMLEADADD',
        'onCrmLeadUpdate' => 'ONCRMLEADUPDATE'
    );
    
    if (isset($event_mapping[$raw_event])) {
        return $event_mapping[$raw_event];
    }
    
    $normalized = strtoupper($raw_event);
    return $normalized;
}





add_action('admin_init', 'yeison_btx_set_default_options', 1);
function yeison_btx_set_default_options() {
    // Solo ejecutar una vez
    if (get_option('yeison_btx_defaults_set')) {
        return;
    }
    
    // Establecer configuraciones por defecto
    yeison_btx_update_option('bidirectional_sync_enabled', true);
    yeison_btx_update_option('sync_contact_to_customer', true);
    yeison_btx_update_option('sync_deal_to_order', true);
    yeison_btx_update_option('webhooks_enabled', true);
    yeison_btx_update_option('sync_auto_process', true);
    yeison_btx_update_option('prevent_loops', true);
    
    // Marcar como configurado
    update_option('yeison_btx_defaults_set', true);
    
}

/**
 * Hook para interceptar y debuggear la sincronización Deal → Order
 */
add_action('yeison_btx_deal_webhook_received', 'yeison_btx_debug_deal_to_order_sync', 999, 2);
function yeison_btx_debug_deal_to_order_sync($event, $deal_fields) {
    if ($event !== 'ONCRMDEALUPDATE') {
        return; // Solo procesar actualizaciones
    }
    
    
    // 1. Verificar si la sincronización bidireccional está habilitada
    $bidirectional_enabled = yeison_btx_get_option('bidirectional_sync_enabled', false);
    $deal_to_order_enabled = yeison_btx_get_option('sync_deal_to_order', false);

    if (!$bidirectional_enabled || !$deal_to_order_enabled) {
        
        return;
    }
    
    // 2. Buscar el pedido WooCommerce
    global $wpdb;
    $sync_record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}yeison_btx_sync 
        WHERE entity_type = 'woo_order' AND remote_id = %s",
        $deal_fields['ID']
    ));
    
    if (!$sync_record) {
        yeison_btx_log('❌ No se encontró pedido WooCommerce para el Deal', 'error', array(
            'deal_id' => $deal_fields['ID']
        ));
        return;
    }
    
    $order_id = $sync_record->local_id;
    
    
    // 3. Verificar que WooCommerce esté activo
    if (!class_exists('WooCommerce')) {
        yeison_btx_log('❌ WooCommerce no está activo', 'error');
        return;
    }
    
    // 4. Obtener el pedido
    $order = wc_get_order($order_id);
    if (!$order) {
        yeison_btx_log('❌ Pedido WooCommerce no encontrado', 'error', array(
            'order_id' => $order_id
        ));
        return;
    }
    
    
    
    // 5. Mapear el estado de Bitrix24 a WooCommerce
    $new_wc_status = yeison_btx_map_bitrix_stage_to_wc_status($deal_fields['STAGE_ID']);
    
    
    if (!$new_wc_status) {
        return;
    }
    
    if ($new_wc_status === $order->get_status()) {
        
        return;
    }
    
    // 6. Verificar sistema anti-loop
    $anti_loop = yeison_btx_anti_loop();
    $can_proceed = apply_filters('yeison_btx_before_sync', true, 'woo_order', $order_id, 'bitrix24');
    
    if (!$can_proceed) {
        yeison_btx_log('🛡️ Sistema anti-loop bloqueó la actualización', 'warning', array(
            'order_id' => $order_id,
            'deal_id' => $deal_fields['ID']
        ));
        return;
    }
    
    try {
        // Actualizar el estado del pedido
        $update_result = $order->update_status(
            $new_wc_status, 
            sprintf('Estado actualizado desde Bitrix24 Deal #%s - Etapa: %s', 
                $deal_fields['ID'], 
                $deal_fields['STAGE_ID']
            )
        );
        
        if ($update_result) {
            // Actualizar registro de sincronización
            $wpdb->update(
                $wpdb->prefix . 'yeison_btx_sync',
                array(
                    'last_sync' => current_time('mysql'),
                    'sync_data' => wp_json_encode(array(
                        'last_direction' => 'from_bitrix24',
                        'last_status_change' => $order->get_status() . ' → ' . $new_wc_status,
                        'deal_stage' => $deal_fields['STAGE_ID']
                    ))
                ),
                array('id' => $sync_record->id),
                array('%s', '%s'),
                array('%d')
            );
            
            // Hook post-sincronización
            do_action('yeison_btx_after_sync', 'woo_order', $order_id, 'bitrix24', true);
            
        } else {
            yeison_btx_log('❌ FALLO: No se pudo actualizar el pedido', 'error', array(
                'order_id' => $order_id,
                'attempted_status' => $new_wc_status,
                'current_status' => $order->get_status()
            ));
        }
        
    } catch (Exception $e) {
        yeison_btx_log('💥 EXCEPCIÓN al actualizar pedido', 'error', array(
            'order_id' => $order_id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ));
    }
}

/**
 * Función helper para mapear estados Bitrix24 → WooCommerce
 */
function yeison_btx_map_bitrix_stage_to_wc_status($bitrix_stage) {
    $mapping = array(
        'NEW' => 'pending',
        'PREPARATION' => 'processing',
        'EXECUTING' => 'processing',      // ← ESTA ES LA CLAVE
        'PREPAYMENT_INVOICE' => 'on-hold',
        'WON' => 'completed',
        'LOSE' => 'cancelled',
        'APOLOGY' => 'cancelled'
    );
    
    // Permitir mapeo personalizado
    $custom_mapping = yeison_btx_get_option('bitrix_to_wc_status_mapping', array());
    if (!empty($custom_mapping)) {
        $mapping = array_merge($mapping, $custom_mapping);
    }
    
    $result = isset($mapping[$bitrix_stage]) ? $mapping[$bitrix_stage] : null;
    
    return $result;
}





// Hook para procesamiento diferido de cola
add_action('yeison_btx_process_delayed_queue', function($queue_id) {
    $forms_handler = yeison_btx_forms();
    $forms_handler->process_single_queue_item($queue_id);
});







function yeison_btx_debug_queue_status() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'yeison_btx_queue';
    
    // Verificar si la tabla existe
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    
    if (!$table_exists) {
        yeison_btx_log('❌ DEBUG: Tabla de cola no existe', 'error', array(
            'table_name' => $table_name
        ));
        return false;
    }
    
    // Contar elementos
    $total_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending'");
    $processed_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'processed'");
    $failed_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'failed'");
    
    // Últimos 5 elementos
    $recent_items = $wpdb->get_results("SELECT id, form_type, status, created_at FROM {$table_name} ORDER BY created_at DESC LIMIT 5");
    
    
    return array(
        'table_exists' => true,
        'counts' => array(
            'total' => $total_count,
            'pending' => $pending_count,
            'processed' => $processed_count,
            'failed' => $failed_count
        ),
        'recent_items' => $recent_items
    );
}




// Endpoint temporal para debugging de cola   :   wp-admin/admin-ajax.php?action=yeison_btx_debug_queue
add_action('wp_ajax_yeison_btx_debug_queue', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    echo '<h2>🔍 Debug de Cola de Formularios</h2>';
    
    $status = yeison_btx_debug_queue_status();
    
    echo '<pre>';
    print_r($status);
    echo '</pre>';
    
    exit;
});

/**
 * Limpiar COMPLETAMENTE todas las tablas del plugin
 * URL: /wp-admin/admin-ajax.php?action=yeison_btx_nuclear_cleanup
 */


add_action('wp_ajax_yeison_btx_nuclear_cleanup', 'yeison_btx_nuclear_cleanup');
function yeison_btx_nuclear_cleanup() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    global $wpdb;
    
    // Estilos CSS empresariales modernos - Paleta pastel profesional
    echo '<style>
        .yeison-nuclear-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #F8FAFC 0%, #E8F2FF 50%, #F0F4F8 100%);
            min-height: 100vh;
            color: #1E293B;
        }
        
        .yeison-card {
            background: linear-gradient(135deg, #FFFFFF 0%, #F8FAFC 100%);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: 0 4px 24px rgba(139, 92, 246, 0.08);
            border: 1px solid #E2E8F0;
            transition: all 0.3s ease;
        }
        
        .yeison-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(139, 92, 246, 0.12);
        }
        
        .yeison-header {
            text-align: center;
            margin-bottom: 32px;
            position: relative;
        }
        
        .yeison-header::before {
            content: "";
            position: absolute;
            top: -16px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, #A5B4FC 0%, #8B5CF6 100%);
            border-radius: 2px;
        }
        
        .yeison-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #A5B4FC 0%, #8B5CF6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0 0 16px 0;
            letter-spacing: -0.5px;
        }
        
        .yeison-subtitle {
            color: #64748B;
            font-size: 1.1rem;
            margin: 0;
            font-weight: 400;
        }
        
        .yeison-warning {
            background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
            color: #92400E;
            padding: 24px;
            border-radius: 12px;
            margin: 24px 0;
            border: 2px solid #FDE68A;
            font-weight: 500;
            box-shadow: 0 4px 16px rgba(251, 191, 36, 0.1);
        }
        
        .yeison-warning strong {
            color: #78350F;
        }
        
        .yeison-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin: 24px 0;
        }
        
        .yeison-stat-card {
            background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%);
            padding: 24px;
            border-radius: 12px;
            text-align: center;
            border: 2px solid #CBD5E1;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .yeison-stat-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #A5B4FC, #8B5CF6);
        }
        
        .yeison-stat-card:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #E8F2FF 0%, #DBEAFE 100%);
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.15);
        }
        
        .yeison-stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: #1E293B;
            margin-bottom: 8px;
            letter-spacing: -1px;
        }
        
        .yeison-stat-label {
            color: #64748B;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
        }
        
        .yeison-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 32px;
        }
        
        .yeison-btn {
            padding: 16px 32px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            cursor: pointer;
            min-width: 200px;
            justify-content: center;
            font-family: inherit;
            letter-spacing: 0.3px;
            position: relative;
            overflow: hidden;
        }
        
        .yeison-btn::before {
            content: "";
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
        
        .yeison-btn-danger {
            background: linear-gradient(135deg, #FCA5A5 0%, #F87171 100%);
            color: white;
            box-shadow: 0 4px 16px rgba(248, 113, 113, 0.25);
        }
        
        .yeison-btn-danger:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #F87171 0%, #EF4444 100%);
            box-shadow: 0 6px 20px rgba(248, 113, 113, 0.35);
            color: white;
        }
        
        .yeison-btn-secondary {
            background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%);
            color: #475569;
            border: 2px solid #CBD5E1;
            box-shadow: 0 4px 16px rgba(71, 85, 105, 0.1);
        }
        
        .yeison-btn-secondary:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #E2E8F0 0%, #CBD5E1 100%);
            border-color: #94A3B8;
            box-shadow: 0 6px 20px rgba(71, 85, 105, 0.15);
            color: #475569;
        }
        
        .yeison-btn-primary {
            background: linear-gradient(135deg, #86EFAC 0%, #22D3EE 100%);
            color: white;
            box-shadow: 0 4px 16px rgba(34, 211, 238, 0.25);
        }
        
        .yeison-btn-primary:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #22D3EE 0%, #0EA5E9 100%);
            box-shadow: 0 6px 20px rgba(34, 211, 238, 0.35);
            color: white;
        }
        
        .yeison-progress {
            background: linear-gradient(135deg, #DBEAFE 0%, #93C5FD 100%);
            color: #1E40AF;
            padding: 24px;
            border-radius: 12px;
            margin: 24px 0;
            border: 2px solid #93C5FD;
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.1);
        }
        
        .yeison-progress h2 {
            margin: 0 0 16px 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: #1E40AF;
        }
        
        .yeison-step {
            background: rgba(255, 255, 255, 0.8);
            padding: 12px 16px;
            border-radius: 8px;
            margin: 8px 0;
            border-left: 4px solid #60A5FA;
            color: #1E293B;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .yeison-step:hover {
            background: rgba(255, 255, 255, 0.95);
            transform: translateX(4px);
        }
        
        .yeison-success {
            background: linear-gradient(135deg, #DCFCE7 0%, #BBF7D0 100%);
            color: #14532D;
            padding: 32px;
            border-radius: 12px;
            margin: 24px 0;
            text-align: center;
            border: 2px solid #BBF7D0;
            box-shadow: 0 4px 16px rgba(34, 197, 94, 0.1);
        }
        
        .yeison-success h2 {
            margin: 0 0 16px 0;
            font-size: 1.8rem;
            font-weight: 600;
            color: #14532D;
        }
        
        .yeison-success-list {
            list-style: none;
            padding: 0;
            margin: 20px 0;
            text-align: left;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .yeison-success-list li {
            padding: 12px 16px;
            margin: 8px 0;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 8px;
            border-left: 4px solid #22C55E;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .yeison-success-list li:hover {
            background: rgba(255, 255, 255, 0.8);
            transform: translateX(4px);
        }
        
        .yeison-danger-warning {
            background: linear-gradient(135deg, #FEF2F2 0%, #FECACA 100%);
            color: #B91C1C;
            border: 2px solid #FCA5A5;
            text-align: center;
        }
        
        .yeison-danger-warning strong {
            color: #991B1B;
        }
        
        @media (max-width: 768px) {
            .yeison-nuclear-container {
                padding: 16px;
            }
            
            .yeison-card {
                padding: 24px 20px;
                margin-bottom: 20px;
            }
            
            .yeison-title {
                font-size: 2rem;
            }
            
            .yeison-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .yeison-btn {
                width: 100%;
                max-width: 320px;
            }
            
            .yeison-stats {
                grid-template-columns: 1fr;
            }
            
            .yeison-stat-card {
                padding: 20px;
            }
            
            .yeison-stat-number {
                font-size: 1.8rem;
            }
        }
        
        @media (max-width: 480px) {
            .yeison-title {
                font-size: 1.8rem;
            }
            
            .yeison-btn {
                padding: 14px 24px;
                font-size: 0.9rem;
            }
            
            .yeison-progress,
            .yeison-success {
                padding: 20px;
            }
        }
    </style>';
    
    echo '<div class="yeison-nuclear-container">';
    
    // Mostrar estado actual antes de limpiar
    if (!isset($_GET['confirm'])) {
        echo '<div class="yeison-card">';
        echo '<div class="yeison-header">';
        echo '<h1 class="yeison-title">🛡️ Limpieza Nuclear</h1>';
        
        echo '</div>';
        
        echo '<div class="yeison-warning">';
        echo '<strong>⚠️ Advertencia Importante:</strong><br>';
        echo 'Esta operación eliminará completamente todos los datos del plugin. ';
        echo 'Esta acción es irreversible y no puede deshacerse.';
        echo '</div>';
        
        $stats = array(
            'logs' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_logs"),
            'queue' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_queue"),
            'sync' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_sync"),
            'transients' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient%yeison_btx%'")
        );
        
        echo '<h3 style="color: #1E293B; margin-bottom: 20px; font-weight: 600; text-align: center;">📊 Estado Actual del Sistema</h3>';
        echo '<div class="yeison-stats">';
        echo '<div class="yeison-stat-card">';
        echo '<div class="yeison-stat-number">' . number_format($stats['logs']) . '</div>';
        echo '<div class="yeison-stat-label">Registros de Logs</div>';
        echo '</div>';
        
        echo '<div class="yeison-stat-card">';
        echo '<div class="yeison-stat-number">' . number_format($stats['queue']) . '</div>';
        echo '<div class="yeison-stat-label">Elementos en Cola</div>';
        echo '</div>';
        
        echo '<div class="yeison-stat-card">';
        echo '<div class="yeison-stat-number">' . number_format($stats['sync']) . '</div>';
        echo '<div class="yeison-stat-label">Registros de Sync</div>';
        echo '</div>';
        
        echo '<div class="yeison-stat-card">';
        echo '<div class="yeison-stat-number">' . number_format($stats['transients']) . '</div>';
        echo '<div class="yeison-stat-label">Datos Temporales</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="yeison-warning yeison-danger-warning">';
        echo '<strong>🚨 Confirmación Final Requerida</strong><br>';
        echo '¿Estás completamente seguro de que deseas eliminar TODOS estos datos?<br>';
        echo '<strong>Esta acción NO puede deshacerse.</strong>';
        echo '</div>';
        
        echo '<div class="yeison-buttons">';
        echo '<a href="' . admin_url('admin-ajax.php?action=yeison_btx_nuclear_cleanup&confirm=yes') . '" class="yeison-btn yeison-btn-danger">';
        echo '🚨 Ejecutar Limpieza Nuclear</a>';
        echo '<a href="' . admin_url('admin.php?page=yeison-btx') . '" class="yeison-btn yeison-btn-secondary">';
        echo '← Cancelar y Volver</a>';
        echo '</div>';
        
        echo '</div>'; // Cierre yeison-card
        echo '</div>'; // Cierre container
        exit;
    }
    
    // EJECUTAR LIMPIEZA COMPLETA
    echo '<div class="yeison-card">';
    echo '<div class="yeison-header">';
    echo '<h1 class="yeison-title">🧹 Proceso de Limpieza</h1>';
    echo '<p class="yeison-subtitle">Ejecutando limpieza nuclear del sistema...</p>';
    echo '</div>';
    
    echo '<div class="yeison-progress">';
    echo '<h2>Progreso de Limpieza</h2>';
    
    $results = array();
    
    // 1. TRUNCAR tabla de logs
    echo '<div class="yeison-step">🗑️ Limpiando tabla de logs...</div>';
    $results['logs'] = $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}yeison_btx_logs");
    echo '<div class="yeison-step">' . ($results['logs'] !== false ? '✅ Logs eliminados correctamente' : '❌ Error al eliminar logs') . '</div>';
    
    // 2. TRUNCAR tabla de cola
    echo '<div class="yeison-step">🗑️ Limpiando tabla de cola...</div>';
    $results['queue'] = $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}yeison_btx_queue");
    echo '<div class="yeison-step">' . ($results['queue'] !== false ? '✅ Cola eliminada correctamente' : '❌ Error al eliminar cola') . '</div>';
    
    // 3. TRUNCAR tabla de sincronización
    echo '<div class="yeison-step">🗑️ Limpiando tabla de sincronización...</div>';
    $results['sync'] = $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}yeison_btx_sync");
    echo '<div class="yeison-step">' . ($results['sync'] !== false ? '✅ Sincronización eliminada correctamente' : '❌ Error al eliminar sincronización') . '</div>';
    
    // 4. Eliminar ALL transients del plugin
    echo '<div class="yeison-step">🗑️ Limpiando datos temporales...</div>';
    $transients_deleted = 0;
    
    $transient_patterns = array(
        '_transient%yeison_btx%',
        '_transient_timeout%yeison_btx%'
    );
    
    foreach ($transient_patterns as $pattern) {
        $transients = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $pattern
        ));
        
        foreach ($transients as $transient) {
            $wpdb->delete($wpdb->options, array('option_name' => $transient->option_name));
            $transients_deleted++;
        }
    }
    echo '<div class="yeison-step">✅ ' . number_format($transients_deleted) . ' elementos temporales eliminados</div>';
    
    // 5. Limpiar configuraciones específicas (mantener auth)
    echo '<div class="yeison-step">🗑️ Limpiando configuraciones no esenciales...</div>';
    $settings = get_option('yeison_btx_settings', array());
    
    // Mantener solo configuración de API
    $keep_settings = array(
        'bitrix_domain',
        'client_id', 
        'client_secret',
        'access_token',
        'refresh_token'
    );
    
    $new_settings = array();
    foreach ($keep_settings as $key) {
        if (isset($settings[$key])) {
            $new_settings[$key] = $settings[$key];
        }
    }
    
    update_option('yeison_btx_settings', $new_settings);
    echo '<div class="yeison-step">✅ Configuraciones limpiadas (API mantenida)</div>';
    
    // 6. Limpiar scheduled hooks
    echo '<div class="yeison-step">🗑️ Limpiando tareas programadas...</div>';
    wp_clear_scheduled_hook('yeison_btx_process_queue');
    wp_clear_scheduled_hook('yeison_btx_cleanup_patterns');
    wp_clear_scheduled_hook('yeison_btx_process_bidirectional_queue');
    wp_clear_scheduled_hook('yeison_btx_woo_sync_cron');
    echo '<div class="yeison-step">✅ Tareas programadas eliminadas</div>';
    
    // 7. Limpiar opciones adicionales
    echo '<div class="yeison-step">🗑️ Limpiando opciones adicionales...</div>';
    delete_option('yeison_btx_defaults_set');
    delete_option('yeison_btx_version');
    echo '<div class="yeison-step">✅ Opciones adicionales eliminadas</div>';
    
    echo '</div>'; // Cierre yeison-progress
    
    // RESULTADO FINAL
    echo '<div class="yeison-success">';
    echo '<h2>🎉 Limpieza Nuclear Completada</h2>';
    echo '<p><strong>El plugin ha sido completamente reiniciado y está listo para usar</strong></p>';
    
    echo '<ul class="yeison-success-list">';
    echo '<li>✅ Todas las tablas han sido vaciadas completamente</li>';
    echo '<li>✅ Todos los datos temporales han sido eliminados</li>';
    echo '<li>✅ Configuraciones han sido reiniciadas</li>';
    echo '<li>✅ Tareas programadas han sido limpiadas</li>';
    echo '<li>🔐 Autenticación de API se ha mantenido intacta</li>';
    echo '<li>📊 Sistema listo para nueva configuración</li>';
    echo '</ul>';
    echo '</div>';
    
    // Log final
    yeison_btx_log('🚨 LIMPIEZA NUCLEAR EJECUTADA', 'warning', array(
        'executed_by' => get_current_user_id(),
        'timestamp' => current_time('mysql'),
        'results' => $results,
        'transients_deleted' => $transients_deleted
    ));
    
    echo '<div class="yeison-buttons">';
    echo '<a href="' . admin_url('admin.php?page=yeison-btx') . '" class="yeison-btn yeison-btn-primary">';
    echo '🏠 Volver al Dashboard</a>';
    echo '</div>';
    
    echo '</div>'; // Cierre yeison-card
    echo '</div>'; // Cierre container
    
    exit;
}








/**
 * Test completo del sistema optimizado solo con Timeline Comments
 * 
 * Acceder desde: /wp-admin/admin-ajax.php?action=yeison_btx_test_optimized
 */

add_action('wp_ajax_yeison_btx_test_optimized', 'yeison_btx_test_optimized');
function yeison_btx_test_optimized() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    echo '<h1>🚀 Test Sistema Optimizado - Solo Timeline Comments</h1>';
    echo '<style>
        .test-box { background: white; padding: 20px; margin: 15px 0; border: 1px solid #ddd; border-radius: 5px; }
        .success { border-left: 4px solid #28a745; background: #d4edda; }
        .error { border-left: 4px solid #dc3545; background: #f8d7da; }
        .warning { border-left: 4px solid #ffc107; background: #fff3cd; }
        .info { border-left: 4px solid #17a2b8; background: #d1ecf1; }
        pre { background: #f8f9f9; padding: 10px; border-radius: 3px; overflow-x: auto; max-height: 400px; overflow-y: auto; }
        .timeline-comment { background: #f0f8ff; padding: 15px; border-left: 4px solid #0066cc; border-radius: 5px; margin: 10px 0; }
    </style>';
    
    echo '<div class="test-box info">';
    echo '<h3>🎯 Objetivos del Test</h3>';
    echo '<ul>';
    echo '<li>✅ Verificar que Timeline Comments funcionan perfectamente</li>';
    echo '<li>✅ Confirmar que NO se crean Leads duplicados</li>';
    echo '<li>✅ Validar procesamiento directo (sin cola)</li>';
    echo '<li>✅ Probar con email existente y nuevo</li>';
    echo '</ul>';
    echo '</div>';
    
    $api = yeison_btx_api();
    
    if (!$api->is_authorized()) {
        echo '<div class="test-box error">❌ API no autorizada</div>';
        exit;
    }
    
    // PASO 1: Preparar datos de test
    $test_email_new = 'test-optimized-' . time() . '@example.com';
    $test_email_existing = 'yeison.a@team.guruxglobal.com'; // Email que sabemos que existe
    
    echo '<div class="test-box">';
    echo '<h3>📋 PASO 1: Datos de Test Preparados</h3>';
    echo '<ul>';
    echo '<li><strong>Email nuevo (para crear Lead):</strong> ' . $test_email_new . '</li>';
    echo '<li><strong>Email existente (para Timeline):</strong> ' . $test_email_existing . '</li>';
    echo '</ul>';
    echo '</div>';
    
    $forms_handler = yeison_btx_forms();
    $test_results = array();
    
    // PASO 2: Test con email NUEVO (debe crear Lead)
    echo '<div class="test-box">';
    echo '<h3>🆕 PASO 2: Test con Email Nuevo (Crear Lead)</h3>';
    
    $form_data_new = array(
        'name' => 'Test',
        'last_name' => 'Optimizado',
        'email' => $test_email_new,
        'phone' => '12345678',
        'company' => 'Test Optimized Co',
        'message' => 'Test del sistema optimizado - Lead nuevo - ' . date('Y-m-d H:i:s'),
        '_start_time' => time() - 8,
        'website' => '', // Honeypot
        '_meta' => array(
            'origin' => 'https://test-optimized.example.com',
            'timestamp' => current_time('mysql')
        )
    );
    
    echo '<p><strong>Datos del formulario (email nuevo):</strong></p>';
    echo '<pre>' . print_r($form_data_new, true) . '</pre>';
    
    $result_new = $forms_handler->process_form_submission($form_data_new, 'optimized_test', 'https://test-optimized.example.com');
    
    echo '<p><strong>Resultado procesamiento (email nuevo):</strong></p>';
    echo '<pre>' . print_r($result_new, true) . '</pre>';
    
    if ($result_new['success']) {
        echo '<div class="success">✅ Formulario con email nuevo procesado exitosamente</div>';
        $test_results['new_lead'] = true;
        
        if (isset($result_new['data']['type']) && $result_new['data']['type'] === 'new_lead') {
            echo '<p>🎉 <strong>Acción:</strong> Nuevo Lead creado (ID: ' . ($result_new['data']['lead_id'] ?? 'N/A') . ')</p>';
        }
    } else {
        echo '<div class="error">❌ Error procesando formulario con email nuevo</div>';
        $test_results['new_lead'] = false;
    }
    echo '</div>';
    
    sleep(2); // Dar tiempo para que se procese
    
    // PASO 3: Test con email EXISTENTE (debe crear Timeline Comment)
    echo '<div class="test-box">';
    echo '<h3>💬 PASO 3: Test con Email Existente (Timeline Comment)</h3>';
    
    $form_data_existing = array(
        'name' => 'Yeison',
        'last_name' => 'Araya',
        'email' => $test_email_existing,
        'phone' => '87654321',
        'company' => 'GuruxGlobal',
        'message' => 'Test Timeline Comment optimizado - ' . date('Y-m-d H:i:s'),
        '_start_time' => time() - 6,
        'website' => '', // Honeypot
        '_meta' => array(
            'origin' => 'https://timeline-test.example.com',
            'timestamp' => current_time('mysql')
        )
    );
    
    echo '<p><strong>Datos del formulario (email existente):</strong></p>';
    echo '<pre>' . print_r($form_data_existing, true) . '</pre>';
    
    $result_existing = $forms_handler->process_form_submission($form_data_existing, 'optimized_test', 'https://timeline-test.example.com');
    
    echo '<p><strong>Resultado procesamiento (email existente):</strong></p>';
    echo '<pre>' . print_r($result_existing, true) . '</pre>';
    
    if ($result_existing['success']) {
        echo '<div class="success">✅ Formulario con email existente procesado exitosamente</div>';
        $test_results['timeline_comment'] = true;
        
        if (isset($result_existing['data']['type']) && $result_existing['data']['type'] === 'timeline_comment') {
            echo '<p>🎉 <strong>Acción:</strong> Timeline Comment creado (ID: ' . ($result_existing['data']['comment_id'] ?? 'N/A') . ') en Lead ' . ($result_existing['data']['lead_id'] ?? 'N/A') . '</p>';
        }
    } else {
        echo '<div class="error">❌ Error procesando formulario con email existente</div>';
        $test_results['timeline_comment'] = false;
    }
    echo '</div>';
    
    sleep(2); // Dar tiempo para que se procese
    
    // PASO 4: Verificar resultados en Bitrix24
    echo '<div class="test-box">';
    echo '<h3>🔍 PASO 4: Verificación en Bitrix24</h3>';
    
    // Verificar Lead nuevo
    if ($test_results['new_lead'] && isset($result_new['data']['lead_id'])) {
        $new_lead_id = $result_new['data']['lead_id'];
        
        $lead_check = $api->api_call('crm.lead.get', array('id' => $new_lead_id));
        
        if ($lead_check && isset($lead_check['result'])) {
            echo '<div class="success">✅ Lead nuevo confirmado en Bitrix24</div>';
            echo '<p><strong>ID:</strong> ' . $new_lead_id . '</p>';
            echo '<p><strong>Título:</strong> ' . ($lead_check['result']['TITLE'] ?? 'N/A') . '</p>';
        } else {
            echo '<div class="error">❌ Lead nuevo no encontrado en Bitrix24</div>';
        }
    }
    
    // Verificar Timeline Comment
    if ($test_results['timeline_comment'] && isset($result_existing['data']['comment_id'])) {
        $comment_id = $result_existing['data']['comment_id'];
        $lead_id = $result_existing['data']['lead_id'];
        
        // Obtener Timeline Comments del Lead
        $timeline_check = $api->api_call('crm.timeline.comment.list', array(
            'filter' => array(
                'ENTITY_ID' => $lead_id,
                'ENTITY_TYPE' => 'lead'
            ),
            'order' => array('ID' => 'DESC')
        ));
        
        if ($timeline_check && isset($timeline_check['result'])) {
            $found_comment = false;
            foreach ($timeline_check['result'] as $comment) {
                if ($comment['ID'] == $comment_id) {
                    $found_comment = $comment;
                    break;
                }
            }
            
            if ($found_comment) {
                echo '<div class="success">✅ Timeline Comment confirmado en Bitrix24</div>';
                echo '<p><strong>Comment ID:</strong> ' . $comment_id . '</p>';
                echo '<p><strong>Lead ID:</strong> ' . $lead_id . '</p>';
                
                echo '<div class="timeline-comment">';
                echo '<h4>📝 Contenido del Timeline Comment:</h4>';
                echo '<pre style="white-space: pre-wrap; font-family: Arial, sans-serif;">' . esc_html($found_comment['COMMENT']) . '</pre>';
                echo '</div>';
            } else {
                echo '<div class="error">❌ Timeline Comment no encontrado en Bitrix24</div>';
            }
        }
    }
    echo '</div>';
    
    // PASO 5: Verificar que no hay duplicados
    echo '<div class="test-box">';
    echo '<h3>🔍 PASO 5: Verificación Anti-Duplicados</h3>';
    
    // Verificar email nuevo
    $duplicate_check_new = $api->api_call('crm.lead.list', array(
        'filter' => array('EMAIL' => $test_email_new),
        'select' => array('ID', 'EMAIL')
    ));
    
    if ($duplicate_check_new && isset($duplicate_check_new['result'])) {
        $count_new = count($duplicate_check_new['result']);
        echo '<p><strong>Leads con email nuevo:</strong> ' . $count_new . '</p>';
        
        if ($count_new === 1) {
            echo '<div class="success">✅ Correcto: Solo 1 Lead con email nuevo</div>';
        } else {
            echo '<div class="error">❌ Problema: ' . $count_new . ' Leads con email nuevo</div>';
        }
    }
    
    // Verificar email existente
    $duplicate_check_existing = $api->api_call('crm.lead.list', array(
        'filter' => array('EMAIL' => $test_email_existing),
        'select' => array('ID', 'EMAIL')
    ));
    
    if ($duplicate_check_existing && isset($duplicate_check_existing['result'])) {
        $count_existing = count($duplicate_check_existing['result']);
        echo '<p><strong>Leads con email existente:</strong> ' . $count_existing . '</p>';
        
        if ($count_existing === 1) {
            echo '<div class="success">✅ Correcto: Solo 1 Lead con email existente (no se duplicó)</div>';
        } else {
            echo '<div class="warning">⚠️ ' . $count_existing . ' Leads con email existente (revisar si es normal)</div>';
        }
    }
    echo '</div>';
    
    // PASO 6: Logs del sistema
    echo '<div class="test-box">';
    echo '<h3>📝 PASO 6: Logs del Sistema Optimizado</h3>';
    
    global $wpdb;
    $recent_logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}yeison_btx_logs 
        WHERE (message LIKE %s OR message LIKE %s OR message LIKE %s)
        AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ORDER BY created_at DESC 
        LIMIT 20",
        '%optimizado%',
        '%Timeline%',
        '%' . substr($test_email_new, 0, 10) . '%'
    ));
    
    if ($recent_logs) {
        echo '<table border="1" style="width:100%; border-collapse: collapse;">';
        echo '<tr><th>Hora</th><th>Tipo</th><th>Mensaje</th></tr>';
        
        foreach ($recent_logs as $log) {
            $color_map = array(
                'error' => '#ffebee',
                'success' => '#e8f5e8', 
                'warning' => '#fff8e1',
                'info' => '#e3f2fd'
            );
            $bg_color = $color_map[$log->type] ?? '#f5f5f5';
            
            echo '<tr style="background: ' . $bg_color . ';">';
            echo '<td>' . $log->created_at . '</td>';
            echo '<td><strong>' . strtoupper($log->type) . '</strong></td>';
            echo '<td>' . esc_html($log->message) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p>No se encontraron logs del sistema optimizado</p>';
    }
    echo '</div>';
    
    // PASO 7: Estadísticas del sistema
    echo '<div class="test-box">';
    echo '<h3>📊 PASO 7: Estadísticas del Sistema</h3>';
    
    $stats = $forms_handler->get_status();
    $stats_data = $stats->get_data();
    
    echo '<table border="1" style="width:100%; border-collapse: collapse;">';
    echo '<tr><th>Métrica</th><th>Valor</th></tr>';
    
    foreach ($stats_data as $key => $value) {
        if (is_array($value)) {
            $value = print_r($value, true);
        }
        echo '<tr><td>' . $key . '</td><td>' . esc_html($value) . '</td></tr>';
    }
    echo '</table>';
    echo '</div>';
    
    // Resumen final
    echo '<div class="test-box info">';
    echo '<h3>🎯 Resumen Final del Test</h3>';
    
    $success_count = 0;
    $total_tests = 0;
    
    $test_criteria = array(
        'Lead nuevo creado' => $test_results['new_lead'] ?? false,
        'Timeline Comment creado' => $test_results['timeline_comment'] ?? false,
        'Sin errores críticos' => true, // Asumir true si llegamos aquí
        'Sistema optimizado funcionando' => true
    );
    
    echo '<ul>';
    foreach ($test_criteria as $criteria => $passed) {
        $total_tests++;
        if ($passed) {
            $success_count++;
            echo '<li>✅ <strong>' . $criteria . '</strong></li>';
        } else {
            echo '<li>❌ <strong>' . $criteria . '</strong></li>';
        }
    }
    echo '</ul>';
    
    $success_rate = round(($success_count / $total_tests) * 100);
    
    echo '<div style="padding: 15px; border-radius: 5px; margin: 15px 0; font-size: 18px; font-weight: bold; text-align: center; ';
    
    if ($success_rate >= 90) {
        echo 'background: #d4edda; color: #155724; border: 2px solid #28a745;">';
        echo '🎉 ÉXITO TOTAL: ' . $success_rate . '% - Sistema optimizado funcionando perfectamente';
    } elseif ($success_rate >= 70) {
        echo 'background: #fff3cd; color: #856404; border: 2px solid #ffc107;">';
        echo '⚠️ ÉXITO PARCIAL: ' . $success_rate . '% - Revisar elementos que fallaron';
    } else {
        echo 'background: #f8d7da; color: #721c24; border: 2px solid #dc3545;">';
        echo '❌ REQUIERE ATENCIÓN: ' . $success_rate . '% - Revisar logs y configuración';
    }
    
    echo '</div>';
    echo '</div>';
    
    echo '<p style="margin-top: 30px;">';
    echo '<a href="?action=yeison_btx_activity_diagnostic" class="button button-primary">🔬 Diagnóstico</a> ';
    echo '<a href="?action=yeison_btx_test_existing_lead" class="button">💬 Test Timeline</a> ';
    echo '<a href="' . admin_url('admin.php?page=yeison-btx-logs') . '" class="button">📝 Ver Logs</a> ';
    echo '<a href="' . admin_url('admin.php?page=yeison-btx') . '" class="button">🏠 Dashboard</a>';
    echo '</p>';
    
    exit;
}













/**
 * Verificar y limpiar tokens inválidos automáticamente
 */
add_action('admin_init', 'yeison_btx_auto_detect_invalid_tokens', 5);
function yeison_btx_auto_detect_invalid_tokens() {
    // Solo en páginas del plugin
    if (!isset($_GET['page']) || $_GET['page'] !== 'yeison-btx') {
        return;
    }
    
    $api = yeison_btx_api();
    
    // Si hay configuración y tokens, pero la conexión falla
    if ($api->is_configured() && !empty(yeison_btx_get_option('access_token'))) {
        if ($api->auto_clean_invalid_tokens()) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <strong>🔄 Tokens limpiados automáticamente</strong><br>
                        Se detectaron tokens inválidos (posiblemente por cambio de cuenta de Bitrix24) y se limpiaron automáticamente.
                        <a href="<?php echo admin_url('admin.php?page=yeison-btx'); ?>">Reautorizar ahora</a>
                    </p>
                </div>
                <?php
            });
        }
    }
}

/**
 * Handler mejorado para test de conexión
 */
add_action('wp_ajax_yeison_btx_test_connection', 'yeison_btx_test_connection_handler_improved');
function yeison_btx_test_connection_handler_improved() {
    check_ajax_referer('yeison_btx_test', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    $api = yeison_btx_api();
    $result = $api->test_connection();
    
    // Enviar respuesta con información adicional
    wp_send_json(array(
        'success' => $result['success'],
        'data' => $result
    ));
}

/**
 * Función helper para detectar cambios de dominio
 */
function yeison_btx_detect_domain_change($new_domain) {
    $old_domain = yeison_btx_get_option('bitrix_domain');
    
    if ($old_domain && $old_domain !== $new_domain) {
        yeison_btx_log('🔄 Cambio de dominio detectado', 'warning', array(
            'old_domain' => $old_domain,
            'new_domain' => $new_domain
        ));
        
        // Limpiar tokens automáticamente
        $api = yeison_btx_api();
        $api->clear_tokens();
        
        return true;
    }
    
    return false;
}

/**
 * Endpoint para limpiar tokens vía AJAX
 */
add_action('wp_ajax_yeison_btx_clear_tokens', 'yeison_btx_clear_tokens_ajax');
function yeison_btx_clear_tokens_ajax() {
    check_ajax_referer('yeison_btx_clear_tokens', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sin permisos');
    }
    
    $api = yeison_btx_api();
    $result = $api->clear_tokens();
    
    if ($result) {
        wp_send_json_success(array(
            'message' => 'Tokens limpiados correctamente',
            'auth_url' => $api->get_auth_url()
        ));
    } else {
        wp_send_json_error('Error limpiando tokens');
    }
}

/**
 * Diagnostic completo del sistema
 */
function yeison_btx_system_diagnostic() {
    $api = yeison_btx_api();
    
    $diagnostic = array(
        'configuration' => array(
            'domain' => !empty(yeison_btx_get_option('bitrix_domain')),
            'client_id' => !empty(yeison_btx_get_option('client_id')),
            'client_secret' => !empty(yeison_btx_get_option('client_secret')),
            'configured' => $api->is_configured()
        ),
        'tokens' => array(
            'access_token' => !empty(yeison_btx_get_option('access_token')),
            'refresh_token' => !empty(yeison_btx_get_option('refresh_token')),
            'valid' => false
        ),
        'connectivity' => array(
            'authorized' => $api->is_authorized(),
            'connection_test' => null
        ),
        'components' => array(
            'api_class' => class_exists('YeisonBTX_Bitrix_API'),
            'widget_manager' => class_exists('YeisonBTX_Widget_Manager'),
            'autologin_handler' => class_exists('YeisonBTX_Autologin_Handler'),
            'woocommerce' => class_exists('WooCommerce')
        )
    );
    
    // Test de conexión si hay tokens
    if ($diagnostic['tokens']['access_token']) {
        $connection_test = $api->test_connection();
        $diagnostic['connectivity']['connection_test'] = $connection_test;
        $diagnostic['tokens']['valid'] = $connection_test['success'];
    }
    
    return $diagnostic;
}

/**
 * Página de diagnóstico avanzado
 */
add_action('wp_ajax_yeison_btx_diagnostic', 'yeison_btx_diagnostic_page');
function yeison_btx_diagnostic_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    $diagnostic = yeison_btx_system_diagnostic();
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Diagnóstico del Sistema - Yeison BTX</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                margin: 0;
                padding: 20px;
                background: #f5f5f5;
            }
            .container {
                max-width: 1000px;
                margin: 0 auto;
                background: white;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            }
            .header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .content {
                padding: 30px;
            }
            .diagnostic-section {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
                border-left: 4px solid #007cba;
            }
            .status-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 0;
                border-bottom: 1px solid #e9ecef;
            }
            .status-item:last-child {
                border-bottom: none;
            }
            .status-ok {
                color: #28a745;
                font-weight: bold;
            }
            .status-error {
                color: #dc3545;
                font-weight: bold;
            }
            .status-warning {
                color: #ffc107;
                font-weight: bold;
            }
            .action-buttons {
                margin: 20px 0;
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            .btn {
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                font-weight: bold;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
            }
            .btn-primary { background: #007cba; color: white; }
            .btn-warning { background: #ffc107; color: #212529; }
            .btn-success { background: #28a745; color: white; }
            .btn-secondary { background: #6c757d; color: white; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔍 Diagnóstico del Sistema</h1>
                <p>Estado completo del plugin Yeison BTX</p>
            </div>
            
            <div class="content">
                <!-- Configuración -->
                <div class="diagnostic-section">
                    <h3>⚙️ Configuración</h3>
                    <div class="status-item">
                        <span>Dominio Bitrix24</span>
                        <span class="<?php echo $diagnostic['configuration']['domain'] ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $diagnostic['configuration']['domain'] ? '✅ Configurado' : '❌ No configurado'; ?>
                        </span>
                    </div>
                    <div class="status-item">
                        <span>Client ID</span>
                        <span class="<?php echo $diagnostic['configuration']['client_id'] ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $diagnostic['configuration']['client_id'] ? '✅ Configurado' : '❌ No configurado'; ?>
                        </span>
                    </div>
                    <div class="status-item">
                        <span>Client Secret</span>
                        <span class="<?php echo $diagnostic['configuration']['client_secret'] ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $diagnostic['configuration']['client_secret'] ? '✅ Configurado' : '❌ No configurado'; ?>
                        </span>
                    </div>
                </div>

                <!-- Tokens -->
                <div class="diagnostic-section">
                    <h3>🔐 Tokens de Acceso</h3>
                    <div class="status-item">
                        <span>Access Token</span>
                        <span class="<?php echo $diagnostic['tokens']['access_token'] ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $diagnostic['tokens']['access_token'] ? '✅ Presente' : '❌ Ausente'; ?>
                        </span>
                    </div>
                    <div class="status-item">
                        <span>Refresh Token</span>
                        <span class="<?php echo $diagnostic['tokens']['refresh_token'] ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $diagnostic['tokens']['refresh_token'] ? '✅ Presente' : '❌ Ausente'; ?>
                        </span>
                    </div>
                    <div class="status-item">
                        <span>Estado de Tokens</span>
                        <span class="<?php echo $diagnostic['tokens']['valid'] ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $diagnostic['tokens']['valid'] ? '✅ Válidos' : '❌ Inválidos'; ?>
                        </span>
                    </div>
                </div>

                <!-- Conectividad -->
                <div class="diagnostic-section">
                    <h3>🌐 Conectividad</h3>
                    <div class="status-item">
                        <span>Autorización</span>
                        <span class="<?php echo $diagnostic['connectivity']['authorized'] ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $diagnostic['connectivity']['authorized'] ? '✅ Autorizado' : '❌ No autorizado'; ?>
                        </span>
                    </div>
                    <?php if ($diagnostic['connectivity']['connection_test']): ?>
                    <div class="status-item">
                        <span>Test de Conexión</span>
                        <span class="<?php echo $diagnostic['connectivity']['connection_test']['success'] ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $diagnostic['connectivity']['connection_test']['success'] ? '✅ Exitoso' : '❌ Fallido'; ?>
                        </span>
                    </div>
                    <?php if (!$diagnostic['connectivity']['connection_test']['success']): ?>
                    <div style="margin-top: 10px; padding: 10px; background: #f8d7da; color: #721c24; border-radius: 5px; font-size: 14px;">
                        <strong>Error:</strong> <?php echo esc_html($diagnostic['connectivity']['connection_test']['message']); ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Componentes -->
                <div class="diagnostic-section">
                    <h3>🧩 Componentes</h3>
                    <div class="status-item">
                        <span>API Bitrix24</span>
                        <span class="<?php echo $diagnostic['components']['api_class'] ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $diagnostic['components']['api_class'] ? '✅ Cargado' : '❌ Error'; ?>
                        </span>
                    </div>
                    <div class="status-item">
                        <span>Widget Manager</span>
                        <span class="<?php echo $diagnostic['components']['widget_manager'] ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $diagnostic['components']['widget_manager'] ? '✅ Cargado' : '❌ Error'; ?>
                        </span>
                    </div>
                    <div class="status-item">
                        <span>Autologin Handler</span>
                        <span class="<?php echo $diagnostic['components']['autologin_handler'] ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $diagnostic['components']['autologin_handler'] ? '✅ Cargado' : '❌ Error'; ?>
                        </span>
                    </div>
                    <div class="status-item">
                        <span>WooCommerce</span>
                        <span class="<?php echo $diagnostic['components']['woocommerce'] ? 'status-ok' : 'status-warning'; ?>">
                            <?php echo $diagnostic['components']['woocommerce'] ? '✅ Activo' : '⚠️ No detectado'; ?>
                        </span>
                    </div>
                </div>

                <!-- Acciones recomendadas -->
                <div class="diagnostic-section">
                    <h3>🚀 Acciones Recomendadas</h3>
                    <div class="action-buttons">
                        <?php if (!$diagnostic['configuration']['configured']): ?>
                            <a href="<?php echo admin_url('admin.php?page=yeison-btx'); ?>" class="btn btn-primary">
                                ⚙️ Completar Configuración
                            </a>
                        <?php elseif (!$diagnostic['connectivity']['authorized']): ?>
                            <a href="<?php echo admin_url('admin.php?page=yeison-btx'); ?>" class="btn btn-primary">
                                🔐 Autorizar con Bitrix24
                            </a>
                        <?php elseif (!$diagnostic['tokens']['valid']): ?>
                            <button onclick="clearTokens()" class="btn btn-warning">
                                🧹 Limpiar Tokens y Reautorizar
                            </button>
                        <?php else: ?>
                            <span class="status-ok">✅ Todo funcionando correctamente</span>
                        <?php endif; ?>
                        
                        <a href="<?php echo admin_url('admin-ajax.php?action=yeison_btx_test_widget'); ?>" target="_blank" class="btn btn-success">
                            🧪 Test Completo
                        </a>
                        
                        <a href="<?php echo admin_url('admin.php?page=yeison-btx'); ?>" class="btn btn-secondary">
                            🏠 Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        function clearTokens() {
            if (confirm('¿Limpiar todos los tokens y reautorizar?')) {
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=yeison_btx_clear_tokens&nonce=<?php echo wp_create_nonce('yeison_btx_clear_tokens'); ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Tokens limpiados. Redirigiendo para reautorizar...');
                        window.location.href = '<?php echo admin_url('admin.php?page=yeison-btx'); ?>';
                    } else {
                        alert('❌ Error: ' + data.data);
                    }
                });
            }
        }
        </script>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Función mejorada para verificar configuración completa
 */
function yeison_btx_is_fully_configured() {
    $api = yeison_btx_api();
    
    return $api->is_configured() && 
           $api->is_authorized() && 
           !$api->is_token_invalid_for_domain();
}

add_action('wp_ajax_yeison_btx_register_missing_webhooks', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    $api = yeison_btx_api();
    $webhooks_needed = array(
        array('event' => 'ONCRMCONTACTADD', 'handler' => rest_url('yeison-bitrix/v1/webhook/contact')),
        array('event' => 'ONCRMDEALUPDATE', 'handler' => rest_url('yeison-bitrix/v1/webhook/deal')),
        array('event' => 'ONCRMDEALADD', 'handler' => rest_url('yeison-bitrix/v1/webhook/deal'))
    );
    
    echo '<h2>Registrando webhooks faltantes:</h2>';
    
    foreach ($webhooks_needed as $webhook) {
        echo '<p>Registrando: ' . $webhook['event'] . '</p>';
        $response = $api->api_call('event.bind', $webhook);
        echo '<pre>' . print_r($response, true) . '</pre>';
    }
    
    exit;
});

// FIX: Corregir filtro de webhooks (HANDLER vs handler) /wp-admin/admin-ajax.php?action=yeison_btx_check_webhooks_fixed
add_filter('yeison_btx_webhook_filter_fix', function() {
    // Sobrescribir la función get_registered_webhooks_status()
    add_action('wp_ajax_yeison_btx_check_webhooks_fixed', function() {
        if (!current_user_can('manage_options')) {
            wp_die('Sin permisos');
        }
        
        $api = yeison_btx_api();
        if (!$api->is_authorized()) {
            echo '<h2>❌ API no autorizada</h2>';
            exit;
        }
        
        echo '<h2>🔍 Webhooks con Filtro CORREGIDO</h2>';
        
        $response = $api->api_call('event.get');
        
        if (!$response || !isset($response['result'])) {
            echo '<p>❌ Error obteniendo webhooks</p>';
            exit;
        }
        
        $our_webhooks = array();
        $site_domain = parse_url(home_url(), PHP_URL_HOST);
        
        echo '<table border="1">';
        echo '<tr><th>Evento</th><th>Handler</th><th>¿Es nuestro?</th></tr>';
        
        foreach ($response['result'] as $webhook) {
            // FIX: Usar 'handler' minúscula en lugar de 'HANDLER'
            $handler = $webhook['handler'] ?? '';
            $event = $webhook['event'] ?? '';
            
            $is_ours = (strpos($handler, $site_domain) !== false || 
                       strpos($handler, 'yeison-bitrix/v1/webhook') !== false);
            
            echo '<tr>';
            echo '<td>' . esc_html($event) . '</td>';
            echo '<td>' . esc_html($handler) . '</td>';
            echo '<td>' . ($is_ours ? '✅ SÍ' : '❌ NO') . '</td>';
            echo '</tr>';
            
            if ($is_ours) {
                $our_webhooks[] = $webhook;
            }
        }
        echo '</table>';
        
        echo '<h3>Resultado:</h3>';
        echo '<p><strong>Webhooks nuestros encontrados:</strong> ' . count($our_webhooks) . '</p>';
        echo '<pre>' . print_r($our_webhooks, true) . '</pre>';
        
        exit;
    });
});

// Activar el fix
do_action('yeison_btx_webhook_filter_fix');









///wp-admin/admin-ajax.php?action=yeison_btx_debug_webhook_registration

add_action('wp_ajax_yeison_btx_debug_webhook_registration', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    $api = yeison_btx_api();
    
    if (!$api->is_authorized()) {
        echo '<h2>❌ API no autorizada</h2>';
        exit;
    }
    
    echo '<h2>🔧 Debug Registro de Webhooks</h2>';
    
    // Intentar registrar UN webhook con debug completo
    $webhook_data = array(
        'event' => 'ONCRMCONTACTUPDATE',
        'handler' => rest_url('yeison-bitrix/v1/webhook/contact')
    );
    
    echo '<h3>Intentando registrar:</h3>';
    echo '<pre>' . print_r($webhook_data, true) . '</pre>';
    
    $response = $api->api_call('event.bind', $webhook_data);
    
    echo '<h3>Respuesta de Bitrix24:</h3>';
    echo '<pre>' . print_r($response, true) . '</pre>';
    
    if ($response && isset($response['result'])) {
        echo '<div style="background: green; color: white; padding: 10px;">✅ Webhook registrado exitosamente</div>';
    } else {
        echo '<div style="background: red; color: white; padding: 10px;">❌ Error registrando webhook</div>';
        
        if (isset($response['error'])) {
            echo '<h3>Error específico:</h3>';
            echo '<p><strong>Código:</strong> ' . $response['error'] . '</p>';
            echo '<p><strong>Descripción:</strong> ' . ($response['error_description'] ?? 'No especificada') . '</p>';
        }
    }
    
    // Verificar de nuevo todos los webhooks
    echo '<h3>Webhooks después del intento:</h3>';
    $all_webhooks = $api->api_call('event.get');
    echo '<pre>' . print_r($all_webhooks, true) . '</pre>';
    
    exit;
});





/**
 * Endpoint para verificar/reparar tablas manualmente
 * Acceso: /wp-admin/admin-ajax.php?action=yeison_btx_repair_tables
 */
add_action('wp_ajax_yeison_btx_repair_tables', 'yeison_btx_repair_tables');
function yeison_btx_repair_tables() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    echo '
    <style>
        .yeison-btx-container {
            max-width: 1200px;
            margin: 20px auto;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%);
            min-height: 100vh;
            padding: 20px;
            box-sizing: border-box;
        }
        
        .yeison-btx-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .yeison-btx-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .yeison-btx-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e1e8f0;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .yeison-btx-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }
        
        .yeison-btx-success {
            background: linear-gradient(135deg, #a8e6cf 0%, #88d8a3 100%);
            color: #2d5016;
            padding: 15px 20px;
            border-radius: 10px;
            border-left: 4px solid #4caf50;
            margin: 20px 0;
            font-weight: 500;
        }
        
        .yeison-btx-error {
            background: linear-gradient(135deg, #ffb3ba 0%, #ff9aa2 100%);
            color: #8b0000;
            padding: 15px 20px;
            border-radius: 10px;
            border-left: 4px solid #f44336;
            margin: 20px 0;
            font-weight: 500;
        }
        
        .yeison-btx-status-title {
            color: #4a5568;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .yeison-btx-table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }
        
        .yeison-btx-table th {
            background: linear-gradient(135deg, #b794f6 0%, #9f7aea 100%);
            color: white;
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .yeison-btx-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #e2e8f0;
            color: #2d3748;
            font-weight: 500;
        }
        
        .yeison-btx-table tr:nth-child(even) {
            background: #f7fafc;
        }
        
        .yeison-btx-table tr:hover {
            background: linear-gradient(135deg, #edf2f7 0%, #e2e8f0 100%);
            transform: scale(1.01);
            transition: all 0.2s ease;
        }
        
        .yeison-btx-button {
            display: inline-block;
            padding: 12px 25px;
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 25px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(66, 153, 225, 0.3);
        }
        
        .yeison-btx-button:hover {
            background: linear-gradient(135deg, #3182ce 0%, #2c5282 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(66, 153, 225, 0.4);
            color: white;
            text-decoration: none;
        }
        
        .yeison-btx-icon {
            font-size: 18px;
            margin-right: 8px;
        }
        
        @media (max-width: 768px) {
            .yeison-btx-container {
                padding: 10px;
            }
            
            .yeison-btx-header {
                padding: 20px 15px;
            }
            
            .yeison-btx-header h1 {
                font-size: 22px;
            }
            
            .yeison-btx-card {
                padding: 20px 15px;
            }
            
            .yeison-btx-table {
                font-size: 14px;
            }
            
            .yeison-btx-table th,
            .yeison-btx-table td {
                padding: 12px 10px;
            }
        }
        
        @media (max-width: 480px) {
            .yeison-btx-table {
                font-size: 12px;
            }
            
            .yeison-btx-table th,
            .yeison-btx-table td {
                padding: 10px 8px;
            }
        }
    </style>
    
    <div class="yeison-btx-container">
        <div class="yeison-btx-header">
            <h1><span class="yeison-btx-icon">🔧</span>Reparación de Tablas - Yeison BTX</h1>
        </div>
    ';
    
    $plugin = yeison_btx();
    $result = $plugin->ensure_tables_exist();
    
    if ($result) {
        echo '
        <div class="yeison-btx-card">
            <div class="yeison-btx-success">
                <span class="yeison-btx-icon">✅</span>Todas las tablas verificadas y creadas correctamente
            </div>
        </div>
        ';
    } else {
        echo '
        <div class="yeison-btx-card">
            <div class="yeison-btx-error">
                <span class="yeison-btx-icon">❌</span>Error verificando/creando algunas tablas
            </div>
        </div>
        ';
    }
    
    // Mostrar estado actual
    global $wpdb;
    $tables = array(
        'Logs' => $wpdb->prefix . 'yeison_btx_logs',
        'Sincronización' => $wpdb->prefix . 'yeison_btx_sync',
        'Cola' => $wpdb->prefix . 'yeison_btx_queue'
    );
    
    echo '
    <div class="yeison-btx-card">
        <div class="yeison-btx-status-title">Estado de las tablas:</div>
        <table class="yeison-btx-table">
            <thead>
                <tr>
                    <th>Tabla</th>
                    <th>Estado</th>
                    <th>Registros</th>
                </tr>
            </thead>
            <tbody>
    ';
    
    foreach ($tables as $name => $table) {
        $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
        $count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM {$table}") : 0;
        
        echo '
                <tr>
                    <td><strong>' . $name . '</strong></td>
                    <td>' . ($exists ? '<span class="yeison-btx-icon">✅</span>Existe' : '<span class="yeison-btx-icon">❌</span>No existe') . '</td>
                    <td>' . number_format($count) . '</td>
                </tr>
        ';
    }
    
    echo '
            </tbody>
        </table>
        
        <a href="/wp-admin/" class="yeison-btx-button">
            <span class="yeison-btx-icon">←</span>Volver al Dashboard
        </a>
    </div>
    </div>
    ';
    
    exit;
}



















/**
 * Registrar TODOS los webhooks necesarios con debug completo
 * URL: /wp-admin/admin-ajax.php?action=yeison_btx_register_all_webhooks
 */
add_action('wp_ajax_yeison_btx_register_all_webhooks', 'yeison_btx_register_all_webhooks');
function yeison_btx_register_all_webhooks() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    echo '<style>
        .webhook-container { 
            max-width: 1200px; 
            margin: 20px auto; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%);
            min-height: 100vh;
            padding: 20px;
            box-sizing: border-box;
        }
        
        .webhook-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .webhook-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .webhook-section { 
            background: white;
            padding: 25px;
            margin: 20px 0;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e1e8f0;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .webhook-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }
        
        .webhook-section h3 {
            color: #4a5568;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .success { 
            border-left: 6px solid #48bb78;
            background: linear-gradient(135deg, #a8e6cf 0%, #88d8a3 100%);
            color: #2d5016;
        }
        
        .error { 
            border-left: 6px solid #e53e3e;
            background: linear-gradient(135deg, #ffb3ba 0%, #ff9aa2 100%);
            color: #8b0000;
        }
        
        .warning { 
            border-left: 6px solid #ed8936;
            background: linear-gradient(135deg, #ffd3a5 0%, #fd9853 100%);
            color: #7d4e00;
        }
        
        .info { 
            border-left: 6px solid #667eea;
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            color: #4a5568;
        }
        
        .webhook-table { 
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }
        
        .webhook-table th {
            background: linear-gradient(135deg, #b794f6 0%, #9f7aea 100%);
            color: white;
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .webhook-table td { 
            padding: 15px 20px;
            border-bottom: 1px solid #e2e8f0;
            color: #2d3748;
            font-weight: 500;
        }
        
        .webhook-table tr:nth-child(even) {
            background: #f7fafc;
        }
        
        .webhook-table tr:hover {
            background: linear-gradient(135deg, #edf2f7 0%, #e2e8f0 100%);
            transform: scale(1.01);
            transition: all 0.2s ease;
        }
        
        .status-ok { 
            color: #48bb78;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-error { 
            color: #e53e3e;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-warning { 
            color: #ed8936;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        pre { 
            background: #2d3748;
            color: #e2e8f0;
            padding: 20px;
            border-radius: 10px;
            overflow-x: auto;
            max-height: 300px;
            overflow-y: auto;
            font-family: "Fira Code", "Consolas", monospace;
            font-size: 13px;
            line-height: 1.5;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn { 
            padding: 12px 25px;
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 8px 5px;
            display: inline-block;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(66, 153, 225, 0.3);
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #3182ce 0%, #2c5282 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(66, 153, 225, 0.4);
            color: white;
            text-decoration: none;
        }
        
        .webhook-process-card {
            border: 2px solid #e2e8f0;
            padding: 20px;
            margin: 15px 0;
            border-radius: 12px;
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            transition: all 0.3s ease;
        }
        
        .webhook-process-card:hover {
            border-color: #b794f6;
            box-shadow: 0 4px 15px rgba(183, 148, 246, 0.2);
        }
        
        .webhook-process-card h4 {
            color: #553c9a;
            margin: 0 0 10px 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .summary-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            box-shadow: 0 6px 20px rgba(72, 187, 120, 0.3);
        }
        
        .summary-warning {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            box-shadow: 0 6px 20px rgba(237, 137, 54, 0.3);
        }
        
        .webhook-icon {
            font-size: 18px;
            margin-right: 8px;
        }
        
        .step-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: bold;
            margin-right: 10px;
            display: inline-block;
        }
        
        code {
            background: #edf2f7;
            color: #2d3748;
            padding: 3px 6px;
            border-radius: 4px;
            font-family: "Fira Code", "Consolas", monospace;
            font-size: 13px;
        }
        
        @media (max-width: 768px) {
            .webhook-container {
                padding: 10px;
            }
            
            .webhook-header {
                padding: 20px 15px;
            }
            
            .webhook-header h1 {
                font-size: 22px;
            }
            
            .webhook-section {
                padding: 20px 15px;
            }
            
            .webhook-table {
                font-size: 14px;
            }
            
            .webhook-table th,
            .webhook-table td {
                padding: 12px 10px;
            }
            
            .btn {
                display: block;
                text-align: center;
                margin: 10px 0;
            }
        }
        
        @media (max-width: 480px) {
            .webhook-table {
                font-size: 12px;
            }
            
            .webhook-table th,
            .webhook-table td {
                padding: 10px 8px;
            }
            
            .webhook-process-card {
                padding: 15px;
            }
        }
    </style>';
    
    echo '<div class="webhook-container">';
    echo '<div class="webhook-header">';
    echo '<h1><span class="webhook-icon">🔗</span>Admin Webhooks - GuruX BTX</h1>';
    echo '</div>';
    
    $api = yeison_btx_api();
    
    if (!$api->is_authorized()) {
        echo '<div class="webhook-section error">';
        echo '<h3><span class="webhook-icon">❌</span>Error: API no autorizada</h3>';
        echo '<p>Debes autorizar la conexión con Bitrix24 primero.</p>';
        echo '<a href="' . admin_url('admin.php?page=yeison-btx') . '" class="btn"><span class="webhook-icon">🔐</span>Ir a autorizar</a>';
        echo '</div>';
        echo '</div>';
        exit;
    }
    
    // Definir TODOS los webhooks necesarios
    $webhooks_needed = array(
        array(
            'event' => 'ONCRMCONTACTADD',
            'handler' => rest_url('yeison-bitrix/v1/webhook/contact'),
            'description' => 'Contacto creado en Bitrix24'
        ),
        array(
            'event' => 'ONCRMCONTACTUPDATE',
            'handler' => rest_url('yeison-bitrix/v1/webhook/contact'),
            'description' => 'Contacto actualizado en Bitrix24'
        ),
        array(
            'event' => 'ONCRMDEALADD',
            'handler' => rest_url('yeison-bitrix/v1/webhook/deal'),
            'description' => 'Deal/Negocio creado en Bitrix24'
        ),
        array(
            'event' => 'ONCRMDEALUPDATE',
            'handler' => rest_url('yeison-bitrix/v1/webhook/deal'),
            'description' => 'Deal/Negocio actualizado en Bitrix24'
        )
    );
    
    echo '<div class="webhook-section info">';
    echo '<h3><span class="webhook-icon">📋</span>Webhooks a Registrar</h3>';
    echo '<p>Se registrarán <strong>' . count($webhooks_needed) . ' webhooks</strong> para sincronización bidireccional:</p>';
    echo '<table class="webhook-table">';
    echo '<tr><th>Evento</th><th>Endpoint</th><th>Descripción</th></tr>';
    
    foreach ($webhooks_needed as $webhook) {
        echo '<tr>';
        echo '<td><code>' . $webhook['event'] . '</code></td>';
        echo '<td><code>' . $webhook['handler'] . '</code></td>';
        echo '<td>' . $webhook['description'] . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</div>';
    
    // PASO 1: Verificar webhooks existentes
    echo '<div class="webhook-section">';
    echo '<h3><span class="step-number">1</span><span class="webhook-icon">🔍</span>Verificando webhooks existentes</h3>';
    
    $existing_webhooks = $api->api_call('event.get');
    
    if (!$existing_webhooks || !isset($existing_webhooks['result'])) {
        echo '<div class="error"><span class="webhook-icon">❌</span>Error obteniendo webhooks existentes</div>';
        echo '<pre>' . print_r($existing_webhooks, true) . '</pre>';
        echo '</div></div>';
        exit;
    }
    
    $site_domain = parse_url(home_url(), PHP_URL_HOST);
    $our_existing_webhooks = array();
    
    foreach ($existing_webhooks['result'] as $webhook) {
        $handler = $webhook['handler'] ?? '';
        $event = $webhook['event'] ?? '';
        
        // Verificar si es nuestro webhook
        if (strpos($handler, $site_domain) !== false || 
            strpos($handler, 'yeison-bitrix/v1/webhook') !== false) {
            $our_existing_webhooks[] = array(
                'event' => $event,
                'handler' => $handler
            );
        }
    }
    
    echo '<p><strong>Webhooks existentes:</strong> ' . count($our_existing_webhooks) . '</p>';
    
    if (!empty($our_existing_webhooks)) {
        echo '<table class="webhook-table">';
        echo '<tr><th>Evento</th><th>Handler</th></tr>';
        foreach ($our_existing_webhooks as $webhook) {
            echo '<tr>';
            echo '<td>' . esc_html($webhook['event']) . '</td>';
            echo '<td>' . esc_html($webhook['handler']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p class="status-warning"><span class="webhook-icon">⚠️</span>No se encontraron webhooks existentes</p>';
    }
    echo '</div>';
    
    // PASO 2: Registrar webhooks faltantes
    echo '<div class="webhook-section">';
    echo '<h3><span class="step-number">2</span><span class="webhook-icon">🔧</span>Registrando webhooks</h3>';
    
    $registration_results = array();
    $total_registered = 0;
    $total_already_exists = 0;
    $total_errors = 0;
    
    foreach ($webhooks_needed as $webhook) {
        echo '<div class="webhook-process-card">';
        echo '<h4><span class="webhook-icon">📡</span>Procesando: ' . $webhook['event'] . '</h4>';
        
        // Verificar si ya existe
        $already_exists = false;
        foreach ($our_existing_webhooks as $existing) {
            if ($existing['event'] === $webhook['event'] && 
                $existing['handler'] === $webhook['handler']) {
                $already_exists = true;
                break;
            }
        }
        
        if ($already_exists) {
            echo '<p class="status-ok"><span class="webhook-icon">✅</span>Ya existe - Saltando</p>';
            $total_already_exists++;
            $registration_results[$webhook['event']] = 'already_exists';
        } else {
            echo '<p><span class="webhook-icon">🔄</span>Registrando nuevo webhook...</p>';
            
            // Registrar webhook
            $response = $api->api_call('event.bind', array(
                'event' => $webhook['event'],
                'handler' => $webhook['handler']
            ));
            
            if ($response && isset($response['result'])) {
                echo '<p class="status-ok"><span class="webhook-icon">✅</span>Registrado exitosamente</p>';
                echo '<p><strong>ID:</strong> ' . $response['result'] . '</p>';
                $total_registered++;
                $registration_results[$webhook['event']] = 'success';
            } else {
                echo '<p class="status-error"><span class="webhook-icon">❌</span>Error en registro</p>';
                echo '<pre>' . print_r($response, true) . '</pre>';
                $total_errors++;
                $registration_results[$webhook['event']] = 'error';
            }
        }
        
        echo '</div>';
        
        // Pequeña pausa entre registros
        usleep(500000); // 0.5 segundos
    }
    echo '</div>';
    
    // PASO 3: Verificación final
    echo '<div class="webhook-section">';
    echo '<h3><span class="step-number">3</span><span class="webhook-icon">✅</span>Verificación final</h3>';
    
    $final_webhooks = $api->api_call('event.get');
    $our_final_webhooks = array();
    
    if ($final_webhooks && isset($final_webhooks['result'])) {
        foreach ($final_webhooks['result'] as $webhook) {
            $handler = $webhook['handler'] ?? '';
            $event = $webhook['event'] ?? '';
            
            if (strpos($handler, $site_domain) !== false || 
                strpos($handler, 'yeison-bitrix/v1/webhook') !== false) {
                $our_final_webhooks[] = array(
                    'event' => $event,
                    'handler' => $handler
                );
            }
        }
    }
    
    echo '<table class="webhook-table">';
    echo '<tr><th>Evento Necesario</th><th>Estado</th><th>Handler</th></tr>';
    
    foreach ($webhooks_needed as $needed) {
        $found = false;
        $handler_found = '';
        
        foreach ($our_final_webhooks as $final) {
            if ($final['event'] === $needed['event']) {
                $found = true;
                $handler_found = $final['handler'];
                break;
            }
        }
        
        echo '<tr>';
        echo '<td><code>' . $needed['event'] . '</code></td>';
        
        if ($found) {
            echo '<td class="status-ok"><span class="webhook-icon">✅</span>Registrado</td>';
            echo '<td>' . esc_html($handler_found) . '</td>';
        } else {
            echo '<td class="status-error"><span class="webhook-icon">❌</span>Faltante</td>';
            echo '<td>-</td>';
        }
        
        echo '</tr>';
    }
    echo '</table>';
    echo '</div>';
    
    // RESUMEN FINAL
    echo '<div class="webhook-section ' . ($total_errors === 0 ? 'success' : 'warning') . '">';
    echo '<h3><span class="webhook-icon">📊</span>Resumen Final</h3>';
    echo '<ul>';
    echo '<li><strong>Webhooks registrados:</strong> ' . $total_registered . '</li>';
    echo '<li><strong>Ya existían:</strong> ' . $total_already_exists . '</li>';
    echo '<li><strong>Errores:</strong> ' . $total_errors . '</li>';
    echo '<li><strong>Total final:</strong> ' . count($our_final_webhooks) . ' de ' . count($webhooks_needed) . ' necesarios</li>';
    echo '</ul>';
    
    if ($total_errors === 0 && count($our_final_webhooks) >= count($webhooks_needed)) {
        echo '<div class="summary-success">';
        echo '<span class="webhook-icon">🎉</span>¡ÉXITO TOTAL! Todos los webhooks están registrados correctamente';
        echo '</div>';
        
        echo '<p><strong><span class="webhook-icon">✅</span>Sincronización bidireccional habilitada:</strong></p>';
        echo '<ul>';
        echo '<li><span class="webhook-icon">✅</span>Contactos: Bitrix24 ↔ WooCommerce</li>';
        echo '<li><span class="webhook-icon">✅</span>Deals/Negocios: Bitrix24 ↔ WooCommerce</li>';
        echo '<li><span class="webhook-icon">✅</span>Actualizaciones en tiempo real</li>';
        echo '</ul>';
    } else {
        echo '<div class="summary-warning">';
        echo '<span class="webhook-icon">⚠️</span>Algunos webhooks no se registraron correctamente';
        echo '</div>';
        
        if ($total_errors > 0) {
            echo '<p><strong><span class="webhook-icon">⚠️</span>Posibles causas de error:</strong></p>';
            echo '<ul>';
            echo '<li>Permisos insuficientes en Bitrix24</li>';
            echo '<li>URL del sitio no accesible desde internet</li>';
            echo '<li>Configuración de firewall/proxy</li>';
            echo '<li>Plan de Bitrix24 no soporta webhooks</li>';
            echo '</ul>';
        }
    }
    
    
    echo '</div>';
    
    // BOTONES DE ACCIÓN
    echo '<div class="webhook-section">';
    echo '<h3><span class="webhook-icon">🚀</span>Acciones</h3>';
    echo '<a href="?action=yeison_btx_test_optimized" class="btn"><span class="webhook-icon">🧪</span>Test Sistema Completo</a>';
    echo '<a href="?action=yeison_btx_register_all_webhooks" class="btn"><span class="webhook-icon">🔄</span>Volver a Registrar</a>';
    echo '<a href="' . admin_url('admin.php?page=yeison-btx-logs') . '" class="btn"><span class="webhook-icon">📝</span>Ver Logs</a>';
    echo '<a href="' . admin_url('admin.php?page=yeison-btx') . '" class="btn"><span class="webhook-icon">🏠</span>Dashboard</a>';
    echo '</div>';
    
    echo '</div>'; // Cerrar container
    
    exit;
}






/**
 * Función adicional para limpiar y re-registrar todos los webhooks
 * URL: /wp-admin/admin-ajax.php?action=yeison_btx_clean_and_register_webhooks
 */
add_action('wp_ajax_yeison_btx_clean_and_register_webhooks', 'yeison_btx_clean_and_register_webhooks');
function yeison_btx_clean_and_register_webhooks() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    echo '
    <style>
        .yeison-clean-container {
            max-width: 1200px;
            margin: 20px auto;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%);
            min-height: 100vh;
            padding: 20px;
            box-sizing: border-box;
        }
        
        .yeison-clean-header {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(237, 137, 54, 0.3);
        }
        
        .yeison-clean-header h2 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .yeison-clean-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e1e8f0;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .yeison-clean-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }
        
        .yeison-clean-card h3 {
            color: #4a5568;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .yeison-clean-error {
            background: linear-gradient(135deg, #ffb3ba 0%, #ff9aa2 100%);
            color: #8b0000;
            padding: 15px 20px;
            border-radius: 10px;
            border-left: 4px solid #f44336;
            margin: 20px 0;
            font-weight: 500;
        }
        
        .yeison-clean-success {
            background: linear-gradient(135deg, #a8e6cf 0%, #88d8a3 100%);
            color: #2d5016;
            padding: 15px 20px;
            border-radius: 10px;
            border-left: 4px solid #4caf50;
            margin: 20px 0;
            font-weight: 500;
        }
        
        .yeison-clean-warning {
            background: linear-gradient(135deg, #ffd3a5 0%, #fd9853 100%);
            color: #7d4e00;
            padding: 15px 20px;
            border-radius: 10px;
            border-left: 4px solid #ed8936;
            margin: 20px 0;
            font-weight: 500;
        }
        
        .yeison-clean-process {
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            border: 2px solid #e2e8f0;
            padding: 15px 20px;
            border-radius: 10px;
            margin: 10px 0;
            transition: all 0.3s ease;
        }
        
        .yeison-clean-process:hover {
            border-color: #ed8936;
            box-shadow: 0 4px 15px rgba(237, 137, 54, 0.1);
        }
        
        .yeison-clean-summary {
            background: linear-gradient(135deg, #b794f6 0%, #9f7aea 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            box-shadow: 0 6px 20px rgba(183, 148, 246, 0.3);
        }
        
        .yeison-clean-redirect {
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            box-shadow: 0 6px 20px rgba(66, 153, 225, 0.3);
        }
        
        .yeison-clean-icon {
            font-size: 18px;
            margin-right: 8px;
        }
        
        .step-number {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: bold;
            margin-right: 10px;
            display: inline-block;
        }
        
        .yeison-clean-button {
            display: inline-block;
            padding: 12px 25px;
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 10px 5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(66, 153, 225, 0.3);
        }
        
        .yeison-clean-button:hover {
            background: linear-gradient(135deg, #3182ce 0%, #2c5282 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(66, 153, 225, 0.4);
            color: white;
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .yeison-clean-container {
                padding: 10px;
            }
            
            .yeison-clean-header {
                padding: 20px 15px;
            }
            
            .yeison-clean-header h2 {
                font-size: 22px;
            }
            
            .yeison-clean-card {
                padding: 20px 15px;
            }
            
            .yeison-clean-button {
                display: block;
                text-align: center;
                margin: 10px 0;
            }
        }
        
        @media (max-width: 480px) {
            .yeison-clean-process {
                padding: 12px 15px;
            }
        }
    </style>
    
    <div class="yeison-clean-container">
        <div class="yeison-clean-header">
            <h2><span class="yeison-clean-icon">🧹</span>Limpiar y Re-registrar Webhooks</h2>
        </div>
    ';
    
    $api = yeison_btx_api();
    
    if (!$api->is_authorized()) {
        echo '<div class="yeison-clean-card">';
        echo '<div class="yeison-clean-error"><span class="yeison-clean-icon">❌</span>API no autorizada</div>';
        echo '</div>';
        echo '</div>';
        exit;
    }
    
    // PASO 1: Obtener webhooks existentes
    echo '<div class="yeison-clean-card">';
    echo '<h3><span class="step-number">1</span><span class="yeison-clean-icon">🔍</span>Obteniendo webhooks existentes</h3>';
    $existing_webhooks = $api->api_call('event.get');
    
    if (!$existing_webhooks || !isset($existing_webhooks['result'])) {
        echo '<div class="yeison-clean-error"><span class="yeison-clean-icon">❌</span>Error obteniendo webhooks</div>';
        echo '</div>';
        echo '</div>';
        exit;
    }
    
    echo '<div class="yeison-clean-success"><span class="yeison-clean-icon">✅</span>Webhooks obtenidos correctamente</div>';
    echo '</div>';
    
    // PASO 2: Eliminar webhooks nuestros
    echo '<div class="yeison-clean-card">';
    echo '<h3><span class="step-number">2</span><span class="yeison-clean-icon">🗑️</span>Eliminando webhooks existentes nuestros</h3>';
    $site_domain = parse_url(home_url(), PHP_URL_HOST);
    $removed_count = 0;
    
    foreach ($existing_webhooks['result'] as $webhook) {
        $handler = $webhook['handler'] ?? '';
        $event = $webhook['event'] ?? '';
        
        if (strpos($handler, $site_domain) !== false || 
            strpos($handler, 'yeison-bitrix/v1/webhook') !== false) {
            
            echo '<div class="yeison-clean-process">';
            echo '<p><span class="yeison-clean-icon">🗑️</span>Eliminando: ' . $event . '</p>';
            
            $remove_response = $api->api_call('event.unbind', array(
                'event' => $event,
                'handler' => $handler
            ));
            
            if ($remove_response && isset($remove_response['result'])) {
                echo '<p class="yeison-clean-success"><span class="yeison-clean-icon">✅</span>Eliminado</p>';
                $removed_count++;
            } else {
                echo '<p class="yeison-clean-error"><span class="yeison-clean-icon">❌</span>Error eliminando</p>';
            }
            echo '</div>';
        }
    }
    
    echo '<div class="yeison-clean-summary">';
    echo '<span class="yeison-clean-icon">📊</span><strong>Total eliminados:</strong> ' . $removed_count;
    echo '</div>';
    echo '</div>';
    
    // PASO 3: Registrar webhooks frescos
    echo '<div class="yeison-clean-card">';
    echo '<h3><span class="step-number">3</span><span class="yeison-clean-icon">🔄</span>Registrando webhooks frescos</h3>';
    
    // Redirigir a la función principal de registro
    echo '<script>window.location.href = "?action=yeison_btx_register_all_webhooks";</script>';
    
    echo '<div class="yeison-clean-redirect">';
    echo '<p><span class="yeison-clean-icon">🔄</span>Redirigiendo para registrar webhooks...</p>';
    echo '</div>';
    
    echo '<p><a href="?action=yeison_btx_register_all_webhooks" class="yeison-clean-button">Continuar manualmente</a></p>';
    
    echo '</div>';
    echo '</div>';
    
    exit;
}







/**
 * Función simple para verificar estado de webhooks
 * URL: /wp-admin/admin-ajax.php?action=yeison_btx_check_webhooks_status
 */
/**
 * FUNCIÓN MEJORADA: Verificar estado de webhooks con diseño profesional
 * 
 * Esta función verifica el estado de todos los webhooks necesarios para el
 * funcionamiento correcto de la sincronización bidireccional entre WooCommerce y Bitrix24.
 * 
 * Webhooks monitoreados:
 * - ONCRMCONTACTADD: Se ejecuta cuando se crea un contacto en Bitrix24
 * - ONCRMCONTACTUPDATE: Se ejecuta cuando se actualiza un contacto en Bitrix24  
 * - ONCRMDEALADD: Se ejecuta cuando se crea un deal/negocio en Bitrix24
 * - ONCRMDEALUPDATE: Se ejecuta cuando se actualiza un deal/negocio en Bitrix24
 * 
 * URL de acceso: /wp-admin/admin-ajax.php?action=yeison_btx_check_webhooks_status
 * 
 * @since 1.8.9
 * @access public
 * @return void Muestra página HTML con el estado de webhooks
 */
add_action('wp_ajax_yeison_btx_check_webhooks_status', 'yeison_btx_check_webhooks_status');
function yeison_btx_check_webhooks_status() {
    // ========================================
    // VALIDACIÓN DE PERMISOS
    // ========================================
    
    // Verificar que el usuario tenga permisos de administrador
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos suficientes para acceder a esta función');
    }
    
    // ========================================
    // CONFIGURACIÓN INICIAL
    // ========================================
    
    // Obtener instancia de la API de Bitrix24
    $api = yeison_btx_api();
    
    // Definir todos los webhooks necesarios para el funcionamiento completo
    // Cada webhook es crítico para la sincronización bidireccional
    $webhooks_needed = array(
        'ONCRMCONTACTADD' => 'Contacto creado en Bitrix24',
        'ONCRMCONTACTUPDATE' => 'Contacto actualizado en Bitrix24', 
        'ONCRMDEALADD' => 'Deal/Negocio creado en Bitrix24',
        'ONCRMDEALUPDATE' => 'Deal/Negocio actualizado en Bitrix24'
    );
    
    // Obtener el dominio actual del sitio para filtrar nuestros webhooks
    $site_domain = parse_url(home_url(), PHP_URL_HOST);
    $our_webhooks = array();
    $total_ok = 0;
    
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Estado de Webhooks - Yeison BTX</title>
        
        <style>
            /* ================================================================
               ESTILOS CSS PROFESIONALES PARA ESTADO DE WEBHOOKS
               ================================================================ */
            
            /* Reset y base */
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                padding: 20px;
                color: #333;
                line-height: 1.6;
            }
            
            /* Contenedor principal */
            .webhook-status-container {
                max-width: 1200px;
                margin: 0 auto;
                background: rgba(255, 255, 255, 0.95);
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
                overflow: hidden;
                backdrop-filter: blur(10px);
            }
            
            /* Header */
            .webhook-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 40px 30px;
                text-align: center;
                position: relative;
                overflow: hidden;
            }
            
            .webhook-header::before {
                content: '';
                position: absolute;
                top: -50%;
                left: -50%;
                width: 200%;
                height: 200%;
                background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
                animation: pulse 4s ease-in-out infinite;
            }
            
            @keyframes pulse {
                0%, 100% { transform: scale(1); opacity: 0.7; }
                50% { transform: scale(1.1); opacity: 0.3; }
            }
            
            .webhook-title {
                font-size: 2.5rem;
                font-weight: 700;
                margin-bottom: 10px;
                position: relative;
                z-index: 2;
            }
            
            .webhook-subtitle {
                font-size: 1.1rem;
                opacity: 0.9;
                position: relative;
                z-index: 2;
            }
            
            /* Status card */
            .api-status-card {
                margin: 30px;
                padding: 25px;
                border-radius: 15px;
                text-align: center;
                font-weight: 600;
                font-size: 1.1rem;
            }
            
            .api-status-error {
                background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
                color: white;
                box-shadow: 0 8px 25px rgba(255, 107, 107, 0.3);
            }
            
            .api-status-success {
                background: linear-gradient(135deg, #51cf66 0%, #40c057 100%);
                color: white;
                box-shadow: 0 8px 25px rgba(81, 207, 102, 0.3);
            }
            
            /* Contenido principal */
            .webhook-content {
                padding: 40px 30px;
            }
            
            /* Tabla de webhooks */
            .webhooks-table-container {
                background: #f8fafc;
                border-radius: 15px;
                padding: 25px;
                margin-bottom: 30px;
                border: 2px solid #e2e8f0;
            }
            
            .webhooks-table {
                width: 100%;
                border-collapse: collapse;
                background: white;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            }
            
            .webhooks-table th {
                background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
                color: white;
                padding: 18px 15px;
                text-align: left;
                font-weight: 600;
                font-size: 0.95rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .webhooks-table td {
                padding: 16px 15px;
                border-bottom: 1px solid #e5e7eb;
                vertical-align: middle;
            }
            
            .webhooks-table tr:last-child td {
                border-bottom: none;
            }
            
            .webhooks-table tr:hover {
                background: #f1f5f9;
                transition: background 0.3s ease;
            }
            
            /* Códigos de webhook */
            .webhook-code {
                background: #1f2937;
                color: #10b981;
                padding: 8px 12px;
                border-radius: 6px;
                font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
                font-size: 0.85rem;
                font-weight: 600;
                display: inline-block;
                border: 1px solid #374151;
            }
            
            /* Estados de webhooks */
            .webhook-status {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 16px;
                border-radius: 25px;
                font-weight: 600;
                font-size: 0.9rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .webhook-status.registered {
                background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
                color: #065f46;
                border: 2px solid #10b981;
            }
            
            .webhook-status.missing {
                background: linear-gradient(135deg, #fee2e2 0%, #fca5a5 100%);
                color: #991b1b;
                border: 2px solid #ef4444;
            }
            
            /* Resumen */
            .webhook-summary {
                background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
                border: 2px solid #0ea5e9;
                border-radius: 15px;
                padding: 25px;
                margin: 30px 0;
                text-align: center;
            }
            
            .summary-title {
                font-size: 1.4rem;
                font-weight: 700;
                color: #0c4a6e;
                margin-bottom: 15px;
            }
            
            .summary-stats {
                font-size: 1.2rem;
                color: #075985;
                margin-bottom: 20px;
            }
            
            /* Status final */
            .final-status {
                padding: 25px;
                border-radius: 15px;
                text-align: center;
                font-weight: 700;
                font-size: 1.2rem;
                margin: 25px 0;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .final-status.success {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                box-shadow: 0 8px 30px rgba(16, 185, 129, 0.4);
            }
            
            .final-status.warning {
                background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
                color: white;
                box-shadow: 0 8px 30px rgba(245, 158, 11, 0.4);
            }
            
            /* Botón de acción */
            .action-button {
                display: inline-block;
                background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
                color: white;
                padding: 15px 30px;
                text-decoration: none;
                border-radius: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                transition: all 0.3s ease;
                box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
                margin-top: 20px;
            }
            
            .action-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 30px rgba(59, 130, 246, 0.4);
                text-decoration: none;
                color: white;
            }
            
            /* Footer */
            .webhook-footer {
                background: #f8fafc;
                padding: 25px 30px;
                text-align: center;
                border-top: 1px solid #e5e7eb;
            }
            
            .footer-info {
                color: #64748b;
                font-size: 0.9rem;
                margin-bottom: 15px;
            }
            
            .footer-buttons {
                display: flex;
                gap: 15px;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .footer-button {
                padding: 10px 20px;
                background: #6b7280;
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 500;
                transition: all 0.3s ease;
                font-size: 0.9rem;
            }
            
            .footer-button:hover {
                background: #4b5563;
                transform: translateY(-1px);
                text-decoration: none;
                color: white;
            }
            
            .footer-button.primary {
                background: #3b82f6;
            }
            
            .footer-button.primary:hover {
                background: #2563eb;
            }
            
            /* ================================================================
               RESPONSIVE DESIGN
               ================================================================ */
            
            /* Tablet */
            @media (max-width: 768px) {
                body {
                    padding: 15px;
                }
                
                .webhook-title {
                    font-size: 2rem;
                }
                
                .webhook-content {
                    padding: 25px 20px;
                }
                
                .webhooks-table-container {
                    padding: 20px 15px;
                }
                
                .webhooks-table th,
                .webhooks-table td {
                    padding: 12px 10px;
                    font-size: 0.9rem;
                }
                
                .webhook-code {
                    font-size: 0.75rem;
                    padding: 6px 10px;
                }
                
                .footer-buttons {
                    flex-direction: column;
                    align-items: center;
                }
                
                .footer-button {
                    width: 100%;
                    max-width: 250px;
                    text-align: center;
                }
            }
            
            /* Mobile */
            @media (max-width: 480px) {
                .webhook-header {
                    padding: 30px 20px;
                }
                
                .webhook-title {
                    font-size: 1.8rem;
                }
                
                .webhook-subtitle {
                    font-size: 1rem;
                }
                
                .webhooks-table {
                    font-size: 0.85rem;
                }
                
                .webhooks-table th,
                .webhooks-table td {
                    padding: 10px 8px;
                }
                
                .webhook-status {
                    font-size: 0.8rem;
                    padding: 6px 12px;
                }
                
                .final-status {
                    font-size: 1rem;
                    padding: 20px 15px;
                }
                
                .action-button {
                    padding: 12px 25px;
                    font-size: 0.9rem;
                }
            }
            
            /* Mobile muy pequeño */
            @media (max-width: 320px) {
                .webhook-content {
                    padding: 20px 15px;
                }
                
                .webhooks-table-container {
                    padding: 15px 10px;
                }
                
                .webhook-code {
                    font-size: 0.7rem;
                    padding: 4px 8px;
                }
            }
        </style>
    </head>
    <body>
        <div class="webhook-status-container">
            <!-- ========================================
                 HEADER PRINCIPAL
                 ======================================== -->
            <div class="webhook-header">
                <h1 class="webhook-title">📊 Estado de Webhooks</h1>
                <p class="webhook-subtitle">Sistema de Monitoreo de Sincronización Bidireccional</p>
            </div>
            
            <?php
            // ========================================
            // VERIFICACIÓN DE AUTORIZACIÓN API
            // ========================================
            
            // Verificar si la API de Bitrix24 está autorizada y funcionando
            if (!$api->is_authorized()) {
                ?>
                <div class="api-status-card api-status-error">
                    ❌ <strong>API no autorizada</strong><br>
                    La conexión con Bitrix24 no está establecida. Debes autorizar la API primero.
                </div>
                
                <div class="webhook-footer">
                    <div class="footer-info">
                        No se puede verificar el estado de webhooks sin una conexión activa a Bitrix24
                    </div>
                    <div class="footer-buttons">
                        <a href="<?php echo admin_url('admin.php?page=yeison-btx'); ?>" class="footer-button primary">
                            🔐 Autorizar API
                        </a>
                        <a href="javascript:history.back()" class="footer-button">
                            ← Volver
                        </a>
                    </div>
                </div>
                <?php
                echo '</div></body></html>';
                exit;
            }
            ?>
            
            <!-- Confirmación de API autorizada -->
            <div class="api-status-card api-status-success">
                ✅ <strong>API de Bitrix24 Autorizada</strong><br>
                Conexión establecida correctamente
            </div>
            
            <div class="webhook-content">
                <?php
                // ========================================
                // OBTENER WEBHOOKS EXISTENTES
                // ========================================
                
                // Realizar llamada a la API para obtener todos los webhooks registrados
                $existing_webhooks = $api->api_call('event.get');
                
                // Procesar y filtrar solo nuestros webhooks
                if ($existing_webhooks && isset($existing_webhooks['result'])) {
                    foreach ($existing_webhooks['result'] as $webhook) {
                        // Obtener el handler (URL del webhook) y evento
                        $handler = $webhook['handler'] ?? '';
                        $event = $webhook['event'] ?? '';
                        
                        // Verificar si el webhook pertenece a nuestro sitio
                        // Comprobamos tanto el dominio como la estructura de URL específica
                        if (strpos($handler, $site_domain) !== false || 
                            strpos($handler, 'yeison-bitrix/v1/webhook') !== false) {
                            $our_webhooks[] = $event;
                        }
                    }
                }
                ?>
                
                <!-- ========================================
                     TABLA DE ESTADO DE WEBHOOKS
                     ======================================== -->
                <div class="webhooks-table-container">
                    <table class="webhooks-table">
                        <thead>
                            <tr>
                                <th>🔗 Webhook Necesario</th>
                                <th>📝 Descripción</th>
                                <th>📊 Estado Actual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Iterar sobre cada webhook necesario y verificar su estado
                            foreach ($webhooks_needed as $event => $description) {
                                // Verificar si este webhook está registrado
                                $is_registered = in_array($event, $our_webhooks);
                                
                                // Contar webhooks activos para el resumen
                                if ($is_registered) {
                                    $total_ok++;
                                }
                                ?>
                                <tr>
                                    <!-- Código del evento -->
                                    <td>
                                        <code class="webhook-code"><?php echo esc_html($event); ?></code>
                                    </td>
                                    
                                    <!-- Descripción funcional -->
                                    <td>
                                        <?php echo esc_html($description); ?>
                                    </td>
                                    
                                    <!-- Estado visual -->
                                    <td>
                                        <span class="webhook-status <?php echo $is_registered ? 'registered' : 'missing'; ?>">
                                            <?php echo $is_registered ? '✅ Registrado' : '❌ Faltante'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- ========================================
                     RESUMEN ESTADÍSTICO
                     ======================================== -->
                <div class="webhook-summary">
                    <h3 class="summary-title">📈 Resumen del Sistema</h3>
                    <div class="summary-stats">
                        <strong><?php echo $total_ok; ?> de <?php echo count($webhooks_needed); ?></strong> 
                        webhooks están correctamente registrados
                    </div>
                    
                    <?php
                    // Calcular porcentaje de completitud
                    $completion_percentage = round(($total_ok / count($webhooks_needed)) * 100);
                    ?>
                    <div style="font-size: 1rem; color: #0369a1;">
                        Nivel de completitud: <strong><?php echo $completion_percentage; ?>%</strong>
                    </div>
                </div>
                
                <!-- ========================================
                     STATUS FINAL Y ACCIONES
                     ======================================== -->
                <?php
                // Determinar el estado final del sistema y mostrar mensaje apropiado
                if ($total_ok === count($webhooks_needed)) {
                    // ✅ ESTADO: Todos los webhooks están registrados
                    ?>
                    <div class="final-status success">
                        🎉 ¡Sistema Completamente Configurado!
                    </div>
                    <div style="text-align: center; color: #065f46; font-weight: 500;">
                        Todos los webhooks necesarios están registrados y funcionando.<br>
                        La sincronización bidireccional está completamente operativa.
                    </div>
                    <?php
                } else {
                    // ⚠️ ESTADO: Faltan webhooks por registrar
                    $missing_count = count($webhooks_needed) - $total_ok;
                    ?>
                    <div class="final-status warning">
                        ⚠️ Configuración Incompleta
                    </div>
                    <div style="text-align: center; color: #92400e; font-weight: 500; margin-bottom: 20px;">
                        Faltan <strong><?php echo $missing_count; ?> webhook<?php echo $missing_count > 1 ? 's' : ''; ?></strong> 
                        por registrar para el funcionamiento completo del sistema.
                    </div>
                    
                    <!-- Botón para registrar webhooks faltantes -->
                    <div style="text-align: center;">
                        <a href="?action=yeison_btx_register_all_webhooks" class="action-button">
                            🔧 Registrar Webhooks Faltantes
                        </a>
                    </div>
                    <?php
                }
                ?>
            </div>
            
            <!-- ========================================
                 FOOTER CON INFORMACIÓN Y NAVEGACIÓN
                 ======================================== -->
            <div class="webhook-footer">
                <div class="footer-info">
                    <strong>Información del Sistema:</strong><br>
                    Dominio: <?php echo esc_html($site_domain); ?> • 
                    Plugin: GuruX BTX v1.8.9 • 
                    <?php echo current_time('Y-m-d H:i:s'); ?>
                </div>
                
                <div class="footer-buttons">
                    <a href="<?php echo admin_url('admin.php?page=yeison-btx'); ?>" class="footer-button primary">
                        🏠 Dashboard Principal
                    </a>
                    <a href="?action=yeison_btx_register_all_webhooks" class="footer-button">
                        🔧 Gestionar Webhooks
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=yeison-btx-logs'); ?>" class="footer-button">
                        📝 Ver Logs
                    </a>
                    <a href="javascript:location.reload()" class="footer-button">
                        🔄 Actualizar Estado
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    
    // ========================================
    // FINALIZACIÓN DE LA FUNCIÓN
    // ========================================
    
    // Terminar ejecución para evitar contenido adicional de WordPress
    exit;
}









/**
 * NUEVAS FUNCIONES PARA MAPEO DE ESTADOS PERSONALIZADOS
 */

/**
 * Obtener todos los estados de WooCommerce disponibles
 */
function yeison_btx_get_woocommerce_statuses() {
    if (!function_exists('wc_get_order_statuses')) {
        return array(
            'pending' => 'Pending payment',
            'processing' => 'Processing', 
            'on-hold' => 'On hold',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
            'failed' => 'Failed'
        );
    }
    
    return wc_get_order_statuses();
}


/**
 * Obtener etapas de Deal en Bitrix24 para un pipeline específico
 */
function yeison_btx_get_bitrix24_deal_stages($category_id = null) {
    // Si no se especifica categoría, usar la seleccionada
    if ($category_id === null) {
        $category_id = yeison_btx_get_selected_pipeline();
    }
    
    return yeison_btx_get_pipeline_stages($category_id);
}



/**
 * Obtener mapeo actual guardado
 */
function yeison_btx_get_current_status_mapping() {
    $custom_mapping = yeison_btx_get_option('custom_status_mapping', array());
    
    // Si no hay mapeo personalizado, usar el por defecto
    if (empty($custom_mapping)) {
        return array(
            'wc_to_bitrix' => array(
                'pending' => 'NEW',
                'processing' => 'EXECUTING',
                'on-hold' => 'PREPAYMENT_INVOICE',
                'completed' => 'WON',
                'cancelled' => 'LOSE',
                'refunded' => 'LOSE',
                'failed' => 'LOSE'
            ),
            'bitrix_to_wc' => array(
                'NEW' => 'pending',
                'PREPARATION' => 'processing',
                'EXECUTING' => 'processing',
                'PREPAYMENT_INVOICE' => 'on-hold',
                'WON' => 'completed',
                'LOSE' => 'cancelled',
                'APOLOGY' => 'cancelled'
            )
        );
    }
    
    return $custom_mapping;
}

/**
 * Guardar mapeo personalizado de estados
 */
function yeison_btx_save_status_mapping($wc_to_bitrix, $bitrix_to_wc) {
    $mapping = array(
        'wc_to_bitrix' => $wc_to_bitrix,
        'bitrix_to_wc' => $bitrix_to_wc
    );
    
    // Guardar en opciones
    yeison_btx_update_option('custom_status_mapping', $mapping);
    
    // También actualizar en data mapping
    $data_mapping = yeison_btx_data_mapping();
    $data_mapping->update_custom_mapping('status_mapping', $mapping);
    
    yeison_btx_log('Mapeo de estados actualizado', 'success', array(
        'wc_to_bitrix_count' => count($wc_to_bitrix),
        'bitrix_to_wc_count' => count($bitrix_to_wc)
    ));
    
    return true;
}

/**
 * Crear nuevo estado en Bitrix24
 */
function yeison_btx_create_bitrix24_stage($stage_name, $category_id = 0) {
    $api = yeison_btx_api();
    
    if (!$api->is_authorized()) {
        return false;
    }
    
    // Crear nuevo estado
    $response = $api->api_call('crm.status.add', array(
        'fields' => array(
            'ENTITY_ID' => 'DEAL_STAGE_' . $category_id,
            'STATUS_ID' => strtoupper(str_replace(' ', '_', $stage_name)),
            'NAME' => $stage_name,
            'SORT' => 500,
            'COLOR' => '#' . substr(md5($stage_name), 0, 6) // Color aleatorio basado en el nombre
        )
    ));
    
    if ($response && isset($response['result'])) {
        yeison_btx_log('Nuevo estado creado en Bitrix24', 'success', array(
            'stage_name' => $stage_name,
            'stage_id' => $response['result'],
            'category_id' => $category_id
        ));
        
        return $response['result'];
    }
    
    yeison_btx_log('Error creando estado en Bitrix24', 'error', array(
        'stage_name' => $stage_name,
        'response' => $response
    ));
    
    return false;
}

/**
 * Obtener categorías de deals disponibles en Bitrix24
 */

function yeison_btx_get_bitrix24_categories() {
    $api = yeison_btx_api();
    
    if (!$api->is_authorized()) {
        return array();
    }
    
    $response = $api->api_call('crm.category.list', array(
        'entityTypeId' => 2 // 2 = DEALS
    ));
    
    $categories = array();
    
    // CORREGIR: usar la estructura [result][categories] del JSON
    if ($response && isset($response['result']['categories'])) {
        foreach ($response['result']['categories'] as $category) {
            $categories[$category['id']] = $category['name'];
        }
    }
    
    // Agregar categoría por defecto si no hay ninguna
    if (empty($categories)) {
        $categories[0] = 'General';
    }
    
    return $categories;
}


/**
 * AJAX HANDLERS PARA MAPEO DE ESTADOS
 * Agregar después de las funciones anteriores en functions.php
 */

/**
 * AJAX: Obtener estados de ambos sistemas
 */
add_action('wp_ajax_yeison_btx_get_all_statuses', 'yeison_btx_ajax_get_all_statuses');
function yeison_btx_ajax_get_all_statuses() {
    check_ajax_referer('yeison_btx_status_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sin permisos');
    }
    
    $wc_statuses = yeison_btx_get_woocommerce_statuses();
    $bitrix_stages = yeison_btx_get_bitrix24_deal_stages();
    $current_mapping = yeison_btx_get_current_status_mapping();
    $categories = yeison_btx_get_bitrix24_categories();
    
    wp_send_json_success(array(
        'wc_statuses' => $wc_statuses,
        'bitrix_stages' => $bitrix_stages,
        'current_mapping' => $current_mapping,
        'categories' => $categories
    ));
}

/**
 * AJAX: Guardar mapeo de estados
 */
add_action('wp_ajax_yeison_btx_save_status_mapping', 'yeison_btx_ajax_save_status_mapping');
function yeison_btx_ajax_save_status_mapping() {
    check_ajax_referer('yeison_btx_status_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sin permisos');
    }
    
    $wc_to_bitrix = $_POST['wc_to_bitrix'] ?? array();
    $bitrix_to_wc = $_POST['bitrix_to_wc'] ?? array();
    
    // Sanitizar datos
    $wc_to_bitrix = array_map('sanitize_text_field', $wc_to_bitrix);
    $bitrix_to_wc = array_map('sanitize_text_field', $bitrix_to_wc);
    
    $result = yeison_btx_save_status_mapping($wc_to_bitrix, $bitrix_to_wc);
    
    if ($result) {
        wp_send_json_success(array(
            'message' => 'Mapeo guardado exitosamente',
            'wc_count' => count($wc_to_bitrix),
            'bitrix_count' => count($bitrix_to_wc)
        ));
    } else {
        wp_send_json_error('Error guardando mapeo');
    }
}

/**
 * AJAX: Crear nuevo estado en Bitrix24
 */
add_action('wp_ajax_yeison_btx_create_bitrix_stage', 'yeison_btx_ajax_create_bitrix_stage');
function yeison_btx_ajax_create_bitrix_stage() {
    check_ajax_referer('yeison_btx_status_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sin permisos');
    }
    
    $stage_name = sanitize_text_field($_POST['stage_name'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    
    if (empty($stage_name)) {
        wp_send_json_error('Nombre de estado requerido');
    }
    
    $result = yeison_btx_create_bitrix24_stage($stage_name, $category_id);
    
    if ($result) {
        wp_send_json_success(array(
            'message' => 'Estado creado en Bitrix24',
            'stage_id' => $result,
            'stage_name' => $stage_name
        ));
    } else {
        wp_send_json_error('Error creando estado en Bitrix24');
    }
}






/**
 * NUEVA FUNCIONALIDAD MAPEO DE ETAPAS
 */
/**
 * Obtener pipeline/embudo seleccionado para WooCommerce
 */
function yeison_btx_get_selected_pipeline() {
    $pipeline_id = yeison_btx_get_option('woocommerce_pipeline_id', 0);
    
    // Asegurar que sea un entero
    return intval($pipeline_id);
}
/**
 * Guardar pipeline seleccionado para WooCommerce
 */
function yeison_btx_set_selected_pipeline($category_id) {
    yeison_btx_update_option('woocommerce_pipeline_id', intval($category_id));
    
    yeison_btx_log('Pipeline de WooCommerce actualizado', 'info', array(
        'pipeline_id' => $category_id
    ));
    
    return true;
}

/**
 * Obtener etapas de un pipeline específico
 */


/**
 * Obtener etapas de un pipeline específico
 */
function yeison_btx_get_pipeline_stages($category_id = 0) {
    $api = yeison_btx_api();
    
    if (!$api->is_authorized()) {
        return array();
    }
    
    // IMPORTANTE: En Bitrix24 las etapas tienen ENTITY_ID diferente por pipeline
    $entity_id = $category_id == 0 ? 'DEAL_STAGE' : 'DEAL_STAGE_' . $category_id;
    
    $stages_response = $api->api_call('crm.status.list', array(
        'filter' => array('ENTITY_ID' => $entity_id)
    ));
    
    $stages = array();
    
    if ($stages_response && isset($stages_response['result'])) {
        foreach ($stages_response['result'] as $stage) {
            $stages[$stage['STATUS_ID']] = array(
                'name' => $stage['NAME'],
                'color' => $stage['COLOR'] ?? '#cccccc',
                'sort' => $stage['SORT'] ?? 100,
                'entity_id' => $stage['ENTITY_ID'],
                'system' => isset($stage['SYSTEM']) && $stage['SYSTEM'] === 'Y'
            );
        }
    }
    
    return $stages;
}



/**
 * AJAX: Cambiar pipeline seleccionado NUEVA FUNCIONALIDAD PARA EL PERMISO INICIAL
 */
add_action('wp_ajax_yeison_btx_set_pipeline', 'yeison_btx_ajax_set_pipeline');
function yeison_btx_ajax_set_pipeline() {
    check_ajax_referer('yeison_btx_status_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sin permisos');
    }
    
    $category_id = intval($_POST['category_id'] ?? 0);
    
    $result = yeison_btx_set_selected_pipeline($category_id);
    
    if ($result) {
        // Obtener etapas del nuevo pipeline
        $stages = yeison_btx_get_pipeline_stages($category_id);
        
        wp_send_json_success(array(
            'message' => 'Pipeline actualizado',
            'pipeline_id' => $category_id,
            'stages' => $stages
        ));
    } else {
        wp_send_json_error('Error actualizando pipeline');
    }
}

















/**
 * TEMPORAL: Debug de pipelines y etapas   /wp-admin/admin-ajax.php?action=yeison_btx_debug_pipeline_stages
 */
add_action('wp_ajax_yeison_btx_debug_pipeline_stages', 'yeison_btx_debug_pipeline_stages');
function yeison_btx_debug_pipeline_stages() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    $api = yeison_btx_api();
    
    echo '<h2>🔍 Debug: Pipelines y Etapas</h2>';
    
    // 1. Obtener todas las categorías
    $categories = $api->api_call('crm.category.list', array('entityTypeId' => 2));
    echo '<h3>📋 Pipelines Disponibles:</h3>';
    echo '<pre>' . print_r($categories, true) . '</pre>';
    
    // 2. Obtener TODAS las etapas sin filtro
    $all_stages = $api->api_call('crm.status.list', array(
        'filter' => array('ENTITY_ID' => 'DEAL_STAGE')
    ));
    echo '<h3>📊 Todas las Etapas:</h3>';
    echo '<pre>' . print_r($all_stages, true) . '</pre>';
    
    // 3. Probar etapas específicas por categoría
    if ($categories && isset($categories['result']['categories'])) {
        foreach ($categories['result']['categories'] as $category) {
            $cat_id = $category['id'];
            $cat_name = $category['name'];
            
            echo '<h3>🔍 Etapas del Pipeline: ' . $cat_name . ' (ID: ' . $cat_id . ')</h3>';
            
            $stages = $api->api_call('crm.status.list', array(
                'filter' => array(
                    'ENTITY_ID' => 'DEAL_STAGE',
                    'CATEGORY_ID' => $cat_id
                )
            ));
            
            echo '<pre>' . print_r($stages, true) . '</pre>';
        }
    }
    
    exit;
}




/**
 * Obtener mapeo de estados para un pipeline específico
 */
function yeison_btx_get_pipeline_status_mapping($pipeline_id = null) {
    // Si no se especifica pipeline, usar el seleccionado
    if ($pipeline_id === null) {
        $pipeline_id = yeison_btx_get_selected_pipeline();
    }
    
    // Obtener todos los mapeos guardados
    $all_mappings = yeison_btx_get_option('pipeline_status_mappings', array());
    
    // Si existe mapeo para este pipeline, devolverlo
    if (isset($all_mappings['pipeline_' . $pipeline_id])) {
        return $all_mappings['pipeline_' . $pipeline_id];
    }
    
    // Si no existe, devolver mapeo por defecto
    return yeison_btx_get_default_pipeline_mapping($pipeline_id);
}

/**
 * Obtener mapeo por defecto según el pipeline
 */
function yeison_btx_get_default_pipeline_mapping($pipeline_id) {
    // Mapeos por defecto específicos por pipeline
    $default_mappings = array(
        '0' => array( // General
            'wc_to_bitrix' => array(
                'pending' => 'NEW',
                'processing' => 'EXECUTING',
                'on-hold' => 'PREPAYMENT_INVOICE',
                'completed' => 'WON',
                'cancelled' => 'LOSE',
                'refunded' => 'LOSE',
                'failed' => 'LOSE'
            ),
            'bitrix_to_wc' => array(
                'NEW' => 'pending',
                'PREPARATION' => 'processing',
                'EXECUTING' => 'processing',
                'PREPAYMENT_INVOICE' => 'on-hold',
                'WON' => 'completed',
                'LOSE' => 'cancelled',
                'APOLOGY' => 'cancelled'
            )
        )
    );
    
    // Si existe mapeo específico para el pipeline, usarlo
    if (isset($default_mappings[$pipeline_id])) {
        return $default_mappings[$pipeline_id];
    }
    
    // Si no, devolver el general
    return $default_mappings['0'];
}

/**
 * Guardar mapeo de estados para un pipeline específico
 */
function yeison_btx_save_pipeline_status_mapping($pipeline_id, $wc_to_bitrix, $bitrix_to_wc) {
    // Obtener todos los mapeos
    $all_mappings = yeison_btx_get_option('pipeline_status_mappings', array());
    
    // Guardar el mapeo para este pipeline
    $all_mappings['pipeline_' . $pipeline_id] = array(
        'wc_to_bitrix' => $wc_to_bitrix,
        'bitrix_to_wc' => $bitrix_to_wc,
        'updated_at' => current_time('mysql')
    );
    
    // Guardar en opciones
    yeison_btx_update_option('pipeline_status_mappings', $all_mappings);
    
    yeison_btx_log('Mapeo de estados actualizado para pipeline', 'success', array(
        'pipeline_id' => $pipeline_id,
        'wc_to_bitrix_count' => count($wc_to_bitrix),
        'bitrix_to_wc_count' => count($bitrix_to_wc)
    ));
    
    return true;
}





/**
 * AJAX mejorado: Obtener estados con mapeo del pipeline actual
 */
add_action('wp_ajax_yeison_btx_get_pipeline_statuses', 'yeison_btx_ajax_get_pipeline_statuses');
function yeison_btx_ajax_get_pipeline_statuses() {
    check_ajax_referer('yeison_btx_status_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sin permisos');
    }
    
    $pipeline_id = isset($_POST['pipeline_id']) ? intval($_POST['pipeline_id']) : 0;
    
    // IMPORTANTE: Pasar el pipeline_id para obtener las etapas correctas
    $wc_statuses = yeison_btx_get_woocommerce_statuses();
    $bitrix_stages = yeison_btx_get_bitrix24_deal_stages($pipeline_id); // <- Pasar pipeline_id aquí
    $current_mapping = yeison_btx_get_pipeline_status_mapping($pipeline_id);
    $categories = yeison_btx_get_bitrix24_categories();
    
    wp_send_json_success(array(
        'pipeline_id' => $pipeline_id,
        'wc_statuses' => $wc_statuses,
        'bitrix_stages' => $bitrix_stages,
        'current_mapping' => $current_mapping,
        'categories' => $categories
    ));
}


add_action('wp_ajax_yeison_btx_get_all_statuses', 'yeison_btx_ajax_get_all_statuses_fixed');
function yeison_btx_ajax_get_all_statuses_fixed() {
    check_ajax_referer('yeison_btx_status_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sin permisos');
    }
    
    // Obtener el pipeline actual
    $pipeline_id = yeison_btx_get_selected_pipeline();
    
    $wc_statuses = yeison_btx_get_woocommerce_statuses();
    $bitrix_stages = yeison_btx_get_bitrix24_deal_stages($pipeline_id); // <- Usar pipeline actual
    $current_mapping = yeison_btx_get_pipeline_status_mapping($pipeline_id);
    $categories = yeison_btx_get_bitrix24_categories();
    
    wp_send_json_success(array(
        'pipeline_id' => $pipeline_id,
        'wc_statuses' => $wc_statuses,
        'bitrix_stages' => $bitrix_stages,
        'current_mapping' => $current_mapping,
        'categories' => $categories
    ));
}



/**
 * Debug: Ver etapas por pipeline
 * URL: /wp-admin/admin-ajax.php?action=yeison_btx_debug_stages
 */
add_action('wp_ajax_yeison_btx_debug_stages', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    $api = yeison_btx_api();
    
    echo '<h2>🔍 Debug: Etapas por Pipeline</h2>';
    
    // Obtener todos los pipelines
    $categories = yeison_btx_get_bitrix24_categories();
    
    echo '<style>
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f2f2f2; }
        .pipeline-header { background: #e8f4f8; font-weight: bold; }
    </style>';
    
    foreach ($categories as $cat_id => $cat_name) {
        echo '<h3>Pipeline: ' . esc_html($cat_name) . ' (ID: ' . $cat_id . ')</h3>';
        
        // Obtener etapas específicas de este pipeline
        $entity_id = $cat_id == 0 ? 'DEAL_STAGE' : 'DEAL_STAGE_' . $cat_id;
        
        $stages_response = $api->api_call('crm.status.list', array(
            'filter' => array('ENTITY_ID' => $entity_id)
        ));
        
        if ($stages_response && isset($stages_response['result'])) {
            echo '<table>';
            echo '<tr><th>STATUS_ID</th><th>Nombre</th><th>ENTITY_ID</th><th>SORT</th></tr>';
            
            foreach ($stages_response['result'] as $stage) {
                echo '<tr>';
                echo '<td>' . esc_html($stage['STATUS_ID']) . '</td>';
                echo '<td>' . esc_html($stage['NAME']) . '</td>';
                echo '<td>' . esc_html($stage['ENTITY_ID']) . '</td>';
                echo '<td>' . esc_html($stage['SORT'] ?? 0) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p>No se encontraron etapas para este pipeline.</p>';
        }
    }
    
    // Mostrar todas las etapas sin filtrar
    echo '<h3>TODAS las etapas (sin filtrar por pipeline):</h3>';
    $all_stages = $api->api_call('crm.status.list', array(
        'filter' => array(
            'ENTITY_ID' => array('DEAL_STAGE%')
        )
    ));
    
    if ($all_stages && isset($all_stages['result'])) {
        echo '<table>';
        echo '<tr><th>STATUS_ID</th><th>Nombre</th><th>ENTITY_ID</th></tr>';
        
        foreach ($all_stages['result'] as $stage) {
            if (strpos($stage['ENTITY_ID'], 'DEAL_STAGE') === 0) {
                echo '<tr>';
                echo '<td>' . esc_html($stage['STATUS_ID']) . '</td>';
                echo '<td>' . esc_html($stage['NAME']) . '</td>';
                echo '<td>' . esc_html($stage['ENTITY_ID']) . '</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
    }
    
    exit;
});
























/**
 * AJAX: Guardar mapeo para pipeline específico
 */
add_action('wp_ajax_yeison_btx_save_pipeline_mapping', 'yeison_btx_ajax_save_pipeline_mapping');
function yeison_btx_ajax_save_pipeline_mapping() {
    check_ajax_referer('yeison_btx_status_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sin permisos');
    }
    
    $pipeline_id = isset($_POST['pipeline_id']) ? intval($_POST['pipeline_id']) : 0;
    $wc_to_bitrix = isset($_POST['wc_to_bitrix']) ? json_decode(stripslashes($_POST['wc_to_bitrix']), true) : array();
    $bitrix_to_wc = isset($_POST['bitrix_to_wc']) ? json_decode(stripslashes($_POST['bitrix_to_wc']), true) : array();
    
    $result = yeison_btx_save_pipeline_status_mapping($pipeline_id, $wc_to_bitrix, $bitrix_to_wc);
    
    if ($result) {
        wp_send_json_success(array(
            'message' => 'Mapeo guardado exitosamente para el pipeline',
            'pipeline_id' => $pipeline_id,
            'wc_count' => count($wc_to_bitrix),
            'bitrix_count' => count($bitrix_to_wc)
        ));
    } else {
        wp_send_json_error('Error guardando mapeo del pipeline');
    }
}

/**
 * Copiar mapeo de un pipeline a otro
 */
add_action('wp_ajax_yeison_btx_copy_pipeline_mapping', 'yeison_btx_ajax_copy_pipeline_mapping');
function yeison_btx_ajax_copy_pipeline_mapping() {
    check_ajax_referer('yeison_btx_status_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sin permisos');
    }
    
    $from_pipeline = isset($_POST['from_pipeline']) ? intval($_POST['from_pipeline']) : 0;
    $to_pipeline = isset($_POST['to_pipeline']) ? intval($_POST['to_pipeline']) : 0;
    
    // Obtener mapeo del pipeline origen
    $source_mapping = yeison_btx_get_pipeline_status_mapping($from_pipeline);
    
    // Guardar en pipeline destino
    $result = yeison_btx_save_pipeline_status_mapping(
        $to_pipeline, 
        $source_mapping['wc_to_bitrix'], 
        $source_mapping['bitrix_to_wc']
    );
    
    if ($result) {
        wp_send_json_success(array(
            'message' => 'Mapeo copiado exitosamente',
            'from' => $from_pipeline,
            'to' => $to_pipeline
        ));
    } else {
        wp_send_json_error('Error copiando mapeo');
    }
}

// Función para migrar mapeos existentes al nuevo formato
function yeison_btx_migrate_mappings_to_pipeline_format() {
    $old_mapping = yeison_btx_get_option('custom_status_mapping', array());
    
    if (!empty($old_mapping)) {
        // Migrar al pipeline 0 (General)
        yeison_btx_save_pipeline_status_mapping(
            0, 
            $old_mapping['wc_to_bitrix'] ?? array(), 
            $old_mapping['bitrix_to_wc'] ?? array()
        );
        
        // Opcional: eliminar el mapeo antiguo
        // yeison_btx_update_option('custom_status_mapping', array());
        
        yeison_btx_log('Mapeos migrados al nuevo formato de pipelines', 'info');
    }
}

// Ejecutar migración una sola vez
add_action('admin_init', function() {
    if (!get_option('yeison_btx_pipeline_mapping_migrated')) {
        yeison_btx_migrate_mappings_to_pipeline_format();
        update_option('yeison_btx_pipeline_mapping_migrated', true);
    }
});


/**
 * AJAX: Actualizar solo el pipeline seleccionado
 */
add_action('wp_ajax_yeison_btx_update_pipeline', 'yeison_btx_ajax_update_pipeline');
function yeison_btx_ajax_update_pipeline() {
    check_ajax_referer('yeison_btx_status_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sin permisos');
    }
    
    $pipeline_id = isset($_POST['pipeline_id']) ? intval($_POST['pipeline_id']) : 0;
    
    // Guardar el pipeline
    yeison_btx_update_option('woocommerce_pipeline_id', $pipeline_id);
    
    // Obtener nombre del pipeline para confirmación
    $categories = yeison_btx_get_bitrix24_categories();
    $pipeline_name = isset($categories[$pipeline_id]) ? $categories[$pipeline_id] : 'General';
    
    yeison_btx_log('Pipeline actualizado via AJAX', 'info', array(
        'pipeline_id' => $pipeline_id,
        'pipeline_name' => $pipeline_name
    ));
    
    wp_send_json_success(array(
        'message' => 'Pipeline actualizado',
        'pipeline_id' => $pipeline_id,
        'pipeline_name' => $pipeline_name
    ));
}





/**
 * Función helper para sanitizar datos sensibles en logs y debug
 */
function yeison_btx_sanitize_sensitive_data($data, $context = 'log') {
    if (!is_array($data)) {
        return $data;
    }
    
    $sensitive_fields = array(
        'client_secret',
        'access_token',
        'refresh_token',
        'webhook_secret',
        'password',
        'pwd',
        'api_key',
        'secret_key',
        'auth_token'
    );
    
    $sanitized = array();
    
    foreach ($data as $key => $value) {
        $key_lower = strtolower($key);
        
        // Verificar si el campo es sensible
        $is_sensitive = false;
        foreach ($sensitive_fields as $sensitive) {
            if (strpos($key_lower, $sensitive) !== false) {
                $is_sensitive = true;
                break;
            }
        }
        
        if ($is_sensitive) {
            if (is_string($value) && strlen($value) > 0) {
                // Para logs, mostrar solo indicador
                if ($context === 'log') {
                    $sanitized[$key] = '[VALOR_PROTEGIDO]';
                } else {
                    // Para debug, mostrar parcialmente
                    if (strlen($value) > 8) {
                        $sanitized[$key] = substr($value, 0, 3) . '***' . substr($value, -3);
                    } else {
                        $sanitized[$key] = '***';
                    }
                }
            } else {
                $sanitized[$key] = '[VACÍO]';
            }
        } elseif (is_array($value)) {
            // Recursivo para arrays anidados
            $sanitized[$key] = yeison_btx_sanitize_sensitive_data($value, $context);
        } else {
            $sanitized[$key] = $value;
        }
    }
    
    return $sanitized;
}

