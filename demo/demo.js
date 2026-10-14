/* Demo helpers shared by /demo/hr/ and /demo/finance/.
   The apps run in their offline mode: nothing is sent anywhere and changes are lost on refresh. */
(function () {
  "use strict";
  var WA = "601116320694";

  // ?lang=ms|en from the website's links sets the app language before it boots
  try {
    var q = new URLSearchParams(location.search).get("lang");
    if (q === "ms" || q === "en") localStorage.setItem("esr-lang", q);
  } catch (e) {}

  function lang() {
    var l = window.I18N && window.I18N.lang;
    if (l) return l;
    try { l = localStorage.getItem("esr-lang"); } catch (e) {}
    return l === "en" ? "en" : "ms";
  }
  function t(en, ms) { return lang() === "ms" ? ms : en; }
  function wa(text) { return "https://wa.me/" + WA + "?text=" + encodeURIComponent(text); }
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  var css = ".demo-bar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;" +
    "background:var(--mari-soft);border-radius:10px;padding:10px 14px;margin-bottom:18px;font-size:.9rem}" +
    ".demo-bar a{color:var(--pine);font-weight:700;text-decoration:none;white-space:nowrap}" +
    ".demo-bar a:hover{text-decoration:underline}.demo-bar .links{display:flex;gap:14px;flex-wrap:wrap}" +
    ".demo-tag{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--marigold)}";
  document.addEventListener("DOMContentLoaded", function () {
    var st = document.createElement("style");
    st.textContent = css;
    document.head.appendChild(st);
  });

  function banner(app) {
    var other = app === "hr"
      ? { href: "../finance/", label: t("Open the Finance demo", "Buka demo Kewangan") }
      : { href: "../hr/", label: t("Open the HR demo", "Buka demo HR") };
    var home = lang() === "ms" ? "/" : "/en/";
    return '<div class="demo-bar"><div>' +
      t("<b>Demo with sample data.</b> The company and people here are made up. Try anything: changes reset when you refresh.",
        "<b>Demo dengan data contoh.</b> Syarikat dan pekerja di sini adalah rekaan. Cubalah apa sahaja: perubahan akan hilang apabila halaman dimuat semula.") +
      '</div><div class="links"><a href="' + other.href + '">' + other.label + '</a>' +
      '<a href="' + wa(t("Hi, I tried the demo and would like to book a full demo.", "Hai, saya sudah mencuba demo dan ingin menempah demo penuh.")) +
      '" target="_blank" rel="noopener">' + t("Book a full demo", "Tempah demo penuh") + '</a>' +
      '<a href="' + home + '">' + t("Back to website", "Kembali ke laman web") + '</a></div></div>';
  }

  // Stands in for the AI runtime: every AI feature shows this message instead of failing silently
  function aiError() {
    return new Error(t(
      "AI features run in the full system, on your own data. Book a demo on WhatsApp (011-1632 0694) to see them.",
      "Ciri AI berfungsi dalam sistem penuh, menggunakan data anda sendiri. Tempah demo di WhatsApp (011-1632 0694) untuk melihatnya."));
  }
  function sample() { return Promise.reject(aiError()); }
  sample.json = function () { return Promise.reject(aiError()); };

  // Stands in for the downloads runtime: saves the file through the browser
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

  window.ESR_DEMO = {
    lang: lang, t: t, esc: esc, banner: banner, sample: sample, downloads: downloads,
    sync: function () { return t("Demo, sample data", "Demo, data contoh"); },
    clockMsg: function () {
      return t("In the full system, staff tap here on their phone and their location is checked against the office. The demo doesn't use your location.",
        "Dalam sistem penuh, pekerja menekan butang ini di telefon dan lokasi mereka disemak dengan lokasi pejabat. Demo ini tidak menggunakan lokasi anda.");
    }
  };
})();
