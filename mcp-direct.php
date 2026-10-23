<?php
/**
 * Plugin Name: MCP Direct – KI-Direktverbindung (API-Key)
 * Description: Lokaler MCP-Server für WordPress. Verbindet KI-Clients (Perplexity, Claude, ChatGPT, Cursor …) DIREKT mit dieser Seite – ohne Cloud-Umweg. Anmeldung per API-Key (Bearer). Enthaelt SEO-Werkzeuge: Sitemaps bauen, IndexNow, Audit, Meta-Titel/Description, robots.txt.
 * Version: 1.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Thomas / Hermes
 * License: GPL-2.0-or-later
 * Text Domain: mcp-direct
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MCP_DIRECT_VERSION', '1.1.0');
define('MCP_DIRECT_FILE', __FILE__);
define('MCP_DIRECT_DIR', plugin_dir_path(__FILE__));

require_once MCP_DIRECT_DIR . 'includes/class-mcp-direct-tools.php';
require_once MCP_DIRECT_DIR . 'includes/class-mcp-direct-seo-tools.php';
require_once MCP_DIRECT_DIR . 'includes/class-mcp-direct-server.php';
require_once MCP_DIRECT_DIR . 'includes/class-mcp-direct-admin.php';

/**
 * Ausgabe der eigenen SEO-Felder (funktioniert ohne Yoast/RankMath).
 * Wird nur aktiv, wenn fuer den Inhalt ein Wert gesetzt wurde.
 */
add_filter('pre_get_document_title', function ($title) {
    if (is_singular()) {
        $own = get_post_meta(get_queried_object_id(), '_mcp_seo_title', true);
        if ($own) {
            return $own;
        }
    }
    return $title;
}, 20);

add_action('wp_head', function () {
    if (!is_singular()) {
        return;
    }
    $id = get_queried_object_id();
    $desc = get_post_meta($id, '_mcp_seo_description', true);
    // Andere Module (z. B. Sprachversionen) koennen die Ausgabe unterdruecken.
    if (!empty($GLOBALS['mcp_seo_skip_description'])) {
        $desc = '';
    }
    if ($desc) {
        echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
    }
    if (get_post_meta($id, '_mcp_seo_noindex', true) === '1') {
        echo '<meta name="robots" content="noindex,follow">' . "\n";
    }
}, 1);

/** Zusatzzeilen fuer robots.txt (per Tool robots_txt setzbar). */
add_filter('robots_txt', function ($output) {
    $extra = get_option('mcp_seo_robots_extra');
    if ($extra) {
        $output = rtrim($output) . "\n\n" . rtrim($extra) . "\n";
    }
    return $output;
}, 20);

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
