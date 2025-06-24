<?php

// TEMPORAL - para ver etapas de Deal en Bitrix24  /wp-admin/admin-ajax.php?action=check_deal_stages
add_action('wp_ajax_check_deal_stages', 'yeison_btx_check_deal_stages_temp');
function yeison_btx_check_deal_stages_temp() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    $api = yeison_btx_api();
    $stages = $api->api_call('crm.status.list', array(
        'filter' => array('ENTITY_ID' => 'DEAL_STAGE')
    ));
    
    echo "<h2>🎯 Etapas de Deal en tu Bitrix24:</h2>";
    
    if ($stages && isset($stages['result'])) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Descripción</th></tr>";
        
        foreach ($stages['result'] as $stage) {
            echo "<tr>";
            echo "<td><strong>" . $stage['STATUS_ID'] . "</strong></td>";
            echo "<td>" . $stage['NAME'] . "</td>";
            echo "<td>" . ($stage['DESCRIPTION'] ?? 'Sin descripción') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Error obteniendo etapas: " . print_r($stages, true) . "</p>";
    }
    
    exit;
}


// TEMPORAL - para ver si llegan webhooks
add_action('wp_ajax_check_webhook_logs', 'yeison_btx_check_webhook_logs_temp');
function yeison_btx_check_webhook_logs_temp() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    global $wpdb;
    
    echo "<h2>🔍 Logs de Webhooks (últimos 10 minutos)</h2>";
    
    $webhook_logs = $wpdb->get_results("
        SELECT * FROM {$wpdb->prefix}yeison_btx_logs 
        WHERE (message LIKE '%webhook%' OR message LIKE '%Contact%' OR message LIKE '%Deal%') 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    
    if ($webhook_logs) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Hora</th><th>Tipo</th><th>Mensaje</th><th>Datos</th></tr>";
        
        foreach ($webhook_logs as $log) {
            echo "<tr>";
            echo "<td>" . $log->created_at . "</td>";
            echo "<td style='color: " . ($log->type === 'error' ? 'red' : 'green') . ";'>" . $log->type . "</td>";
            echo "<td>" . $log->message . "</td>";
            echo "<td>" . substr($log->data, 0, 100) . "...</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ NO HAY LOGS de webhooks en los últimos 10 minutos</p>";
        echo "<p><strong>Esto significa que Bitrix24 NO está enviando webhooks.</strong></p>";
    }
    
    exit;
}


// para ver si llegan webhooks         /wp-admin/admin-ajax.php?action=check_webhook_logs
add_action('wp_ajax_check_webhook_logs', 'yeison_btx_check_webhook_logs_temp');
function yeison_btx_check_webhook_logs_temp() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    global $wpdb;
    
    echo "<h2>🔍 Logs de Webhooks (últimos 10 minutos)</h2>";
    
    $webhook_logs = $wpdb->get_results("
        SELECT * FROM {$wpdb->prefix}yeison_btx_logs 
        WHERE (message LIKE '%webhook%' OR message LIKE '%Contact%' OR message LIKE '%Deal%') 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    
    if ($webhook_logs) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Hora</th><th>Tipo</th><th>Mensaje</th><th>Datos</th></tr>";
        
        foreach ($webhook_logs as $log) {
            echo "<tr>";
            echo "<td>" . $log->created_at . "</td>";
            echo "<td style='color: " . ($log->type === 'error' ? 'red' : 'green') . ";'>" . $log->type . "</td>";
            echo "<td>" . $log->message . "</td>";
            echo "<td>" . substr($log->data, 0, 100) . "...</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ NO HAY LOGS de webhooks en los últimos 10 minutos</p>";
        echo "<p><strong>Esto significa que Bitrix24 NO está enviando webhooks.</strong></p>";
    }
    
    exit;
}



// TEMPORAL - para interceptar webhooks de Deal
add_action('wp_ajax_debug_deal_webhook', 'yeison_btx_debug_deal_webhook_temp');
function yeison_btx_debug_deal_webhook_temp() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    echo '<h2>🎯 Interceptor de Webhook de Deal</h2>';
    echo '<p><strong>INSTRUCCIONES:</strong></p>';
    echo '<ol>';
    echo '<li>Deja esta página abierta</li>';
    echo '<li>Ve a Bitrix24 y cambia el estado de un Deal</li>';
    echo '<li>Recarga esta página para ver los datos</li>';
    echo '</ol>';
    
    global $wpdb;
    
    // Últimos logs de webhooks
    $webhook_logs = $wpdb->get_results("
        SELECT * FROM {$wpdb->prefix}yeison_btx_logs 
        WHERE message LIKE '%🎯 Webhook Deal%' 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    
    if ($webhook_logs) {
        echo '<h3>📨 Últimos Webhooks de Deal:</h3>';
        foreach ($webhook_logs as $log) {
            echo '<div style="border: 1px solid #ddd; padding: 10px; margin: 10px 0;">';
            echo '<strong>Hora:</strong> ' . $log->created_at . '<br>';
            echo '<strong>Mensaje:</strong> ' . $log->message . '<br>';
            if (!empty($log->data)) {
                echo '<strong>Datos completos:</strong><br>';
                echo '<pre style="background: #f0f0f0; padding: 10px; max-height: 300px; overflow: auto;">';
                echo esc_html($log->data);
                echo '</pre>';
            }
            echo '</div>';
        }
    } else {
        echo '<p style="color: red;">❌ No hay webhooks de Deal recientes</p>';
    }
    
    exit;
}


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
    
    echo '<h1>🚨 LIMPIEZA NUCLEAR - YEISON BTX</h1>';
    echo '<p style="color: red;"><strong>ADVERTENCIA: Esto eliminará TODOS los datos del plugin</strong></p>';
    
    // Mostrar estado actual antes de limpiar
    if (!isset($_GET['confirm'])) {
        $stats = array(
            'logs' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_logs"),
            'queue' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_queue"),
            'sync' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_sync"),
            'transients' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient%yeison_btx%'")
        );
        
        echo '<h2>📊 Estado Actual:</h2>';
        echo '<ul>';
        echo '<li><strong>Logs:</strong> ' . $stats['logs'] . ' registros</li>';
        echo '<li><strong>Cola:</strong> ' . $stats['queue'] . ' elementos</li>';
        echo '<li><strong>Sincronización:</strong> ' . $stats['sync'] . ' registros</li>';
        echo '<li><strong>Transients:</strong> ' . $stats['transients'] . ' elementos temporales</li>';
        echo '</ul>';
        
        echo '<br><p style="background: red; color: white; padding: 15px; font-size: 18px;">';
        echo '⚠️ ¿Estás COMPLETAMENTE seguro de eliminar TODOS estos datos?<br>';
        echo 'Esta acción NO se puede deshacer.';
        echo '</p>';
        
        echo '<a href="' . admin_url('admin-ajax.php?action=yeison_btx_nuclear_cleanup&confirm=yes') . '" 
              style="background: red; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; margin: 10px;">
              🚨 SÍ, ELIMINAR TODO</a>';
        echo ' ';
        echo '<a href="' . admin_url('admin.php?page=yeison-btx') . '" 
              style="background: gray; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; margin: 10px;">
              ❌ Cancelar</a>';
        
        exit;
    }
    
    // EJECUTAR LIMPIEZA COMPLETA
    echo '<h2>🧹 Iniciando Limpieza Nuclear...</h2>';
    
    $results = array();
    
    // 1. TRUNCAR tabla de logs
    echo '<p>🗑️ Limpiando tabla de logs...</p>';
    $results['logs'] = $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}yeison_btx_logs");
    echo $results['logs'] !== false ? '✅ Logs eliminados<br>' : '❌ Error en logs<br>';
    
    // 2. TRUNCAR tabla de cola
    echo '<p>🗑️ Limpiando tabla de cola...</p>';
    $results['queue'] = $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}yeison_btx_queue");
    echo $results['queue'] !== false ? '✅ Cola eliminada<br>' : '❌ Error en cola<br>';
    
    // 3. TRUNCAR tabla de sincronización
    echo '<p>🗑️ Limpiando tabla de sincronización...</p>';
    $results['sync'] = $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}yeison_btx_sync");
    echo $results['sync'] !== false ? '✅ Sincronización eliminada<br>' : '❌ Error en sincronización<br>';
    
    // 4. Eliminar ALL transients del plugin
    echo '<p>🗑️ Limpiando datos temporales...</p>';
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
    echo "✅ {$transients_deleted} transients eliminados<br>";
    
    // 5. Limpiar configuraciones específicas (mantener auth)
    echo '<p>🗑️ Limpiando configuraciones no esenciales...</p>';
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
    echo '✅ Configuraciones limpiadas (API mantenida)<br>';
    
    // 6. Limpiar scheduled hooks
    echo '<p>🗑️ Limpiando tareas programadas...</p>';
    wp_clear_scheduled_hook('yeison_btx_process_queue');
    wp_clear_scheduled_hook('yeison_btx_cleanup_patterns');
    wp_clear_scheduled_hook('yeison_btx_process_bidirectional_queue');
    wp_clear_scheduled_hook('yeison_btx_woo_sync_cron');
    echo '✅ Tareas programadas eliminadas<br>';
    
    // 7. Limpiar opciones adicionales
    echo '<p>🗑️ Limpiando opciones adicionales...</p>';
    delete_option('yeison_btx_defaults_set');
    delete_option('yeison_btx_version');
    echo '✅ Opciones adicionales eliminadas<br>';
    
    // RESULTADO FINAL
    echo '<br><div style="background: green; color: white; padding: 20px; border-radius: 10px; margin: 20px 0;">';
    echo '<h2>🎉 LIMPIEZA NUCLEAR COMPLETADA</h2>';
    echo '<p><strong>El plugin ha sido completamente reiniciado:</strong></p>';
    echo '<ul>';
    echo '<li>✅ Todas las tablas vaciadas</li>';
    echo '<li>✅ Todos los datos temporales eliminados</li>';
    echo '<li>✅ Configuraciones reiniciadas</li>';
    echo '<li>✅ Tareas programadas limpiadas</li>';
    echo '<li>🔐 Autenticación API mantenida</li>';
    echo '</ul>';
    echo '</div>';
    
    // Log final
    yeison_btx_log('🚨 LIMPIEZA NUCLEAR EJECUTADA', 'warning', array(
        'executed_by' => get_current_user_id(),
        'timestamp' => current_time('mysql'),
        'results' => $results,
        'transients_deleted' => $transients_deleted
    ));
    
    echo '<p><a href="' . admin_url('admin.php?page=yeison-btx') . '" 
          style="background: blue; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px;">
          🏠 Volver al Dashboard</a></p>';
    
    exit;
}

