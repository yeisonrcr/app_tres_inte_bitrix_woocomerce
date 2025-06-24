<?php
/**
 * Manejador de Webhooks de Bitrix24 - VERSIÓN CORREGIDA
 * 
 * @package YeisonBTX
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class YeisonBTX_Webhook_Handler {
    
    /**
     * Instancia única (Singleton)
     */
    private static $instance = null;
    
    /**
     * Configuración de webhooks
     */
    private $config = array();
    
    /**
     * Constructor privado
     */
    private function __construct() {
        $this->load_config();
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
     * Cargar configuración
     */
    private function load_config() {
        $this->config = array(
            'enabled' => yeison_btx_get_option('webhooks_enabled', true),
            'webhook_secret' => yeison_btx_get_option('webhook_secret', wp_generate_password(32, false)),
            'allowed_events' => yeison_btx_get_option('webhook_events', array(
                'ONCRMDEALADD',
                'ONCRMDEALUPDATE', 
                'ONCRMCONTACTADD',
                'ONCRMCONTACTUPDATE'
            )),
            'auto_register' => yeison_btx_get_option('webhook_auto_register', true)
        );
        
        // Generar secret si no existe
        if (empty(yeison_btx_get_option('webhook_secret'))) {
            yeison_btx_update_option('webhook_secret', $this->config['webhook_secret']);
        }
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // ✅ FIX #1: Registrar endpoints con PRIORIDAD ALTA
        add_action('rest_api_init', array($this, 'register_webhook_endpoints'), 5);
        
        // Auto-registrar webhooks en Bitrix24 cuando se autoriza
        add_action('yeison_btx_api_authorized', array($this, 'auto_register_webhooks'));
        
        // Limpiar webhooks al desautorizar
        add_action('yeison_btx_api_deauthorized', array($this, 'cleanup_webhooks'));
        
    }
    
    /**
     * Registrar endpoints REST API para webhooks
     */
    public function register_webhook_endpoints() {
        // ✅ FIX #3: Log del registro
        
        // Esto es IMPORTANTE REVISARLO yeison_btx_log('📡 Registrando endpoints REST API', 'info');
        
        // Endpoint para deals
        $deal_registered = register_rest_route('yeison-bitrix/v1', '/webhook/deal', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_deal_webhook'),
            'permission_callback' => array($this, 'verify_webhook_security'),
            'args' => array()
        ));
        
        // Endpoint para contactos
        $contact_registered = register_rest_route('yeison-bitrix/v1', '/webhook/contact', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_contact_webhook'),
            'permission_callback' => array($this, 'verify_webhook_security'),
            'args' => array()
        ));
        
        // ✅ FIX #4: Endpoint de status SIN verificación de seguridad
        $status_registered = register_rest_route('yeison-bitrix/v1', '/webhook/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_webhook_status'),
            'permission_callback' => '__return_true' // ✅ Siempre permitir GET status
        ));
        
        
        





    }
    
    
    /**
     * ✅ FIX #8: Verificación de seguridad más permisiva
     */
    public function verify_webhook_security_permissive($request) {
        // Solo verificar rate limiting básico, sin ser tan estricto
        return $this->check_rate_limit();
    }
    
    /**
     * Verificar seguridad del webhook (VERSIÓN ORIGINAL para webhooks reales)
     */
    public function verify_webhook_security($request) {
        // ✅ FIX #9: Log de la verificación para debugging
        $user_agent = $request->get_header('user-agent');
        $ip = yeison_btx_get_ip();
        
        
        // ✅ FIX #10: Permitir tests desde localhost/admin
        if (in_array($ip, array('127.0.0.1', '::1')) || 
            strpos($_SERVER['HTTP_REFERER'] ?? '', wp_parse_url(admin_url(), PHP_URL_HOST)) !== false) {
            yeison_btx_log('🔐 Permitiendo acceso desde localhost/admin', 'info');
            return true;
        }
        
        // Verificar origen
        if (strpos($user_agent, 'Bitrix') === false) {
            yeison_btx_log('Webhook: User-Agent sospechoso', 'warning', array(
                'user_agent' => $user_agent,
                'ip' => $ip
            ));
            // ✅ FIX #11: No bloquear por User-Agent, solo advertir
            // return false; // Comentado para ser menos estricto
        }
        
        // Verificar signature si está configurado
        $signature = $request->get_header('X-Bitrix-Signature');
        if (!empty($this->config['webhook_secret']) && !empty($signature)) {
            $body = $request->get_body();
            $expected_signature = hash_hmac('sha256', $body, $this->config['webhook_secret']);
            
            if (!hash_equals($signature, $expected_signature)) {
                yeison_btx_log('Webhook: Signature inválida', 'error', array(
                    'received_signature' => $signature,
                    'ip' => $ip
                ));
                return false;
            }
        }
        
        // Verificar rate limiting básico
        if (!$this->check_rate_limit()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Rate limiting básico
     */
    private function check_rate_limit() {
        $ip = yeison_btx_get_ip();
        $transient_key = 'yeison_btx_webhook_rate_' . md5($ip);
        $current_count = get_transient($transient_key) ?: 0;
        
        // ✅ FIX #12: Límite más permisivo para debugging
        $max_requests = 200; // Aumentado de 100 a 200
        
        if ($current_count >= $max_requests) {
            return false;
        }
        
        set_transient($transient_key, $current_count + 1, 60);
        return true;
    }
    
    /**
     * Manejar webhook de Deal
     */
    

    public function handle_deal_webhook($request) {
        try {
            // Obtener datos de múltiples formas
            $data = $request->get_json_params();
            
            // Si no hay datos JSON, intentar desde $_POST
            if (empty($data)) {
                $data = $_POST;
            }
            
            
            if (!$this->config['enabled']) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Webhooks deshabilitados'
                ), 200);
            }
            
            // Extraer evento - Bitrix24 puede enviar de diferentes formas
            $event = '';
            if (isset($data['event'])) {
                $event = $data['event'];
            } elseif (isset($_GET['event'])) {
                $event = $_GET['event'];
            } elseif (isset($_POST['event'])) {
                $event = $_POST['event'];
            }
            
            // Extraer campos del deal
            $deal_fields = array();
            if (isset($data['data']['FIELDS'])) {
                $deal_fields = $data['data']['FIELDS'];
            } elseif (isset($data['FIELDS'])) {
                $deal_fields = $data['FIELDS'];
            } else {
                // Buscar campos que empiecen con mayúscula (campos de Bitrix24)
                foreach ($data as $key => $value) {
                    if (ctype_upper($key[0])) {
                        $deal_fields[$key] = $value;
                    }
                }
            }
            // Procesar según el evento
            $result = false;
            
            // Normalizar evento
            $normalized_event = yeison_btx_normalize_event($event);
            
            switch ($normalized_event) {
                case 'ONCRMDEALADD':
                    $result = $this->process_deal_added($deal_fields);
                    break;
                    
                case 'ONCRMDEALUPDATE':
                    $result = $this->process_deal_updated($deal_fields);
                    break;
                    
                default:
                
                    
                    // Si no hay evento específico pero hay datos de deal, asumir update
                    if (!empty($deal_fields['ID'])) {
                        //yeison_btx_log('🔄 Procesando como actualización por defecto', 'info');
                        $result = $this->process_deal_updated($deal_fields);
                    }
                    break;
            }
            
            return new WP_REST_Response(array(
                'success' => true,
                'processed' => $result,
                'event_received' => $event,
                'event_normalized' => $normalized_event,
                'deal_id' => $deal_fields['ID'] ?? 'unknown',
                'message' => 'Webhook procesado'
            ), 200);
            
        } catch (Exception $e) {
            
            
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Error interno'
            ), 500);
        }
    }

















    /**
     * Manejar webhook de Contacto
     */
    
    
    public function handle_contact_webhook($request) {
        try {
            // 1. Intentar obtener datos de múltiples formas
            $data = $request->get_json_params();
            
            // Si get_json_params() falla, intentar get_body()
            if (empty($data)) {
                $raw_body = $request->get_body();
                
                if (!empty($raw_body)) {
                    $data = json_decode($raw_body, true);
                    
                    
                }
            }
            
            // Si aún no hay datos, intentar $_POST
            if (empty($data)) {
                $post_data = $_POST;
                
                if (!empty($post_data)) {
                    $data = $post_data;
                    
                }
            }
            
            // Validar datos básicos
            if (empty($data)) {
                
                return new WP_REST_Response(array(
                    'success' => false, 
                    'message' => 'No hay datos en el webhook',
                    'debug' => array(
                        'content_type' => $request->get_header('content-type'),
                        'method' => $request->get_method(),
                        'body_length' => strlen($request->get_body())
                    )
                ), 400);
            }
            
            // Detectar formato de Bitrix24
            $event = '';
            $contact_fields = array();
            
            // Formato 1: {event: "xxx", data: {FIELDS: {...}}}
            if (isset($data['event']) && isset($data['data']['FIELDS'])) {
                $event = $data['event'];
                $contact_fields = $data['data']['FIELDS'];
            }
            // Formato 2: Datos directos con event
            elseif (isset($data['event'])) {
                $event = $data['event'];
                $contact_fields = $data;
                unset($contact_fields['event']);
            }
            // Formato 3: Solo fields sin event wrapper
            elseif (isset($data['ID'])) {
                $event = 'ONCRMCONTACTUPDATE'; // Asumir update por defecto
                $contact_fields = $data;
            }
            // Formato 4: Bitrix24 puede enviar como formulario
            elseif (isset($data['auth']) || isset($data['event'])) {
                $event = $data['event'] ?? 'ONCRMCONTACTUPDATE';
                
                // Buscar campos que empiecen con mayúscula (campos de Bitrix24)
                $contact_fields = array();
                foreach ($data as $key => $value) {
                    if (ctype_upper($key[0])) {
                        $contact_fields[$key] = $value;
                    }
                }
            }
            
            if (empty($contact_fields['ID'])) {
                yeison_btx_log('❌ Webhook Contact: No se pudo extraer ID', 'error', array(
                    'data_structure' => array_keys($data),
                    'event' => $event,
                    'contact_fields_keys' => array_keys($contact_fields)
                ));
                
                return new WP_REST_Response(array(
                    'success' => false, 
                    'message' => 'ID de contacto requerido',
                    'received_structure' => array_keys($data)
                ), 400);
            }
            
            // Normalizar evento
            $normalized_event = yeison_btx_normalize_event($event);
            
            
            if (!$this->config['enabled']) {
                return new WP_REST_Response(array('success' => false, 'message' => 'Webhooks deshabilitados'), 200);
            }
            
            $result = false;
            
            // Procesar según evento normalizado
            switch ($normalized_event) {
                case 'ONCRMCONTACTADD':
                    
                    $result = $this->process_contact_added($contact_fields);
                    break;
                    
                case 'ONCRMCONTACTUPDATE':
                    
                    $result = $this->process_contact_updated($contact_fields);
                    break;
                    
                default:
                    
                    // Intentar procesar como actualización por defecto
                    if (strpos(strtolower($event), 'update') !== false || empty($event)) {
                        yeison_btx_log('🔄 Procesando como actualización por defecto', 'info');
                        $result = $this->process_contact_updated($contact_fields);
                    }
                    break;
            }
            
            return new WP_REST_Response(array(
                'success' => true,
                'processed' => $result,
                'event_received' => $event,
                'event_normalized' => $normalized_event,
                'contact_id' => $contact_fields['ID'],
                'contact_name' => trim(($contact_fields['NAME'] ?? '') . ' ' . ($contact_fields['LAST_NAME'] ?? ''))
            ), 200);
            
        } catch (Exception $e) {
            yeison_btx_log('❌ Error crítico en webhook Contact', 'error', array(
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ));
            
            return new WP_REST_Response(array(
                'success' => false, 
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage()
            ), 500);
        }
    }














    
    private function process_deal_added($deal_fields) {
        
        
        // Disparar evento para sincronización bidireccional
        do_action('yeison_btx_deal_webhook_received', 'ONCRMDEALADD', $deal_fields);
        
        return true;
    }
    
    /**
     * Procesar Deal actualizado
     */
    


    private function process_deal_updated($deal_fields) {
        // Validar que tenemos ID del deal
        if (empty($deal_fields['ID'])) {
            yeison_btx_log('Deal ID vacío en webhook', 'error', array('fields' => $deal_fields));
            return false;
        }
        
        $deal_id = $deal_fields['ID'];
        
        // OBTENER DATOS COMPLETOS DEL DEAL desde Bitrix24
        $api = yeison_btx_api();
        $deal_response = $api->api_call('crm.deal.get', array(
            'id' => $deal_id
        ));
        
        if (!$deal_response || !isset($deal_response['result'])) {
            yeison_btx_log('Error obteniendo datos completos del Deal', 'error', array(
                'deal_id' => $deal_id,
                'response' => $deal_response
            ));
            return false;
        }
        
        // Usar datos completos del Deal
        $complete_deal_fields = $deal_response['result'];
        
        // 🆕 NUEVA FUNCIONALIDAD: Crear Timeline Comment para cambio de estado
        $this->create_deal_status_comment($deal_id, $complete_deal_fields);
        
        
        // Disparar evento para sincronización bidireccional con datos completos
        do_action('yeison_btx_deal_webhook_received', 'ONCRMDEALUPDATE', $complete_deal_fields);
        
        return true;
    }











    private function create_deal_status_comment($deal_id, $deal_fields) {
        $api = yeison_btx_api();
        
        if (!$api->is_authorized()) {
            return false;
        }
        
        $current_stage = $deal_fields['STAGE_ID'] ?? 'UNKNOWN';
        
        // 🛡️ Verificar si el estado realmente cambió
        $previous_stage = $this->get_previous_deal_stage($deal_id);
        
        // Si el estado no cambió, no crear comentario
        if ($previous_stage === $current_stage) {
            yeison_btx_log('ℹ️ Estado no cambió, saltando comentario', 'info', array(
                'deal_id' => $deal_id,
                'stage' => $current_stage
            ));
            return false;
        }
        
        // 🛡️ SOLUCIÓN 2: Lock más específico basado en la transición
        $transition_key = "yeison_btx_deal_transition_{$deal_id}_{$previous_stage}_to_{$current_stage}";
        if (get_transient($transition_key)) {
            yeison_btx_log('🔒 Transición ya procesada recientemente', 'info', array(
                'deal_id' => $deal_id,
                'transition' => "{$previous_stage} -> {$current_stage}"
            ));
            return false;
        }
        
        // Establecer lock específico por 60 segundos (más tiempo)
        set_transient($transition_key, time(), 60);
        
        // Mapeo de estados a nombres amigables
        $stage_names = array(
            'NEW' => 'Nuevo',
            'PREPARATION' => 'Preparación', 
            'EXECUTING' => 'En Proceso',
            'PREPAYMENT_INVOICE' => 'En Espera de Pago',
            'WON' => 'Completado',
            'LOSE' => 'Cancelado',
            'APOLOGY' => 'Reembolsado'
        );
        
        $current_stage_name = $stage_names[$current_stage] ?? $current_stage;
        $previous_stage_name = $stage_names[$previous_stage] ?? $previous_stage;
        
        // 📝 Crear comentario simple
        $comment_text = "El estado del pedido cambió de **{$previous_stage_name}** a **{$current_stage_name}**.";
        
        // Datos para Timeline API
        $timeline_data = array(
            'ENTITY_ID' => $deal_id,
            'ENTITY_TYPE' => 'deal',
            'COMMENT' => $comment_text
        );
        
        // Enviar a Bitrix24
        $response = $api->api_call('crm.timeline.comment.add', array(
            'fields' => $timeline_data
        ));
        
        if ($response && isset($response['result'])) {
            $comment_id = $response['result'];
            
            // ✅ IMPORTANTE: Guardar el estado actual DESPUÉS de crear el comentario exitosamente
            $this->save_deal_stage($deal_id, $current_stage);
                        
            return $comment_id;
        }
        
        // Si falla, eliminar el lock para permitir reintento
        delete_transient($transition_key);
        
        yeison_btx_log('❌ Error creando Timeline Comment en Deal', 'error', array(
            'deal_id' => $deal_id,
            'transition' => "{$previous_stage_name} -> {$current_stage_name}",
            'response' => $response
        ));
        
        return false;
    }

    // 🔧 MÉTODO AUXILIAR MEJORADO para obtener el estado anterior
    private function get_previous_deal_stage($deal_id) {
        $option_key = "yeison_btx_deal_stage_{$deal_id}";
        return get_option($option_key, null);
    }

    // 🔧 MÉTODO AUXILIAR para guardar el estado actual
    private function save_deal_stage($deal_id, $stage) {
        $option_key = "yeison_btx_deal_stage_{$deal_id}";
        return update_option($option_key, $stage);
    }

















    private function process_contact_added($contact_fields) {
        
        // Disparar evento para sincronización bidireccional
        do_action('yeison_btx_contact_webhook_received', 'ONCRMCONTACTADD', $contact_fields);
        
        return true;
    }
    
    private function process_contact_updated($contact_fields) {
        // Validar que tenemos los datos mínimos
        if (empty($contact_fields['ID'])) {
            yeison_btx_log('Proceso de actualización: Contact ID vacío', 'error', array(
                'contact_fields' => $contact_fields
            ));
            return false;
        }
        
        // 🔥 OBTENER DATOS COMPLETOS DEL CONTACTO desde Bitrix24
        $api = yeison_btx_api();
        $contact_response = $api->api_call('crm.contact.get', array(
            'id' => $contact_fields['ID']
        ));
        
        if (!$contact_response || !isset($contact_response['result'])) {
            yeison_btx_log('❌ Error obteniendo datos completos del contacto', 'error', array(
                'contact_id' => $contact_fields['ID'],
                'response' => $contact_response
            ));
            return false;
        }
        
        // Usar datos completos del contacto
        $complete_contact_fields = $contact_response['result'];
        
        
        // Disparar evento para sincronización bidireccional con datos completos
        do_action('yeison_btx_contact_webhook_received', 'ONCRMCONTACTUPDATE', $complete_contact_fields);
        
        
        return true;
    }
    
    
    public function get_webhook_status() {
        $api = yeison_btx_api();
        
        $status = array(
            'enabled' => $this->config['enabled'],
            'version' => 'fixed_version',
            'endpoints' => array(
                'deal' => rest_url('yeison-bitrix/v1/webhook/deal'),
                'contact' => rest_url('yeison-bitrix/v1/webhook/contact'),
                'test' => rest_url('yeison-bitrix/v1/webhook/test')
            ),
            'events' => $this->config['allowed_events'],
            'api_authorized' => $api->is_authorized(),
            'webhook_secret_configured' => !empty($this->config['webhook_secret']),
            'timestamp' => current_time('mysql'),
            'registered_webhooks' => $this->get_registered_webhooks_status()
        );
        
        return new WP_REST_Response($status, 200);
    }
    
    // ... mantener todos los demás métodos exactamente iguales ...
    
    public function auto_register_webhooks() {
        if (!$this->config['auto_register']) {
            return;
        }
        
        $api = yeison_btx_api();
        if (!$api->is_authorized()) {
            return;
        }
        
        yeison_btx_log('Iniciando auto-registro de webhooks', 'info');
        
        $webhooks_to_register = array(
            array(
                'event' => 'ONCRMDEALADD',
                'handler' => rest_url('yeison-bitrix/v1/webhook/deal')
            ),
            array(
                'event' => 'ONCRMDEALUPDATE', 
                'handler' => rest_url('yeison-bitrix/v1/webhook/deal')
            ),
            array(
                'event' => 'ONCRMCONTACTADD',
                'handler' => rest_url('yeison-bitrix/v1/webhook/contact')
            ),
            array(
                'event' => 'ONCRMCONTACTUPDATE',
                'handler' => rest_url('yeison-bitrix/v1/webhook/contact')
            )
        );
        
        $registered = 0;
        foreach ($webhooks_to_register as $webhook) {
            if ($this->register_webhook_in_bitrix($webhook['event'], $webhook['handler'])) {
                $registered++;
            }
        }
        
        
        return $registered;
    }
    
    private function register_webhook_in_bitrix($event, $handler_url) {
        $api = yeison_btx_api();
        
        // Verificar si ya existe
        $existing = $api->api_call('event.get', array(
            'filter' => array(
                'EVENT' => $event,
                'HANDLER' => $handler_url
            )
        ));
        
        if ($existing && !empty($existing['result'])) {
            
            return true;
        }
        
        // Registrar nuevo webhook
        $response = $api->api_call('event.bind', array(
            'event' => $event,
            'handler' => $handler_url
        ));
        
        if ($response && isset($response['result'])) {
            
            return true;
        }
        
        yeison_btx_log("Error registrando webhook: {$event}", 'error', array(
            'response' => $response
        ));
        
        return false;
    }
    
    private function get_registered_webhooks_status() {
        $api = yeison_btx_api();
        
        if (!$api->is_authorized()) {
            return array('error' => 'API no autorizada');
        }
        
        $response = $api->api_call('event.get');
        
        if (!$response || !isset($response['result'])) {
            return array('error' => 'No se pudieron obtener webhooks');
        }
        
        $our_webhooks = array();
        $site_domain = parse_url(home_url(), PHP_URL_HOST);
        
        foreach ($response['result'] as $webhook) {
            if (isset($webhook['HANDLER']) && (
                strpos($webhook['HANDLER'], $site_domain) !== false || 
                strpos($webhook['HANDLER'], 'yeison-bitrix/v1/webhook') !== false
            )) {
                $our_webhooks[] = $webhook;
            }
        }
        
        return $our_webhooks;
    }
    
    public function cleanup_webhooks() {
        $api = yeison_btx_api();
        
        if (!$api->is_authorized()) {
            return;
        }
        
        yeison_btx_log('Limpiando webhooks de Bitrix24', 'info');
        
        $response = $api->api_call('event.get');
        
        if ($response && isset($response['result'])) {
            $site_url = parse_url(home_url(), PHP_URL_HOST);
            $removed = 0;
            
            foreach ($response['result'] as $webhook) {
                if (strpos($webhook['HANDLER'], $site_url) !== false) {
                    $unbind_response = $api->api_call('event.unbind', array(
                        'event' => $webhook['EVENT'],
                        'handler' => $webhook['HANDLER']
                    ));
                    
                    if ($unbind_response && isset($unbind_response['result'])) {
                        $removed++;
                    }
                }
            }
            
            yeison_btx_log("Webhooks removidos: {$removed}", 'success');
        }
    }
    
    public function test_webhook_connectivity() {
        $api = yeison_btx_api();
        
        if (!$api->is_authorized()) {
            return array(
                'success' => false,
                'message' => 'API no autorizada'
            );
        }
        
        $test_url = rest_url('yeison-bitrix/v1/webhook/test');
        
        $test_data = array(
            'test' => true,
            'timestamp' => time(),
            'source' => 'manual_test'
        );
        
        $response = wp_remote_post($test_url, array(
            'body' => wp_json_encode($test_data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'Bitrix24-Test'
            )
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Error en test: ' . $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        
        return array(
            'success' => true,
            'message' => 'Test de webhook exitoso',
            'response' => $decoded
        );
    }
    
    public function force_register_webhooks() {
        $api = yeison_btx_api();
        
        if (!$api->is_authorized()) {
            yeison_btx_log('🔴 No se pueden registrar webhooks: API no autorizada', 'error');
            return false;
        }
        
        $webhooks_to_register = array(
            array(
                'event' => 'ONCRMCONTACTUPDATE',
                'handler' => rest_url('yeison-bitrix/v1/webhook/contact')
            ),
            array(
                'event' => 'ONCRMCONTACTADD',
                'handler' => rest_url('yeison-bitrix/v1/webhook/contact')
            ),
            array(
                'event' => 'ONCRMDEALUPDATE',
                'handler' => rest_url('yeison-bitrix/v1/webhook/deal')
            ),
            array(
                'event' => 'ONCRMDEALADD',
                'handler' => rest_url('yeison-bitrix/v1/webhook/deal')
            )
        );
        
        $registered = 0;




        foreach ($webhooks_to_register as $webhook) {
            
            
            // ✅ Verificar si ya existe el webhook ANTES de registrar
            $existing_check = $api->api_call('event.get', array(
                'filter' => array(
                    'EVENT' => $webhook['event'],
                    'HANDLER' => $webhook['handler']
                )
            ));
            
            if ($existing_check && !empty($existing_check['result'])) {
                
                $registered++;
                continue; // Saltar al siguiente webhook
            }
            
            $response = $api->api_call('event.bind', array(
                'event' => $webhook['event'],
                'handler' => $webhook['handler']
            ));


}






        
        yeison_btx_log("🎯 Registro de webhooks completado: {$registered} registrados", 'info');
        return $registered;
    }
}

/**
 * Función global para obtener instancia
 */
function yeison_btx_webhooks() {
    return YeisonBTX_Webhook_Handler::get_instance();
}