/* Runtime for the HR and Finance apps on hrmanagement.esrmy.com/app/.
   The apps were built for Claude's artifact runtime (window.claude.use). This file provides the same interface,
   backed by the server: db (live documents per company), user, sample (AI), assets (CV files) and downloads.
   Changes are synced by polling, so other people's edits show up within a few seconds. */
(function () {
  "use strict";
  var APP = (document.currentScript && document.currentScript.getAttribute("data-app")) || "hr";
  var API = "/app/api/";
  var POLL_MS = 4000;

  // ?lang=ms|en from the account page sets the app language before it starts
  try {
    var ql = new URLSearchParams(location.search).get("lang");
    if (ql === "ms" || ql === "en") localStorage.setItem("esr-lang", ql);
  } catch (e) {}

  // A small "My account" button back to the account page (plan, team, log out)
  document.addEventListener("DOMContentLoaded", function () {
    var ms = (function () { try { return localStorage.getItem("esr-lang") !== "en"; } catch (e) { return true; } })();
    var a = document.createElement("a");
    a.href = "/app/";
    a.textContent = ms ? "Akaun saya" : "My account";
    a.setAttribute("style", "position:fixed;right:14px;bottom:14px;z-index:50;background:var(--surface,#fff);color:var(--ink,#1C2A27);" +
      "border:1px solid var(--line,#DCE0D7);border-radius:999px;padding:7px 14px;font:600 13px/1.2 system-ui,sans-serif;" +
      "text-decoration:none;box-shadow:0 4px 14px rgba(0,0,0,.12)");
    document.body.appendChild(a);
  });

  function err(code, message) { var e = new Error(message || code); e.code = code; return e; }

  function call(path, opts) {
    opts = opts || {};
    var headers = { "X-ESR": "1" };
    var body = opts.body;
    if (body !== undefined && !(body instanceof FormData)) { headers["Content-Type"] = "application/json"; body = JSON.stringify(body); }
    return fetch(API + path, { method: opts.method || (body !== undefined ? "POST" : "GET"), headers: headers, body: body, credentials: "same-origin" })
      .then(function (r) {
        if (r.status === 401) { location.href = "/app/login.php?next=" + encodeURIComponent(location.pathname); throw err("unauthenticated", "Please log in again."); }
        if (r.status === 402) { location.href = "/app/"; throw err("revoked", "Your subscription has ended."); }
        return r.json().catch(function () { return {}; }).then(function (j) {
          if (!r.ok) throw err(j.code || (r.status === 403 ? "permission-denied" : r.status >= 500 ? "unavailable" : "invalid-argument"), j.error || ("Request failed (" + r.status + ")"));
          return j;
        });
      }, function () { throw err("unavailable", "No connection. Check your internet and try again."); });
  }

  /* ---------------- db: a small Firestore-like store kept in sync with the server ---------------- */
  var cache = {};        // "col/id" -> data
  var ver = 0, loaded = false, polling = false, timer = null;
  var listeners = [];    // {kind:"col"|"doc", col, id, order, dir, lim, next}

  function clone(x) { return x === undefined ? undefined : JSON.parse(JSON.stringify(x)); }
  function docSnap(col, id) {
    var d = cache[col + "/" + id];
    return { id: id, exists: d !== undefined, data: function () { return clone(d); } };
  }
  function colSnap(l) {
    var docs = [], pre = l.col + "/";
    Object.keys(cache).forEach(function (k) {
      if (k.indexOf(pre) === 0 && k.indexOf("/", pre.length) < 0) docs.push(docSnap(l.col, k.slice(pre.length)));
    });
    if (l.order) {
      var f = l.order, s = l.dir === "desc" ? -1 : 1;
      docs.sort(function (a, b) { var x = cache[l.col + "/" + a.id][f], y = cache[l.col + "/" + b.id][f]; return x === y ? 0 : (x === undefined || x < y ? -s : s); });
    }
    if (l.lim) docs = docs.slice(0, l.lim);
    return { docs: docs, size: docs.length, empty: !docs.length, forEach: function (fn) { docs.forEach(fn); } };
  }
  function fire(l) {
    try { l.next(l.kind === "col" ? colSnap(l) : docSnap(l.col, l.id)); } catch (e) { console.error(e); }
  }
  function notify(changedCols) {
    listeners.forEach(function (l) { if (!changedCols || changedCols[l.col]) fire(l); });
  }
  function apply(docs) {
    var changed = {};
    docs.forEach(function (d) {
      var k = d.col + "/" + d.id;
      if (d.deleted) delete cache[k]; else cache[k] = d.data;
      changed[d.col] = 1;
    });
    return changed;
  }
  function poll() {
    if (polling) return;
    polling = true;
    clearTimeout(timer);
    call("data.php?app=" + APP + "&since=" + ver).then(function (j) {
      var changed = apply(j.docs || []);
      ver = Math.max(ver, j.ver || 0);
      if (!loaded) { loaded = true; notify(null); } else if (Object.keys(changed).length) notify(changed);
    }).catch(function () { /* retried on the next tick */ }).then(function () {
      polling = false;
      timer = setTimeout(poll, document.hidden ? POLL_MS * 4 : POLL_MS);
    });
  }
  document.addEventListener("visibilitychange", function () { if (!document.hidden) poll(); });

  function listen(l) {
    listeners.push(l);
    if (loaded) setTimeout(function () { fire(l); }, 0); else poll();
    return function () { var i = listeners.indexOf(l); if (i >= 0) listeners.splice(i, 1); };
  }
  function write(op, col, id, data) {
    return call("data.php?app=" + APP, { body: { op: op, col: col, id: id, data: data } }).then(function (j) {
      var ch = apply([j.doc || { col: col, id: id, deleted: true }]);
      notify(ch);
      setTimeout(poll, 0);
    });
  }
  function docRef(col, id) {
    return {
      id: id,
      set: function (data) { return write("set", col, id, clone(data)); },
      update: function (data) { return write("update", col, id, clone(data)); },
      delete: function () { return write("delete", col, id); },
      get: function () { return (loaded ? Promise.resolve() : new Promise(function (r) { var u = listen({ kind: "doc", col: col, id: id, next: function () { u(); r(); } }); })).then(function () { return docSnap(col, id); }); },
      onSnapshot: function (next) { return listen({ kind: "doc", col: col, id: id, next: next }); }
    };
  }
  function query(col, order, dir, lim) {
    return {
      doc: function (id) { return docRef(col, id); },
      orderBy: function (f, d) { return query(col, f, d || "asc", lim); },
      limit: function (n) { return query(col, order, dir, n); },
      onSnapshot: function (next) { return listen({ kind: "col", col: col, order: order, dir: dir, lim: lim, next: next }); },
      get: function () { return (loaded ? Promise.resolve() : new Promise(function (r) { var u = listen({ kind: "col", col: col, next: function () { u(); r(); } }); })).then(function () { return colSnap({ col: col, order: order, dir: dir, lim: lim }); }); }
    };
  }
  var db = {
    collection: function (c) { return query(c); },
    doc: function (path) { var p = String(path).split("/"); return docRef(p[0], p.slice(1).join("/")); }
  };

  /* ---------------- user ---------------- */
  var mePromise = call("session.php");
  var user = {
    me: function () { return mePromise.then(function (j) { return { id: j.user.id, name: j.user.name, email: j.user.email }; }); },
    canEdit: function () { return mePromise.then(function (j) { return !!j.canEdit; }); },
    profiles: function (ids) { return call("session.php?op=profiles", { body: { ids: ids } }).then(function (j) { return j.profiles || {}; }); }
  };

  /* ---------------- sample: AI through the server ---------------- */
  function b64(blob) {
    return new Promise(function (res, rej) {
      var r = new FileReader();
      r.onload = function () { res(String(r.result).split(",")[1]); };
      r.onerror = function () { rej(err("invalid-argument", "Couldn't read the image.")); };
      r.readAsDataURL(blob);
    });
  }
  function userContent(text, images) {
    if (!images || !images.length) return Promise.resolve(text);
    return Promise.all(images.map(function (im) {
      return b64(im).then(function (d) { return { type: "image", source: { type: "base64", media_type: im.type || "image/png", data: d } }; });
    })).then(function (blocks) { return blocks.concat([{ type: "text", text: text }]); });
  }
  function textOf(content) {
    return (content || []).filter(function (b) { return b.type === "text"; }).map(function (b) { return b.text; }).join("");
  }
  function run(input, o, json) {
    o = o || {};
    var tools = o.tools || [];
    var defs = tools.map(function (t) { return { name: t.name, description: t.description || "", input_schema: t.inputSchema || t.input_schema || { type: "object", properties: {} } }; });
    var first = typeof input === "string" ? [{ role: "user", content: input }] : input.map(function (m) { return { role: m.role, content: m.content }; });
    var last = first[first.length - 1];
    return userContent(last.content, o.images).then(function (c) {
      last.content = c;
      var msgs = first, rounds = 0;
      function step() {
        return call("ai.php", { body: { messages: msgs, tools: defs, json: !!json } }).then(function (r) {
          if (r.stop_reason === "tool_use" && rounds++ < 8) {
            msgs = msgs.concat([{ role: "assistant", content: r.content }]);
            var uses = r.content.filter(function (b) { return b.type === "tool_use"; });
            return Promise.all(uses.map(function (u) {
              var t = tools.filter(function (x) { return x.name === u.name; })[0];
              return Promise.resolve().then(function () { return t ? t.execute(u.input || {}) : { error: "Unknown tool" }; })
                .then(function (out) { return { type: "tool_result", tool_use_id: u.id, content: JSON.stringify(out === undefined ? null : out) }; },
                  function (e) { return { type: "tool_result", tool_use_id: u.id, is_error: true, content: String(e && e.message || e) }; });
            })).then(function (results) { msgs = msgs.concat([{ role: "user", content: results }]); return step(); });
          }
          var text = textOf(r.content);
          if (o.onText) try { o.onText({ text: text }); } catch (e) {}
          return text;
        });
      }
      return step();
    });
  }
  function parseJson(text) {
    var t = String(text).trim().replace(/^```(?:json)?\s*/i, "").replace(/\s*```$/, "");
    try { return JSON.parse(t); } catch (e) {}
    var a = t.search(/[\[{]/), b = Math.max(t.lastIndexOf("}"), t.lastIndexOf("]"));
    if (a >= 0 && b > a) { try { return JSON.parse(t.slice(a, b + 1)); } catch (e) {} }
    throw err("invalid-response", "The AI reply couldn't be read. Please try again.");
  }
  var sample = function (input, o) { return run(input, o, false).then(function (text) { return { text: text }; }); };
  sample.json = function (prompt, o) { return run(prompt, o, true).then(parseJson); };
  sample.limits = function () { return mePromise.then(function (j) { return j.ai ? { images: { maxCount: 3 } } : null; }); };

  /* ---------------- assets: uploaded files (CVs) ---------------- */
  var assets = {
    upload: function (file, o) {
      var f = new FormData();
      f.append("file", file);
      f.append("type", (o && o.type) || file.type || "application/octet-stream");
      return call("assets.php", { body: f }).then(function (j) { return { id: j.id, url: API + "assets.php?id=" + j.id }; });
    },
    delete: function (id) { return call("assets.php?op=delete&id=" + encodeURIComponent(id), { method: "POST" }); },
    url: function (id) { return API + "assets.php?id=" + encodeURIComponent(id); }
  };

  /* ---------------- downloads: save through the browser ---------------- */
  var downloads = {
    save: function (o) {
      var data = o.data;
      var blob = data instanceof Blob ? data : new Blob([data], { type: o.mimeType || "text/plain;charset=utf-8" });
      var a = document.createElement("a");
      a.href = URL.createObjectURL(blob);
      a.download = o.filename || "download.txt";
      document.body.appendChild(a);
      a.click();
      setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
      return Promise.resolve();
    }
  };

  var ns = { db: db, user: user, sample: sample, assets: assets, downloads: downloads };
  window.claude = {
    use: function (name) {
      if (name === "sample") return mePromise.then(function (j) { if (!j.ai || !j.canEdit) throw err("unavailable", "AI is not available."); return sample; });
      if (name === "assets" || name === "downloads") return mePromise.then(function (j) { if (!j.canEdit) throw err("permission-denied"); return ns[name]; });
      return ns[name] ? mePromise.then(function () { return ns[name]; }) : Promise.reject(err("unavailable", "Not available: " + name));
    }
  };
})();