/**
 * Función más suave - Solo limpiar cola y logs (mantener sync)
 * URL: /wp-admin/admin-ajax.php?action=yeison_btx_soft_cleanup
 */
add_action('wp_ajax_yeison_btx_soft_cleanup', 'yeison_btx_soft_cleanup');
function yeison_btx_soft_cleanup() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    global $wpdb;
    
    echo '<h2>🧽 Limpieza Suave - Solo Cola y Logs</h2>';
    
    if (!isset($_GET['confirm'])) {
        $queue_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_queue");
        $logs_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_logs");
        
        echo "<p>Se eliminarán:</p>";
        echo "<ul>";
        echo "<li><strong>Cola:</strong> {$queue_count} elementos</li>";
        echo "<li><strong>Logs:</strong> {$logs_count} registros</li>";
        echo "</ul>";
        echo "<p><strong>Se mantendrán:</strong> Registros de sincronización</p>";
        
        echo '<a href="?action=yeison_btx_soft_cleanup&confirm=yes" 
              style="background: orange; color: white; padding: 10px 20px; text-decoration: none;">
              🧽 Limpiar Cola y Logs</a>';
        exit;
    }
    
    // Ejecutar limpieza suave
    $queue_deleted = $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}yeison_btx_queue");
    $logs_deleted = $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}yeison_btx_logs");
    
    echo '<div style="background: green; color: white; padding: 15px;">';
    echo '<h3>✅ Limpieza Suave Completada</h3>';
    echo '<p>Cola y logs eliminados. Sincronización mantenida.</p>';
    echo '</div>';
    
    exit;
}

/**
 * Solo para desarrolladores - Mostrar estado completo
 * URL: /wp-admin/admin-ajax.php?action=yeison_btx_debug_status
 */
add_action('wp_ajax_yeison_btx_debug_status', 'yeison_btx_debug_status');
function yeison_btx_debug_status() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    global $wpdb;
    
    echo '<h2>🔍 Estado Completo del Sistema</h2>';
    
    // Estadísticas de tablas
    echo '<h3>📊 Tablas:</h3>';
    $tables = array('yeison_btx_logs', 'yeison_btx_queue', 'yeison_btx_sync');
    
    foreach ($tables as $table) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$table}");
        echo "<p><strong>{$table}:</strong> {$count} registros</p>";
    }
    
    // Transients
    echo '<h3>⏰ Transients:</h3>';
    $transients = $wpdb->get_results("
        SELECT option_name, option_value 
        FROM {$wpdb->options} 
        WHERE option_name LIKE '_transient%yeison_btx%' 
        LIMIT 20
    ");
    
    if ($transients) {
        echo '<ul>';
        foreach ($transients as $transient) {
            echo '<li>' . $transient->option_name . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>No hay transients activos</p>';
    }
    
    // Configuraciones
    echo '<h3>⚙️ Configuraciones:</h3>';
    $settings = get_option('yeison_btx_settings', array());
    echo '<pre>' . print_r($settings, true) . '</pre>';
    
    // Enlaces rápidos
    echo '<h3>🔗 Acciones Rápidas:</h3>';
    echo '<p>';
    echo '<a href="?action=yeison_btx_soft_cleanup" style="background: orange; color: white; padding: 10px; margin: 5px; text-decoration: none;">🧽 Limpieza Suave</a>';
    echo '<a href="?action=yeison_btx_nuclear_cleanup" style="background: red; color: white; padding: 10px; margin: 5px; text-decoration: none;">🚨 Limpieza Nuclear</a>';
    echo '</p>';
    
    exit;
}










/**
 * Hook para interceptar y debuggear la sincronización Deal → Order
 */
add_action('yeison_btx_deal_webhook_received', 'yeison_btx_debug_deal_to_order_sync', 999, 2);
function yeison_btx_debug_deal_to_order_sync($event, $deal_fields) {
    if ($event !== 'ONCRMDEALUPDATE') {
        return; // Solo procesar actualizaciones
    }
    
    yeison_btx_log('🚀 INICIANDO debug de sincronización Deal → Order', 'info', array(
        'deal_id' => $deal_fields['ID'] ?? 'unknown',
        'stage' => $deal_fields['STAGE_ID'] ?? 'unknown'
    ));
    
    // 1. Verificar si la sincronización bidireccional está habilitada
    $bidirectional_enabled = yeison_btx_get_option('bidirectional_sync_enabled', false);
    $deal_to_order_enabled = yeison_btx_get_option('sync_deal_to_order', false);
    
    yeison_btx_log('⚙️ Verificando configuración de sincronización', 'info', array(
        'bidirectional_sync_enabled' => $bidirectional_enabled,
        'sync_deal_to_order' => $deal_to_order_enabled
    ));
    
    if (!$bidirectional_enabled || !$deal_to_order_enabled) {
        yeison_btx_log('❌ Sincronización Deal → Order DESHABILITADA en configuración', 'warning');
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
    yeison_btx_log('✅ Pedido WooCommerce encontrado', 'success', array(
        'order_id' => $order_id,
        'deal_id' => $deal_fields['ID']
    ));
    
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
    
    yeison_btx_log('📦 Estado actual del pedido WooCommerce', 'info', array(
        'order_id' => $order_id,
        'current_woo_status' => $order->get_status(),
        'bitrix_stage' => $deal_fields['STAGE_ID'] ?? 'unknown'
    ));
    
    // 5. Mapear el estado de Bitrix24 a WooCommerce
    $new_wc_status = yeison_btx_map_bitrix_stage_to_wc_status($deal_fields['STAGE_ID']);
    yeison_btx_log('🔄 Mapeando estado Bitrix → WooCommerce', 'info', array(
        'bitrix_stage' => $deal_fields['STAGE_ID'],
        'mapped_wc_status' => $new_wc_status,
        'current_wc_status' => $order->get_status()
    ));
    
    if (!$new_wc_status) {
        yeison_btx_log('⚠️ No se pudo mapear el estado de Bitrix24 a WooCommerce', 'warning', array(
            'bitrix_stage' => $deal_fields['STAGE_ID']
        ));
        return;
    }
    
    if ($new_wc_status === $order->get_status()) {
        yeison_btx_log('ℹ️ El estado ya es el mismo, no se necesita actualizar', 'info', array(
            'current_status' => $order->get_status(),
            'new_status' => $new_wc_status
        ));
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
    
    // 7. INTENTAR ACTUALIZAR EL PEDIDO
    yeison_btx_log('🔄 Intentando actualizar estado del pedido', 'info', array(
        'order_id' => $order_id,
        'from_status' => $order->get_status(),
        'to_status' => $new_wc_status
    ));
    
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
            yeison_btx_log('🎉 ÉXITO: Pedido actualizado correctamente', 'success', array(
                'order_id' => $order_id,
                'new_status' => $new_wc_status,
                'deal_id' => $deal_fields['ID'],
                'bitrix_stage' => $deal_fields['STAGE_ID']
            ));
            
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
    
    yeison_btx_log('🗺️ Mapeo de estado realizado', 'debug', array(
        'bitrix_stage' => $bitrix_stage,
        'wc_status' => $result,
        'mapping_used' => $mapping
    ));
    
    return $result;
}

/**
 * Test directo para forzar la sincronización de un Deal específico
 * URL: /wp-admin/admin-ajax.php?action=yeison_btx_test_deal_sync&deal_id=34
 */
add_action('wp_ajax_yeison_btx_test_deal_sync', 'yeison_btx_test_deal_sync');
function yeison_btx_test_deal_sync() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    $deal_id = $_GET['deal_id'] ?? '34';
    
    echo '<h2>🧪 Test Directo de Sincronización Deal → Order</h2>';
    echo '<p><strong>Deal ID:</strong> ' . $deal_id . '</p>';
    
    // 1. Obtener datos del Deal desde Bitrix24
    echo '<h3>📡 Paso 1: Obteniendo datos del Deal desde Bitrix24...</h3>';
    $api = yeison_btx_api();
    
    if (!$api->is_authorized()) {
        echo '<div style="background: red; color: white; padding: 15px;">❌ API no autorizada</div>';
        exit;
    }
    
    $deal_response = $api->api_call('crm.deal.get', array('id' => $deal_id));
    
    if (!$deal_response || !isset($deal_response['result'])) {
        echo '<div style="background: red; color: white; padding: 15px;">❌ No se pudo obtener el Deal</div>';
        echo '<pre>' . print_r($deal_response, true) . '</pre>';
        exit;
    }
    
    $deal_data = $deal_response['result'];
    echo '<div style="background: green; color: white; padding: 10px;">✅ Deal obtenido correctamente</div>';
    echo '<p><strong>Título:</strong> ' . $deal_data['TITLE'] . '</p>';
    echo '<p><strong>Estado actual:</strong> ' . $deal_data['STAGE_ID'] . '</p>';
    echo '<p><strong>Monto:</strong> ' . $deal_data['OPPORTUNITY'] . ' ' . $deal_data['CURRENCY_ID'] . '</p>';
    
    // 2. Buscar pedido WooCommerce vinculado
    echo '<h3>🔍 Paso 2: Buscando pedido WooCommerce vinculado...</h3>';
    global $wpdb;
    
    $sync_record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}yeison_btx_sync 
        WHERE entity_type = 'woo_order' AND remote_id = %s",
        $deal_id
    ));
    
    if (!$sync_record) {
        echo '<div style="background: red; color: white; padding: 15px;">❌ No se encontró pedido WooCommerce vinculado</div>';
        exit;
    }
    
    $order_id = $sync_record->local_id;
    echo '<div style="background: green; color: white; padding: 10px;">✅ Pedido encontrado: #' . $order_id . '</div>';
    
    // 3. Verificar WooCommerce
    echo '<h3>🛒 Paso 3: Verificando WooCommerce...</h3>';
    if (!class_exists('WooCommerce')) {
        echo '<div style="background: red; color: white; padding: 15px;">❌ WooCommerce no está activo</div>';
        exit;
    }
    
    $order = wc_get_order($order_id);
    if (!$order) {
        echo '<div style="background: red; color: white; padding: 15px;">❌ Pedido no encontrado en WooCommerce</div>';
        exit;
    }
    
    echo '<div style="background: green; color: white; padding: 10px;">✅ Pedido WooCommerce válido</div>';
    echo '<p><strong>Estado actual:</strong> ' . $order->get_status() . '</p>';
    echo '<p><strong>Total:</strong> ' . $order->get_formatted_order_total() . '</p>';
    
    // 4. Mapear estado
    echo '<h3>🗺️ Paso 4: Mapeando estado Bitrix → WooCommerce...</h3>';
    $bitrix_stage = $deal_data['STAGE_ID'];
    $new_wc_status = yeison_btx_map_bitrix_stage_to_wc_status($bitrix_stage);
    
    echo '<p><strong>Estado Bitrix24:</strong> ' . $bitrix_stage . '</p>';
    echo '<p><strong>Estado WooCommerce actual:</strong> ' . $order->get_status() . '</p>';
    echo '<p><strong>Estado WooCommerce que debería ser:</strong> ' . $new_wc_status . '</p>';
    
    if (!$new_wc_status) {
        echo '<div style="background: orange; color: black; padding: 15px;">⚠️ No se pudo mapear el estado</div>';
        exit;
    }
    
    if ($new_wc_status === $order->get_status()) {
        echo '<div style="background: blue; color: white; padding: 15px;">ℹ️ El estado ya es correcto, no se necesita cambio</div>';
        exit;
    }
    
    // 5. INTENTAR ACTUALIZAR
    echo '<h3>🔄 Paso 5: Actualizando pedido...</h3>';
    
    try {
        $old_status = $order->get_status();
        
        $update_result = $order->update_status(
            $new_wc_status,
            sprintf('✅ Estado actualizado desde Bitrix24 Deal #%s - Test directo', $deal_id)
        );
        
        if ($update_result) {
            echo '<div style="background: green; color: white; padding: 15px; font-size: 18px;">';
            echo '<h3>🎉 ¡ÉXITO TOTAL!</h3>';
            echo '<p>✅ Pedido actualizado correctamente</p>';
            echo '<p><strong>Cambio:</strong> ' . $old_status . ' → ' . $new_wc_status . '</p>';
            echo '</div>';
            
            // Actualizar registro de sincronización
            $wpdb->update(
                $wpdb->prefix . 'yeison_btx_sync',
                array(
                    'last_sync' => current_time('mysql'),
                    'sync_data' => wp_json_encode(array(
                        'test_sync' => true,
                        'last_direction' => 'from_bitrix24_test',
                        'status_change' => $old_status . ' → ' . $new_wc_status,
                        'deal_stage' => $bitrix_stage
                    ))
                ),
                array('id' => $sync_record->id)
            );
            
            yeison_btx_log('🧪 TEST DIRECTO: Pedido actualizado exitosamente', 'success', array(
                'deal_id' => $deal_id,
                'order_id' => $order_id,
                'status_change' => $old_status . ' → ' . $new_wc_status,
                'bitrix_stage' => $bitrix_stage
            ));
            
        } else {
            echo '<div style="background: red; color: white; padding: 15px;">';
            echo '<h3>❌ FALLÓ LA ACTUALIZACIÓN</h3>';
            echo '<p>La función update_status() devolvió false</p>';
            echo '</div>';
        }
        
    } catch (Exception $e) {
        echo '<div style="background: red; color: white; padding: 15px;">';
        echo '<h3>💥 EXCEPCIÓN</h3>';
        echo '<p><strong>Error:</strong> ' . $e->getMessage() . '</p>';
        echo '<p><strong>Archivo:</strong> ' . $e->getFile() . ':' . $e->getLine() . '</p>';
        echo '</div>';
    }
    
    echo '<hr>';
    echo '<p><a href="' . admin_url('post.php?post=' . $order_id . '&action=edit') . '" target="_blank">🔗 Ver pedido en WooCommerce</a></p>';
    echo '<p><a href="' . admin_url('admin-ajax.php?action=yeison_btx_debug_deal_changes') . '">🔍 Volver al debug</a></p>';
    
    exit;
}



