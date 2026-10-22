"""Builds the customer apps (app/hr/app.html, app/finance/app.html) from the demo copies in demo/.

The demo pages are the HR and Finance artifact code plus hooks for sample data. For customers the sample data
and demo helpers are removed and app/runtime.js is loaded instead, which connects the apps to the server.
Run after rebuilding the demos:  python3 tools/build_app.py
"""
import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parent.parent
for src_name, app_id, title in [("hr", "hr", "HR"), ("finance", "fin", "Kewangan / Finance")]:
    s = (ROOT / "demo" / src_name / "index.html").read_text(encoding="utf-8")
    s, n = re.subn(r'<script src="\.\./demo\.js"></script>\n<script src="seed\.js"></script>\n',
                   f'<script src="/app/runtime.js?v=1" data-app="{app_id}"></script>\n', s)
    assert n == 1, src_name
    s, n = re.subn(r"<title>.*?</title>", f"<title>{title} | Excellent Synergy Resources</title>", s, count=1)
    assert n == 1
    s = s.replace('fetch("/_blob/"+', 'fetch("/app/api/assets.php?id="+').replace('href="/_blob/${', 'href="/app/api/assets.php?id=${')
    assert "/_blob/" not in s, src_name
    out = ROOT / "app" / src_name / "app.html"
    out.write_text(s, encoding="utf-8")
    print("wrote", out.relative_to(ROOT))
