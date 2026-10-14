/* Sample data for the Finance demo. The company, customers, suppliers and staff are all fictional.
   Dates are worked out from today's date so the demo never goes stale. */
window.ESR_SEED = function (h) {
  "use strict";
  var iso = h.iso, TODAY = h.TODAY;
  var t = window.ESR_DEMO.t;
  var today = iso(TODAY);

  function pad(n) { return String(n).padStart(2, "0"); }
  function addDays(dt, n) { var x = new Date(dt); x.setDate(x.getDate() + n); return x; }
  function dayIn(monthsAgo, dom) {           // a date in a month, clamped to the month's length
    var first = new Date(TODAY.getFullYear(), TODAY.getMonth() - monthsAgo, 1);
    var last = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
    return new Date(first.getFullYear(), first.getMonth(), Math.min(dom, last));
  }
  function monthId(monthsAgo) { var x = dayIn(monthsAgo, 1); return x.getFullYear() + "-" + pad(x.getMonth() + 1); }
  function monthEnd(monthsAgo) { var x = dayIn(monthsAgo, 1); return iso(new Date(x.getFullYear(), x.getMonth() + 1, 0)); }
  function stamp(dateIso, hh) { return dateIso + "T" + pad(hh || 9) + ":00:00.000Z"; }
  var seed = 11;
  function rnd() { seed = (seed * 9301 + 49297) % 233280; return seed / 233280; }
  function r2(n) { return Math.round(n * 100) / 100; }

  var HIST = 13;                              // months of history
  var prevStart = iso(dayIn(1, 1));           // bank feed covers last month and this month

  /* ---------------- company ---------------- */
  var settings = {
    company: "Contoh Perunding Sdn Bhd", uen: "202001000000 (DEMO)", address: "Level 8, Menara Contoh, Jalan Contoh, 50450 Kuala Lumpur",
    email: "accounts@contoh-perunding.example", gstRegistered: "No", gstNo: "", gstRate: 8, fyStart: 1, terms: 30,
    invPrefix: "INV-", nextInv: 1001, bankDetails: "Demo Bank Berhad · Contoh Perunding Sdn Bhd · A/C 0000 0000 0000"
  };
  var accounts = [
    ["1000", "Bank – current account", "Asset"], ["1100", "Accounts receivable", "Asset"], ["1300", "Input tax", "Asset"], ["1400", "Prepayments and deposits", "Asset"], ["1500", "Office equipment", "Asset"],
    ["2000", "Accounts payable", "Liability"], ["2100", "Output tax", "Liability"], ["2200", "EPF, SOCSO and EIS payable", "Liability"], ["2300", "PCB payable", "Liability"], ["2400", "Accrued expenses", "Liability"],
    ["3000", "Share capital", "Equity"], ["3100", "Retained earnings", "Equity"],
    ["4000", "Consulting revenue", "Income"], ["4100", "Recruitment fees", "Income"], ["4900", "Other income", "Income"],
    ["5000", "Salaries and wages", "Expense"], ["5010", "Employer EPF, SOCSO and EIS", "Expense"], ["5020", "Staff claims and benefits", "Expense"],
    ["6000", "Rent", "Expense"], ["6010", "Utilities and telecoms", "Expense"], ["6020", "Software and subscriptions", "Expense"], ["6030", "Marketing and advertising", "Expense"],
    ["6040", "Travel and transport", "Expense"], ["6050", "Professional fees", "Expense"], ["6060", "Office supplies", "Expense"], ["6070", "Bank charges", "Expense"],
    ["6080", "Training", "Expense"], ["6090", "Meals and entertainment", "Expense"]
  ].map(function (a) { return { code: a[0], name: a[1], type: a[2] }; });

  /* ---------------- invoices ---------------- */
  var CUSTOMERS = ["Bayu Hijau Logistik Sdn Bhd", "Seri Mutiara Klinik Sdn Bhd", "Kiara Hartanah Sdn Bhd", "Lembah Pintar Solutions Sdn Bhd",
    "Puncak Selera Food Industries Sdn Bhd", "Rimba Kreatif Studio", "Delima Retail Sdn Bhd", "Teluk Marine Services Sdn Bhd"];
  var WORK = [["HR advisory retainer", "4000", 18000], ["Payroll outsourcing, monthly", "4000", 12500], ["Employee handbook review", "4000", 15800],
    ["Recruitment fee – Operations Manager", "4100", 22400], ["Recruitment fee – Account Executive", "4100", 11200], ["Performance review workshop", "4000", 14500],
    ["Recruitment fee – Finance Manager", "4100", 26800], ["Compensation benchmarking study", "4000", 28500]];
  var invoices = [], num = 1040;
  for (var k = HIST - 1; k >= 0; k--) {
    for (var i = 0; i < 6; i++) {
      var dt = dayIn(k, 2 + i * 5);
      if (iso(dt) > today) continue;
      var cust = CUSTOMERS[(k * 3 + i) % CUSTOMERS.length], w = WORK[(k + i * 2) % WORK.length];
      var lines = [{ desc: w[0] + ", " + dt.toLocaleDateString("en-GB", { month: "long", year: "numeric" }), qty: 1, price: w[2] + Math.round(rnd() * 8) * 500, account: w[1], gst: false }];
      if (rnd() < 0.3) lines.push({ desc: "Additional consultant days", qty: 2, price: 1800, account: "4000", gst: false });
      var total = lines.reduce(function (a, l) { return a + l.qty * l.price; }, 0);
      var due = iso(addDays(dt, 30));
      var inv = { id: "i" + num, number: "INV-" + num, customer: cust, date: iso(dt), due: due, ref: rnd() < 0.5 ? "PO-" + (4000 + num) : "", notes: "Thank you for your business.", status: "Approved", lines: lines, payments: [] };
      var payDate = iso(addDays(dt, 18 + Math.floor(rnd() * 20)));
      if (payDate < today) inv.payments.push({ date: payDate, amount: total, note: "" });
      invoices.push(inv); num++;
    }
  }
  function byNo(n) { return invoices.filter(function (x) { return x.number === "INV-" + n; })[0]; }
  // a few to make receivables interesting
  var old = invoices.filter(function (x) { return x.date < iso(dayIn(4, 1)) && x.date >= iso(dayIn(5, 1)); })[0];
  if (old) { old.payments = []; old.notes = "Reminder sent twice."; }                               // 90+ days late
  var mid = invoices.filter(function (x) { return x.date < iso(dayIn(2, 1)) && x.date >= iso(dayIn(3, 1)); })[1];
  if (mid) mid.payments = [];                                                                       // 31–60 days late
  var part = invoices.filter(function (x) { return x.payments.length && x.date >= iso(dayIn(1, 1)); })[0];
  if (part) { part.payments[0].amount = r2(part.payments[0].amount * 0.5); part.payments[0].note = "Part payment"; }
  var voided = invoices.filter(function (x) { return x.date < iso(dayIn(6, 1)); })[2];
  if (voided) { voided.status = "Void"; voided.payments = []; voided.notes = "Raised in error, replaced by the next invoice."; }
  invoices.push({ id: "i" + num, number: "INV-" + num, customer: CUSTOMERS[5], date: today, due: iso(addDays(TODAY, 30)), ref: "", notes: "", status: "Draft",
    lines: [{ desc: "Brand and culture workshop", qty: 1, price: 7800, account: "4000", gst: false }], payments: [] });
  num++;
  settings.nextInv = num;

  /* ---------------- bills ---------------- */
  var bills = [], bn = 1;
  function bill(supplier, ref, date, dueDays, desc, account, amount, paidAfter) {
    if (date > today) return;
    var b = { id: "b" + (bn++), supplier: supplier, ref: ref, date: date, due: iso(addDays(new Date(date + "T00:00:00"), dueDays)), notes: "", status: "Approved",
      lines: [{ desc: desc, account: account, amount: amount, gst: false }], payments: [] };
    var pd = iso(addDays(new Date(date + "T00:00:00"), paidAfter));
    if (paidAfter !== null && pd < today) b.payments.push({ date: pd, amount: amount, note: "" });
    bills.push(b);
  }
  for (k = HIST - 1; k >= 0; k--) {
    var mon = dayIn(k, 1).toLocaleDateString("en-GB", { month: "short", year: "numeric" }), mid2 = monthId(k).replace("-", "");
    bill("Menara Contoh Properties Sdn Bhd", "RENT-" + mid2, iso(dayIn(k, 1)), 7, "Office rent, " + mon, "6000", 8500, 4);
    bill("Pantas Fibre Broadband", "PF-" + mid2, iso(dayIn(k, 8)), 14, "Fibre broadband and phone lines, " + mon, "6010", 480 + Math.round(rnd() * 60), 10);
    bill("Awan Cloud Services", "AWN-" + mid2, iso(dayIn(k, 3)), 0, "Cloud software licences, " + mon, "6020", 1180, 0);
    bill("Elektrik & Air Bangunan", "UTL-" + mid2, iso(dayIn(k, 12)), 14, "Electricity and water, " + mon, "6010", 610 + Math.round(rnd() * 140), 9);
    bill("Cetak Pantas Supplies", "CP-" + (700 + k), iso(dayIn(k, 16)), 30, "Stationery and printing", "6060", 220 + Math.round(rnd() * 380), 25);
    if (k % 3 === 0) bill("Kemas Tax Agents", "KTA-" + mid2, iso(dayIn(k, 20)), 30, "Tax and secretarial services, quarter", "6050", 3600, 21);
    if (k % 2 === 1) bill("Gema Digital Ads", "GDA-" + mid2, iso(dayIn(k, 22)), 14, "Online advertising campaign", "6030", 2400 + Math.round(rnd() * 1600), 12);
    if (k === 4) bill("Ilmu Training Centre", "ITC-219", iso(dayIn(k, 18)), 30, "Leadership course, 2 staff", "6080", 3200, 20);
  }
  // one supplies bill left overdue
  var lateBill = bills.filter(function (b) { return b.supplier === "Cetak Pantas Supplies" && b.due < today && b.due >= iso(addDays(TODAY, -20)); })[0];
  if (lateBill) lateBill.payments = [];

  /* ---------------- staff (Malaysian payroll) ---------------- */
  // [id, name, nationality, marital, children, monthly salary, allowance, zakat, startedMonthsAgo]
  var STAFF = [
    ["me01", "Azlan bin Mohd Noor", "Malaysian", "Married, spouse not working", 3, 14000, 800, 0, 60],
    ["me02", "Grace Lau Siew Fong", "Malaysian", "Married, spouse working", 2, 9500, 500, 0, 48],
    ["me03", "Nor Hidayah binti Salleh", "Malaysian", "Single", 0, 8000, 400, 50, 40],
    ["me04", "Vikram a/l Ganesan", "Malaysian", "Married, spouse working", 1, 7200, 400, 0, 36],
    ["me05", "Teo Jia Xin", "Malaysian", "Single", 0, 6500, 300, 0, 30],
    ["me06", "Khairul Anuar bin Jamal", "Malaysian", "Married, spouse not working", 2, 5800, 300, 30, 26],
    ["me07", "Melissa Chua", "Malaysian", "Single", 0, 5200, 300, 0, 20],
    ["me08", "Siti Aminah binti Yahya", "Malaysian", "Married, spouse working", 1, 4500, 200, 0, 16],
    ["me09", "Rizal bin Hamdan", "Malaysian", "Single", 0, 4200, 200, 0, 10],
    ["me10", "Ana Ramos", "Foreign", "Single", 0, 3800, 200, 0, 7]
  ];
  var startBase = iso(dayIn(HIST, 1));
  var my_employees = STAFF.map(function (s, i) {
    var start = iso(dayIn(s[8], 1));
    return { id: s[0], name: s[1], ic: s[2] === "Malaysian" ? "XXXXXX-14-" + pad(i + 1) + "01" : "", tin: "IG XXXXXXXX" + i + "0", epfNo: s[2] === "Malaysian" ? "DEMO-EPF-" + pad(i + 1) : "",
      socsoNo: s[2] === "Malaysian" ? "DEMO-SOCSO-" + pad(i + 1) : "", nationality: s[2], residence: "Resident", marital: s[3], children: s[4],
      salary: s[5], allowance: s[6], zakat: s[7], start: start < startBase ? start : start, end: "", roster: "" };
  });
  var myMonths = [];
  for (k = HIST - 1; k >= 1; k--) myMonths.push(monthId(k));
  function paidRef(mid) { var p = mid.split("-").map(Number); return new Date(p[0], p[1], 14); }
  function myRunExtra(mid) {
    var isLast = mid === monthId(1), pdt = iso(paidRef(mid));
    var paid = !isLast && pdt < today;
    return { status: "Finalized", pcbPaid: paid ? { date: pdt, ref: "CP39-" + mid.replace("-", "") } : null, statPaid: paid ? { date: pdt, ref: "STAT-" + mid.replace("-", "") } : null };
  }

  /* ---------------- journals ---------------- */
  var journals = [{
    id: "jopen", date: iso(dayIn(HIST, 1)), ref: "OB", memo: "Opening balances", kind: "Opening balance",
    lines: [{ account: "1000", debit: 185000, credit: 0, memo: "Bank" }, { account: "1500", debit: 42000, credit: 0, memo: "Office equipment" },
      { account: "1400", debit: 17000, credit: 0, memo: "Rental deposit" }, { account: "3000", debit: 0, credit: 100000, memo: "Share capital" },
      { account: "3100", debit: 0, credit: 144000, memo: "Retained earnings" }]
  }];
  // payroll and statutory payment journals, built from the payroll runs once they exist
  function afterPayroll(S) {
    S.my_payroll.forEach(function (r) {
      var T = r.totals, mid = r.id, p = mid.split("-").map(Number), end = iso(new Date(p[0], p[1], 0));
      var stat = r2(T.epfEe + T.epfEr + T.socsoEe + T.socsoEr + T.eisEe + T.eisEr), er = r2(T.epfEr + T.socsoEr + T.eisEr), tax = r2(T.pcb + T.zakat);
      S.journals.push({ id: "jpay" + mid, date: end, ref: "Payroll " + mid, memo: "Payroll for " + mid, kind: "Payroll",
        lines: [{ account: "5000", debit: T.gross, credit: 0, memo: "Salaries and allowances" }, { account: "5010", debit: er, credit: 0, memo: "Employer EPF, SOCSO and EIS" },
          { account: "2200", debit: 0, credit: stat, memo: "EPF, SOCSO and EIS payable" }, { account: "2300", debit: 0, credit: tax, memo: "PCB and zakat payable" },
          { account: "1000", debit: 0, credit: T.net, memo: "Net pay" }] });
      if (r.statPaid) S.journals.push({ id: "jstat" + mid, date: r.statPaid.date, ref: "Statutory " + mid, memo: "EPF, SOCSO, EIS and PCB for " + mid, kind: "Manual",
        lines: [{ account: "2200", debit: stat, credit: 0, memo: "EPF, SOCSO and EIS" }, { account: "2300", debit: tax, credit: 0, memo: "PCB and zakat" },
          { account: "1000", debit: 0, credit: r2(stat + tax), memo: "Paid to KWSP, PERKESO and LHDN" }] });
    });

    /* ---------------- bank feed for last month and this month ---------------- */
    var bl = [], n = 1, stPrev = "st_prev", stCur = "st_cur";
    function line(date, desc, amount, extra) {
      var o = { id: "bl" + pad(n++), date: date, desc: desc, amount: r2(amount), status: "New", importedAt: stamp(date < iso(dayIn(0, 1)) ? iso(dayIn(0, 2)) : today, 8),
        statementId: date < iso(dayIn(0, 1)) ? stPrev : stCur };
      for (var key in extra) o[key] = extra[key];
      bl.push(o); return o;
    }
    S.invoices.forEach(function (inv) {
      inv.payments.forEach(function (p) {
        if (p.date < prevStart) return;
        var o = line(p.date, "IBG CR " + inv.customer.toUpperCase().slice(0, 24) + " " + inv.number, p.amount, { status: "Matched", match: { kind: "inv", id: inv.id }, matchLabel: inv.number + " · " + inv.customer });
        p.bankLine = o.id;
      });
    });
    S.bills.forEach(function (b) {
      b.payments.forEach(function (p) {
        if (p.date < prevStart) return;
        var o = line(p.date, "IBG DR " + b.supplier.toUpperCase().slice(0, 24) + " " + b.ref, -p.amount, { status: "Matched", match: { kind: "bill", id: b.id }, matchLabel: b.supplier });
        p.bankLine = o.id;
      });
    });
    S.journals.forEach(function (j) {
      if (j.date < prevStart || j.date > today) return;
      var bank = j.lines.filter(function (l) { return l.account === "1000"; })[0];
      if (!bank || j.kind === "Opening balance") return;
      var key = "j:" + j.id + "#1";
      line(j.date, j.kind === "Payroll" ? "BULK PAYMENT SALARY " + j.ref.slice(-7) : "JOMPAY KWSP PERKESO LHDN", (bank.debit || 0) - (bank.credit || 0),
        { status: "Matched", match: { kind: "led", key: key }, ledgerKey: key, matchLabel: "Books · " + j.memo });
    });
    // bank charges and small spends, categorised straight from the feed
    [1, 0].forEach(function (k2) {
      var d1 = iso(dayIn(k2, 27));
      if (d1 < today) line(d1, "SERVICE CHARGE", -15, { status: "Categorised", account: "6070", gst: false });
      var d2 = iso(dayIn(k2, 6));
      if (d2 < today) line(d2, "E-HAILING RIDE KL SENTRAL", -38.5, { status: "Categorised", account: "6040", gst: false });
    });
    // still to match this month
    var y1 = iso(addDays(TODAY, -1)), y2 = iso(addDays(TODAY, -2)), y3 = iso(addDays(TODAY, -3));
    line(y3, "E-HAILING RIDE BANGSAR SOUTH", -42.8, { ai: { account: "6040", gst: false, reason: t("Ride-hailing for a client visit", "Perjalanan e-hailing untuk lawatan pelanggan") } });
    line(y3, "RESTORAN NASI KANDAR CONTOH", -186.4, { ai: { account: "6090", gst: false, reason: t("Restaurant bill, likely a client or team meal", "Bil restoran, mungkin makan bersama pelanggan atau pasukan") } });
    line(y2, "ONLINE PURCHASE DESIGN TOOL ANNUAL", -599, { ai: { account: "6020", gst: false, reason: t("Annual software subscription", "Langganan perisian tahunan") } });
    line(y1, "PETROL STATION SHAH ALAM", -120, { ai: { account: "6040", gst: false, reason: t("Fuel for travel to client sites", "Minyak untuk perjalanan ke tapak pelanggan") } });
    var open = S.invoices.filter(function (x) { return x.status === "Approved" && !x.payments.length && x.due >= today; })[0];
    if (open) {
      var amt = open.lines.reduce(function (a, l) { return a + l.qty * l.price; }, 0);
      line(y1, "IBG CR " + open.customer.toUpperCase().slice(0, 24), amt, {});
    }
    line(y1, "INTEREST CREDIT", 21.37, { ai: { account: "4900", gst: false, reason: t("Bank interest", "Faedah bank") } });
    bl.sort(function (a, b) { return a.date < b.date ? -1 : a.date > b.date ? 1 : 0; });
    S.banklines = bl;
  }

  // once the books exist: reconcile last month and record the two statements
  function finish(S, bankLedgerAt) {
    var end = monthEnd(1);
    S.recs = [{ id: end, endDate: end, statementBalance: bankLedgerAt(end), outstanding: [], cleared: S.banklines.filter(function (l) { return l.statementId === "st_prev"; }).length, at: stamp(iso(dayIn(0, 3)), 10) }];
    function stmt(id, start, endD, closing, src, file) {
      var ls = S.banklines.filter(function (l) { return l.statementId === id; });
      return { id: id, fileName: file, bank: "Demo Bank", last4: "0000", periodStart: start, periodEnd: endD,
        opening: closing === null ? null : bankLedgerAt(iso(addDays(new Date(start + "T00:00:00"), -1))), closing: closing,
        moneyIn: r2(ls.filter(function (l) { return l.amount > 0; }).reduce(function (a, l) { return a + l.amount; }, 0)),
        moneyOut: r2(-ls.filter(function (l) { return l.amount < 0; }).reduce(function (a, l) { return a + l.amount; }, 0)),
        lines: ls.length, source: src, importedAt: stamp(src === "ai" ? iso(dayIn(0, 2)) : today, 8) };
    }
    var mname = dayIn(1, 1).toLocaleDateString("en-GB", { month: "short", year: "numeric" }).replace(" ", "-");
    S.statements = [stmt("st_prev", iso(dayIn(1, 1)), end, bankLedgerAt(end), "ai", "Statement-" + mname + ".pdf"),
      stmt("st_cur", iso(dayIn(0, 1)), iso(addDays(TODAY, -1)), null, "csv", "Statement-this-month.csv")];
  }

  /* ---------------- LHDN ---------------- */
  var yr = TODAY.getFullYear();
  var cpPaid = {};
  for (var n2 = 1; n2 <= 12; n2++) { var due2 = new Date(yr, n2, 15); if (iso(due2) < today) cpPaid[n2] = iso(addDays(due2, -2)); }
  var forms = {};
  forms["EA-" + (yr - 1)] = yr + "-02-24";
  if (today > yr + "-04-30") forms["E-" + (yr - 1)] = yr + "-04-22";
  if (today > yr + "-07-31") forms["C-" + (yr - 1)] = yr + "-07-24";
  var cp204 = {}; cp204[yr] = { estimate: 48000, payments: cpPaid };
  var ws = {}; ws[yr] = { revenue: 1380000, expenses: 1150000, addBack: 14000, capAllow: 9500, lossBf: 0, otherIncome: 600 };
  var my = { entity: settings.company, regNo: settings.uen, tin: "C DEMO000000", employerNo: "E DEMO000-00", epfNo: "DEMO-EPF-ER", socsoNo: "DEMO-SOCSO-ER",
    fyeMonth: 12, sme: "Yes", cp204: cp204, forms: forms, ws: ws };

  /* ---------------- sample AI output, clearly labelled ---------------- */
  var note = t("Sample answer. In the full system, Claude answers from your own books.", "Contoh jawapan. Dalam sistem penuh, Claude menjawab berdasarkan buku akaun anda.");
  var chat = [
    { role: "user", content: t("Which customers are paying late?", "Pelanggan mana yang lewat membayar?") },
    { role: "assistant", tools: ["aged receivables", "invoices"], content: t(
      "Two customers are behind:\n- One invoice is **over 90 days** overdue. It has had two reminders, so call the customer's finance contact this week.\n- Another is **31–60 days** late. A polite reminder with the invoice attached usually works.\n\nEverything else is within terms.\n\n" + note,
      "Dua pelanggan lewat membayar:\n- Satu invois sudah **lebih 90 hari** tertunggak. Dua peringatan telah dihantar, jadi hubungi bahagian kewangan pelanggan minggu ini.\n- Satu lagi lewat **31–60 hari**. Peringatan sopan bersama salinan invois biasanya berkesan.\n\nYang lain masih dalam tempoh bayaran.\n\n" + note) }
  ];
  function insight(cls, title, body) { return '<div class="insight ' + cls + '"><b>' + title + "</b><p>" + body + "</p></div>"; }
  var briefing = '<div class="brief">' +
    insight("risk", t("Chase two overdue invoices", "Kejar dua invois tertunggak"), t("One invoice is past 90 days. Collecting it would lift cash noticeably this month.", "Satu invois melebihi 90 hari. Mengutipnya akan meningkatkan tunai dengan ketara bulan ini.")) +
    insight("note", t("Statutory payments due on the 15th", "Bayaran berkanun perlu dibuat sebelum 15hb"), t("Last month's EPF, SOCSO, EIS and PCB are ready to pay.", "KWSP, PERKESO, SIP dan PCB bulan lepas sedia untuk dibayar.")) +
    insight("opportunity", t("Recruitment fees are growing", "Yuran pengambilan pekerja meningkat"), t("Recruitment work brings in a bigger share of revenue than last year. Consider a fixed-fee package.", "Kerja pengambilan pekerja menyumbang bahagian hasil yang lebih besar berbanding tahun lepas. Pertimbangkan pakej yuran tetap.")) +
    '</div><p class="reasons" style="margin-top:10px">' + note + "</p>";

  return {
    settings: settings, accounts: accounts, my: my,
    data: { invoices: invoices, bills: bills, journals: journals, banklines: [], my_employees: my_employees, my_payroll: [], recs: [], statements: [], activity: [] },
    myMonths: myMonths, myRunExtra: myRunExtra, afterPayroll: afterPayroll, finish: finish,
    chat: chat, briefing: briefing
  };
};