/**
 * Interceptor de datos de webhook Deal con logging detallado
 * URL: /wp-admin/admin-ajax.php?action=yeison_btx_debug_deal_changes
 */
add_action('wp_ajax_yeison_btx_debug_deal_changes', 'yeison_btx_debug_deal_changes');
function yeison_btx_debug_deal_changes() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    global $wpdb;
    
    echo '<h2>🎯 Debug: Cambios de Deal desde Bitrix24</h2>';
    echo '<p><strong>INSTRUCCIONES:</strong></p>';
    echo '<ol>';
    echo '<li>Deja esta página abierta</li>';
    echo '<li>Ve a Bitrix24 y cambia el estado de un Deal (ej: de "En proceso" a "Completado")</li>';
    echo '<li>Espera 10 segundos y recarga esta página</li>';
    echo '</ol>';
    
    // Mostrar últimos logs de webhooks Deal
    $deal_logs = $wpdb->get_results("
        SELECT * FROM {$wpdb->prefix}yeison_btx_logs 
        WHERE (message LIKE '%Deal%' OR message LIKE '%webhook%' OR message LIKE '%🎯%') 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    
    if ($deal_logs) {
        echo '<h3>📨 Webhooks Deal Recientes (última hora):</h3>';
        foreach ($deal_logs as $log) {
            echo '<div style="border: 2px solid #007cba; padding: 15px; margin: 10px 0; background: #f8f9fa;">';
            echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">';
            echo '<strong style="color: #007cba;">⏰ ' . $log->created_at . '</strong>';
            echo '<span style="background: ' . ($log->type == 'error' ? 'red' : ($log->type == 'success' ? 'green' : 'orange')) . '; color: white; padding: 5px 10px; border-radius: 3px;">' . strtoupper($log->type) . '</span>';
            echo '</div>';
            echo '<div style="margin-bottom: 10px;"><strong>📝 Mensaje:</strong> ' . esc_html($log->message) . '</div>';
            
            if (!empty($log->data)) {
                $data = json_decode($log->data, true);
                echo '<details style="margin-top: 10px;">';
                echo '<summary style="cursor: pointer; font-weight: bold; color: #007cba;">🔍 Ver datos completos del webhook</summary>';
                echo '<div style="background: #f0f0f0; padding: 15px; margin-top: 10px; border-radius: 5px; max-height: 400px; overflow-y: auto;">';
                
                if ($data) {
                    // Mostrar datos estructurados
                    if (isset($data['deal_id'])) {
                        echo '<p><strong>🆔 Deal ID:</strong> ' . $data['deal_id'] . '</p>';
                    }
                    if (isset($data['stage'])) {
                        echo '<p><strong>📊 Estado/Etapa:</strong> ' . $data['stage'] . '</p>';
                    }
                    if (isset($data['opportunity'])) {
                        echo '<p><strong>💰 Monto:</strong> ' . $data['opportunity'] . '</p>';
                    }
                    if (isset($data['order_id'])) {
                        echo '<p><strong>🛒 Order ID WooCommerce:</strong> ' . $data['order_id'] . '</p>';
                    }
                    
                    echo '<hr>';
                    echo '<pre style="font-size: 12px; background: white; padding: 10px; border-radius: 3px;">' . print_r($data, true) . '</pre>';
                } else {
                    echo '<pre style="font-size: 12px;">' . esc_html($log->data) . '</pre>';
                }
                echo '</div></details>';
            }
            echo '</div>';
        }
    } else {
        echo '<div style="background: #ffcccc; border: 1px solid red; padding: 15px; border-radius: 5px;">';
        echo '<p><strong>❌ No hay webhooks de Deal recientes</strong></p>';
        echo '<p>Esto puede significar que:</p>';
        echo '<ul>';
        echo '<li>Los webhooks no están registrados en Bitrix24</li>';
        echo '<li>Los webhooks no están llegando correctamente</li>';
        echo '<li>No has hecho cambios recientes a un Deal</li>';
        echo '</ul>';
        echo '</div>';
    }
    
    // Estado de la configuración
    echo '<h3>⚙️ Estado de Configuración:</h3>';
    echo '<div style="background: #e6f3ff; padding: 15px; border-radius: 5px;">';
    
    $api = yeison_btx_api();
    echo '<p><strong>🔗 API Autorizada:</strong> ' . ($api->is_authorized() ? '✅ Sí' : '❌ No') . '</p>';
    
    $bidirectional = yeison_btx_get_option('bidirectional_sync_enabled', false);
    echo '<p><strong>🔄 Sincronización Bidireccional:</strong> ' . ($bidirectional ? '✅ Habilitada' : '❌ Deshabilitada') . '</p>';
    
    $deal_to_order = yeison_btx_get_option('sync_deal_to_order', false);
    echo '<p><strong>📦 Deal → Order:</strong> ' . ($deal_to_order ? '✅ Habilitada' : '❌ Deshabilitada') . '</p>';
    
    echo '<p><strong>🌐 Endpoint Deal:</strong> <code>' . rest_url('yeison-bitrix/v1/webhook/deal') . '</code></p>';
    echo '</div>';
    
    // Tabla de sincronización actual
    echo '<h3>🔗 Pedidos Sincronizados Recientemente:</h3>';
    $synced_orders = $wpdb->get_results("
        SELECT * FROM {$wpdb->prefix}yeison_btx_sync 
        WHERE entity_type = 'woo_order' 
        ORDER BY last_sync DESC 
        LIMIT 10
    ");
    
    if ($synced_orders) {
        echo '<table border="1" style="border-collapse: collapse; width: 100%; margin-top: 10px;">';
        echo '<tr style="background: #f0f0f0;">';
        echo '<th style="padding: 8px;">Order ID</th>';
        echo '<th style="padding: 8px;">Deal ID</th>';
        echo '<th style="padding: 8px;">Estado</th>';
        echo '<th style="padding: 8px;">Última Sync</th>';
        echo '<th style="padding: 8px;">Acción</th>';
        echo '</tr>';
        
        foreach ($synced_orders as $sync) {
            $sync_data = json_decode($sync->sync_data, true);
            echo '<tr>';
            echo '<td style="padding: 8px; text-align: center;"><strong>' . $sync->local_id . '</strong></td>';
            echo '<td style="padding: 8px; text-align: center;"><strong>' . $sync->remote_id . '</strong></td>';
            echo '<td style="padding: 8px; text-align: center;">' . $sync->sync_status . '</td>';
            echo '<td style="padding: 8px; text-align: center;">' . $sync->last_sync . '</td>';
            echo '<td style="padding: 8px; text-align: center;">';
            echo '<a href="' . admin_url('post.php?post=' . $sync->local_id . '&action=edit') . '" target="_blank" style="text-decoration: none;">🔗 Ver Pedido</a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p style="color: orange;">⚠️ No hay pedidos sincronizados aún</p>';
    }
    
    echo '<hr>';
    echo '<p><strong>🔄 Acciones rápidas:</strong></p>';
    echo '<a href="' . admin_url('admin-ajax.php?action=yeison_btx_force_webhooks') . '" style="background: blue; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; margin: 5px;">🔧 Forzar Registro Webhooks</a>';
    echo '<a href="' . admin_url('admin.php?page=yeison-btx') . '" style="background: gray; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; margin: 5px;">🏠 Volver al Dashboard</a>';
    
    exit;
}

/**
 * Hook temporal para capturar TODOS los datos que llegan del webhook Deal
 * Se ejecuta automáticamente cuando llega un webhook
 */
add_action('yeison_btx_deal_webhook_received', 'yeison_btx_debug_deal_webhook_data', 5, 2);
function yeison_btx_debug_deal_webhook_data($event, $deal_fields) {
    // Log súper detallado de lo que recibimos
    yeison_btx_log('🔍 DEBUG: Webhook Deal recibido - DATOS COMPLETOS', 'info', array(
        'timestamp' => current_time('mysql'),
        'event_received' => $event,
        'deal_id' => $deal_fields['ID'] ?? 'NO_ID',
        'deal_title' => $deal_fields['TITLE'] ?? 'Sin título',
        'deal_stage' => $deal_fields['STAGE_ID'] ?? 'Sin etapa',
        'deal_opportunity' => $deal_fields['OPPORTUNITY'] ?? 'Sin monto',
        'deal_currency' => $deal_fields['CURRENCY_ID'] ?? 'Sin moneda',
        'all_fields_received' => $deal_fields,
        'fields_count' => count($deal_fields),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ));
    
    // Si tenemos Deal ID, buscar el pedido WooCommerce correspondiente
    if (!empty($deal_fields['ID'])) {
        global $wpdb;
        
        $sync_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}yeison_btx_sync 
            WHERE entity_type = 'woo_order' AND remote_id = %s",
            $deal_fields['ID']
        ));
        
        if ($sync_record) {
            yeison_btx_log('🎯 Deal vinculado a pedido WooCommerce encontrado', 'success', array(
                'deal_id' => $deal_fields['ID'],
                'woo_order_id' => $sync_record->local_id,
                'sync_status' => $sync_record->sync_status,
                'last_sync' => $sync_record->last_sync
            ));
            
            // Intentar obtener el pedido de WooCommerce
            if (class_exists('WooCommerce') && function_exists('wc_get_order')) {
                $order = wc_get_order($sync_record->local_id);
                if ($order) {
                    yeison_btx_log('📦 Estado actual del pedido WooCommerce', 'info', array(
                        'order_id' => $order->get_id(),
                        'current_status' => $order->get_status(),
                        'total' => $order->get_total(),
                        'currency' => $order->get_currency(),
                        'bitrix_stage' => $deal_fields['STAGE_ID'] ?? 'unknown'
                    ));
                } else {
                    yeison_btx_log('⚠️ Pedido WooCommerce no encontrado', 'warning', array(
                        'order_id' => $sync_record->local_id,
                        'deal_id' => $deal_fields['ID']
                    ));
                }
            }
        } else {
            yeison_btx_log('⚠️ Deal no tiene pedido WooCommerce vinculado', 'warning', array(
                'deal_id' => $deal_fields['ID'],
                'suggestion' => 'Puede ser un Deal creado directamente en Bitrix24'
            ));
        }
    }
}































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
    
    echo '<h1>🚨 LIMPIEZA NUCLEAR - YEISON BTX</h1>';
    echo '<p style="color: red;"><strong>ADVERTENCIA: Esto eliminará TODOS los datos del plugin</strong></p>';
    
    // Mostrar estado actual antes de limpiar
    if (!isset($_GET['confirm'])) {
        $stats = array(
            'logs' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_logs"),
            'queue' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_queue"),
            'sync' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_sync"),
            'transients' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient%yeison_btx%'")
        );
        
        echo '<h2>📊 Estado Actual:</h2>';
        echo '<ul>';
        echo '<li><strong>Logs:</strong> ' . $stats['logs'] . ' registros</li>';
        echo '<li><strong>Cola:</strong> ' . $stats['queue'] . ' elementos</li>';
        echo '<li><strong>Sincronización:</strong> ' . $stats['sync'] . ' registros</li>';
        echo '<li><strong>Transients:</strong> ' . $stats['transients'] . ' elementos temporales</li>';
        echo '</ul>';
        
        echo '<br><p style="background: red; color: white; padding: 15px; font-size: 18px;">';
        echo '⚠️ ¿Estás COMPLETAMENTE seguro de eliminar TODOS estos datos?<br>';
        echo 'Esta acción NO se puede deshacer.';
        echo '</p>';
        
        echo '<a href="' . admin_url('admin-ajax.php?action=yeison_btx_nuclear_cleanup&confirm=yes') . '" 
              style="background: red; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; margin: 10px;">
              🚨 SÍ, ELIMINAR TODO</a>';
        echo ' ';
        echo '<a href="' . admin_url('admin.php?page=yeison-btx') . '" 
              style="background: gray; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; margin: 10px;">
              ❌ Cancelar</a>';
        
        exit;
    }
    
    // EJECUTAR LIMPIEZA COMPLETA
    echo '<h2>🧹 Iniciando Limpieza Nuclear...</h2>';
    
    $results = array();
    
    // 1. TRUNCAR tabla de logs
    echo '<p>🗑️ Limpiando tabla de logs...</p>';
    $results['logs'] = $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}yeison_btx_logs");
    echo $results['logs'] !== false ? '✅ Logs eliminados<br>' : '❌ Error en logs<br>';
    
    // 2. TRUNCAR tabla de cola
    echo '<p>🗑️ Limpiando tabla de cola...</p>';
    $results['queue'] = $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}yeison_btx_queue");
    echo $results['queue'] !== false ? '✅ Cola eliminada<br>' : '❌ Error en cola<br>';
    
    // 3. TRUNCAR tabla de sincronización
    echo '<p>🗑️ Limpiando tabla de sincronización...</p>';
    $results['sync'] = $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}yeison_btx_sync");
    echo $results['sync'] !== false ? '✅ Sincronización eliminada<br>' : '❌ Error en sincronización<br>';
    
    // 4. Eliminar ALL transients del plugin
    echo '<p>🗑️ Limpiando datos temporales...</p>';
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
    echo "✅ {$transients_deleted} transients eliminados<br>";
    
    // 5. Limpiar configuraciones específicas (mantener auth)
    echo '<p>🗑️ Limpiando configuraciones no esenciales...</p>';
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
    echo '✅ Configuraciones limpiadas (API mantenida)<br>';
    
    // 6. Limpiar scheduled hooks
    echo '<p>🗑️ Limpiando tareas programadas...</p>';
    wp_clear_scheduled_hook('yeison_btx_process_queue');
    wp_clear_scheduled_hook('yeison_btx_cleanup_patterns');
    wp_clear_scheduled_hook('yeison_btx_process_bidirectional_queue');
    wp_clear_scheduled_hook('yeison_btx_woo_sync_cron');
    echo '✅ Tareas programadas eliminadas<br>';
    
    // 7. Limpiar opciones adicionales
    echo '<p>🗑️ Limpiando opciones adicionales...</p>';
    delete_option('yeison_btx_defaults_set');
    delete_option('yeison_btx_version');
    echo '✅ Opciones adicionales eliminadas<br>';
    
    // RESULTADO FINAL
    echo '<br><div style="background: green; color: white; padding: 20px; border-radius: 10px; margin: 20px 0;">';
    echo '<h2>🎉 LIMPIEZA NUCLEAR COMPLETADA</h2>';
    echo '<p><strong>El plugin ha sido completamente reiniciado:</strong></p>';
    echo '<ul>';
    echo '<li>✅ Todas las tablas vaciadas</li>';
    echo '<li>✅ Todos los datos temporales eliminados</li>';
    echo '<li>✅ Configuraciones reiniciadas</li>';
    echo '<li>✅ Tareas programadas limpiadas</li>';
    echo '<li>🔐 Autenticación API mantenida</li>';
    echo '</ul>';
    echo '</div>';
    
    // Log final
    yeison_btx_log('🚨 LIMPIEZA NUCLEAR EJECUTADA', 'warning', array(
        'executed_by' => get_current_user_id(),
        'timestamp' => current_time('mysql'),
        'results' => $results,
        'transients_deleted' => $transients_deleted
    ));
    
    echo '<p><a href="' . admin_url('admin.php?page=yeison-btx') . '" 
          style="background: blue; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px;">
          🏠 Volver al Dashboard</a></p>';
    
    exit;
}























































