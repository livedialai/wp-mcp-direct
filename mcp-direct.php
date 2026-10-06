<?php
/**
 * Plugin Name: MCP Direct – KI-Direktverbindung (API-Key)
 * Description: Lokaler MCP-Server für WordPress. Verbindet KI-Clients (Perplexity, Claude, ChatGPT, Cursor …) DIREKT mit dieser Seite – ohne Cloud-Umweg. Anmeldung per API-Key (Bearer).
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Thomas / Hermes
 * License: GPL-2.0-or-later
 * Text Domain: mcp-direct
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MCP_DIRECT_VERSION', '1.0.0');
define('MCP_DIRECT_FILE', __FILE__);
define('MCP_DIRECT_DIR', plugin_dir_path(__FILE__));

require_once MCP_DIRECT_DIR . 'includes/class-mcp-direct-tools.php';
require_once MCP_DIRECT_DIR . 'includes/class-mcp-direct-server.php';
require_once MCP_DIRECT_DIR . 'includes/class-mcp-direct-admin.php';

/**
 * Erzeugt einen neuen API-Key.
 */
function mcp_direct_generate_key() {
    return 'mcpd_' . bin2hex(random_bytes(24));
}

/**
 * Aktivierung: Key und Service-Benutzer anlegen, falls noch nicht vorhanden.
 */
register_activation_hook(__FILE__, function () {
    if (!get_option('mcp_direct_api_key')) {
        update_option('mcp_direct_api_key', mcp_direct_generate_key(), false);
    }
    if (!get_option('mcp_direct_user_id')) {
        $admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
        update_option('mcp_direct_user_id', $admins ? (int) $admins[0] : 1, false);
    }
    if (get_option('mcp_direct_enabled', null) === null) {
        update_option('mcp_direct_enabled', '1', false);
    }
});

add_action('rest_api_init', array('MCP_Direct_Server', 'register_routes'));

if (is_admin()) {
    MCP_Direct_Admin::init();
}
