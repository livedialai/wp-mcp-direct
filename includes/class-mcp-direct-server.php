<?php
/**
 * MCP-Server: REST-Endpoint /wp-json/mcp-direct/v1/mcp
 * JSON-RPC 2.0 ueber Streamable HTTP (Antworten als application/json).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MCP_Direct_Server {

    const NS = 'mcp-direct/v1';

    public static function register_routes() {
        register_rest_route(self::NS, '/mcp', array(
            array(
                'methods'             => 'POST',
                'callback'            => array(__CLASS__, 'handle_post'),
                'permission_callback' => array(__CLASS__, 'authenticate'),
            ),
            array(
                'methods'             => 'GET',
                'callback'            => array(__CLASS__, 'handle_get'),
                'permission_callback' => array(__CLASS__, 'authenticate'),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array(__CLASS__, 'handle_delete'),
                'permission_callback' => array(__CLASS__, 'authenticate'),
            ),
        ));
    }

    /**
     * Auth: Bearer-Key gegen gespeicherten API-Key; setzt den Service-Benutzer.
     */
    public static function authenticate($request) {
        if (get_option('mcp_direct_enabled', '1') !== '1') {
            return new WP_Error('mcp_direct_disabled', 'MCP Direct ist in den Einstellungen deaktiviert.', array('status' => 403));
        }
        $token    = self::get_bearer_token();
        $expected = get_option('mcp_direct_api_key');
        if (!$expected || !$token || !hash_equals($expected, $token)) {
            self::log('AUTH FEHLGESCHLAGEN ip=' . self::client_ip());
            return new WP_Error('mcp_direct_unauthorized', 'Ungueltiger oder fehlender API-Key. Sende: Authorization: Bearer <key>', array('status' => 401));
        }
        $user_id = (int) get_option('mcp_direct_user_id', 1);
        if ($user_id && get_userdata($user_id)) {
            wp_set_current_user($user_id);
        }
        return true;
    }

    /**
     * Key aus Authorization-Header (oder X-API-Key) lesen - mit Hosting-Fallbacks.
     */
    private static function get_bearer_token() {
        $auth = '';
        foreach (array('HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'PHP_AUTH_PW') as $k) {
            if (!empty($_SERVER[$k])) {
                $auth = $_SERVER[$k];
                break;
            }
        }
        if ($auth === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            foreach ((array) $headers as $name => $value) {
                if (strtolower($name) === 'authorization') {
                    $auth = $value;
                    break;
                }
            }
        }
        if ($auth === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ((array) $headers as $name => $value) {
                if (strtolower($name) === 'authorization') {
                    $auth = $value;
                    break;
                }
            }
        }
        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            return trim((string) $_SERVER['HTTP_X_API_KEY']);
        }
        if ($auth !== '' && stripos($auth, 'Bearer ') === 0) {
            return trim(substr($auth, 7));
        }
        if ($auth !== '' && stripos($auth, 'Basic ') === 0) {
            // Basic: alles nach dem Doppelpunkt als Key akzeptieren (manche Clients senden so)
            $decoded = base64_decode(substr($auth, 6));
            if ($decoded !== false && strpos($decoded, ':') !== false) {
                return trim(substr($decoded, strpos($decoded, ':') + 1));
            }
        }
        return '';
    }

    public static function handle_get() {
        $resp = new WP_REST_Response(null, 405);
        $resp->header('Allow', 'POST, DELETE');
        return $resp;
    }

    public static function handle_delete() {
        // Streamable HTTP: Session-Ende ist bei uns zustandslos ein No-Op.
        return new WP_REST_Response(null, 200);
    }

    public static function handle_post($request) {
        $raw   = $request->get_body();
        $start = microtime(true);
        $msg   = json_decode($raw, true);

        if (!is_array($msg)) {
            return self::rpc_error(null, -32700, 'Parse error');
        }

        // Batch? (Array von Nachrichten)
        if (self::is_list($msg)) {
            $out = array();
            foreach ($msg as $single) {
                if (is_array($single)) {
                    $out[] = self::handle_one($single, $start)['body'];
                }
            }
            return new WP_REST_Response($out, 200);
        }

        $handled = self::handle_one($msg, $start);
        if ($handled['status'] === 202) {
            return new WP_REST_Response(null, 202);
        }
        return new WP_REST_Response($handled['body'], 200);
    }

    /**
     * Eine JSON-RPC-Nachricht verarbeiten.
     * Rueckgabe: array('body' => <jsonrpc-antwort>, 'status' => 200|202)
     */
    private static function handle_one($msg, $start) {
        $id     = array_key_exists('id', $msg) ? $msg['id'] : null;
        $method = isset($msg['method']) ? (string) $msg['method'] : '';
        $params = isset($msg['params']) && is_array($msg['params']) ? $msg['params'] : array();

        if ($method === '') {
            return array('body' => self::rpc_error($id, -32600, 'Invalid Request'), 'status' => 200);
        }

        // Notifications: keine Antwort
        if ($id === null && strpos($method, 'notifications/') === 0) {
            return array('body' => null, 'status' => 202);
        }

        switch ($method) {
            case 'initialize':
                $client = isset($params['clientInfo']['name']) ? $params['clientInfo']['name'] : '';
                if ($client !== '' && isset($params['clientInfo']['version'])) {
                    $client .= ' ' . $params['clientInfo']['version'];
                }
                update_option('mcp_direct_last_client', $client, false);
                $result = array(
                    'protocolVersion' => self::negotiate_protocol($params),
                    'capabilities'    => array('tools' => array('listChanged' => false)),
                    'serverInfo'      => array(
                        'name'    => 'mcp-direct',
                        'title'   => get_bloginfo('name') . ' MCP (direkt)',
                        'version' => MCP_DIRECT_VERSION,
                    ),
                    'instructions'    => 'Direkter MCP-Zugriff auf diese WordPress-Seite (inkl. WooCommerce). Nutze "site_info" fuer einen Ueberblick. Fuer alles, was kein eigenes Tool hat, nutze "rest_request" mit Routen wie /wp/v2/pages, /wp/v2/media oder /wc/v3/products. Speichere neue Inhalte standardmaessig als Entwurf, ausser der Nutzer will veroeffentlichen.',
                );
                return array('body' => self::rpc_ok($id, $result), 'status' => 200);

            case 'ping':
                return array('body' => self::rpc_ok($id, (object) array()), 'status' => 200);

            case 'tools/list':
                return array('body' => self::rpc_ok($id, array('tools' => MCP_Direct_Tools::definitions())), 'status' => 200);

            case 'tools/call':
                $name = isset($params['name']) ? (string) $params['name'] : '';
                $args = isset($params['arguments']) && is_array($params['arguments']) ? $params['arguments'] : array();
                $tool_result = MCP_Direct_Tools::call($name, $args);
                $ms = (int) round((microtime(true) - $start) * 1000);
                if (is_wp_error($tool_result)) {
                    self::log('tools/call ' . $name . ' -> FEHLER (' . $ms . 'ms): ' . $tool_result->get_error_message());
                    $result = array(
                        'content' => array(array('type' => 'text', 'text' => $tool_result->get_error_message())),
                        'isError' => true,
                    );
                } else {
                    self::log('tools/call ' . $name . ' ok (' . $ms . 'ms)');
                    $result = array(
                        'content' => array(array('type' => 'text', 'text' => $tool_result)),
                        'isError' => false,
                    );
                }
                return array('body' => self::rpc_ok($id, $result), 'status' => 200);

            default:
                return array('body' => self::rpc_error($id, -32601, 'Method not found: ' . $method), 'status' => 200);
        }
    }

    private static function negotiate_protocol($params) {
        $supported = array('2024-11-05', '2025-03-26', '2025-06-18');
        $asked     = isset($params['protocolVersion']) ? (string) $params['protocolVersion'] : '';
        if (in_array($asked, $supported, true)) {
            return $asked;
        }
        return '2025-06-18';
    }

    private static function rpc_ok($id, $result) {
        return array('jsonrpc' => '2.0', 'id' => $id, 'result' => $result);
    }

    private static function rpc_error($id, $code, $message) {
        return array('jsonrpc' => '2.0', 'id' => $id, 'error' => array('code' => $code, 'message' => $message));
    }

    private static function is_list($arr) {
        if (function_exists('array_is_list')) {
            return array_is_list($arr);
        }
        $i = 0;
        foreach ($arr as $k => $v) {
            if ($k !== $i++) {
                return false;
            }
        }
        return true;
    }

    private static function client_ip() {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?';
    }

    /**
     * Kleines Ringprotokoll (max. 60 Zeilen) fuer die Admin-Anzeige.
     */
    public static function log($line) {
        $log = get_option('mcp_direct_log', array());
        if (!is_array($log)) {
            $log = array();
        }
        $client = get_option('mcp_direct_last_client', '');
        $log[]  = current_time('Y-m-d H:i:s') . ' | ' . ($client !== '' ? '[' . $client . '] ' : '') . $line;
        if (count($log) > 60) {
            $log = array_slice($log, -60);
        }
        update_option('mcp_direct_log', $log, false);
    }
}