/* 

¿Qué hace este código?
php// Hook para procesamiento diferido de cola
add_action('yeison_btx_process_delayed_queue', function($queue_id) {
    $forms_handler = yeison_btx_forms();
    $forms_handler->process_single_queue_item($queue_id);
});
📋 Desglose línea por línea:
1. add_action('yeison_btx_process_delayed_queue', ...)

Registra un hook personalizado de WordPress
El hook se llama yeison_btx_process_delayed_queue
Cuando alguien dispare este hook, se ejecutará la función

2. function($queue_id) { ... }

Es una función anónima (closure) que recibe un parámetro: $queue_id
Este $queue_id es el ID del elemento en la cola que necesita ser procesado

3. $forms_handler = yeison_btx_forms();

Obtiene la instancia del manejador de formularios
yeison_btx_forms() es nuestra función global que retorna el singleton

4. $forms_handler->process_single_queue_item($queue_id);

Llama al método que procesa el elemento de la cola
Intenta crear el Lead en Bitrix24


🎯 ¿Para qué sirve este hook?
Problema que resuelve:
En el código actualizado, cuando un formulario se procesa, tenemos dos estrategias:
php// Opción 1: Procesar directamente (método mejorado)
$this->process_single_queue_item_with_retry($queue_id);

// Opción 2: Programar para 2 segundos después (backup)
wp_schedule_single_event(time() + 2, 'yeison_btx_process_delayed_queue', array($queue_id));
¿Cuándo se usa?

Si el procesamiento inmediato falla (por timing de BD)
Como backup/respaldo para asegurar que el formulario se procese
Para casos de alta concurrencia donde múltiples formularios llegan al mismo tiempo


🔄 Flujo completo:
mermaidgraph TD
    A[Formulario enviado] --> B[Agregar a cola BD]
    B --> C[Intentar procesar inmediatamente]
    C --> D{¿Éxito?}
    D -->|SÍ| E[✅ Terminado]
    D -->|NO| F[Programar procesamiento diferido]
    F --> G[Esperar 2 segundos]
    G --> H[Hook se dispara]
    H --> I[Procesar elemento de cola]
    I --> J[✅ Lead creado en Bitrix24]

🛡️ Beneficios de este enfoque:
1. Redundancia

Si falla el procesamiento inmediato, hay un backup
Garantiza que ningún formulario se pierda

2. Manejo de timing

Los 2 segundos de delay permiten que la BD termine la transacción
Evita problemas de concurrencia

3. Asíncrono

No bloquea la respuesta al usuario
El usuario ve "éxito" inmediatamente
El procesamiento real ocurre en background

4. WordPress nativo

Usa el sistema de cron de WordPress
Se ejecuta en el próximo request HTTP
Robusto y confiable

 */

