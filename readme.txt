=== MCP Direct – KI-Direktverbindung (API-Key) ===
Contributors: thomas
Tags: mcp, ai, perplexity, claude, chatgpt, woocommerce
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later

Lokaler MCP-Server fuer WordPress: verbindet KI-Clients (Perplexity, Claude,
ChatGPT, Cursor ...) direkt mit der Seite - ohne Cloud-Zwischendienst.

== Beschreibung ==

Stellt einen MCP-Endpoint (JSON-RPC 2.0 ueber Streamable HTTP) direkt auf der
WordPress-Seite bereit: /wp-json/mcp-direct/v1/mcp

Authentifizierung per API-Key (Authorization: Bearer <key>), geeignet fuer den
"Custom Connector" von Perplexity (Authentication: API Key) und jeden anderen
MCP-faehigen Client.

Werkzeuge:
* site_info - Ueberblick ueber die Seite
* rest_request - beliebige WP-/WooCommerce-REST-Routen aufrufen
* post_save - Beitraege/Seiten anlegen und bearbeiten
* upload_media_from_url - Bilder/Dateien von URL importieren
* woocommerce_list_products / woocommerce_update_products / woocommerce_create_product
* fs_list / fs_read / fs_write - Dateien unter wp-content (Theme-Entwicklung), mit Backup
* menus - Navigation verwalten

== Installation ==

1. Ordner `mcp-direct` nach `/wp-content/plugins/` hochladen (oder ZIP installieren).
2. Plugin aktivieren.
3. Einstellungen -> MCP Direct: Endpoint-URL und API-Key kopieren.
4. Im KI-Client als MCP-Server eintragen (Transport: Streamable HTTP, Auth: API Key).

== Changelog ==

= 1.0.0 =
* Erstversion.
