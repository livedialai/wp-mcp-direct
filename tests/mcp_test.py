#!/usr/bin/env python3
"""MCP-Direct Funktionsprobe: simuliert einen echten MCP-Client (Perplexity-Stil)."""
import json, sys, time, urllib.request, urllib.error

BASE = sys.argv[2].strip() if len(sys.argv) > 2 else "http://127.0.0.1:8091/wp-json/mcp-direct/v1/mcp"
KEY = sys.argv[1].strip() if len(sys.argv) > 1 else ""
if not KEY:
    print("Usage: mcp_test.py <API-KEY> [ENDPOINT-URL] [BILD-URL]"); sys.exit(2)

results = []
def check(name, ok, info=""):
    results.append((name, ok, info))
    print(("PASS " if ok else "FAIL ") + name + ((" | " + str(info)[:260]) if info else ""))

def rpc(method, params=None, mid=1, notify=False):
    body = {"jsonrpc": "2.0", "method": method}
    if not notify:
        body["id"] = mid
    if params is not None:
        body["params"] = params
    req = urllib.request.Request(BASE, data=json.dumps(body).encode("utf-8"), headers={
        "Content-Type": "application/json",
        "Authorization": "Bearer " + KEY,
        "User-Agent": "Perplexity-Connector-Simulation/1.0",
        "Accept": "application/json, text/event-stream",
    }, method="POST")
    try:
        with urllib.request.urlopen(req, timeout=180) as r:
            data = r.read().decode("utf-8", "replace"); code = r.status
    except urllib.error.HTTPError as e:
        data = e.read().decode("utf-8", "replace"); code = e.code
    try:
        parsed = json.loads(data) if data.strip() else {}
    except Exception:
        parsed = {"_raw": data[:200]}
    return code, parsed

def tool(name, args, mid):
    code, resp = rpc("tools/call", {"name": name, "arguments": args}, mid)
    res = resp.get("result") or {}
    txt = "".join(c.get("text", "") for c in (res.get("content") or []))
    return bool(res.get("isError")), txt, resp