// Hook para procesamiento diferido de cola
add_action('yeison_btx_process_delayed_queue', function($queue_id) {
    $forms_handler = yeison_btx_forms();
    $forms_handler->process_single_queue_item($queue_id);
});

































/**
 * Script de Test para verificar prevención de Leads duplicados
 * 
 * Acceder desde: /wp-admin/admin-ajax.php?action=yeison_btx_test_duplicate_leads
 */

// Agregar esta función a functions.php
add_action('wp_ajax_yeison_btx_test_duplicate_leads', 'yeison_btx_test_duplicate_leads');
function yeison_btx_test_duplicate_leads() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    echo '<h1>🧪 Test de Prevención de Leads Duplicados</h1>';
    echo '<style>
        .test-section { background: #f9f9f9; padding: 15px; margin: 10px 0; border-left: 4px solid #0073aa; }
        .success { border-left-color: #28a745; background: #d4edda; }
        .error { border-left-color: #dc3545; background: #f8d7da; }
        .warning { border-left-color: #ffc107; background: #fff3cd; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>';
    
    // Email de test único
    $test_email = 'test-' . time() . '@example.com';
    
    echo '<div class="test-section">';
    echo '<h2>📧 Email de Test: ' . $test_email . '</h2>';
    echo '<p>Este test enviará 2 formularios con el mismo email para verificar que:</p>';
    echo '<ul>';
    echo '<li>✅ El primer formulario cree un Lead nuevo</li>';
    echo '<li>✅ El segundo formulario cree una actividad (NO un Lead duplicado)</li>';
    echo '</ul>';
    echo '</div>';
    
    // Test 1: Primer formulario (debe crear Lead)
    echo '<div class="test-section">';
    echo '<h3>🔵 Test 1: Primer Formulario (debe crear Lead nuevo)</h3>';
    
    $form_data_1 = array(
        'name' => 'Juan',
        'last_name' => 'Pérez',
        'email' => $test_email,
        'phone' => '89887777',
        'company' => 'Test Company',
        'message' => 'Este es el primer mensaje de test',
        '_start_time' => time() - 10,
        'website' => '' // Honeypot vacío
    );
    
    echo '<p><strong>Datos del formulario 1:</strong></p>';
    echo '<pre>' . print_r($form_data_1, true) . '</pre>';
    
    // Procesar formulario 1
    $forms_handler = yeison_btx_forms();
    $result_1 = $forms_handler->process_form_submission($form_data_1, 'test_script', 'https://test.example.com');
    
    echo '<p><strong>Resultado:</strong></p>';
    if ($result_1['success']) {
        echo '<div class="success">✅ Formulario 1 procesado exitosamente</div>';
        echo '<pre>' . print_r($result_1, true) . '</pre>';
        
        // Esperar un momento para asegurar que se procese
        sleep(2);
        
        // Verificar que se creó el Lead
        $queue_id_1 = $result_1['data']['queue_id'] ?? null;
        if ($queue_id_1) {
            echo '<p>🔍 Verificando procesamiento de cola (ID: ' . $queue_id_1 . ')...</p>';
            
            global $wpdb;
            $queue_status = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}yeison_btx_queue WHERE id = %d",
                $queue_id_1
            ));
            
            if ($queue_status) {
                echo '<p><strong>Estado de la cola:</strong></p>';
                echo '<pre>' . print_r($queue_status, true) . '</pre>';
                
                if ($queue_status->status === 'processed') {
                    echo '<div class="success">✅ Cola procesada correctamente - Lead creado</div>';
                } else {
                    echo '<div class="warning">⚠️ Cola pendiente de procesamiento</div>';
                }
            }
        }
    } else {
        echo '<div class="error">❌ Error procesando formulario 1</div>';
        echo '<pre>' . print_r($result_1, true) . '</pre>';
    }
    echo '</div>';
    
    // Esperar antes del segundo test
    sleep(3);
    
    // Test 2: Segundo formulario (debe crear actividad)
    echo '<div class="test-section">';
    echo '<h3>🟡 Test 2: Segundo Formulario (debe crear actividad, NO Lead duplicado)</h3>';
    
    $form_data_2 = array(
        'name' => 'Juan',
        'last_name' => 'Pérez',
        'email' => $test_email, // ¡MISMO EMAIL!
        'phone' => '89887777',
        'company' => 'Test Company Updated',
        'message' => 'Este es el SEGUNDO mensaje de test - debería crear actividad',
        '_start_time' => time() - 8,
        'website' => '' // Honeypot vacío
    );
    
    echo '<p><strong>Datos del formulario 2 (MISMO EMAIL):</strong></p>';
    echo '<pre>' . print_r($form_data_2, true) . '</pre>';
    
    // Procesar formulario 2
    $result_2 = $forms_handler->process_form_submission($form_data_2, 'test_script', 'https://test.example.com');
    
    echo '<p><strong>Resultado:</strong></p>';
    if ($result_2['success']) {
        echo '<div class="success">✅ Formulario 2 procesado exitosamente</div>';
        echo '<pre>' . print_r($result_2, true) . '</pre>';
        
        // Esperar un momento para asegurar que se procese
        sleep(2);
        
        // Verificar que se creó actividad (no Lead)
        $queue_id_2 = $result_2['data']['queue_id'] ?? null;
        if ($queue_id_2) {
            echo '<p>🔍 Verificando procesamiento de cola (ID: ' . $queue_id_2 . ')...</p>';
            
            $queue_status_2 = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}yeison_btx_queue WHERE id = %d",
                $queue_id_2
            ));
            
            if ($queue_status_2) {
                echo '<p><strong>Estado de la cola:</strong></p>';
                echo '<pre>' . print_r($queue_status_2, true) . '</pre>';
                
                if ($queue_status_2->status === 'processed') {
                    echo '<div class="success">✅ Cola procesada correctamente - Debería ser actividad</div>';
                } else {
                    echo '<div class="warning">⚠️ Cola pendiente de procesamiento</div>';
                }
            }
        }
    } else {
        echo '<div class="error">❌ Error procesando formulario 2</div>';
        echo '<pre>' . print_r($result_2, true) . '</pre>';
    }
    echo '</div>';
    
    // Verificar logs para ver qué pasó
    echo '<div class="test-section">';
    echo '<h3>📝 Logs del Sistema (últimos 20)</h3>';
    
    $recent_logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}yeison_btx_logs 
        WHERE message LIKE %s OR message LIKE %s OR message LIKE %s
        ORDER BY created_at DESC 
        LIMIT 20",
        '%' . $test_email . '%',
        '%Lead existente%',
        '%actividad%'
    ));
    
    if ($recent_logs) {
        echo '<table border="1" style="width: 100%; border-collapse: collapse;">';
        echo '<tr><th>Hora</th><th>Tipo</th><th>Mensaje</th><th>Datos</th></tr>';
        
        foreach ($recent_logs as $log) {
            $color = array(
                'error' => '#ffebee',
                'success' => '#e8f5e8', 
                'warning' => '#fff8e1',
                'info' => '#e3f2fd'
            );
            $bg_color = $color[$log->type] ?? '#f5f5f5';
            
            echo '<tr style="background: ' . $bg_color . ';">';
            echo '<td>' . $log->created_at . '</td>';
            echo '<td><strong>' . strtoupper($log->type) . '</strong></td>';
            echo '<td>' . esc_html($log->message) . '</td>';
            echo '<td><small>' . esc_html(substr($log->data, 0, 200)) . '...</small></td>';
            echo '</tr>';
        }
        
        echo '</table>';
    } else {
        echo '<p>No se encontraron logs relacionados.</p>';
    }
    echo '</div>';
    
    // Resumen final
    echo '<div class="test-section">';
    echo '<h3>📊 Resumen del Test</h3>';
    
    $total_leads = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_sync WHERE entity_type = 'form_lead'"
    ));
    
    $total_queue = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_queue WHERE status = 'processed'"
    ));
    
    echo '<ul>';
    echo '<li><strong>Total Leads sincronizados:</strong> ' . $total_leads . '</li>';
    echo '<li><strong>Total elementos de cola procesados:</strong> ' . $total_queue . '</li>';
    echo '<li><strong>Email de test usado:</strong> ' . $test_email . '</li>';
    echo '</ul>';
    
    echo '<h4>✅ Resultados Esperados:</h4>';
    echo '<ul>';
    echo '<li>Formulario 1: Crea un Lead nuevo en Bitrix24</li>';
    echo '<li>Formulario 2: Crea una actividad para el Lead existente (NO Lead duplicado)</li>';
    echo '<li>En los logs debe aparecer "Lead existente encontrado" para el segundo formulario</li>';
    echo '</ul>';
    
    if ($result_1['success'] && $result_2['success']) {
        echo '<div class="success"><h4>🎉 Test completado exitosamente</h4></div>';
    } else {
        echo '<div class="error"><h4>❌ Test falló - revisar logs arriba</h4></div>';
    }
    
    echo '</div>';
    
    echo '<p style="margin-top: 30px;">';
    echo '<a href="' . admin_url('admin.php?page=yeison-btx-logs') . '" class="button button-primary">📝 Ver Logs Completos</a> ';
    echo '<a href="' . admin_url('admin.php?page=yeison-btx') . '" class="button button-secondary">🏠 Volver al Dashboard</a>';
    echo '</p>';
    
    exit;
}

