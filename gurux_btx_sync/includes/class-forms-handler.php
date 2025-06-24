<?php
/**
 * Manejador de formularios universal - VERSIÓN OPTIMIZADA SOLO TIMELINE COMMENTS
 * 
 * @package YeisonBTX
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class YeisonBTX_Forms_Handler {
    
    /**
     * Instancia única (Singleton)
     */
    private static $instance = null;
    
    /**
     * Configuración del handler
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
            'enabled' => yeison_btx_get_option('forms_capture_enabled', true),
            'auto_process' => yeison_btx_get_option('forms_auto_process', true),
            'excluded_fields' => array('password', 'pass', 'pwd', '_token', '_nonce'),
            'honeypot_fields' => array('website', 'url', 'homepage'),
            'allowed_form_patterns' => array(
                'contact', 'lead', 'quote', 'newsletter', 'subscribe',
                'inquiry', 'demo', 'consultation', 'wpcf7', 'wpforms',
                'elementor', 'mailchimp', 'gravity', 'ninja'),
            'excluded_form_patterns' => array(
                'login', 'admin', 'checkout', 'cart', 'woocommerce',
                'wp-', 'search', 'comment', 'password', 'register',
                'billing', 'shipping', 'payment', 'order', 'review')
        );
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Endpoint REST API
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Endpoint clásico (por si REST API falla)
        add_action('wp_ajax_nopriv_yeison_btx_form', array($this, 'handle_form_ajax'));
        add_action('wp_ajax_yeison_btx_form', array($this, 'handle_form_ajax'));
        
        // Hook para interceptar TODOS los envíos de formularios
        add_action('init', array($this, 'setup_universal_form_capture'), 1);
        
    }
    
    /**
     * Configurar captura universal de formularios
     */
    public function setup_universal_form_capture() {
        // No capturar en páginas de WooCommerce
        if (function_exists('is_woocommerce') && is_woocommerce()) return;
        if (function_exists('is_cart') && is_cart()) return;
        if (function_exists('is_checkout') && is_checkout()) return;
        if (function_exists('is_account_page') && is_account_page()) return;

        if (!$this->config['enabled']) {
            return;
        }
        
        // Agregar JavaScript para captura universal
        add_action('wp_footer', array($this, 'inject_universal_capture_script'));
        add_action('admin_footer', array($this, 'inject_universal_capture_script'));
    }
    
    /**
     * Inyectar script de captura universal
     */
    public function inject_universal_capture_script() {
        if (is_login()) return;
        
        ?>
        <script type="text/javascript">
        (function() {
            console.log('🚀 Yeison BTX: Sistema de captura optimizado cargado');
            
            function captureFormSubmission(event) {
                const form = event.target;
                
                if (!shouldCaptureForm(form)) {
                    return;
                }

                function shouldCaptureForm(form) {
                    const formId = form.id ? form.id.toLowerCase() : '';
                    const formClass = form.className ? form.className.toLowerCase() : '';
                    const formAction = form.action ? form.action.toLowerCase() : '';
                    
                    // EXCLUIR formularios específicos
                    const excludePatterns = [
                        'login', 'admin', 'checkout', 'cart', 'woocommerce', 
                        'wp-', 'search', 'comment', 'password', 'register',
                        'billing', 'shipping', 'payment', 'order'
                    ];
                    
                    for (let pattern of excludePatterns) {
                        if (formId.includes(pattern) || formClass.includes(pattern) || formAction.includes(pattern)) {
                            return false;
                        }
                    }
                    
                    // INCLUIR solo formularios de contacto/leads
                    const includePatterns = [
                        'contact', 'lead', 'quote', 'newsletter', 'subscribe',
                        'inquiry', 'demo', 'consultation', 'wpcf7', 'wpforms',
                        'elementor', 'mailchimp'
                    ];
                    
                    for (let pattern of includePatterns) {
                        if (formId.includes(pattern) || formClass.includes(pattern)) {
                            return true;
                        }
                    }
                    
                    // INCLUIR si tiene campos típicos de contacto
                    const inputs = form.querySelectorAll('input, textarea, select');
                    let hasContactFields = 0;
                    
                    for (let input of inputs) {
                        const name = input.name ? input.name.toLowerCase() : '';
                        const id = input.id ? input.id.toLowerCase() : '';
                        
                        if (name.includes('email') || id.includes('email') ||
                            name.includes('message') || id.includes('message') ||
                            name.includes('phone') || id.includes('phone') ||
                            name.includes('name') || id.includes('name')) {
                            hasContactFields++;
                        }
                    }
                    
                    return hasContactFields >= 2;
                }

                console.log('📝 Yeison BTX: Formulario de contacto detectado');
                
                // Recoger datos del formulario
                const formData = new FormData(form);
                const data = {};
                
                for (let [key, value] of formData.entries()) {
                    data[key] = value;
                }
                
                // Agregar metadatos
                data._yeison_meta = {
                    form_id: form.id || 'no-id',
                    form_action: form.action || window.location.href,
                    page_url: window.location.href,
                    page_title: document.title,
                    timestamp: new Date().toISOString(),
                    user_agent: navigator.userAgent.substring(0, 100)
                };
                
                // Enviar a nuestro endpoint
                fetch('<?php echo rest_url('yeison-btx/v1/form'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                    },
                    body: JSON.stringify({form_data: data})
                })
                .then(response => response.json())
                .then(result => {
                    console.log('✅ Yeison BTX: Formulario procesado exitosamente');
                })
                .catch(error => {
                    console.error('❌ Yeison BTX: Error procesando formulario:', error);
                });
            }
            
            // Interceptar envíos de formularios
            document.addEventListener('submit', captureFormSubmission, true);
            
            console.log('🎯 Yeison BTX: Sistema optimizado activo');
        })();
        </script>
        <?php
    }
    
    /**
     * Registrar rutas REST API
     */
    public function register_rest_routes() {
        register_rest_route('yeison-btx/v1', '/form', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_form_rest'),
            'permission_callback' => '__return_true',
            'args' => array(
                'form_data' => array(
                    'required' => true,
                    'type' => 'object'
                )
            )
        ));
        
        register_rest_route('yeison-btx/v1', '/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_status'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Manejar formulario via REST API
     */
    public function handle_form_rest($request) {
        $form_data = $request->get_param('form_data');
        $origin = $request->get_header('origin') ?: $request->get_header('referer');
        
        if (empty($form_data)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'No hay datos de formulario'
            ), 400);
        }
        
        $result = $this->process_form_submission($form_data, 'rest_api', $origin);
        
        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }
    
    /**
     * 🔥 FUNCIÓN PRINCIPAL OPTIMIZADA: Procesar formulario
     */
    public function process_form_submission($form_data, $source = 'unknown', $origin = '') {
        $result = array(
            'success' => false,
            'message' => '',
            'data' => array()
        );
        
        try {
            
            if (!$this->config['enabled']) {
                $result['message'] = 'Captura deshabilitada';
                return $result;
            }

            // Validaciones básicas
            if (!$this->is_allowed_form($form_data, $origin)) {
                $result['success'] = true;
                $result['message'] = 'Formulario procesado';
                return $result;
            }
            
            if ($this->is_spam($form_data)) {
                $result['success'] = true;
                $result['message'] = 'Formulario procesado correctamente';
                return $result;
            }
            
            // Sanitizar datos
            $clean_data = $this->sanitize_form_data($form_data);
            
            // Determinar tipo de formulario
            $form_type = $this->detect_form_type($clean_data, $origin);
            
            // Agregar metadatos
            $clean_data['_meta'] = array(
                'source' => $source,
                'origin' => $origin,
                'ip' => yeison_btx_get_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'timestamp' => current_time('mysql'),
                'form_type' => $form_type,
                'processing_method' => 'timeline_optimized'
            );
            
            // 🔥 PROCESAMIENTO DIRECTO OPTIMIZADO (sin cola)
            if ($this->config['auto_process']) {
                $direct_result = $this->process_form_direct($clean_data, $form_type);
                
                if ($direct_result['success']) {
                    $result['success'] = true;
                    $result['message'] = 'Formulario procesado exitosamente';
                    $result['data'] = $direct_result['data'];
                } else {
                    // Fallback a cola si falla el procesamiento directo
                    $queue_id = yeison_btx_add_to_queue($form_type, $clean_data);
                    
                    if ($queue_id) {
                        $result['success'] = true;
                        $result['message'] = 'Formulario en cola de procesamiento';
                        $result['data'] = array('queue_id' => $queue_id, 'method' => 'queue_fallback');
                    } else {
                        $result['message'] = 'Error procesando formulario';
                    }
                }
            } else {
                // Modo cola
                $queue_id = yeison_btx_add_to_queue($form_type, $clean_data);
                
                if ($queue_id) {
                    $result['success'] = true;
                    $result['message'] = 'Formulario recibido';
                    $result['data'] = array('queue_id' => $queue_id);
                }
            }
            
        } catch (Exception $e) {
            yeison_btx_log('💥 Error crítico en procesamiento optimizado', 'error', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            
            $result['message'] = 'Error interno del servidor';
        }
        
        return $result;
    }

    /**
     * 🆕 PROCESAMIENTO DIRECTO OPTIMIZADO (sin cola)
     */
    private function process_form_direct($form_data, $form_type) {
        $result = array('success' => false, 'data' => array());
        
        try {
            $api = yeison_btx_api();
            
            if (!$api->is_authorized()) {
                yeison_btx_log('❌ API no autorizada para procesamiento directo', 'error');
                return $result;
            }
            
            // 🔍 EXTRAER EMAIL Y VERIFICAR DUPLICADOS
            $lead_email = $this->extract_email_from_form_data($form_data);
            
            if (!empty($lead_email)) {
                $existing_lead = $this->check_existing_lead_by_email($lead_email);
                
                if ($existing_lead) {
                    
                    
                    // ✅ CREAR TIMELINE COMMENT (método que funciona perfectamente)
                    $comment_id = $this->create_timeline_comment_for_lead($existing_lead, $form_data);
                    
                    if ($comment_id) {
                        $result['success'] = true;
                        $result['data'] = array(
                            'type' => 'timeline_comment',
                            'lead_id' => $existing_lead,
                            'comment_id' => $comment_id,
                            'action' => 'existing_lead_updated'
                        );
                        
                        
                        
                        return $result;
                    }
                }
            }
            
            // 🆕 CREAR NUEVO LEAD (si no existe duplicado)
            $lead_data = yeison_btx_map_form_to_lead($form_data);
            $lead_id = $api->create_lead($lead_data);
            
            if ($lead_id) {
                $result['success'] = true;
                $result['data'] = array(
                    'type' => 'new_lead',
                    'lead_id' => $lead_id,
                    'action' => 'new_lead_created'
                );
                
            }
            
        } catch (Exception $e) {
            yeison_btx_log('❌ Error en procesamiento directo', 'error', array(
                'error' => $e->getMessage()
            ));
        }
        
        return $result;
    }

    /**
     * 🔥 FUNCIÓN OPTIMIZADA: Extraer email de formulario
     */
    private function extract_email_from_form_data($form_data) {
        $possible_fields = array(
            'email', 'Email', 'EMAIL', 'e-mail', 'e_mail', 
            'correo', 'mail', 'your-email', 'contact-email'
        );
        
        // 1. Búsqueda directa por campos conocidos
        foreach ($possible_fields as $field) {
            if (isset($form_data[$field]) && !empty($form_data[$field])) {
                $email = sanitize_email($form_data[$field]);
                if (is_email($email)) {
                    return $email;
                }
            }
        }
        
        // 2. Búsqueda por contenido que contiene 'email'
        foreach ($form_data as $key => $value) {
            if (strpos(strtolower($key), 'email') !== false && !empty($value)) {
                $email = sanitize_email($value);
                if (is_email($email)) {
                    return $email;
                }
            }
        }
        
        // 3. Búsqueda por formato de email
        foreach ($form_data as $value) {
            if (is_string($value) && strpos($value, '@') !== false) {
                $email = sanitize_email($value);
                if (is_email($email)) {
                    return $email;
                }
            }
        }
        
        return null;
    }

    /**
     * 🔥 FUNCIÓN OPTIMIZADA: Verificar Lead existente
     */
    private function check_existing_lead_by_email($email) {
        if (empty($email) || !is_email($email)) {
            return false;
        }
        
        $api = yeison_btx_api();
        
        if (!$api->is_authorized()) {
            return false;
        }
        
        
        $response = $api->api_call('crm.lead.list', array(
            'filter' => array('EMAIL' => $email),
            'select' => array('ID', 'EMAIL', 'STATUS_ID')
        ));
        
        if ($response && isset($response['result']) && !empty($response['result'])) {
            $lead_id = $response['result'][0]['ID'];
            
            
            
            return $lead_id;
        }
        
        
        return false;
    }

    /**
     * 🔥 FUNCIÓN PRINCIPAL: Crear Timeline Comment (método que funciona 100%)
     */
    private function create_timeline_comment_for_lead($lead_id, $form_data) {
        $api = yeison_btx_api();
        
        if (!$api->is_authorized()) {
            return false;
        }
        
        
        // 🎨 CREAR COMENTARIO FORMATEADO Y ATRACTIVO
        $comment_parts = array();
        $comment_parts[] = "🌟 **NUEVO FORMULARIO WEB RECIBIDO** 🌟";
        $comment_parts[] = "";
        $comment_parts[] = "🌐 **Sitio Web:** " . parse_url(home_url(), PHP_URL_HOST);
        
        if (isset($form_data['_meta']['origin'])) {
            $comment_parts[] = "📍 **Página de Origen:** " . $form_data['_meta']['origin'];
        }
        
        $comment_parts[] = "";
        $comment_parts[] = "👤 **INFORMACIÓN DEL CONTACTO:**";
        
        // Mapeo mejorado de campos
        $field_labels = array(
            'name' => '👤 Nombre',
            'first_name' => '👤 Nombre',
            'last_name' => '👤 Apellido',
            'email' => '📧 Email',
            'phone' => '📞 Teléfono',
            'company' => '🏢 Empresa',
            'message' => '💬 Mensaje',
            'subject' => '📝 Asunto',
            'website' => '🌐 Sitio Web',
            'budget' => '💰 Presupuesto'
        );
        
        foreach ($form_data as $key => $value) {
            if (!is_array($value) && !empty($value) && $key !== '_meta' && strpos($key, '_') !== 0) {
                $label = $field_labels[strtolower($key)] ?? ('📋 ' . ucfirst(str_replace(array('_', '-'), ' ', $key)));
                $comment_parts[] = $label . ": **" . $value . "**";
            }
        }
        
        $comment_parts[] = "";
        $comment_parts[] = "🕒 **Fecha:** " . current_time('Y-m-d H:i:s');
        $comment_parts[] = "🤖 **Sistema:** Yeison BTX (Timeline Optimizado)";
        
        if (isset($form_data['_meta']['ip'])) {
            $comment_parts[] = "🌍 **IP:** " . $form_data['_meta']['ip'];
        }
        
        $comment_text = implode("\n", $comment_parts);
        
        // Datos para Timeline API
        $timeline_data = array(
            'ENTITY_ID' => $lead_id,
            'ENTITY_TYPE' => 'lead',
            'COMMENT' => $comment_text
        );
        
        // ✅ ENVIAR A BITRIX24 (método comprobado que funciona)
        $response = $api->api_call('crm.timeline.comment.add', array(
            'fields' => $timeline_data
        ));
        
        if ($response && isset($response['result'])) {
            $comment_id = $response['result'];
            
            
            
            return $comment_id;
        }
        
        yeison_btx_log('❌ Error creando Timeline Comment', 'error', array(
            'lead_id' => $lead_id,
            'response' => $response
        ));
        
        return false;
    }

    // === FUNCIONES DE UTILIDAD ===
    
    private function is_allowed_form($form_data, $origin = '') {
        $origin_lower = strtolower($origin);
        
        // Excluir patrones problemáticos
        foreach ($this->config['excluded_form_patterns'] as $pattern) {
            if (strpos($origin_lower, $pattern) !== false) {
                return false;
            }
        }
        
        // Incluir patrones permitidos
        foreach ($this->config['allowed_form_patterns'] as $pattern) {
            if (strpos($origin_lower, $pattern) !== false) {
                return true;
            }
        }
        
        // Verificar campos de contacto
        $contact_fields_count = 0;
        foreach (array_keys($form_data) as $field) {
            $field_lower = strtolower($field);
            
            if (strpos($field_lower, 'email') !== false ||
                strpos($field_lower, 'message') !== false ||
                strpos($field_lower, 'phone') !== false ||
                strpos($field_lower, 'name') !== false) {
                $contact_fields_count++;
            }
        }
        
        return $contact_fields_count >= 2;
    }

    private function sanitize_form_data($form_data) {
        $clean_data = array();
        
        foreach ($form_data as $key => $value) {
            if (in_array(strtolower($key), $this->config['excluded_fields'])) {
                continue;
            }
            
            if (is_array($value)) {
                $clean_data[$key] = $this->sanitize_form_data($value);
            } elseif (is_email($value)) {
                $clean_data[$key] = sanitize_email($value);
            } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
                $clean_data[$key] = esc_url_raw($value);
            } else {
                $clean_data[$key] = sanitize_textarea_field($value);
            }
        }
        
        return $clean_data;
    }
    
    private function detect_form_type($form_data, $origin = '') {
        $patterns = array(
            'contact' => array('email', 'name', 'message'),
            'quote' => array('budget', 'project', 'price'),
            'newsletter' => array('email', 'subscribe'),
            'support' => array('help', 'support', 'issue')
        );
        
        $fields = array_keys($form_data);
        $field_string = strtolower(implode(' ', $fields));
        $origin_string = strtolower($origin);
        
        foreach ($patterns as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($field_string, $keyword) !== false || 
                    strpos($origin_string, $keyword) !== false) {
                    return $type;
                }
            }
        }
        
        return 'general';
    }
    
    private function is_spam($form_data) {
        // Verificar honeypot
        foreach ($this->config['honeypot_fields'] as $honeypot) {
            if (isset($form_data[$honeypot]) && !empty($form_data[$honeypot])) {
                return true;
            }
        }
        
        // Verificar tiempo muy rápido
        if (isset($form_data['_start_time'])) {
            $time_taken = time() - intval($form_data['_start_time']);
            if ($time_taken < 3) {
                return true;
            }
        }
        
        return false;
    }
    
    public function get_status() {
        global $wpdb;
        
        $stats = array(
            'enabled' => $this->config['enabled'],
            'version' => 'optimized_timeline_only',
            'auto_process' => $this->config['auto_process'],
            'processing_method' => 'direct_with_queue_fallback',
            'pending_queue' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_queue WHERE status = 'pending'"),
            'processed_today' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}yeison_btx_queue WHERE status = 'processed' AND DATE(processed_at) = %s",
                current_time('Y-m-d')
            )),
            'endpoints' => array(
                'rest_api' => rest_url('yeison-btx/v1/form'),
                'ajax' => admin_url('admin-ajax.php?action=yeison_btx_form')
            )
        );
        
        return rest_ensure_response($stats);
    }
}

/**
 * Función global para obtener instancia
 */
function yeison_btx_forms() {
    return YeisonBTX_Forms_Handler::get_instance();
}