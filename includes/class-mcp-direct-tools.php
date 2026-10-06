<?php
/**
 * Die MCP-Tools: Inhalte, WooCommerce, Medien, Dateien, Menues, REST-Passthrough.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MCP_Direct_Tools {

    public static function definitions() {
        return array(
            self::def_site_info(),
            self::def_rest_request(),
            self::def_post_save(),
            self::def_upload_media(),
            self::def_wc_list(),
            self::def_wc_update(),
            self::def_wc_create(),
            self::def_fs_list(),
            self::def_fs_read(),
            self::def_fs_write(),
            self::def_menus(),
        );
    }

    /** Tool ausfuehren. Rueckgabe: String (Text/JSON) oder WP_Error. */
    public static function call($name, $args) {
        try {
            switch ($name) {
                case 'site_info': return self::t_site_info($args);
                case 'rest_request': return self::t_rest_request($args);
                case 'post_save': return self::t_post_save($args);
                case 'upload_media_from_url': return self::t_upload_media($args);
                case 'woocommerce_list_products': return self::t_wc_list($args);
                case 'woocommerce_update_products': return self::t_wc_update($args);
                case 'woocommerce_create_product': return self::t_wc_create($args);
                case 'fs_list': return self::t_fs_list($args);
                case 'fs_read': return self::t_fs_read($args);
                case 'fs_write': return self::t_fs_write($args);
                case 'menus': return self::t_menus($args);
                default:
                    return new WP_Error('mcp_direct_unknown_tool', 'Unbekanntes Tool: ' . $name);
            }
        } catch (Throwable $e) {
            return new WP_Error('mcp_direct_exception', get_class($e) . ': ' . $e->getMessage());
        }
    }

    /* ============================ Definitionen ============================ */

    private static function def_site_info() {
        return array(
            'name' => 'site_info',
            'description' => 'Ueberblick ueber diese WordPress-Seite: URLs, Versionen, Theme, aktive Plugins, Anzahl Seiten/Beitraege/Produkte. Am Anfang nutzen.',
            'inputSchema' => array('type' => 'object', 'properties' => (object) array()),
        );
    }

    private static function def_rest_request() {
        return array(
            'name' => 'rest_request',
            'description' => 'Beliebige WordPress- oder WooCommerce-REST-Route aufrufen (volle Admin-Rechte). Beispiele: route "/wp/v2/pages" + params {"per_page":50}; route "/wp/v2/pages" + params {"search":"Impressum"} (GET); POST route "/wp/v2/pages" + params {"title":"Neu","content":"...","status":"draft"}; route "/wc/v3/products" (GET) oder "/wc/v3/products/categories" (POST {"name":"Neu"}); Update: PUT "/wp/v2/pages/123". Nutze dieses Tool fuer alles, was kein eigenes Tool hat (Menues via "menus", Dateien via fs_*).',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'method' => array('type' => 'string', 'enum' => array('GET', 'POST', 'PUT', 'PATCH', 'DELETE'), 'description' => 'HTTP-Methode (Standard: GET)'),
                    'route' => array('type' => 'string', 'description' => 'REST-Route, z. B. "/wp/v2/pages" oder "/wc/v3/products/123"'),
                    'params' => array('type' => 'object', 'description' => 'Parameter bzw. Body als Objekt (optional)'),
                ),
                'required' => array('route'),
            ),
        );
    }

    private static function def_post_save() {
        return array(
            'name' => 'post_save',
            'description' => 'Beitrag oder Seite anlegen oder aktualisieren. Ohne "id" wird neu angelegt (Standard-Status: draft). Mit "id" werden nur die angegebenen Felder geaendert.',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'id' => array('type' => 'integer', 'description' => 'ID eines bestehenden Beitrags/einer Seite (zum Aktualisieren)'),
                    'type' => array('type' => 'string', 'enum' => array('post', 'page'), 'description' => 'post oder page (Standard: page)'),
                    'title' => array('type' => 'string'),
                    'content' => array('type' => 'string', 'description' => 'Inhalt (HTML/Gutenberg erlaubt)'),
                    'excerpt' => array('type' => 'string'),
                    'slug' => array('type' => 'string'),
                    'status' => array('type' => 'string', 'enum' => array('draft', 'publish', 'pending', 'private')),
                    'parent' => array('type' => 'integer', 'description' => 'Eltern-Seite (ID)'),
                    'categories' => array('type' => 'array', 'items' => array('type' => 'integer'), 'description' => 'Kategorie-IDs (nur bei post)'),
                    'featured_media' => array('type' => 'integer', 'description' => 'Beitragsbild (Attachment-ID, 0 = entfernen)'),
                    'meta' => array('type' => 'object', 'description' => 'Zusaetzliche Meta-Felder (Schluessel/Wert)'),
                ),
            ),
        );
    }

    private static function def_upload_media() {
        return array(
            'name' => 'upload_media_from_url',
            'description' => 'Bild/Datei von einer URL herunterladen und in die Mediathek legen. Liefert die Attachment-ID fuer "featured_media" oder Produktbilder.',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'url' => array('type' => 'string', 'description' => 'Oeffentliche HTTP(S)-URL der Datei'),
                    'title' => array('type' => 'string'),
                    'alt_text' => array('type' => 'string'),
                    'filename' => array('type' => 'string', 'description' => 'Optionaler Dateiname inkl. Endung'),
                ),
                'required' => array('url'),
            ),
        );
    }

    private static function def_wc_list() {
        return array(
            'name' => 'woocommerce_list_products',
            'description' => 'WooCommerce-Produkte auflisten (mit Preisen, Lager, Kategorien). Filter: search, category, sku, status.',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'search' => array('type' => 'string', 'description' => 'Suchbegriff'),
                    'category' => array('description' => 'Kategorie-ID oder Slug (auch Liste)'),
                    'sku' => array('type' => 'string'),
                    'status' => array('type' => 'string', 'description' => 'publish, draft, private, any (Standard: publish)'),
                    'page' => array('type' => 'integer', 'description' => 'Seite (Standard 1)'),
                    'per_page' => array('type' => 'integer', 'description' => '1-100 (Standard 20)'),
                ),
            ),
        );
    }

    private static function def_wc_update() {
        return array(
            'name' => 'woocommerce_update_products',
            'description' => 'Mehrere WooCommerce-Produkte auf einmal aendern (Preise, Lager, Beschreibungen, Status, Kategorien). Fuer Massenbearbeitung.',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'updates' => array(
                        'type' => 'array',
                        'description' => 'Liste von Aenderungen',
                        'items' => array(
                            'type' => 'object',
                            'properties' => array(
                                'id' => array('type' => 'integer', 'description' => 'Produkt-ID'),
                                'name' => array('type' => 'string'),
                                'regular_price' => array('type' => 'string', 'description' => 'z. B. "12.50" (Punkt als Trenner)'),
                                'sale_price' => array('type' => 'string', 'description' => '"" = Angebotspreis entfernen'),
                                'stock_status' => array('type' => 'string', 'enum' => array('instock', 'outofstock', 'onbackorder')),
                                'stock_quantity' => array('type' => 'integer'),
                                'manage_stock' => array('type' => 'boolean'),
                                'description' => array('type' => 'string'),
                                'short_description' => array('type' => 'string'),
                                'status' => array('type' => 'string', 'enum' => array('publish', 'draft', 'private')),
                                'categories' => array('type' => 'array', 'items' => array('type' => 'integer'), 'description' => 'Kategorie-IDs (ersetzt Zuordnung)'),
                            ),
                            'required' => array('id'),
                        ),
                    ),
                ),
                'required' => array('updates'),
            ),
        );
    }

    private static function def_wc_create() {
        return array(
            'name' => 'woocommerce_create_product',
            'description' => 'Neues einfaches WooCommerce-Produkt anlegen (Standard-Status: publish).',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'name' => array('type' => 'string'),
                    'regular_price' => array('type' => 'string', 'description' => 'z. B. "9.90"'),
                    'sale_price' => array('type' => 'string'),
                    'sku' => array('type' => 'string'),
                    'description' => array('type' => 'string'),
                    'short_description' => array('type' => 'string'),
                    'status' => array('type' => 'string', 'enum' => array('publish', 'draft', 'private')),
                    'categories' => array('type' => 'array', 'items' => array('type' => 'integer'), 'description' => 'Kategorie-IDs'),
                    'image_url' => array('type' => 'string', 'description' => 'Bild-URL (wird importiert)'),
                ),
                'required' => array('name'),
            ),
        );
    }

    private static function def_fs_list() {
        return array(
            'name' => 'fs_list',
            'description' => 'Dateien/Ordner unterhalb von wp-content auflisten (Theme/Plugin-Dateien). Pfad relativ zu wp-content, z. B. "themes/mein-theme".',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'path' => array('type' => 'string', 'description' => 'Pfad relativ zu wp-content (Standard: "")'),
                ),
            ),
        );
    }

    private static function def_fs_read() {
        return array(
            'name' => 'fs_read',
            'description' => 'Datei unterhalb von wp-content lesen (z. B. Theme-CSS oder functions.php). Pfad relativ zu wp-content.',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'path' => array('type' => 'string'),
                ),
                'required' => array('path'),
            ),
        );
    }

    private static function def_fs_write() {
        return array(
            'name' => 'fs_write',
            'description' => 'Datei unterhalb von wp-content schreiben/aktualisieren (Theme-Anpassungen). Vorhandene Datei wird vorher automatisch gesichert. Immer nach dem Schreiben pruefen (fs_read).',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'path' => array('type' => 'string', 'description' => 'Pfad relativ zu wp-content'),
                    'content' => array('type' => 'string', 'description' => 'Kompletter neuer Dateiinhalt'),
                ),
                'required' => array('path', 'content'),
            ),
        );
    }

    private static function def_menus() {
        return array(
            'name' => 'menus',
            'description' => 'Navigation/Menues verwalten. action=list (Uebersicht), add_item (Punkt hinzufuegen; Menu per Name oder ID, legt fehlendes Menu an), set_location (Menu einer Theme-Position zuweisen), delete_item.',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'action' => array('type' => 'string', 'enum' => array('list', 'add_item', 'set_location', 'delete_item')),
                    'menu' => array('description' => 'Menue: ID (Zahl) oder Name (Text)'),
                    'title' => array('type' => 'string', 'description' => 'Beschriftung des Menuepunkts'),
                    'url' => array('type' => 'string', 'description' => 'Ziel-URL (fuer eigene Links)'),
                    'post_id' => array('type' => 'integer', 'description' => 'Ziel als Beitrag/Seite (ID)'),
                    'parent_id' => array('type' => 'integer'),
                    'position' => array('type' => 'integer'),
                    'location' => array('type' => 'string', 'description' => 'Theme-Position, z. B. "primary" oder "left-menu"'),
                    'item_id' => array('type' => 'integer', 'description' => 'Menuepunkt-ID (delete_item)'),
                ),
                'required' => array('action'),
            ),
        );
    }

    /* ============================ Implementierungen ============================ */

    private static function t_site_info($args) {
        $theme = wp_get_theme();
        $data  = array(
            'site_url' => site_url(),
            'name' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'locale' => get_locale(),
            'theme' => $theme->get('Name') . ' (' . $theme->get('Version') . ')',
            'permalink_structure' => get_option('permalink_structure'),
            'is_ssl' => is_ssl(),
            'counts' => array(
                'posts' => (int) wp_count_posts('post')->publish,
                'pages' => (int) wp_count_posts('page')->publish,
            ),
            'woocommerce' => class_exists('WooCommerce') ? WC()->version : null,
            'active_plugins' => array(),
            'mcp_endpoint' => rest_url('mcp-direct/v1/mcp'),
        );
        if (class_exists('WooCommerce')) {
            $data['counts']['products'] = (int) wp_count_posts('product')->publish;
        }
        foreach ((array) get_option('active_plugins', array()) as $plugin_file) {
            $data['active_plugins'][] = $plugin_file;
        }
        return self::json_out($data);
    }

    private static function t_rest_request($args) {
        $method = strtoupper(isset($args['method']) ? (string) $args['method'] : 'GET');
        if (!in_array($method, array('GET', 'POST', 'PUT', 'PATCH', 'DELETE'), true)) {
            return new WP_Error('mcp_direct_bad_method', 'Ungueltige Methode: ' . $method);
        }
        $route = isset($args['route']) ? trim((string) $args['route']) : '';
        if ($route === '') {
            return new WP_Error('mcp_direct_bad_route', 'route fehlt (z. B. "/wp/v2/pages").');
        }
        if (strpos($route, '/') !== 0) {
            $route = '/' . $route;
        }
        $params = isset($args['params']) && is_array($args['params']) ? $args['params'] : array();

        $req = new WP_REST_Request($method, $route);
        if (!empty($params)) {
            if (in_array($method, array('POST', 'PUT', 'PATCH', 'DELETE'), true)) {
                $req->set_header('Content-Type', 'application/json');
                $req->set_body(wp_json_encode($params));
                foreach ($params as $k => $v) {
                    $req->set_param($k, $v);
                }
            } else {
                $req->set_query_params($params);
                foreach ($params as $k => $v) {
                    $req->set_param($k, $v);
                }
            }
        }

        $resp = rest_do_request($req);
        if (is_wp_error($resp)) {
            return $resp;
        }
        $status  = $resp->get_status();
        $headers = $resp->get_headers();
        $out     = array('status' => $status, 'body' => $resp->get_data());
        if (isset($headers['X-WP-Total'])) {
            $out['total'] = (int) $headers['X-WP-Total'];
            $out['total_pages'] = isset($headers['X-WP-TotalPages']) ? (int) $headers['X-WP-TotalPages'] : null;
        }
        return self::json_out($out);
    }

    private static function t_post_save($args) {
        $id = isset($args['id']) ? (int) $args['id'] : 0;
        if ($id) {
            $existing = get_post($id);
            if (!$existing) {
                return new WP_Error('mcp_direct_post_missing', 'Beitrag/Seite mit ID ' . $id . ' nicht gefunden.');
            }
            $postarr = array('ID' => $id);
        } else {
            $type = isset($args['type']) ? (string) $args['type'] : 'page';
            if (!in_array($type, array('post', 'page'), true)) {
                return new WP_Error('mcp_direct_bad_type', 'type muss "post" oder "page" sein.');
            }
            $postarr = array('post_type' => $type, 'post_status' => 'draft');
        }

        if (isset($args['title'])) {
            $postarr['post_title'] = (string) $args['title'];
        }
        if (isset($args['content'])) {
            $postarr['post_content'] = (string) $args['content'];
        }
        if (isset($args['excerpt'])) {
            $postarr['post_excerpt'] = (string) $args['excerpt'];
        }
        if (isset($args['slug'])) {
            $postarr['post_name'] = sanitize_title((string) $args['slug']);
        }
        if (isset($args['status'])) {
            $status = (string) $args['status'];
            if (!in_array($status, array('draft', 'publish', 'pending', 'private'), true)) {
                return new WP_Error('mcp_direct_bad_status', 'status muss draft/publish/pending/private sein.');
            }
            $postarr['post_status'] = $status;
        }
        if (isset($args['parent'])) {
            $postarr['post_parent'] = (int) $args['parent'];
        }

        $new_id = $id ? wp_update_post($postarr, true) : wp_insert_post($postarr, true);
        if (is_wp_error($new_id)) {
            return $new_id;
        }

        if (isset($args['categories']) && get_post_type($new_id) === 'post') {
            wp_set_post_categories($new_id, array_map('intval', (array) $args['categories']));
        }
        if (isset($args['featured_media'])) {
            $thumb = (int) $args['featured_media'];
            if ($thumb > 0) {
                set_post_thumbnail($new_id, $thumb);
            } else {
                delete_post_thumbnail($new_id);
            }
        }
        if (isset($args['meta']) && is_array($args['meta'])) {
            foreach ($args['meta'] as $meta_key => $meta_value) {
                update_post_meta($new_id, (string) $meta_key, $meta_value);
            }
        }

        return self::json_out(array(
            'id' => $new_id,
            'type' => get_post_type($new_id),
            'status' => get_post_status($new_id),
            'title' => get_the_title($new_id),
            'link' => get_permalink($new_id),
            'updated' => (bool) $id,
        ));
    }

    private static function t_upload_media($args) {
        $url = isset($args['url']) ? esc_url_raw((string) $args['url']) : '';
        if ($url === '') {
            return new WP_Error('mcp_direct_bad_url', 'url fehlt.');
        }
        $id = self::sideload_url(
            $url,
            isset($args['title']) ? (string) $args['title'] : '',
            isset($args['alt_text']) ? (string) $args['alt_text'] : '',
            isset($args['filename']) ? (string) $args['filename'] : ''
        );
        if (is_wp_error($id)) {
            return $id;
        }
        return self::json_out(array(
            'id' => $id,
            'url' => wp_get_attachment_url($id),
            'mime' => get_post_mime_type($id),
        ));
    }

    /** Download + Attachment anlegen. Rueckgabe: Attachment-ID oder WP_Error. */
    private static function sideload_url($url, $title = '', $alt = '', $filename = '', $post_id = 0) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url($url, 120);
        if (is_wp_error($tmp)) {
            $msg = $tmp->get_error_message();
            if (stripos($msg, 'valid URL') !== false) {
                $msg .= ' (Hinweis: WordPress blockt interne/lokale Adressen aus Sicherheitsgruenden - nutze eine oeffentliche URL oder eine vorhandene Attachment-ID.)';
            }
            return new WP_Error('mcp_direct_download', 'Download fehlgeschlagen: ' . $msg);
        }

        if ($filename === '') {
            $path = (string) wp_parse_url($url, PHP_URL_PATH);
            $filename = sanitize_file_name(basename($path));
        }
        if ($filename === '' || strpos($filename, '.') === false) {
            $mime = function_exists('mime_content_type') ? mime_content_type($tmp) : '';
            $ext  = '';
            if ($mime) {
                $map = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/svg+xml' => 'svg', 'application/pdf' => 'pdf');
                if (isset($map[$mime])) {
                    $ext = '.' . $map[$mime];
                }
            }
            // Dateiname ohne Endung (z. B. URL endet auf "/png") -> neutraler Name + erkannte Endung
            $filename = 'datei-' . time() . $ext;
        }

        $file_array = array('name' => $filename, 'tmp_name' => $tmp);
        $id = media_handle_sideload($file_array, (int) $post_id, $title !== '' ? $title : null);
        if (is_wp_error($id)) {
            @unlink($tmp);
            return new WP_Error('mcp_direct_sideload', 'Import fehlgeschlagen: ' . $id->get_error_message());
        }
        if ($alt !== '') {
            update_post_meta($id, '_wp_attachment_image_alt', $alt);
        }
        return $id;
    }

    private static function t_wc_list($args) {
        if (!class_exists('WooCommerce')) {
            return new WP_Error('mcp_direct_no_wc', 'WooCommerce ist auf dieser Seite nicht aktiv.');
        }
        $per_page = isset($args['per_page']) ? (int) $args['per_page'] : 20;
        $per_page = max(1, min(100, $per_page));
        $page     = max(1, isset($args['page']) ? (int) $args['page'] : 1);

        $q = array(
            'limit'    => $per_page,
            'page'     => $page,
            'paginate' => true,
            'status'   => isset($args['status']) && $args['status'] !== '' ? (string) $args['status'] : 'publish',
            'orderby'  => 'date',
            'order'    => 'DESC',
        );
        if (!empty($args['search'])) {
            $q['s'] = (string) $args['search'];
        }
        if (!empty($args['sku'])) {
            $q['sku'] = (string) $args['sku'];
        }
        if (!empty($args['category'])) {
            $slugs = array();
            foreach ((array) $args['category'] as $cat) {
                if (is_numeric($cat)) {
                    $term = get_term((int) $cat, 'product_cat');
                    if ($term && !is_wp_error($term)) {
                        $slugs[] = $term->slug;
                    }
                } else {
                    $slugs[] = (string) $cat;
                }
            }
            if ($slugs) {
                $q['category'] = $slugs;
            }
        }

        $res = wc_get_products($q);
        $out = array(
            'total' => is_object($res) ? (int) $res->total : 0,
            'pages' => is_object($res) ? (int) $res->max_num_pages : 0,
            'page' => $page,
            'products' => array(),
        );
        $products = is_object($res) ? $res->products : array();
        foreach ($products as $p) {
            $cats = wp_get_post_terms($p->get_id(), 'product_cat', array('fields' => 'names'));
            $out['products'][] = array(
                'id' => $p->get_id(),
                'name' => $p->get_name(),
                'sku' => $p->get_sku(),
                'type' => $p->get_type(),
                'status' => $p->get_status(),
                'price' => $p->get_price(),
                'regular_price' => $p->get_regular_price(),
                'sale_price' => $p->get_sale_price(),
                'stock_status' => $p->get_stock_status(),
                'stock_quantity' => $p->get_stock_quantity(),
                'categories' => is_array($cats) ? $cats : array(),
            );
        }
        return self::json_out($out);
    }

    private static function t_wc_update($args) {
        if (!class_exists('WooCommerce')) {
            return new WP_Error('mcp_direct_no_wc', 'WooCommerce ist auf dieser Seite nicht aktiv.');
        }
        $updates = isset($args['updates']) && is_array($args['updates']) ? $args['updates'] : array();
        if (!$updates) {
            return new WP_Error('mcp_direct_no_updates', 'updates fehlt (Liste von {id, ...}).');
        }
        $results = array();
        $ok = 0;
        $failed = 0;
        foreach ($updates as $u) {
            $id = isset($u['id']) ? (int) $u['id'] : 0;
            if (!$id) {
                $results[] = array('id' => null, 'ok' => false, 'error' => 'id fehlt');
                $failed++;
                continue;
            }
            $p = wc_get_product($id);
            if (!$p) {
                $results[] = array('id' => $id, 'ok' => false, 'error' => 'Produkt nicht gefunden');
                $failed++;
                continue;
            }
            try {
                if (array_key_exists('name', $u)) {
                    $p->set_name((string) $u['name']);
                }
                if (array_key_exists('regular_price', $u)) {
                    $p->set_regular_price((string) $u['regular_price']);
                }
                if (array_key_exists('sale_price', $u)) {
                    $p->set_sale_price((string) $u['sale_price']);
                }
                if (array_key_exists('stock_status', $u)) {
                    $p->set_stock_status((string) $u['stock_status']);
                }
                if (array_key_exists('manage_stock', $u)) {
                    $p->set_manage_stock((bool) $u['manage_stock']);
                }
                if (array_key_exists('stock_quantity', $u)) {
                    $p->set_stock_quantity((int) $u['stock_quantity']);
                }
                if (array_key_exists('description', $u)) {
                    $p->set_description((string) $u['description']);
                }
                if (array_key_exists('short_description', $u)) {
                    $p->set_short_description((string) $u['short_description']);
                }
                if (array_key_exists('status', $u)) {
                    $p->set_status((string) $u['status']);
                }
                if (array_key_exists('categories', $u)) {
                    $p->set_category_ids(array_map('intval', (array) $u['categories']));
                }
                $p->save();
                $results[] = array('id' => $id, 'ok' => true, 'price' => $p->get_price(), 'stock_status' => $p->get_stock_status());
                $ok++;
            } catch (Throwable $e) {
                $results[] = array('id' => $id, 'ok' => false, 'error' => $e->getMessage());
                $failed++;
            }
        }
        return self::json_out(array('updated' => $ok, 'failed' => $failed, 'results' => $results));
    }

    private static function t_wc_create($args) {
        if (!class_exists('WooCommerce')) {
            return new WP_Error('mcp_direct_no_wc', 'WooCommerce ist auf dieser Seite nicht aktiv.');
        }
        $name = isset($args['name']) ? trim((string) $args['name']) : '';
        if ($name === '') {
            return new WP_Error('mcp_direct_no_name', 'name fehlt.');
        }
        try {
            $p = new WC_Product_Simple();
            $p->set_name($name);
            if (isset($args['regular_price'])) {
                $p->set_regular_price((string) $args['regular_price']);
            }
            if (isset($args['sale_price']) && $args['sale_price'] !== '') {
                $p->set_sale_price((string) $args['sale_price']);
            }
            if (!empty($args['sku'])) {
                $p->set_sku((string) $args['sku']);
            }
            if (isset($args['description'])) {
                $p->set_description((string) $args['description']);
            }
            if (isset($args['short_description'])) {
                $p->set_short_description((string) $args['short_description']);
            }
            if (!empty($args['categories'])) {
                $p->set_category_ids(array_map('intval', (array) $args['categories']));
            }
            $status = isset($args['status']) && in_array($args['status'], array('publish', 'draft', 'private'), true) ? (string) $args['status'] : 'publish';
            $p->set_status($status);
            if (!empty($args['image_url'])) {
                $img_id = self::sideload_url((string) $args['image_url'], $name, '');
                if (!is_wp_error($img_id)) {
                    $p->set_image_id($img_id);
                }
            }
            $id = $p->save();
        } catch (Throwable $e) {
            return new WP_Error('mcp_direct_wc_error', $e->getMessage());
        }
        return self::json_out(array(
            'id' => $id,
            'name' => $p->get_name(),
            'price' => $p->get_price(),
            'status' => $p->get_status(),
            'link' => get_permalink($id),
        ));
    }

    private static function t_fs_list($args) {
        $rel  = isset($args['path']) ? trim((string) $args['path']) : '';
        $full = self::resolve_content_path($rel, false);
        if (is_wp_error($full)) {
            return $full;
        }
        if (!is_dir($full)) {
            return new WP_Error('mcp_direct_not_dir', 'Kein Verzeichnis: ' . $rel);
        }
        $items = array();
        $entries = @scandir($full);
        if (!is_array($entries)) {
            return new WP_Error('mcp_direct_list_fail', 'Verzeichnis nicht lesbar: ' . $rel);
        }
        $count = 0;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $count++;
            if ($count > 400) {
                $items[] = array('name' => '… (weitere Eintraege gekuerzt)');
                break;
            }
            $path = $full . '/' . $entry;
            $items[] = array(
                'name' => $entry,
                'type' => is_dir($path) ? 'dir' : 'file',
                'size' => is_file($path) ? filesize($path) : null,
                'modified' => date('Y-m-d H:i', filemtime($path)),
            );
        }
        return self::json_out(array('path' => $rel === '' ? '(wp-content)' : $rel, 'items' => $items));
    }

    private static function t_fs_read($args) {
        $rel = isset($args['path']) ? trim((string) $args['path']) : '';
        if ($rel === '') {
            return new WP_Error('mcp_direct_no_path', 'path fehlt.');
        }
        $full = self::resolve_content_path($rel, false);
        if (is_wp_error($full)) {
            return $full;
        }
        if (!is_file($full)) {
            return new WP_Error('mcp_direct_not_file', 'Keine Datei: ' . $rel);
        }
        if (filesize($full) > 524288) {
            return new WP_Error('mcp_direct_too_big', 'Datei zu gross (>512 KB): ' . $rel);
        }
        $content = file_get_contents($full);
        if ($content === false) {
            return new WP_Error('mcp_direct_read_fail', 'Datei nicht lesbar: ' . $rel);
        }
        return $content;
    }

    private static function t_fs_write($args) {
        $rel = isset($args['path']) ? trim((string) $args['path']) : '';
        if ($rel === '') {
            return new WP_Error('mcp_direct_no_path', 'path fehlt.');
        }
        if (!isset($args['content'])) {
            return new WP_Error('mcp_direct_no_content', 'content fehlt.');
        }
        $full = self::resolve_content_path($rel, true);
        if (is_wp_error($full)) {
            return $full;
        }
        if (file_exists($full) && is_dir($full)) {
            return new WP_Error('mcp_direct_is_dir', 'Ziel ist ein Verzeichnis: ' . $rel);
        }

        $backup = null;
        if (file_exists($full)) {
            $backup_dir = WP_CONTENT_DIR . '/uploads/mcp-direct-backups/' . date('Ymd-His');
            wp_mkdir_p($backup_dir . '/' . dirname($rel));
            $backup = $backup_dir . '/' . $rel;
            if (!@copy($full, $backup)) {
                $backup = null;
            }
        }

        wp_mkdir_p(dirname($full));
        $bytes = file_put_contents($full, (string) $args['content']);
        if ($bytes === false) {
            return new WP_Error('mcp_direct_write_fail', 'Schreiben fehlgeschlagen: ' . $rel);
        }
        @chmod($full, 0644);
        return self::json_out(array('ok' => true, 'bytes' => $bytes, 'path' => $rel, 'backup' => $backup));
    }

    /** Pfad relativ zu wp-content aufloesen (mit Ausbruchsschutz). Rueckgabe: absoluter Pfad oder WP_Error. */
    private static function resolve_content_path($rel, $for_write) {
        $rel = ltrim((string) $rel, '/');
        if ($rel !== '' && (strpos($rel, '..') !== false || preg_match('#^[a-zA-Z]:#', $rel))) {
            return new WP_Error('mcp_direct_bad_path', 'Ungueltiger Pfad: ' . $rel);
        }
        $base = WP_CONTENT_DIR;
        $full = $rel === '' ? $base : $base . '/' . $rel;

        if ($for_write) {
            // Elternordner pruefen, falls vorhanden
            $dir = is_dir(dirname($full)) ? realpath(dirname($full)) : null;
            $base_real = realpath($base);
            if ($dir !== null && $base_real !== false && strpos($dir, $base_real) !== 0) {
                return new WP_Error('mcp_direct_outside', 'Pfad ausserhalb von wp-content: ' . $rel);
            }
            return $full;
        }

        $real = realpath($full);
        $base_real = realpath($base);
        if ($real === false || $base_real === false || strpos($real, $base_real) !== 0) {
            return new WP_Error('mcp_direct_missing', 'Pfad nicht gefunden oder ausserhalb von wp-content: ' . $rel);
        }
        return $real;
    }

    private static function t_menus($args) {
        $action = isset($args['action']) ? (string) $args['action'] : '';
        switch ($action) {
            case 'list':
                $menus = array();
                foreach (wp_get_nav_menus() as $menu) {
                    $locations = array();
                    $assigned  = get_nav_menu_locations();
                    foreach ((array) $assigned as $loc => $menu_id) {
                        if ((int) $menu_id === (int) $menu->term_id) {
                            $locations[] = $loc;
                        }
                    }
                    $items = wp_get_nav_menu_items($menu->term_id);
                    $menus[] = array(
                        'id' => (int) $menu->term_id,
                        'name' => $menu->name,
                        'count' => $items ? count($items) : 0,
                        'locations' => $locations,
                    );
                }
                return self::json_out(array(
                    'menus' => $menus,
                    'registered_locations' => (object) get_registered_nav_menus(),
                ));

            case 'add_item':
                $menu_id = self::resolve_menu($args);
                if (is_wp_error($menu_id)) {
                    return $menu_id;
                }
                $title = isset($args['title']) ? (string) $args['title'] : '';
                if ($title === '') {
                    return new WP_Error('mcp_direct_no_title', 'title fehlt.');
                }
                $item = array(
                    'menu-item-title' => $title,
                    'menu-item-status' => 'publish',
                    'menu-item-position' => isset($args['position']) ? (int) $args['position'] : 0,
                    'menu-item-parent-id' => isset($args['parent_id']) ? (int) $args['parent_id'] : 0,
                );
                if (!empty($args['post_id'])) {
                    $post_id = (int) $args['post_id'];
                    $pt = get_post_type($post_id);
                    if (!$pt) {
                        return new WP_Error('mcp_direct_no_post', 'Beitrag/Seite mit ID ' . $post_id . ' nicht gefunden.');
                    }
                    $item['menu-item-type'] = 'post_type';
                    $item['menu-item-object'] = $pt;
                    $item['menu-item-object-id'] = $post_id;
                } elseif (!empty($args['url'])) {
                    $item['menu-item-type'] = 'custom';
                    $item['menu-item-url'] = esc_url_raw((string) $args['url']);
                } else {
                    return new WP_Error('mcp_direct_no_target', 'url oder post_id angeben.');
                }
                $item_id = wp_update_nav_menu_item($menu_id, 0, $item);
                if (is_wp_error($item_id)) {
                    return $item_id;
                }
                return self::json_out(array('ok' => true, 'menu_id' => $menu_id, 'item_id' => $item_id));

            case 'set_location':
                $location = isset($args['location']) ? (string) $args['location'] : '';
                $menu_id  = self::resolve_menu($args, false);
                if ($location === '' || is_wp_error($menu_id)) {
                    return new WP_Error('mcp_direct_bad_args', 'location und menu (ID) noetig.');
                }
                $registered = get_registered_nav_menus();
                if (!isset($registered[$location])) {
                    return new WP_Error('mcp_direct_bad_location', 'Unbekannte Position "' . $location . '". Verfuegbar: ' . implode(', ', array_keys($registered)));
                }
                $locs = get_theme_mod('nav_menu_locations');
                if (!is_array($locs)) {
                    $locs = array();
                }
                $locs[$location] = (int) $menu_id;
                set_theme_mod('nav_menu_locations', $locs);
                return self::json_out(array('ok' => true, 'locations' => (object) $locs));

            case 'delete_item':
                $item_id = isset($args['item_id']) ? (int) $args['item_id'] : 0;
                if (!$item_id || get_post_type($item_id) !== 'nav_menu_item') {
                    return new WP_Error('mcp_direct_bad_item', 'item_id ist kein Menuepunkt.');
                }
                wp_delete_post($item_id, true);
                return self::json_out(array('ok' => true, 'deleted' => $item_id));

            default:
                return new WP_Error('mcp_direct_bad_action', 'action muss list/add_item/set_location/delete_item sein.');
        }
    }

    /** Menue per ID oder Name aufloesen (optional anlegen). Rueckgabe: Term-ID oder WP_Error. */
    private static function resolve_menu($args, $create = true) {
        if (!isset($args['menu'])) {
            return new WP_Error('mcp_direct_no_menu', 'menu (ID oder Name) fehlt.');
        }
        $menu = $args['menu'];
        if (is_numeric($menu)) {
            return (int) $menu;
        }
        $term = get_term_by('name', (string) $menu, 'nav_menu');
        if ($term && !is_wp_error($term)) {
            return (int) $term->term_id;
        }
        if (!$create) {
            return new WP_Error('mcp_direct_menu_missing', 'Menue "' . $menu . '" nicht gefunden.');
        }
        $new_id = wp_create_nav_menu((string) $menu);
        if (is_wp_error($new_id)) {
            return $new_id;
        }
        return (int) $new_id;
    }

    /** JSON mit Groessenbegrenzung. */
    private static function json_out($data) {
        $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = wp_json_encode($data);
        }
        if (is_string($json) && strlen($json) > 150000) {
            $json = function_exists('mb_substr') ? mb_substr($json, 0, 150000, 'UTF-8') : substr($json, 0, 150000);
            $json .= "\n… (Ausgabe gekuerzt)";
        }
        return $json;
    }
}