/**
 * TAMBIÉN AGREGAR ESTE ENDPOINT PARA VERIFICAR UN EMAIL ESPECÍFICO
 */
add_action('wp_ajax_yeison_btx_check_lead_by_email', 'yeison_btx_check_lead_by_email');
function yeison_btx_check_lead_by_email() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    $email = $_GET['email'] ?? '';
    
    if (empty($email)) {
        echo '<h2>❌ Falta parámetro email</h2>';
        echo '<p>Uso: ?action=yeison_btx_check_lead_by_email&email=ejemplo@email.com</p>';
        exit;
    }
    
    echo '<h1>🔍 Verificar Lead por Email</h1>';
    echo '<p><strong>Email a buscar:</strong> ' . esc_html($email) . '</p>';
    
    $forms_handler = yeison_btx_forms();
    
    // Usar reflexión para acceder al método privado (solo para testing)
    $reflection = new ReflectionClass($forms_handler);
    $check_method = $reflection->getMethod('check_existing_lead_by_email');
    $check_method->setAccessible(true);
    
    $existing_lead = $check_method->invoke($forms_handler, $email);
    
    if ($existing_lead) {
        echo '<div style="background: #d4edda; padding: 15px; border-radius: 5px;">';
        echo '<h3>✅ Lead Encontrado</h3>';
        echo '<p><strong>ID del Lead:</strong> ' . $existing_lead . '</p>';
        echo '</div>';
    } else {
        echo '<div style="background: #f8d7da; padding: 15px; border-radius: 5px;">';
        echo '<h3>❌ Lead No Encontrado</h3>';
        echo '<p>No existe un Lead con este email en Bitrix24</p>';
        echo '</div>';
    }
    
    exit;
}


























/**
 * Diagnóstico completo de actividades en Bitrix24
 * 
 * Acceder desde: /wp-admin/admin-ajax.php?action=yeison_btx_activity_diagnostic
 */

