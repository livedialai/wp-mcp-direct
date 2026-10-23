<?php
/**
 * SEO-Werkzeuge fuer MCP Direct: Sitemaps, IndexNow, Audit, Meta, robots.txt.
 *
 * Diese Tools sind dazu gedacht, auch von anderen SEO-Leuten genutzt zu werden:
 * sie funktionieren ohne Yoast/RankMath, lesen aber deren Meta-Felder mit aus.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MCP_Direct_SEO_Tools {

    /** Speicherort der erzeugten Sitemaps (unterhalb von wp-content/uploads). */
    const DIR = 'mcp-seo';

    public static function definitions() {
        return array(
            self::def_sitemap_build(),
            self::def_indexnow(),
            self::def_audit(),
            self::def_meta_set(),
            self::def_robots(),
        );
    }

    public static function names() {
        return array('sitemap_build', 'indexnow_submit', 'seo_audit', 'seo_meta_set', 'robots_txt');
    }

    public static function call($name, $args) {
        switch ($name) {
            case 'sitemap_build':   return self::t_sitemap_build($args);
            case 'indexnow_submit': return self::t_indexnow($args);
            case 'seo_audit':       return self::t_audit($args);
            case 'seo_meta_set':    return self::t_meta_set($args);
            case 'robots_txt':      return self::t_robots($args);
        }
        return new WP_Error('mcp_seo_unknown', 'Unbekanntes SEO-Tool: ' . $name);
    }

    /* ============================ Definitionen ============================ */

    private static function def_sitemap_build() {
        return array(
            'name' => 'sitemap_build',
            'description' => 'Sitemap-Index + Unterseiten als XML erzeugen (ohne SEO-Plugin). Getrennt je Inhaltstyp, automatisch aufgeteilt (Standard 5000 URLs je Datei). Gibt die oeffentlichen URLs zurueck und schlaegt die robots.txt-Zeile vor.',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'types' => array('type' => 'array', 'items' => array('type' => 'string'), 'description' => 'Inhaltstypen, z. B. ["post","page","product"]. Leer = alle oeffentlichen Typen.'),
                    'per_file' => array('type' => 'integer', 'description' => 'URLs je Datei (Standard 5000, max 50000)'),
                    'include_taxonomies' => array('type' => 'boolean', 'description' => 'Auch Kategorien/Tags aufnehmen'),
                ),
            ),
        );
    }

    private static function def_indexnow() {
        return array(
            'name' => 'indexnow_submit',
            'description' => 'URLs sofort an Bing/Yandex/Seznam melden (IndexNow). Legt den Key automatisch im Webroot an, wenn noetig. Entweder eigene urls[] oder all=true mit limit (neueste Beitraege/Seiten).',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'urls' => array('type' => 'array', 'items' => array('type' => 'string'), 'description' => 'Konkrete URLs'),
                    'all' => array('type' => 'boolean', 'description' => 'Neueste Inhalte automatisch nehmen'),
                    'limit' => array('type' => 'integer', 'description' => 'Bei all=true: Anzahl (Standard 100, max 10000)'),
                    'types' => array('type' => 'array', 'items' => array('type' => 'string'), 'description' => 'Bei all=true: Inhaltstypen'),
                ),
            ),
        );
    }

    private static function def_audit() {
        return array(
            'name' => 'seo_audit',
            'description' => 'SEO-Audit ohne externes Tool: Anzahl Inhalte, fehlende/zu lange Titel, fehlende Meta-Description, noindex, Sitemap-Abdeckung, robots.txt. Liest Yoast- und RankMath-Felder mit.',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'types' => array('type' => 'array', 'items' => array('type' => 'string'), 'description' => 'Inhaltstypen (Standard: post,page)'),
                    'limit' => array('type' => 'integer', 'description' => 'Wie viele Inhalte pruefen (Standard 200, max 2000)'),
                ),
            ),
        );
    }

    private static function def_meta_set() {
        return array(
            'name' => 'seo_meta_set',
            'description' => 'SEO-Titel, Meta-Description und noindex fuer einen Inhalt setzen (eigene Felder, Ausgabe im <head> ueber das Plugin). Funktioniert ohne SEO-Plugin.',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'post_id' => array('type' => 'integer', 'description' => 'ID des Beitrags/der Seite'),
                    'title' => array('type' => 'string', 'description' => 'SEO-Titel (max ~60 Zeichen)'),
                    'description' => array('type' => 'string', 'description' => 'Meta-Description (max ~155 Zeichen)'),
                    'noindex' => array('type' => 'boolean'),
                ),
                'required' => array('post_id'),
            ),
        );
    }

    private static function def_robots() {
        return array(
            'name' => 'robots_txt',
            'description' => 'robots.txt lesen oder Zusatzzeilen setzen (Sitemap-Verweis, Disallow-Regeln). Die Zeilen werden ueber den WordPress-Filter ausgegeben, es wird keine Datei ueberschrieben.',
            'inputSchema' => array(
                'type' => 'object',
                'properties' => array(
                    'action' => array('type' => 'string', 'enum' => array('read', 'write')),
                    'content' => array('type' => 'string', 'description' => 'Bei write: die Zusatzzeilen (z. B. "Sitemap: https://…/sitemap_index.xml")'),
                ),
                'required' => array('action'),
            ),
        );
    }

    /* ============================ Helfer ============================ */

    private static function json_out($data) {
        return wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private static function public_types($requested) {
        $types = is_array($requested) ? array_filter(array_map('sanitize_key', $requested)) : array();
        if (!$types) {
            $types = array_values(get_post_types(array('public' => true), 'names'));
            $types = array_values(array_diff($types, array('attachment')));
        }
        return $types;
    }

    private static function sitemap_dir() {
        $up = wp_upload_dir();
        $dir = trailingslashit($up['basedir']) . self::DIR;
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        return array($dir, trailingslashit($up['baseurl']) . self::DIR);
    }

    private static function iso($post) {
        $ts = $post->post_modified_gmt && $post->post_modified_gmt !== '0000-00-00 00:00:00'
            ? $post->post_modified_gmt : $post->post_date_gmt;
        return mysql2date('c', $ts, false);
    }

    private static function post_meta_seo($id, $keys) {
        $out = array();
        foreach ($keys as $k) {
            $v = get_post_meta($id, $k, true);
            if ($v !== '' && $v !== false) {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    /* ============================ Implementierungen ============================ */

    private static function t_sitemap_build($args) {
        $types = self::public_types($args['types'] ?? null);
        $per = isset($args['per_file']) ? max(100, min(50000, (int) $args['per_file'])) : 5000;
        list($dir, $base) = self::sitemap_dir();

        $files = array();
        $total = 0;
        $index_entries = array();

        foreach ($types as $type) {
            $offset = 0;
            $part = 1;
            while (true) {
                $q = new WP_Query(array(
                    'post_type' => $type,
                    'post_status' => 'publish',
                    'posts_per_page' => $per,
                    'offset' => $offset,
                    'orderby' => 'ID',
                    'order' => 'ASC',
                    'no_found_rows' => true,
                    'fields' => 'all',
                ));
                if (!$q->have_posts()) {
                    break;
                }
                $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
                foreach ($q->posts as $p) {
                    $loc = get_permalink($p);
                    if (!$loc) {
                        continue;
                    }
                    $xml .= "  <url><loc>" . esc_url($loc) . '</loc><lastmod>' . self::iso($p) . "</lastmod></url>\n";
                }
                $xml .= "</urlset>\n";

                $name = 'sitemap-' . $type . ($part > 1 ? '-' . $part : '') . '.xml';
                file_put_contents(trailingslashit($dir) . $name, $xml);
                $index_entries[] = array('loc' => $base . $name, 'type' => $type, 'count' => count($q->posts));
                $files[] = $base . $name;
                $total += count($q->posts);
                $offset += $per;
                $part++;
                if (count($q->posts) < $per) {
                    break;
                }
            }
        }

        if (!empty($args['include_taxonomies'])) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            $terms = get_terms(array('taxonomy' => get_taxonomies(array('public' => true)), 'hide_empty' => true, 'number' => 5000));
            if (!is_wp_error($terms)) {
                foreach ($terms as $t) {
                    $link = get_term_link($t);
                    if (!is_wp_error($link)) {
                        $xml .= "  <url><loc>" . esc_url($link) . "</loc></url>\n";
                    }
                }
            }
            $xml .= "</urlset>\n";
            file_put_contents(trailingslashit($dir) . 'sitemap-taxonomies.xml', $xml);
            $index_entries[] = array('loc' => $base . 'sitemap-taxonomies.xml', 'type' => 'taxonomies', 'count' => is_wp_error($terms) ? 0 : count($terms));
        }

        $idx  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $idx .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($index_entries as $e) {
            $idx .= "  <sitemap><loc>" . esc_url($e['loc']) . '</loc><lastmod>' . mysql2date('c', current_time('mysql', true), false) . "</lastmod></sitemap>\n";
        }
        $idx .= "</sitemapindex>\n";
        file_put_contents(trailingslashit($dir) . 'sitemap-index.xml', $idx);

        return self::json_out(array(
            'ok' => true,
            'types' => $types,
            'urls_total' => $total,
            'files' => $index_entries,
            'sitemap_index_url' => $base . 'sitemap-index.xml',
            'robots_line' => 'Sitemap: ' . $base . 'sitemap-index.xml',
            'hint' => 'Sitemap-Index im Browser oeffnen und pruefen; robots.txt-Zeile per robots_txt setzen.',
        ));
    }

    private static function t_indexnow($args) {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        $key  = get_option('mcp_seo_indexnow_key');
        if (!$key) {
            $key = md5(wp_generate_password(32, false, false) . time());
            update_option('mcp_seo_indexnow_key', $key, false);
        }
        $key_file = ABSPATH . $key . '.txt';
        if (!file_exists($key_file)) {
            @file_put_contents($key_file, $key);
        }

        $urls = array();
        if (!empty($args['urls']) && is_array($args['urls'])) {
            foreach ($args['urls'] as $u) {
                $u = esc_url_raw($u);
                if ($u) {
                    $urls[] = $u;
                }
            }
        } elseif (!empty($args['all'])) {
            $limit = isset($args['limit']) ? max(1, min(10000, (int) $args['limit'])) : 100;
            $types = self::public_types($args['types'] ?? null);
            $q = new WP_Query(array('post_type' => $types, 'post_status' => 'publish',
                'posts_per_page' => $limit, 'orderby' => 'modified', 'order' => 'DESC', 'no_found_rows' => true));
            foreach ($q->posts as $p) {
                $l = get_permalink($p);
                if ($l) {
                    $urls[] = $l;
                }
            }
        }

        $urls = array_values(array_unique($urls));
        if (!$urls) {
            return new WP_Error('mcp_seo_no_urls', 'Keine URLs uebergeben (urls[] oder all=true + limit).');
        }

        $body = wp_json_encode(array(
            'host' => $host,
            'key' => $key,
            'keyLocation' => 'https://' . $host . '/' . $key . '.txt',
            'urlList' => array_slice($urls, 0, 10000),
        ));

        $res = wp_remote_post('https://api.indexnow.org/indexnow', array(
            'timeout' => 30,
            'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
            'body' => $body,
        ));

        return self::json_out(array(
            'ok' => !is_wp_error($res),
            'http' => is_wp_error($res) ? null : (int) wp_remote_retrieve_response_code($res),
            'key_location' => 'https://' . $host . '/' . $key . '.txt',
            'key_file_written' => file_exists($key_file),
            'submitted' => count($urls),
            'first_urls' => array_slice($urls, 0, 5),
            'error' => is_wp_error($res) ? $res->get_error_message() : null,
        ));
    }

    private static function t_audit($args) {
        $types = self::public_types($args['types'] ?? array('post', 'page'));
        $limit = isset($args['limit']) ? max(1, min(2000, (int) $args['limit'])) : 200;

        $yoast = defined('WPSEO_VERSION');
        $rank  = defined('RANK_MATH_VERSION');

        $report = array('plugin_detected' => $yoast ? 'Yoast' : ($rank ? 'RankMath' : 'keins (eigene Felder)'), 'types' => array());

        foreach ($types as $type) {
            $q = new WP_Query(array('post_type' => $type, 'post_status' => 'publish',
                'posts_per_page' => $limit, 'no_found_rows' => true));
            $row = array('total' => (int) wp_count_posts($type)->publish,
                'checked' => 0, 'missing_title' => 0, 'long_title' => 0,
                'missing_description' => 0, 'long_description' => 0, 'noindex' => 0, 'short_content' => 0,
                'examples' => array());

            foreach ($q->posts as $p) {
                $row['checked']++;
                $meta = self::post_meta_seo($p->ID, array(
                    '_mcp_seo_title', '_mcp_seo_description', '_mcp_seo_noindex',
                    '_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_meta-robots-noindex',
                    'rank_math_title', 'rank_math_description', 'rank_math_robots',
                ));
                $title = $meta['_mcp_seo_title'] ?? ($meta['_yoast_wpseo_title'] ?? ($meta['rank_math_title'] ?? ''));
                $desc  = $meta['_mcp_seo_description'] ?? ($meta['_yoast_wpseo_metadesc'] ?? ($meta['rank_math_description'] ?? ''));

                if ($title === '') {
                    $t = get_the_title($p);
                    $title = $t;
                    if (mb_strlen($t) < 10) {
                        $row['missing_title']++;
                    }
                }
                if (mb_strlen($title) > 60) {
                    $row['long_title']++;
                }
                if ($desc === '') {
                    $row['missing_description']++;
                } elseif (mb_strlen($desc) > 160) {
                    $row['long_description']++;
                }
                $noindex = ($meta['_mcp_seo_noindex'] ?? '') === '1'
                    || ($meta['_yoast_wpseo_meta-robots-noindex'] ?? '') === '1';
                if ($noindex) {
                    $row['noindex']++;
                }
                if (mb_strlen(wp_strip_all_tags((string) $p->post_content)) < 300) {
                    $row['short_content']++;
                }
                if (count($row['examples']) < 5 && ($desc === '' || $noindex)) {
                    $row['examples'][] = array('id' => $p->ID, 'title' => mb_substr(get_the_title($p), 0, 60), 'missing_description' => $desc === '', 'noindex' => $noindex);
                }
            }
            $report['types'][$type] = $row;
        }

        $report['robots'] = get_option('robots_txt');
        $report['sitemap_option'] = get_option('mcp_seo_sitemaps');
        return self::json_out($report);
    }

    private static function t_meta_set($args) {
        $id = (int) ($args['post_id'] ?? 0);
        if (!$id || !get_post($id)) {
            return new WP_Error('mcp_seo_bad_post', 'post_id fehlt oder ungueltig.');
        }
        if (isset($args['title'])) {
            update_post_meta($id, '_mcp_seo_title', sanitize_text_field((string) $args['title']));
        }
        if (isset($args['description'])) {
            update_post_meta($id, '_mcp_seo_description', sanitize_text_field((string) $args['description']));
        }
        if (isset($args['noindex'])) {
            update_post_meta($id, '_mcp_seo_noindex', !empty($args['noindex']) ? '1' : '0');
        }
        return self::json_out(array(
            'ok' => true,
            'post_id' => $id,
            'title' => get_post_meta($id, '_mcp_seo_title', true),
            'description' => get_post_meta($id, '_mcp_seo_description', true),
            'noindex' => get_post_meta($id, '_mcp_seo_noindex', true) === '1',
            'permalink' => get_permalink($id),
        ));
    }

    private static function t_robots($args) {
        $action = $args['action'] ?? 'read';
        if ($action === 'write') {
            $content = (string) ($args['content'] ?? '');
            update_option('mcp_seo_robots_extra', $content, false);
            return self::json_out(array('ok' => true, 'extra_lines' => $content, 'note' => 'Wird ueber den robots_txt-Filter ausgegeben.'));
        }
        return self::json_out(array(
            'option_robots_txt' => get_option('robots_txt'),
            'extra_lines' => get_option('mcp_seo_robots_extra'),
            'live_url' => home_url('/robots.txt'),
            'sitemap_guess' => home_url('/wp-content/uploads/' . self::DIR . '/sitemap-index.xml'),
        ));
    }
}
