<?php
/**
 * Admin: Einstellungen unter Einstellungen -> MCP Direct.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MCP_Direct_Admin {

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_init', array(__CLASS__, 'handle_post'));
        add_filter('plugin_action_links_' . plugin_basename(MCP_DIRECT_FILE), array(__CLASS__, 'action_links'));
    }

    public static function menu() {
        add_options_page('MCP Direct', 'MCP Direct', 'manage_options', 'mcp-direct', array(__CLASS__, 'render'));
    }

    public static function action_links($links) {
        $links[] = '<a href="' . esc_url(admin_url('options-general.php?page=mcp-direct')) . '">Einstellungen</a>';
        return $links;
    }

    public static function handle_post() {
        if (empty($_POST['mcp_direct_action']) || !current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('mcp_direct_settings');
        $action = sanitize_key($_POST['mcp_direct_action']);
        if ($action === 'save') {
            update_option('mcp_direct_enabled', !empty($_POST['mcp_direct_enabled']) ? '1' : '0', false);
            update_option('mcp_direct_user_id', isset($_POST['mcp_direct_user_id']) ? (int) $_POST['mcp_direct_user_id'] : 1, false);
        } elseif ($action === 'regenerate') {
            update_option('mcp_direct_api_key', mcp_direct_generate_key(), false);
        } elseif ($action === 'clearlog') {
            update_option('mcp_direct_log', array(), false);
        }
        wp_safe_redirect(admin_url('options-general.php?page=mcp-direct&updated=1'));
        exit;
    }

    public static function render() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $key      = get_option('mcp_direct_api_key');
        $enabled  = get_option('mcp_direct_enabled', '1') === '1';
        $user_id  = (int) get_option('mcp_direct_user_id', 1);
        $endpoint = rest_url('mcp-direct/v1/mcp');
        $log      = get_option('mcp_direct_log', array());
        $client   = get_option('mcp_direct_last_client', '');

        $admins = get_users(array('capability' => 'manage_options', 'fields' => array('ID', 'user_login', 'display_name'), 'number' => 50));
        ?>
        <div class="wrap">
            <h1>MCP Direct – KI-Direktverbindung</h1>
            <p>Dieser Endpoint verbindet KI-Clients (z.&nbsp;B. Perplexity) <strong>direkt</strong> mit dieser WordPress-Seite – ohne externen Cloud-Dienst. Anmeldung per API-Key.</p>

            <?php if (!empty($_GET['updated'])): ?>
                <div class="notice notice-success is-dismissible"><p>Gespeichert.</p></div>
            <?php endif; ?>

            <?php if (!is_ssl()): ?>
                <div class="notice notice-warning"><p><strong>Hinweis:</strong> Die Seite laeuft nicht ueber HTTPS. Fuer eine oeffentliche Verbindung unbedingt HTTPS aktivieren.</p></div>
            <?php endif; ?>

            <h2>1. Verbindungsdaten</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="mcp_endpoint">Endpoint-URL</label></th>
                    <td>
                        <input id="mcp_endpoint" type="text" readonly class="large-text code" value="<?php echo esc_attr($endpoint); ?>">
                        <p class="description">Diese URL bei Perplexity eintragen: <em>Einstellungen &rarr; Connectors &rarr; + Custom connector &rarr; Remote &rarr; MCP Server URL</em>.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mcp_key">API-Key</label></th>
                    <td>
                        <input id="mcp_key" type="text" readonly class="large-text code" value="<?php echo esc_attr((string) $key); ?>" onclick="this.select();">
                        <p class="description">
                            Bei Perplexity als <strong>Authentication: API Key</strong> eintragen (der Key wird als <code>Authorization: Bearer …</code> gesendet).
                            Geheim halten – wer den Key hat, hat vollen Admin-Zugriff.
                        </p>
                        <form method="post" style="margin-top:6px;">
                            <?php wp_nonce_field('mcp_direct_settings'); ?>
                            <input type="hidden" name="mcp_direct_action" value="regenerate">
                            <button type="submit" class="button" onclick="return confirm('Neuen Key erzeugen? Der alte wird sofort ungueltig.');">Neuen Key erzeugen</button>
                        </form>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Status</th>
                    <td>
                        <form method="post">
                            <?php wp_nonce_field('mcp_direct_settings'); ?>
                            <input type="hidden" name="mcp_direct_action" value="save">
                            <label><input type="checkbox" name="mcp_direct_enabled" value="1" <?php checked($enabled); ?>> Endpoint aktiv</label>
                            <p style="margin-top:8px;">
                                <label>Aktionen ausfuehren als:
                                    <select name="mcp_direct_user_id">
                                        <?php foreach ($admins as $admin): ?>
                                            <option value="<?php echo (int) $admin->ID; ?>" <?php selected($user_id, (int) $admin->ID); ?>>
                                                <?php echo esc_html($admin->display_name . ' (' . $admin->user_login . ', #' . $admin->ID . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </p>
                            <p><button type="submit" class="button button-primary">Speichern</button></p>
                        </form>
                    </td>
                </tr>
            </table>

            <h2>2. In Perplexity verbinden</h2>
            <ol>
                <li>Perplexity (Web oder Desktop) &rarr; <em>Konto/All settings &rarr; Connectors &rarr; + Custom connector &rarr; Remote</em>.</li>
                <li>Name: z.&nbsp;B. <code>Meine Website</code></li>
                <li>MCP Server URL: die Endpoint-URL oben (HTTPS).</li>
                <li>Authentication: <strong>API Key</strong> &rarr; den Key oben einfuegen.</li>
                <li>Transport: <strong>Streamable HTTP</strong> &rarr; Add &rarr; Connector-Karte anklicken und aktivieren.</li>
            </ol>
            <p class="description">Test per Kommandozeile:<br>
            <code>curl -s -X POST <?php echo esc_html($endpoint); ?> -H "Content-Type: application/json" -H "Authorization: Bearer <?php echo esc_html((string) $key); ?>" -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'</code></p>

            <h2>3. Protokoll (letzte Aufrufe)</h2>
            <p class="description">Letzter bekannter Client: <?php echo $client !== '' ? esc_html($client) : '–'; ?></p>
            <pre style="background:#fff;border:1px solid #ccd0d4;padding:10px;max-height:260px;overflow:auto;"><?php
                if (empty($log) || !is_array($log)) {
                    echo 'Noch keine Aufrufe.';
                } else {
                    echo esc_html(implode("\n", array_reverse($log)));
                }
            ?></pre>
            <form method="post">
                <?php wp_nonce_field('mcp_direct_settings'); ?>
                <input type="hidden" name="mcp_direct_action" value="clearlog">
                <button type="submit" class="button">Protokoll leeren</button>
            </form>
        </div>
        <?php
    }
}