// Agregar a functions.php
add_action('wp_ajax_yeison_btx_activity_diagnostic', 'yeison_btx_activity_diagnostic');
function yeison_btx_activity_diagnostic() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    echo '<h1>🔬 Diagnóstico Completo de Actividades Bitrix24</h1>';
    echo '<style>
        .diagnostic { background: white; padding: 20px; margin: 15px 0; border: 1px solid #ddd; border-radius: 5px; }
        .success { border-left: 4px solid #28a745; background: #d4edda; }
        .error { border-left: 4px solid #dc3545; background: #f8d7da; }
        .warning { border-left: 4px solid #ffc107; background: #fff3cd; }
        .info { border-left: 4px solid #17a2b8; background: #d1ecf1; }
        pre { background: #f8f9f9; padding: 10px; border-radius: 3px; overflow-x: auto; max-height: 300px; overflow-y: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f2f2f2; }
    </style>';
    
    $api = yeison_btx_api();
    
    if (!$api->is_authorized()) {
        echo '<div class="diagnostic error">❌ API de Bitrix24 no autorizada</div>';
        exit;
    }
    
    // 1. Información de la API
    echo '<div class="diagnostic info">';
    echo '<h3>🔌 Estado de la API</h3>';
    echo '<p>✅ API autorizada correctamente</p>';
    echo '<p><strong>Dominio:</strong> ' . yeison_btx_get_option('bitrix_domain') . '</p>';
    echo '</div>';
    
    // 2. Obtener Leads disponibles
    echo '<div class="diagnostic">';
    echo '<h3>📋 Leads Disponibles para Test</h3>';
    
    $leads_response = $api->api_call('crm.lead.list', array(
        'select' => array('ID', 'TITLE', 'EMAIL', 'STATUS_ID'),
        'order' => array('ID' => 'DESC'),
        'filter' => array(),
        'start' => 0
    ));
    
    if ($leads_response && isset($leads_response['result'])) {
        echo '<p>✅ Se encontraron ' . count($leads_response['result']) . ' Leads</p>';
        echo '<table>';
        echo '<tr><th>ID</th><th>Título</th><th>Email</th><th>Estado</th><th>Acciones</th></tr>';
        
        foreach (array_slice($leads_response['result'], 0, 5) as $lead) {
            $email_display = '';
            if (isset($lead['EMAIL']) && is_array($lead['EMAIL']) && !empty($lead['EMAIL'])) {
                $email_display = $lead['EMAIL'][0]['VALUE'] ?? 'Sin email';
            }
            
            echo '<tr>';
            echo '<td>' . $lead['ID'] . '</td>';
            echo '<td>' . ($lead['TITLE'] ?? 'Sin título') . '</td>';
            echo '<td>' . $email_display . '</td>';
            echo '<td>' . ($lead['STATUS_ID'] ?? 'N/A') . '</td>';
            echo '<td>';
            echo '<a href="?action=yeison_btx_test_simple_activity&lead_id=' . $lead['ID'] . '" target="_blank">Test Simple</a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="error">❌ No se pudieron obtener Leads</div>';
        echo '<pre>' . print_r($leads_response, true) . '</pre>';
    }
    echo '</div>';
    
    // 3. Campos disponibles para actividades
    echo '<div class="diagnostic">';
    echo '<h3>🏗️ Estructura de Campos para Actividades</h3>';
    
    $fields_response = $api->api_call('crm.activity.fields');
    
    if ($fields_response && isset($fields_response['result'])) {
        echo '<p>✅ Campos obtenidos correctamente</p>';
        echo '<details>';
        echo '<summary>Ver todos los campos (click para expandir)</summary>';
        echo '<pre>' . print_r($fields_response['result'], true) . '</pre>';
        echo '</details>';
        
        // Campos más importantes
        $important_fields = array('OWNER_TYPE_ID', 'OWNER_ID', 'TYPE_ID', 'SUBJECT', 'DESCRIPTION', 'START_TIME', 'END_TIME', 'COMPLETED');
        
        echo '<h4>🎯 Campos Importantes:</h4>';
        echo '<table>';
        echo '<tr><th>Campo</th><th>Tipo</th><th>Requerido</th><th>Descripción</th></tr>';
        
        foreach ($important_fields as $field) {
            if (isset($fields_response['result'][$field])) {
                $field_info = $fields_response['result'][$field];
                echo '<tr>';
                echo '<td><strong>' . $field . '</strong></td>';
                echo '<td>' . ($field_info['type'] ?? 'N/A') . '</td>';
                echo '<td>' . ($field_info['isRequired'] ? '✅ Sí' : '❌ No') . '</td>';
                echo '<td>' . ($field_info['title'] ?? 'N/A') . '</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
    } else {
        echo '<div class="error">❌ No se pudieron obtener campos</div>';
        echo '<pre>' . print_r($fields_response, true) . '</pre>';
    }
    echo '</div>';
    
    // 4. Tipos de actividad
    echo '<div class="diagnostic">';
    echo '<h3>📝 Tipos de Actividad Disponibles</h3>';
    
    $types_response = $api->api_call('crm.activity.type.list');
    
    if ($types_response && isset($types_response['result'])) {
        echo '<table>';
        echo '<tr><th>ID</th><th>Nombre</th><th>Descripción</th></tr>';
        
        foreach ($types_response['result'] as $type) {
            echo '<tr>';
            echo '<td>' . ($type['ID'] ?? 'N/A') . '</td>';
            echo '<td>' . ($type['NAME'] ?? 'N/A') . '</td>';
            echo '<td>' . ($type['DESCRIPTION'] ?? 'N/A') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="error">❌ No se pudieron obtener tipos de actividad</div>';
        echo '<pre>' . print_r($types_response, true) . '</pre>';
    }
    echo '</div>';
    
    // 5. Test de actividad básica
    if (!empty($leads_response['result'])) {
        $test_lead_id = $leads_response['result'][0]['ID'];
        
        echo '<div class="diagnostic">';
        echo '<h3>🧪 Test de Actividad Básica</h3>';
        echo '<p>Probando con Lead ID: ' . $test_lead_id . '</p>';
        
        $basic_activity = array(
            'OWNER_TYPE_ID' => 1,
            'OWNER_ID' => $test_lead_id,
            'SUBJECT' => 'Test diagnóstico ' . date('Y-m-d H:i:s')
        );
        
        echo '<p><strong>Datos a enviar:</strong></p>';
        echo '<pre>' . print_r($basic_activity, true) . '</pre>';
        
        $test_response = $api->api_call('crm.activity.add', array(
            'fields' => $basic_activity
        ));
        
        echo '<p><strong>Respuesta:</strong></p>';
        echo '<pre>' . print_r($test_response, true) . '</pre>';
        
        if ($test_response && isset($test_response['result'])) {
            echo '<div class="success">✅ Test básico exitoso - Activity ID: ' . $test_response['result'] . '</div>';
        } else {
            echo '<div class="error">❌ Test básico falló</div>';
        }
        echo '</div>';
        
        // 6. Test de comentario Timeline
        echo '<div class="diagnostic">';
        echo '<h3>💬 Test de Comentario Timeline</h3>';
        
        $timeline_comment = array(
            'ENTITY_ID' => $test_lead_id,
            'ENTITY_TYPE' => 'lead',
            'COMMENT' => 'Test de comentario Timeline desde diagnóstico - ' . date('Y-m-d H:i:s')
        );
        
        echo '<p><strong>Datos del comentario:</strong></p>';
        echo '<pre>' . print_r($timeline_comment, true) . '</pre>';
        
        $timeline_response = $api->api_call('crm.timeline.comment.add', array(
            'fields' => $timeline_comment
        ));
        
        echo '<p><strong>Respuesta Timeline:</strong></p>';
        echo '<pre>' . print_r($timeline_response, true) . '</pre>';
        
        if ($timeline_response && isset($timeline_response['result'])) {
            echo '<div class="success">✅ Comentario Timeline exitoso - ID: ' . $timeline_response['result'] . '</div>';
        } else {
            echo '<div class="error">❌ Comentario Timeline falló</div>';
        }
        echo '</div>';
    }
    
    // 7. Permisos del usuario
    echo '<div class="diagnostic">';
    echo '<h3>👤 Información del Usuario API</h3>';
    
    $user_info = $api->api_call('user.current');
    
    if ($user_info && isset($user_info['result'])) {
        echo '<table>';
        echo '<tr><th>Campo</th><th>Valor</th></tr>';
        
        $user_fields = array('ID', 'NAME', 'LAST_NAME', 'EMAIL', 'ACTIVE', 'ADMIN');
        foreach ($user_fields as $field) {
            if (isset($user_info['result'][$field])) {
                echo '<tr><td>' . $field . '</td><td>' . $user_info['result'][$field] . '</td></tr>';
            }
        }
        echo '</table>';
    } else {
        echo '<div class="error">❌ No se pudo obtener información del usuario</div>';
    }
    echo '</div>';
    
    // 8. Recomendaciones
    echo '<div class="diagnostic warning">';
    echo '<h3>💡 Recomendaciones</h3>';
    echo '<ul>';
    echo '<li>Si el test básico funciona, usar configuración mínima en el código</li>';
    echo '<li>Si Timeline funciona mejor, usar como método principal</li>';
    echo '<li>Verificar permisos del usuario API en Bitrix24</li>';
    echo '<li>Revisar configuración de webhooks si están activos</li>';
    echo '</ul>';
    echo '</div>';
    
    echo '<p style="margin-top: 30px;">';
    echo '<a href="?action=yeison_btx_test_duplicate_leads" class="button button-primary">🔄 Test Leads Duplicados</a> ';
    echo '<a href="' . admin_url('admin.php?page=yeison-btx-logs') . '" class="button">📝 Ver Logs</a> ';
    echo '<a href="' . admin_url('admin.php?page=yeison-btx') . '" class="button">🏠 Dashboard</a>';
    echo '</p>';
    
    exit;
}
















/**
 * Test específico para el problema de emails duplicados reportado
 * 
 * Acceder desde: /wp-admin/admin-ajax.php?action=yeison_btx_test_email_duplicate
 */

add_action('wp_ajax_yeison_btx_test_email_duplicate', 'yeison_btx_test_email_duplicate');
function yeison_btx_test_email_duplicate() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    // Obtener email específico del usuario o usar uno de test
    $test_email = $_GET['email'] ?? 'ben.perchir@ecomycr.com';
    
    echo '<h1>🎯 Test Específico: Email Duplicado</h1>';
    echo '<style>
        .test-box { background: white; padding: 20px; margin: 15px 0; border: 1px solid #ddd; border-radius: 5px; }
        .success { border-left: 4px solid #28a745; background: #d4edda; }
        .error { border-left: 4px solid #dc3545; background: #f8d7da; }
        .warning { border-left: 4px solid #ffc107; background: #fff3cd; }
        .info { border-left: 4px solid #17a2b8; background: #d1ecf1; }
        pre { background: #f8f9f9; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>';
    
    echo '<div class="test-box info">';
    echo '<h3>📧 Email a Probar: ' . esc_html($test_email) . '</h3>';
    echo '<p>Este test replicará el escenario exacto del problema reportado:</p>';
    echo '<ol>';
    echo '<li>Verificar si ya existe Lead con este email</li>';
    echo '<li>Si existe: crear actividad</li>';
    echo '<li>Si no existe: crear Lead nuevo</li>';
    echo '</ol>';
    echo '</div>';
    
    $api = yeison_btx_api();
    
    if (!$api->is_authorized()) {
        echo '<div class="test-box error">❌ API no autorizada</div>';
        exit;
    }
    
    // PASO 1: Verificar Lead existente
    echo '<div class="test-box">';
    echo '<h3>🔍 PASO 1: Verificar Lead Existente</h3>';
    
    $search_response = $api->api_call('crm.lead.list', array(
        'filter' => array('EMAIL' => $test_email),
        'select' => array('ID', 'TITLE', 'EMAIL', 'STATUS_ID', 'NAME', 'LAST_NAME')
    ));
    
    echo '<p><strong>Búsqueda realizada para email:</strong> ' . $test_email . '</p>';
    echo '<p><strong>Respuesta de Bitrix24:</strong></p>';
    echo '<pre>' . print_r($search_response, true) . '</pre>';
    
    $existing_lead_id = null;
    
    if ($search_response && isset($search_response['result']) && !empty($search_response['result'])) {
        $existing_lead = $search_response['result'][0];
        $existing_lead_id = $existing_lead['ID'];
        
        echo '<div class="success">✅ Lead existente encontrado</div>';
        echo '<table border="1" style="width:100%; border-collapse: collapse;">';
        echo '<tr><th>Campo</th><th>Valor</th></tr>';
        foreach ($existing_lead as $key => $value) {
            if (is_array($value)) {
                $value = print_r($value, true);
            }
            echo '<tr><td>' . $key . '</td><td>' . esc_html($value) . '</td></tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="warning">⚠️ No se encontró Lead existente con este email</div>';
    }
    echo '</div>';
    
    // PASO 2: Simular formulario
    echo '<div class="test-box">';
    echo '<h3>📝 PASO 2: Simular Envío de Formulario</h3>';
    
    $form_data = array(
        'name' => 'Ben',
        'last_name' => 'Perchir', 
        'email' => $test_email,
        'phone' => '89287777',
        'company' => 'ecomycr',
        'message' => 'Test de prevención de duplicados - ' . date('Y-m-d H:i:s'),
        '_start_time' => time() - 10,
        'website' => '', // Honeypot
        '_meta' => array(
            'origin' => 'https://yeison.guruxdev.com',
            'timestamp' => current_time('mysql')
        )
    );
    
    echo '<p><strong>Datos del formulario:</strong></p>';
    echo '<pre>' . print_r($form_data, true) . '</pre>';
    
    // Usar el Forms Handler corregido
    $forms_handler = yeison_btx_forms();
    $result = $forms_handler->process_form_submission($form_data, 'manual_test', 'https://yeison.guruxdev.com');
    
    echo '<p><strong>Resultado del procesamiento:</strong></p>';
    echo '<pre>' . print_r($result, true) . '</pre>';
    
    if ($result['success']) {
        echo '<div class="success">✅ Formulario procesado exitosamente</div>';
    } else {
        echo '<div class="error">❌ Error procesando formulario</div>';
    }
    echo '</div>';
    
    // PASO 3: Verificar qué se creó
    echo '<div class="test-box">';
    echo '<h3>🔍 PASO 3: Verificar Resultado</h3>';
    
    sleep(3); // Dar tiempo para procesar
    
    // Buscar Lead nuevamente
    $final_search = $api->api_call('crm.lead.list', array(
        'filter' => array('EMAIL' => $test_email),
        'select' => array('ID', 'TITLE', 'EMAIL', 'STATUS_ID'),
        'order' => array('ID' => 'DESC')
    ));
    
    echo '<p><strong>Leads encontrados después del test:</strong></p>';
    
    if ($final_search && isset($final_search['result'])) {
        echo '<p>Total de Leads con este email: ' . count($final_search['result']) . '</p>';
        
        if (count($final_search['result']) > 1) {
            echo '<div class="error">❌ ¡PROBLEMA! Se encontraron múltiples Leads con el mismo email</div>';
        } else {
            echo '<div class="success">✅ Correcto: Solo 1 Lead con este email</div>';
        }
        
        echo '<table border="1" style="width:100%; border-collapse: collapse;">';
        echo '<tr><th>ID</th><th>Título</th><th>Email</th><th>Estado</th></tr>';
        
        foreach ($final_search['result'] as $lead) {
            $email_display = '';
            if (isset($lead['EMAIL']) && is_array($lead['EMAIL'])) {
                $email_display = $lead['EMAIL'][0]['VALUE'] ?? 'Sin email';
            }
            
            echo '<tr>';
            echo '<td>' . $lead['ID'] . '</td>';
            echo '<td>' . ($lead['TITLE'] ?? 'Sin título') . '</td>';
            echo '<td>' . $email_display . '</td>';
            echo '<td>' . ($lead['STATUS_ID'] ?? 'N/A') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    echo '</div>';
    
    // PASO 4: Verificar actividades
    if ($existing_lead_id) {
        echo '<div class="test-box">';
        echo '<h3>📋 PASO 4: Verificar Actividades del Lead Existente</h3>';
        
        $activities = $api->api_call('crm.activity.list', array(
            'filter' => array(
                'OWNER_TYPE_ID' => 1,
                'OWNER_ID' => $existing_lead_id
            ),
            'select' => array('ID', 'SUBJECT', 'DESCRIPTION', 'CREATED'),
            'order' => array('ID' => 'DESC')
        ));
        
        if ($activities && isset($activities['result'])) {
            echo '<p>Actividades encontradas: ' . count($activities['result']) . '</p>';
            
            if (!empty($activities['result'])) {
                echo '<table border="1" style="width:100%; border-collapse: collapse;">';
                echo '<tr><th>ID</th><th>Asunto</th><th>Descripción</th><th>Fecha</th></tr>';
                
                foreach (array_slice($activities['result'], 0, 5) as $activity) {
                    echo '<tr>';
                    echo '<td>' . $activity['ID'] . '</td>';
                    echo '<td>' . ($activity['SUBJECT'] ?? 'Sin asunto') . '</td>';
                    echo '<td>' . substr($activity['DESCRIPTION'] ?? '', 0, 100) . '...</td>';
                    echo '<td>' . ($activity['CREATED'] ?? 'N/A') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
        } else {
            echo '<div class="warning">⚠️ No se encontraron actividades o error al obtenerlas</div>';
            echo '<pre>' . print_r($activities, true) . '</pre>';
        }
        echo '</div>';
    }
    
    // PASO 5: Logs del sistema
    echo '<div class="test-box">';
    echo '<h3>📝 PASO 5: Logs del Sistema (últimos 10)</h3>';
    
    global $wpdb;
    $recent_logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}yeison_btx_logs 
        WHERE message LIKE %s OR message LIKE %s OR message LIKE %s
        ORDER BY created_at DESC 
        LIMIT 10",
        '%' . $test_email . '%',
        '%Lead existente%',
        '%actividad%'
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
        echo '<p>No se encontraron logs relacionados con este email</p>';
    }
    echo '</div>';
    
    // Resumen final
    echo '<div class="test-box info">';
    echo '<h3>📊 Resumen del Test</h3>';
    
    $lead_count = 0;
    if ($final_search && isset($final_search['result'])) {
        $lead_count = count($final_search['result']);
    }
    
    echo '<ul>';
    echo '<li><strong>Email probado:</strong> ' . $test_email . '</li>';
    echo '<li><strong>Lead existía antes:</strong> ' . ($existing_lead_id ? 'Sí (ID: ' . $existing_lead_id . ')' : 'No') . '</li>';
    echo '<li><strong>Leads totales con este email:</strong> ' . $lead_count . '</li>';
    echo '<li><strong>Formulario procesado:</strong> ' . ($result['success'] ? 'Sí' : 'No') . '</li>';
    echo '</ul>';
    
    if ($existing_lead_id && $lead_count === 1) {
        echo '<div class="success"><h4>🎉 ¡ÉXITO! No se creó Lead duplicado</h4></div>';
    } elseif (!$existing_lead_id && $lead_count === 1) {
        echo '<div class="success"><h4>✅ Correcto: Se creó Lead nuevo (no existía antes)</h4></div>';
    } elseif ($lead_count > 1) {
        echo '<div class="error"><h4>❌ PROBLEMA: Se encontraron Leads duplicados</h4></div>';
    } else {
        echo '<div class="warning"><h4>⚠️ Resultado no concluyente</h4></div>';
    }
    echo '</div>';
    
    echo '<p style="margin-top: 30px;">';
    echo '<a href="?action=yeison_btx_activity_diagnostic" class="button button-primary">🔬 Diagnóstico Actividades</a> ';
    echo '<a href="?action=yeison_btx_test_duplicate_leads" class="button">🧪 Test Completo</a> ';
    echo '<a href="' . admin_url('admin.php?page=yeison-btx-logs') . '" class="button">📝 Ver Logs</a> ';
    echo '<a href="' . admin_url('admin.php?page=yeison-btx') . '" class="button">🏠 Dashboard</a>';
    echo '</p>';
    
    exit;
}




















