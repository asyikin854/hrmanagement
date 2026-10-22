"""Builds the customer apps (app/hr/app.html, app/finance/app.html) from the demo copies in demo/.

The demo pages are the HR and Finance artifact code plus hooks for sample data. For customers the sample data
and demo helpers are removed and app/runtime.js is loaded instead, which connects the apps to the server.
Run after rebuilding the demos:  python3 tools/build_app.py
"""
import json
import pathlib
import re

AI_OFF = "AI features aren't switched on for your account yet. Contact us on WhatsApp 011-1632 0694."
TEXT = [
    ("AI isn't available in this view. Open the page in Claude to use Ask HR.", AI_OFF),
    ("CV saved. AI isn't available in this view, so no summary was made.", "CV saved. AI features aren't switched on, so no summary was made."),
    ("AI isn't available in this view, so only CSV statements can be read here.", "AI features aren't switched on, so only CSV statements can be read."),
    ("AI isn't available in this view.", AI_OFF),
    ("AI is unavailable in this view", "AI features aren't switched on"),
    ("Shared storage isn't available in this view, so changes here last only until you close the page.", "Can't reach the server, so changes may not be saved. Check your connection and refresh the page."),
    ("Shared storage isn't available in this view, so changes last only until you close the page.", "Can't reach the server, so changes may not be saved. Check your connection and refresh the page."),
    ("Downloads aren't available in this view.", "Downloads aren't available right now. Refresh the page and try again."),
    ("Clock-in isn't available in this view.", "Clock-in isn't available right now. Refresh the page and try again."),
]
BM = [
    (AI_OFF, "Ciri AI belum diaktifkan untuk akaun anda. Hubungi kami di WhatsApp 011-1632 0694."),
    ("CV saved. AI features aren't switched on, so no summary was made.", "CV disimpan. Ciri AI belum diaktifkan, jadi tiada ringkasan dibuat."),
    ("AI features aren't switched on, so only CSV statements can be read.", "Ciri AI belum diaktifkan, jadi hanya penyata CSV boleh dibaca."),
    ("AI features aren't switched on", "Ciri AI belum diaktifkan"),
    ("Can't reach the server, so changes may not be saved. Check your connection and refresh the page.", "Tidak dapat menghubungi pelayan, jadi perubahan mungkin tidak disimpan. Semak sambungan anda dan muat semula halaman."),
    ("Downloads aren't available right now. Refresh the page and try again.", "Muat turun tidak tersedia sekarang. Muat semula halaman dan cuba lagi."),
    ("Clock-in isn't available right now. Refresh the page and try again.", "Daftar masuk tidak tersedia sekarang. Muat semula halaman dan cuba lagi."),
]

ROOT = pathlib.Path(__file__).resolve().parent.parent
for src_name, app_id, title in [("hr", "hr", "HR"), ("finance", "fin", "Kewangan / Finance")]:
    s = (ROOT / "demo" / src_name / "index.html").read_text(encoding="utf-8")
    s, n = re.subn(r'<script src="\.\./demo\.js"></script>\n<script src="seed\.js"></script>\n',
                   f'<script src="/app/runtime.js?v=1" data-app="{app_id}"></script>\n', s)
    assert n == 1, src_name
    s, n = re.subn(r"<title>.*?</title>", f"<title>{title} | Excellent Synergy Resources</title>", s, count=1)
    assert n == 1
    # Messages written for Claude's artifact viewer, reworded for customers (with BM translations below)
    for old, new in TEXT:
        s = s.replace(old, new)
    s = s.replace("const DICT={", "const DICT={" + "".join(json.dumps(en) + ":" + json.dumps(ms) + "," for en, ms in BM), 1)
    s = s.replace('fetch("/_blob/"+', 'fetch("/app/api/assets.php?id="+').replace('href="/_blob/${', 'href="/app/api/assets.php?id=${')
    assert "/_blob/" not in s, src_name
    if src_name == "hr":  # staff write their own clock records; the attendance view must escape them
        assert "esc(i.loc)" in s and "Number(i.acc)" in s, "whereCell lost its escaping: re-apply it in demo/hr/index.html"
    assert "in this view" not in s and "in Claude to" not in s, src_name
    out = ROOT / "app" / src_name / "app.html"
    out.write_text(s, encoding="utf-8")
    print("wrote", out.relative_to(ROOT))
