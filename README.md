# MCP Direct – KI-Direktverbindung für WordPress & WooCommerce

**Lokaler MCP-Server als WordPress-Plugin.** Verbindet KI-Clients (Perplexity, Claude, ChatGPT, Cursor …) direkt mit der WordPress-Seite — **ohne Cloud-Umweg, ohne Drittanbieter-Relay**. Authentifizierung per API-Key (Bearer).

> English: Self-hosted MCP server plugin for WordPress/WooCommerce. Connects AI clients directly via Streamable HTTP + API key — no third-party cloud in between.

## Was es kann (11 Werkzeuge)

| Bereich | Werkzeug | Zweck |
|---|---|---|
| Seite & REST | `site_info` | URLs, Versionen, Theme, Plugins, Inhaltszahlen |
| | `rest_request` | Jede WP-/WooCommerce-REST-Route mit Admin-Rechten |
| Inhalte | `post_save` | Beiträge/Seiten anlegen & ändern |
| | `upload_media_from_url` | Bilder von URLs in die Mediathek |
| WooCommerce | `woocommerce_list_products` | Produkte auflisten (Suche, Kategorie, SKU) |
| | `woocommerce_create_product` | Produkt anlegen |
| | `woocommerce_update_products` | Preise, Lager, Beschreibungen, Kategorien in Masse |
| Dateien | `fs_list`, `fs_read`, `fs_write` | Dateien unter wp-content (Theme/Plugin) — Schreiben mit Auto-Backup |
| Menüs | `menus` | Menüs anlegen/ändern, Positionen zuweisen |

## Voraussetzungen

- WordPress 6.0+, PHP 7.4+ (getestet mit WP 7.1 und WooCommerce 11)
- HTTPS empfohlen

## Installation

1. wp-admin → Plugins → Installieren → **Plugin hochladen** → `mcp-direct.zip` wählen → aktivieren
2. **Einstellungen → MCP Direct**: API-Key kopieren

oder per WP-CLI:

```bash
wp plugin install mcp-direct.zip --activate
```

## Mit Perplexity verbinden

1. Perplexity → Einstellungen → **Connectors** → **+ Custom connector** → **Remote**
2. **MCP Server URL**: `https://DEINE-SEITE.tld/wp-json/mcp-direct/v1/mcp`
3. **Authentication: API Key** → den Key aus den Plugin-Einstellungen einfügen
4. **Transport: Streamable HTTP**
5. „Add" → Connector-Karte anklicken und aktivieren

Andere Clients (Claude, ChatGPT, Cursor …): als Remote-MCP-Server denselben Endpoint eintragen, Header `Authorization: Bearer <KEY>` — alternativ `X-API-Key: <KEY>`.

## Sicherheit

- Der API-Key hat **volle Admin-Rechte** auf der Seite — nicht weitergeben.
- Endpoint jederzeit deaktivierbar; Key jederzeit neu erzeugbar (Einstellungen → MCP Direct).
- Zugriffe werden protokolliert (letzte 60 Aufrufe im wp-admin sichtbar).
- Datei-Schreibzugriffe sind auf `wp-content/` begrenzt und legen vor jeder Änderung ein Backup an.
- Nur über HTTPS betreiben.

## Betriebs-Hinweise (Fallstricke)

- **Pretty Permalinks** nötig, damit die `/wp-json/...`-URL funktioniert (sonst `?rest_route=/mcp-direct/v1/mcp` verwenden).
- **WordPress blockt interne/lokale URLs** beim Medien-Import (SSRF-Schutz) — öffentlich erreichbare URLs verwenden oder vorhandene Attachment-IDs nutzen.
- **Manche Server strippen den `Authorization`-Header** → Fallback-Header `X-API-Key: <KEY>` wird ebenfalls akzeptiert.
- **Objektcache-Warnung (z. B. Lima-City/APCu):** per WP-CLI gemachte Änderungen (Plugin aktivieren, Keys setzen) werden im Web erst nach einem Cache-Flush aus dem Web-Kontext sichtbar.

## Tests

`tests/mcp_test.py` — 26 Prüfungen (Protokoll-Handshake, Inhalte, WooCommerce, Dateien, Menüs, Fehlerfälle):

```bash
python3 tests/mcp_test.py <API-KEY> https://DEINE-SEITE.tld/wp-json/mcp-direct/v1/mcp
```

## Lizenz

GPL-2.0-or-later — siehe [LICENSE](LICENSE).