# 1 initialize
code, r = rpc("initialize", {"protocolVersion": "2025-06-18", "capabilities": {}, "clientInfo": {"name": "SimClient", "version": "1.0"}}, 1)
check("initialize", code == 200 and (r.get("result") or {}).get("serverInfo", {}).get("name") == "mcp-direct", (r.get("result") or {}).get("serverInfo"))
# 2 notification
code, _ = rpc("notifications/initialized", None, None, notify=True)
check("notifications/initialized -> 202", code == 202, code)
# 3 tools/list
code, r = rpc("tools/list", None, 2)
tools = [t["name"] for t in (r.get("result") or {}).get("tools", [])]
check("tools/list (%d Tools)" % len(tools), len(tools) >= 11, tools)
# 4 ping
code, r = rpc("ping", None, 3)
check("ping", "result" in r, r.get("result"))
# 5 site_info
err, txt, _ = tool("site_info", {}, 10)
info = json.loads(txt) if txt.startswith("{") else {}
check("site_info", (not err) and bool(info.get("wp_version")), info.get("wp_version"))
# 6 post_save create
err, txt, _ = tool("post_save", {"type": "page", "title": "MCP Testseite", "content": "<h1>Hallo</h1><p>Direkt angelegt.</p>", "status": "draft"}, 11)
p1 = json.loads(txt) if txt.startswith("{") else {}
check("post_save create draft", (not err) and bool(p1.get("id")), p1)
pid = p1.get("id")
# 7 post_save update
err, txt, _ = tool("post_save", {"id": pid, "status": "publish", "title": "MCP Testseite 2"}, 12)
p1u = json.loads(txt) if txt.startswith("{") else {}
check("post_save update to publish", (not err) and p1u.get("status") == "publish", p1u)
# 8 rest_request GET page
err, txt, _ = tool("rest_request", {"method": "GET", "route": "/wp/v2/pages/%s" % pid}, 13)
rr = json.loads(txt) if txt.startswith("{") else {}
title_rendered = ((rr.get("body") or {}).get("title") or {}).get("rendered")
check("rest_request GET page", (not err) and title_rendered == "MCP Testseite 2", rr.get("status"))
# 9 rest_request POST + DELETE
err, txt, _ = tool("rest_request", {"method": "POST", "route": "/wp/v2/pages", "params": {"title": "REST Testseite", "status": "draft"}}, 14)
rr2 = json.loads(txt) if txt.startswith("{") else {}
pid2 = (rr2.get("body") or {}).get("id")
check("rest_request POST page", (not err) and bool(pid2), pid2)
err, txt, _ = tool("rest_request", {"method": "DELETE", "route": "/wp/v2/pages/%s" % pid2, "params": {"force": False}}, 15)
rr3 = json.loads(txt) if txt.startswith("{") else {}
check("rest_request DELETE page (->Papierkorb)", (not err) and rr3.get("status") == 200, rr3.get("status"))
# 10 search
err, txt, _ = tool("rest_request", {"method": "GET", "route": "/wp/v2/pages", "params": {"search": "MCP Testseite", "per_page": 5}}, 16)
rr4 = json.loads(txt) if txt.startswith("{") else {}
check("rest_request Suche", (not err) and isinstance(rr4.get("body"), list) and len(rr4["body"]) >= 1, rr4.get("total"))
# 11 WC Kategorie
err, txt, _ = tool("rest_request", {"method": "POST", "route": "/wc/v3/products/categories", "params": {"name": "Testkategorie %d" % int(time.time())}}, 17)
cat = json.loads(txt) if txt.startswith("{") else {}
cat_id = (cat.get("body") or {}).get("id")
check("WC Kategorie via REST", (not err) and bool(cat_id), cat_id)
# 12 wc_create_product
err, txt, _ = tool("woocommerce_create_product", {"name": "Testpizza MCP", "regular_price": "9.90", "description": "Lecker.", "categories": [cat_id]}, 18)
prod = json.loads(txt) if txt.startswith("{") else {}
check("wc_create_product", (not err) and bool(prod.get("id")), prod)
prod_id = prod.get("id")
# 13 wc list
err, txt, _ = tool("woocommerce_list_products", {"search": "Testpizza"}, 19)
lst = json.loads(txt) if txt.startswith("{") else {}
found = [p for p in lst.get("products", []) if p.get("id") == prod_id]
check("wc_list_products", (not err) and len(found) == 1 and found[0].get("price") == "9.90", found[:1])
# 14 wc update
err, txt, _ = tool("woocommerce_update_products", {"updates": [{"id": prod_id, "regular_price": "10.50", "stock_status": "outofstock", "stock_quantity": 3, "manage_stock": True, "description": "Neue Beschreibung"}]}, 20)
upd = json.loads(txt) if txt.startswith("{") else {}
check("wc_update_products", (not err) and upd.get("updated") == 1, upd)
# 15 verify product
err, txt, _ = tool("rest_request", {"method": "GET", "route": "/wc/v3/products/%s" % prod_id}, 22)
vp = json.loads(txt) if txt.startswith("{") else {}
b = vp.get("body") or {}
check("WC Produkt verifiziert (10.50 / qty 3)", (not err) and b.get("regular_price") == "10.50" and b.get("stock_quantity") == 3, {"price": b.get("regular_price"), "stock": b.get("stock_status"), "qty": b.get("stock_quantity")})
# 16 upload media
_img = sys.argv[3].strip() if len(sys.argv) > 3 else "https://httpbin.org/image/png"
err, txt, _ = tool("upload_media_from_url", {"url": _img, "title": "MCP Testbild", "alt_text": "Testbild"}, 23)
med = json.loads(txt) if txt.startswith("{") else {}
check("upload_media_from_url", (not err) and bool(med.get("id")), med)
# 17 fs_list
err, txt, _ = tool("fs_list", {"path": ""}, 24)
fsl = json.loads(txt) if txt.startswith("{") else {}
check("fs_list wp-content", (not err) and any(i.get("name") == "themes" for i in fsl.get("items", [])), len(fsl.get("items", [])))
# 18 fs_read
err, txt, _ = tool("fs_read", {"path": "themes/twentytwentyfive/style.css"}, 25)
check("fs_read Theme-CSS", (not err) and "Theme Name" in txt, txt[:60])
# 19 fs_write
err, txt, _ = tool("fs_write", {"path": "uploads/mcp-test.txt", "content": "Hallo Welt 1"}, 26)
w1 = json.loads(txt) if txt.startswith("{") else {}
check("fs_write neu", (not err) and w1.get("ok"), w1)
err, txt, _ = tool("fs_write", {"path": "uploads/mcp-test.txt", "content": "Hallo Welt 2"}, 27)
w2 = json.loads(txt) if txt.startswith("{") else {}
check("fs_write ueberschreiben + Backup", (not err) and w2.get("ok") and w2.get("backup"), w2.get("backup"))
err, txt, _ = tool("fs_read", {"path": "uploads/mcp-test.txt"}, 28)
check("fs_read verifiziert", txt == "Hallo Welt 2", txt[:40])
# 20 menus
err, txt, _ = tool("menus", {"action": "list"}, 29)
ml = json.loads(txt) if txt.startswith("{") else {}
check("menus list", not err, ml.get("menus"))
# 21 menu add_item
err, txt, _ = tool("menus", {"action": "add_item", "menu": "MCP Menue", "title": "Startseite", "url": "/"}, 30)
mi = json.loads(txt) if txt.startswith("{") else {}
check("menus add_item (legt Menue an)", (not err) and bool(mi.get("item_id")), mi)
# 22 unknown tool
err, txt, _ = tool("gibt_es_nicht", {}, 31)
check("Unbekanntes Tool -> isError", err is True, txt[:80])
# 23 Batch
body = [{"jsonrpc": "2.0", "id": 40, "method": "ping"}, {"jsonrpc": "2.0", "id": 41, "method": "tools/list"}]
req = urllib.request.Request(BASE, data=json.dumps(body).encode(), headers={"Content-Type": "application/json", "Authorization": "Bearer " + KEY}, method="POST")
try:
    with urllib.request.urlopen(req, timeout=60) as r:
        batch = json.loads(r.read().decode())
    check("Batch-Request", isinstance(batch, list) and len(batch) == 2, len(batch) if isinstance(batch, list) else type(batch))
except Exception as e:
    check("Batch-Request", False, str(e))

ok = sum(1 for _, o, _ in results if o)
print("\n== ERGEBNIS: %d/%d bestanden ==" % (ok, len(results)))
for n, o, i in results:
    if not o:
        print("  FAIL:", n, "|", str(i)[:300])
sys.exit(0 if ok == len(results) else 1)
