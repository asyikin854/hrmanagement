/* Sample data for the HR demo. Everyone and everything here is fictional.
   Dates are worked out from today's date so the demo never goes stale. */
window.ESR_SEED = function (h) {
  "use strict";
  var iso = h.iso, TODAY = h.TODAY, workdays = h.workdays, ONB = h.ONB, OFFB = h.OFFB;
  var t = window.ESR_DEMO.t;
  var DOMAIN = "@contoh-teknologi.example";

  function addDays(n) { var x = new Date(TODAY); x.setDate(x.getDate() + n); return x; }
  function day(n) { return iso(addDays(n)); }            // n days from today (negative = past)
  function ago(n) { return day(-n); }
  function stamp(n, hh, mm) { var x = addDays(-n); x.setHours(hh || 9, mm || 0, 0, 0); return x.toISOString(); }
  function weekday(n) { var x = addDays(n); while (x.getDay() === 0 || x.getDay() === 6) x.setDate(x.getDate() + (n >= 0 ? 1 : -1)); return x; }
  function wd(n) { return iso(weekday(n)); }
  function pad(n) { return String(n).padStart(2, "0"); }
  // small deterministic random so the demo looks the same on every visit
  var seed = 7;
  function rnd() { seed = (seed * 9301 + 49297) % 233280; return seed / 233280; }
  function pick(a) { return a[Math.floor(rnd() * a.length)]; }

  /* ---------------- settings ---------------- */
  var yr = TODAY.getFullYear(), half = TODAY.getMonth() < 6 ? 1 : 2;
  var cycle = "H" + half + " " + yr, prevCycle = half === 1 ? "H2 " + (yr - 1) : "H1 " + yr;
  var settings = {
    company: "Contoh Teknologi Sdn Bhd", cycle: cycle, annualDays: 14, sickDays: 14,
    cpfEe: 0, cpfEr: 0, owCeiling: 8000,
    workStart: "09:00", graceMins: 15, absentCutoff: "11:00", autoFrom: "",
    sites: "KL HQ, 3.1390, 101.6869, 200"
  };

  /* ---------------- employees ---------------- */
  // [id, name, role, dept, level, type, location, gender, manager, hiredDaysAgo, promotedDaysAgo, salary, allowance, rating, email]
  var P = [
    ["e01", "Farid Hakimi bin Osman", "Managing Director", "Operations", "L6", "Full-time", "Kuala Lumpur", "Male", "", 2900, 1100, 264000, 1500, 4, "farid"],
    ["e02", "Nurul Aisyah binti Rahman", "Head of People", "People", "L5", "Full-time", "Kuala Lumpur", "Female", "e01", 2050, 480, 168000, 800, 4, "aisyah"],
    ["e03", "Daniel Lim Wei Jie", "Head of Engineering", "Engineering", "L6", "Full-time", "Kuala Lumpur", "Male", "e01", 2400, 700, 228000, 1000, 5, "daniel"],
    ["e04", "Priya a/p Subramaniam", "Sales Director", "Sales", "L5", "Full-time", "Kuala Lumpur", "Female", "e01", 1900, 560, 174000, 1200, 4, "priya"],
    ["e05", "Siti Hajar binti Kamal", "Finance Manager", "Finance", "L5", "Full-time", "Kuala Lumpur", "Female", "e01", 1500, 400, 150000, 600, 4, "sitihajar"],
    ["e06", "Jason Tan Kok Wai", "Marketing Lead", "Marketing", "L4", "Full-time", "Kuala Lumpur", "Male", "e01", 1300, 380, 120000, 500, 3, "jason"],
    ["e07", "Muhammad Irfan bin Zulkifli", "Senior Software Engineer", "Engineering", "L4", "Full-time", "Kuala Lumpur", "Male", "e03", 1650, 300, 126000, 400, 5, "irfan"],
    ["e08", "Chong Mei Ling", "Software Engineer", "Engineering", "L3", "Full-time", "Kuala Lumpur", "Female", "e07", 780, 780, 84000, 300, 4, "meiling"],
    ["e09", "Arjun Nair", "Software Engineer", "Engineering", "L3", "Full-time", "Kuala Lumpur", "Male", "e07", 1180, 1060, 70800, 300, 4, "arjun"],
    ["e10", "Nur Syafiqah binti Azmi", "QA Engineer", "Engineering", "L2", "Full-time", "Kuala Lumpur", "Female", "e03", 620, 620, 57600, 300, 3, "syafiqah"],
    ["e11", "Kelvin Ong", "DevOps Engineer", "Engineering", "L3", "Full-time", "Remote", "Male", "e03", 900, 420, 88800, 300, 4, "kelvin"],
    ["e12", "Aiman Haziq bin Roslan", "Junior Developer", "Engineering", "L1", "Full-time", "Kuala Lumpur", "Male", "e07", 38, 38, 42000, 200, 3, "aiman"],
    ["e13", "Hafiz bin Ismail", "Account Executive", "Sales", "L2", "Full-time", "Kuala Lumpur", "Male", "e04", 820, 820, 50400, 500, 2, "hafiz"],
    ["e14", "Rachel Goh", "Senior Account Manager", "Sales", "L4", "Full-time", "Kuala Lumpur", "Female", "e04", 1250, 1000, 103200, 800, 4, "rachel"],
    ["e15", "Kavitha a/p Rajan", "Account Executive", "Sales", "L2", "Full-time", "Kuala Lumpur", "Female", "e14", 168, 168, 54000, 500, 3, "kavitha"],
    ["e16", "Lee Jun Hao", "Business Development Manager", "Sales", "L3", "Full-time", "Singapore", "Male", "e04", 700, 330, 96000, 800, 4, "junhao"],
    ["e17", "Amirah binti Yusof", "Operations Executive", "Operations", "L2", "Full-time", "Kuala Lumpur", "Female", "e18", 540, 540, 52800, 300, 4, "amirah"],
    ["e18", "Wong Kah Hin", "Operations Manager", "Operations", "L4", "Full-time", "Kuala Lumpur", "Male", "e01", 1450, 600, 114000, 600, 3, "kahhin"],
    ["e19", "Nadia binti Hamzah", "Customer Success Executive", "Operations", "L2", "Full-time", "Kuala Lumpur", "Female", "e18", 980, 450, 55200, 300, 4, "nadia"],
    ["e20", "Ravi a/l Krishnan", "Logistics Coordinator", "Operations", "L2", "Contract", "Kuala Lumpur", "Male", "e18", 350, 350, 46800, 300, 3, "ravi"],
    ["e21", "Tan Hui Min", "Content Marketing Executive", "Marketing", "L2", "Full-time", "Remote", "Female", "e06", 460, 460, 50400, 300, 4, "huimin"],
    ["e22", "Izzat bin Fauzi", "Digital Marketing Intern", "Marketing", "L1", "Intern", "Kuala Lumpur", "Male", "e06", 75, 75, 21600, 0, 3, "izzat"],
    ["e23", "Lim Siew Ling", "Accounts Executive", "Finance", "L2", "Full-time", "Kuala Lumpur", "Female", "e05", 1020, 1020, 51600, 300, 3, "siewling"],
    ["e24", "Zulaikha binti Ahmad", "Payroll Executive", "People", "L2", "Full-time", "Kuala Lumpur", "Female", "e02", 690, 330, 55200, 300, 4, "zulaikha"],
    ["e25", "Marcus Yeoh", "Talent Acquisition Specialist", "People", "L3", "Full-time", "Kuala Lumpur", "Male", "e02", 24, 24, 78000, 300, 3, "marcus"],
    ["e26", "Farah Nabila binti Hassan", "Graphic Designer", "Marketing", "L2", "Part-time", "Remote", "Female", "e06", 400, 400, 30000, 0, 4, "farah"],
    ["e27", "Benjamin Teoh", "Software Engineer", "Engineering", "L3", "Full-time", "Kuala Lumpur", "Male", "e07", 1300, 1300, 76800, 300, 3, "benjamin"],
    ["e28", "Haslinda binti Omar", "Account Executive", "Sales", "L2", "Full-time", "Kuala Lumpur", "Female", "e04", 900, 900, 49200, 500, 3, "haslinda"],
    ["e29", "Gan Chee Keong", "Operations Executive", "Operations", "L3", "Full-time", "Kuala Lumpur", "Male", "e18", 1600, 1200, 70800, 300, 3, "cheekeong"],
    ["e30", "Shalini a/p Mohan", "Social Media Executive", "Marketing", "L2", "Contract", "Kuala Lumpur", "Female", "e06", 600, 600, 45600, 200, 3, "shalini"],
    ["e31", "Ahmad Danial bin Shukri", "Account Executive", "Sales", "L2", "Full-time", "Kuala Lumpur", "Male", "e14", 520, 520, 48000, 500, 2, "danial"],
    ["e32", "Joanne Cheah", "Software Engineer", "Engineering", "L3", "Full-time", "Kuala Lumpur", "Female", "e07", 430, 430, 81600, 300, 4, "joanne"],
    ["e33", "Syed Hakeem bin Syed Ali", "Sales Executive", "Sales", "L2", "Full-time", "Kuala Lumpur", "Male", "e14", 590, 590, 51600, 500, 3, "hakeem"]
  ];
  var exits = { e27: [75, "Resignation"], e28: [205, "Resignation"], e29: [300, "Mutual agreement"], e30: [130, "End of contract"], e31: [30, "Resignation"] };
  var employees = P.map(function (r) {
    var ex = exits[r[0]];
    return {
      id: r[0], name: r[1], role: r[2], department: r[3], level: r[4], type: r[5], location: r[6], gender: r[7],
      managerId: r[8], hireDate: ago(r[9]), lastPromotion: ago(r[10]), salary: r[11], allowance: r[12], rating: r[13],
      email: r[14] + DOMAIN, status: ex ? "Exited" : "Active", exitDate: ex ? ago(ex[0]) : "", exitReason: ex ? ex[1] : "",
      contractEnd: "", residency: "Citizen", userId: ex ? "" : "u" + r[0].slice(1)
    };
  });
  function emp(id) { return employees.filter(function (e) { return e.id === id; })[0]; }
  emp("e20").contractEnd = day(19);
  emp("e30").contractEnd = emp("e30").exitDate;
  emp("e19").status = "On leave";
  emp("e23").exitDate = day(16); emp("e23").exitReason = "Resignation";   // serving notice
  emp("e12").userId = "";                                                   // new hire, clock-in account not linked yet

  /* ---------------- time off ---------------- */
  function lv(id, emp, type, status, start, end, note, createdAgo) {
    return { id: id, employeeId: emp, type: type, status: status, start: start, end: end, days: workdays(start, end), note: note, createdAt: stamp(createdAgo, 10, 12) };
  }
  var leave = [
    lv("l01", "e08", "Annual", "Pending", wd(9), wd(11), "Family trip to Langkawi", 1),
    lv("l02", "e17", "Annual", "Pending", wd(14), wd(14), "Moving house", 2),
    lv("l03", "e09", "Annual", "Pending", wd(21), wd(25), "Balik kampung", 0),
    lv("l04", "e19", "Parental", "Approved", ago(40), day(50), "Maternity leave", 60),
    lv("l05", "e14", "Annual", "Approved", wd(0), wd(1), "Personal", 9),
    lv("l06", "e21", "Sick", "Approved", wd(-1), wd(-1), "Medical certificate attached", 1),
    lv("l07", "e07", "Annual", "Approved", wd(4), wd(8), "Year-end holiday", 20),
    lv("l08", "e13", "Annual", "Declined", wd(2), wd(3), "Clashes with quarter-end sales push", 6),
    lv("l09", "e10", "Unpaid", "Approved", wd(-3), wd(-3), "Extra day after annual leave ran out", 12),
    lv("l10", "e04", "Annual", "Approved", ago(70), ago(66), "", 90),
    lv("l11", "e03", "Annual", "Approved", ago(120), ago(114), "", 140),
    lv("l12", "e24", "Sick", "Approved", ago(33), ago(32), "", 33),
    lv("l13", "e16", "Annual", "Approved", ago(55), ago(53), "", 75),
    lv("l14", "e11", "Compassionate", "Approved", ago(95), ago(94), "", 95),
    lv("l15", "e02", "Annual", "Approved", ago(150), ago(146), "", 170),
    lv("l16", "e05", "Annual", "Approved", ago(25), ago(23), "", 45),
    lv("l17", "e18", "Sick", "Approved", ago(12), ago(12), "", 12)
  ];
  leave = leave.filter(function (l) { return l.start.slice(0, 4) === String(yr) || l.status === "Pending" || l.end >= iso(TODAY); });

  /* ---------------- recruitment ---------------- */
  var jobs = [
    { id: "j01", title: "Senior Backend Engineer", department: "Engineering", level: "L4", location: "Kuala Lumpur", type: "Full-time", openings: 1, status: "Open", salaryMin: 108000, salaryMax: 138000, postedAt: ago(34),
      description: "## About the role\nYou'll build and run the services behind our payroll and invoicing products.\n\n## What you'll do\n- Design and ship Node.js and PostgreSQL services\n- Own reliability for payments and payroll runs\n- Mentor two engineers\n\n## What we're looking for\n- 5+ years building backend systems\n- Experience with financial or payroll data is a plus" },
    { id: "j02", title: "Account Executive", department: "Sales", level: "L2", location: "Kuala Lumpur", type: "Full-time", openings: 2, status: "Open", salaryMin: 48000, salaryMax: 60000, postedAt: ago(20),
      description: "## About the role\nHelp SMEs across the Klang Valley move their HR and payroll online.\n\n## What you'll do\n- Run demos for business owners\n- Manage a pipeline of 40+ accounts\n\n## What we're looking for\n- 1–3 years in B2B sales\n- Fluent in Bahasa Malaysia and English" },
    { id: "j03", title: "Digital Marketing Executive", department: "Marketing", level: "L2", location: "Remote", type: "Full-time", openings: 1, status: "Open", salaryMin: 45600, salaryMax: 57600, postedAt: ago(12),
      description: "## About the role\nGrow our pipeline through search, social and email.\n\n## What you'll do\n- Plan and run paid campaigns\n- Report weekly on cost per lead" },
    { id: "j04", title: "Finance Intern", department: "Finance", level: "L1", location: "Kuala Lumpur", type: "Intern", openings: 1, status: "Closed", salaryMin: 18000, salaryMax: 21600, postedAt: ago(110),
      description: "Six-month internship supporting month-end close and payroll." }
  ];
  function cand(id, name, job, stage, source, exp, notice, appliedAgo, extra) {
    var c = { id: id, name: name, email: name.toLowerCase().split(" ")[0] + "." + id + "@mail.example", jobId: job, stage: stage, source: source,
      expectedSalary: exp, noticeWeeks: notice, appliedAt: ago(appliedAgo), resume: "", notes: "" };
    for (var k in (extra || {})) c[k] = extra[k];
    return c;
  }
  var candidates = [
    cand("c01", "Tan Wei Ming", "j01", "Interview", "LinkedIn", 130000, 8, 28, {
      resume: "6 years building payment and ledger services at a KL fintech.",
      notes: "- Strong system design round\n- Ask about on-call experience in the final interview",
      cv: { name: "TanWeiMing_CV.pdf", kind: "pdf", size: 184320, uploadedAt: stamp(28, 10, 4) },
      cvText: "Tan Wei Ming\nSenior Software Engineer\nNode.js, Go, PostgreSQL, Kafka",
      cvSummary: { headline: "Senior Software Engineer, 6 years in payments", summary: "Backend engineer who led a ledger migration at a mid-size fintech and runs services handling about 2 million transactions a month.",
        totalYears: 6, currentRole: "Senior Software Engineer at a KL fintech", skills: ["Node.js", "Go", "PostgreSQL", "Kafka", "AWS"],
        experience: [{ role: "Senior Software Engineer", company: "Fintech company, Kuala Lumpur", period: "2022 to present", note: "Led move to an event-driven ledger" },
          { role: "Software Engineer", company: "E-commerce platform", period: "2019 to 2022", note: "Checkout and payment gateway integrations" }],
        education: ["BSc Computer Science"], certifications: ["AWS Solutions Architect Associate"], languages: ["English", "Bahasa Malaysia", "Mandarin"],
        highlights: ["Cut API response times by 40%", "Mentored three junior engineers"], matchesRole: ["Payments and ledger services", "Node.js and PostgreSQL"],
        missing: ["Payroll domain experience"], flags: [], at: stamp(28, 10, 6) },
      aiScreen: { score: 8, summary: "Strong backend fit with direct payments experience; payroll knowledge can be learned.", strengths: ["Payments and ledger systems", "Mentoring"],
        concerns: ["Expected salary near the top of the band"], questions: ["Walk me through the ledger migration and what you'd do differently.", "How do you make a monthly payroll run safe to re-run?", "How have you handled an on-call incident with money involved?"], at: stamp(27, 9, 0) } }),
    cand("c02", "Nur Iman binti Halim", "j01", "Screening", "Careers page", 115000, 4, 9, {
      resume: "5 years backend at a logistics start-up.", aiScreen: { score: 6, summary: "Solid backend skills; less experience with financial data.", strengths: ["Go and PostgreSQL", "Start-up pace"], concerns: ["No payments background"], questions: ["Tell me about a data consistency bug you fixed."], at: stamp(8, 11, 0) } }),
    cand("c03", "Vincent Chan", "j01", "Applied", "Job board", 140000, 12, 2, { resume: "8 years Java, banking." }),
    cand("c04", "Aina Sofea binti Rahim", "j02", "Offer", "Referral", 54000, 4, 18, {
      resume: "2 years selling accounting software to SMEs.", notes: "- Great demo role-play\n- Offer sent, waiting for reply",
      aiScreen: { score: 9, summary: "Excellent fit: SME software sales experience and strong references.", strengths: ["SME sales", "Bilingual"], concerns: [], questions: ["How do you handle an owner who says they'll stick with Excel?"], at: stamp(16, 9, 30) } }),
    cand("c05", "Gary Loh", "j02", "Interview", "LinkedIn", 58000, 4, 15, { resume: "3 years telco B2B sales.", aiScreen: { score: 7, summary: "Good closer; new to HR software.", strengths: ["Pipeline discipline"], concerns: ["Product knowledge"], questions: ["What's your typical sales cycle?"], at: stamp(14, 9, 0) } }),
    cand("c06", "Nurfarhana binti Said", "j02", "Screening", "Careers page", 50000, 2, 6, { resume: "1 year inside sales, edtech." }),
    cand("c07", "Darren Kok", "j02", "Applied", "Job board", 52000, 4, 1, { resume: "Retail banking relationship officer." }),
    cand("c08", "Mohd Faiz bin Kassim", "j02", "Rejected", "Agency", 72000, 8, 19, { resume: "Senior sales manager.", rejectReason: "Expected salary well above the band" }),
    cand("c09", "Clara Ng", "j03", "Interview", "LinkedIn", 54000, 4, 10, { resume: "3 years paid social for consumer brands.", aiScreen: { score: 7, summary: "Strong paid social; less B2B.", strengths: ["Meta and Google Ads"], concerns: ["B2B lead generation"], questions: ["How would you lower our cost per demo booked?"], at: stamp(9, 15, 0) } }),
    cand("c10", "Puteri Balqis", "j03", "Applied", "Careers page", 48000, 2, 3, { resume: "Content and SEO, 2 years." }),
    cand("c11", "Aiman Haziq bin Roslan", "j04", "Hired", "Careers page", 21600, 0, 95, { resume: "Final-year accounting student.", hiredAt: ago(60), employeeId: "e12" })
  ];

  /* ---------------- performance ---------------- */
  var goalList = [
    ["e07", "Ship the new payroll engine", "All customers on the new engine with zero failed runs", 45, 60],
    ["e08", "Cut page load time on the dashboard", "Under 2 seconds at the 90th percentile", 15, 80],
    ["e09", "Automate end-to-end tests for payroll", "80% of payroll flows covered", 40, 30],
    ["e10", "Build a regression test pack", "Run before every release", 70, 70],
    ["e11", "Move hosting to Malaysia region", "All services served from the Malaysia region", -10, 60],
    ["e13", "Close 12 new accounts this quarter", "12 signed contracts", 20, 25],
    ["e14", "Grow upsell revenue", "RM120k in upsells", 60, 80],
    ["e15", "Complete product certification", "Pass all three modules", 10, 100],
    ["e16", "Open five Singapore accounts", "Five signed contracts", 45, 40],
    ["e17", "Halve onboarding time for new customers", "From 10 days to 5", -5, 70],
    ["e18", "Customer satisfaction above 4.5", "Average survey score", 75, 90],
    ["e21", "Publish two guides a month", "24 guides this year", 70, 60],
    ["e06", "Launch the new website", "Live in Bahasa Malaysia and English", -20, 100],
    ["e24", "Zero payroll errors", "No corrections after payday", 80, 90],
    ["e25", "Fill all open roles", "All four roles filled", 75, 25],
    ["e02", "Roll out the new review cycle", "All reviews submitted on time", 25, 55],
    ["e05", "Close the books in 5 working days", "Month-end close within 5 days", 40, 80],
    ["e32", "Lead the mobile clock-in project", "Released to all customers", 50, 50]
  ];
  var goals = goalList.map(function (g, i) { return { id: "g" + pad(i + 1), employeeId: g[0], title: g[1], measure: g[2], due: day(g[3]), progress: g[4] }; });

  var slug = function (c) { return c.replace(/[^A-Za-z0-9]/g, ""); };
  var reviews = [];
  var reviewText = {
    strengths: ["Consistently delivers on time and helps teammates.", "Customers ask for them by name.", "Owns problems end to end.", "Clear communicator in both languages."],
    improvements: ["Delegate more to the junior team.", "Write things down so others can pick them up.", "Speak up earlier when deadlines are at risk."],
    next: ["Lead one cross-team project.", "Mentor a new joiner.", "Take on a customer-facing presentation each quarter."]
  };
  employees.forEach(function (e, i) {
    if (e.status === "Exited" || e.hireDate > ago(200)) return;
    reviews.push({ id: e.id + "_" + slug(prevCycle), employeeId: e.id, cycle: prevCycle, rating: e.rating, status: "Submitted",
      strengths: reviewText.strengths[i % 4], improvements: reviewText.improvements[i % 3], nextGoals: reviewText.next[i % 3], updatedAt: stamp(150 + i, 15, 0) });
    if (i % 5 === 3) return;   // not started yet this cycle
    reviews.push({ id: e.id + "_" + slug(cycle), employeeId: e.id, cycle: cycle, rating: e.rating, status: i % 3 === 0 ? "Draft" : "Submitted",
      strengths: reviewText.strengths[(i + 1) % 4], improvements: reviewText.improvements[(i + 2) % 3], nextGoals: reviewText.next[(i + 1) % 3], updatedAt: stamp(3 + (i % 9), 16, 0) });
  });

  /* ---------------- claims ---------------- */
  var claimsBefore = [
    { id: "x01", employeeId: "e14", category: "Transport", amount: 86.40, date: ago(3), description: "Grab to client office, Cyberjaya", status: "Pending" },
    { id: "x02", employeeId: "e16", category: "Meals", amount: 212.00, date: ago(5), description: "Client lunch, Singapore", status: "Pending" },
    { id: "x03", employeeId: "e11", category: "Equipment", amount: 389.00, date: ago(6), description: "Laptop stand and keyboard for home office", status: "Pending" },
    { id: "x04", employeeId: "e21", category: "Training", amount: 450.00, date: ago(40), description: "SEO course", status: "Paid", paidIn: iso(addDays(-30)).slice(0, 7) },
    { id: "x05", employeeId: "e13", category: "Transport", amount: 64.20, date: ago(48), description: "Toll and parking, Shah Alam", status: "Paid", paidIn: iso(addDays(-30)).slice(0, 7) },
    { id: "x06", employeeId: "e10", category: "Medical", amount: 120.00, date: ago(70), description: "Clinic visit", status: "Paid", paidIn: iso(addDays(-60)).slice(0, 7) },
    { id: "x07", employeeId: "e09", category: "Meals", amount: 340.00, date: ago(20), description: "Team dinner", status: "Rejected" }
  ];
  var claimsAfter = [   // approved, so they're paid in this month's payroll
    { id: "x08", employeeId: "e04", category: "Transport", amount: 156.80, date: ago(9), description: "Mileage, Seremban client visit", status: "Approved" },
    { id: "x09", employeeId: "e07", category: "Training", amount: 899.00, date: ago(14), description: "Cloud certification exam", status: "Approved" }
  ];

  /* ---------------- onboarding ---------------- */
  function cl(id, emp, kind, doneCount, createdAgo) {
    var steps = kind === "Onboarding" ? ONB : OFFB;
    return { id: id, employeeId: emp, kind: kind, createdAt: ago(createdAgo), items: steps.map(function (s, i) { return { t: s, done: i < doneCount }; }) };
  }
  var checklists = [cl("k01", "e12", "Onboarding", 5, 45), cl("k02", "e25", "Onboarding", 6, 30), cl("k03", "e15", "Onboarding", 9, 175),
    cl("k04", "e23", "Offboarding", 2, 10), cl("k05", "e31", "Offboarding", 7, 45)];

  /* ---------------- policies ---------------- */
  var policies = [
    { id: "p01", title: "Annual and sick leave", category: "Leave", updatedAt: stamp(120),
      body: "## Annual leave\n- 14 days a year for everyone, from your first day\n- Apply at least 7 days ahead for leave of 3 days or more\n- Up to 5 unused days carry over to March next year\n\n## Sick leave\n- 14 days a year with a medical certificate\n- Up to 60 days of hospitalisation leave a year\n- Tell your manager before 9am on the day" },
    { id: "p02", title: "Working from home", category: "Work arrangements", updatedAt: stamp(200),
      body: "## Who can work from home\n- Everyone who has passed probation, up to 2 days a week\n- Agree the days with your manager\n\n## Clocking in\n- Choose Remote when you clock in so we know where you are" },
    { id: "p03", title: "Expense claims", category: "Pay and benefits", updatedAt: stamp(90),
      body: "## What you can claim\n- Travel to clients, parking and tolls\n- Client meals up to RM150 a person\n- Training approved by your manager\n\n## How to claim\n- Submit within 30 days with a receipt\n- Approved claims are paid with your next salary" },
    { id: "p04", title: "Code of conduct", category: "Conduct", updatedAt: stamp(300),
      body: "## Our standards\n- Treat colleagues and customers with respect\n- Keep customer data private\n- Report concerns to People or the Managing Director" }
  ];

  /* ---------------- attendance and clock-ins ---------------- */
  var now = new Date(), nowMins = now.getHours() * 60 + now.getMinutes();
  var lat0 = 3.1390, lng0 = 101.6869;
  function entry(dateIso, mins, office, far) {
    var p = dateIso.split("-").map(Number), at = new Date(p[0], p[1] - 1, p[2], Math.floor(mins / 60), mins % 60);
    var dist = far ? 1800 + Math.round(rnd() * 900) : Math.round(15 + rnd() * 90);
    var e = { at: at.toISOString(), time: pad(Math.floor(mins / 60)) + ":" + pad(mins % 60), tz: 480,
      lat: Math.round((lat0 + (rnd() - 0.5) * (far ? 0.03 : 0.001)) * 1e5) / 1e5, lng: Math.round((lng0 + (rnd() - 0.5) * (far ? 0.03 : 0.001)) * 1e5) / 1e5,
      acc: Math.round(8 + rnd() * 25), site: office ? "KL HQ" : "", dist: office ? dist : null, inRange: office && !far };
    return e;
  }
  var onLeaveOn = function (id, ds) { return leave.some(function (l) { return l.employeeId === id && l.status === "Approved" && l.start <= ds && l.end >= ds; }); };
  var clock = [];
  employees.forEach(function (e, idx) {
    if (e.status === "Exited" || e.status === "On leave") return;
    var uid = e.userId || "u12";
    var days = {};
    for (var n = 21; n >= 0; n--) {
      var x = addDays(-n), ds = iso(x);
      if (x.getDay() === 0 || x.getDay() === 6 || ds < e.hireDate || onLeaveOn(e.id, ds)) continue;
      if (rnd() < 0.04 && n > 0) continue;                 // the odd missing day
      var remote = e.location === "Remote" || rnd() < 0.12;
      var late = rnd() < 0.1;
      var inM = late ? 9 * 60 + 17 + Math.floor(rnd() * 35) : 8 * 60 + 15 + Math.floor(rnd() * 50);
      if (n === 0 && inM > nowMins) continue;               // not in yet today
      var rec = { mode: remote ? "Remote" : "Office", in: entry(ds, inM, !remote, !remote && rnd() < 0.03) };
      var outM = 17 * 60 + 40 + Math.floor(rnd() * 70);
      if (n > 0) rec.out = entry(ds, outM, !remote, false);
      days[ds] = rec;
    }
    clock.push({ id: uid, updatedAt: new Date().toISOString(), days: days });
  });
  var profiles = { u12: { name: "Aiman Haziq", email: "aimanhaziq.r@mail.example" } };

  var attendance = [];
  [[wd(-2), "e13", "Absent"], [wd(-6), "e20", "Absent"], [wd(-8), "e22", "Late"]].forEach(function (a) {
    var doc = attendance.filter(function (x) { return x.id === a[0]; })[0];
    if (!doc) { doc = { id: a[0], date: a[0], records: {} }; attendance.push(doc); }
    doc.records[a[1]] = { status: a[2], time: a[2] === "Late" ? "10:05" : "" };
  });

  /* ---------------- activity ---------------- */
  var activity = [
    [0, 9, 40, "Approved Rachel Goh's annual leave"],
    [0, 9, 12, "Added applicant Darren Kok for Account Executive"],
    [1, 16, 30, "Moved Tan Wei Ming to Interview"],
    [1, 11, 5, "Started offboarding for Lim Siew Ling"],
    [2, 15, 20, "Sent offer to Aina Sofea binti Rahim"],
    [3, 10, 0, "Finalized payroll for " + iso(addDays(-30)).slice(0, 7)],
    [4, 14, 45, "Muhammad Irfan bin Zulkifli completed a goal: Release the payslip redesign"],
    [6, 9, 30, "Posted Digital Marketing Executive"],
    [8, 17, 10, "Updated policy: Expense claims"],
    [10, 12, 0, "Linked a clock-in account to Marcus Yeoh"]
  ].map(function (a, i) { return { id: "a" + pad(i + 1), text: a[3], at: stamp(a[0], a[1], a[2]) }; });

  /* ---------------- payroll months to finalise ---------------- */
  var payMonths = [];
  for (var m = 9; m >= 1; m--) { var dm = new Date(TODAY.getFullYear(), TODAY.getMonth() - m, 1); payMonths.push(dm.getFullYear() + "-" + pad(dm.getMonth() + 1)); }

  /* ---------------- sample AI output, clearly labelled ---------------- */
  var note = t("Sample answer. In the full system, Claude answers from your own live data.", "Contoh jawapan. Dalam sistem penuh, Claude menjawab berdasarkan data sebenar anda.");
  var chat = [
    { role: "user", content: t("Who should we worry about losing this quarter?", "Siapa yang mungkin berhenti kerja suku tahun ini?") },
    { role: "assistant", tools: ["workforce metrics", "search people", "performance"], content: t(
      "Three people stand out:\n- **Arjun Nair** (Software Engineer): rated 4/5 but not promoted in almost 3 years, and paid about 16% below other L3 engineers. Worth a pay review this quarter.\n- **Hafiz bin Ismail** (Account Executive): rated 2/5 and behind on his sales goal. Agree a clear improvement plan with Priya.\n- **Rachel Goh** (Senior Account Manager): strong performer, no promotion in over 2 years. Talk to her about a path to Sales Manager.\n\n" + note,
      "Tiga orang perlu diberi perhatian:\n- **Arjun Nair** (Jurutera Perisian): penilaian 4/5 tetapi tidak dinaikkan pangkat hampir 3 tahun, dan gajinya kira-kira 16% lebih rendah daripada jurutera L3 lain. Wajar disemak gajinya suku tahun ini.\n- **Hafiz bin Ismail** (Eksekutif Akaun): penilaian 2/5 dan ketinggalan dalam sasaran jualan. Persetujui pelan penambahbaikan yang jelas bersama Priya.\n- **Rachel Goh** (Pengurus Akaun Kanan): berprestasi tinggi, tiada kenaikan pangkat lebih 2 tahun. Bincangkan laluan ke jawatan Pengurus Jualan.\n\n" + note) }
  ];
  function insight(cls, title, body) { return '<div class="insight ' + cls + '"><b>' + title + "</b><p>" + body + "</p></div>"; }
  var briefing = '<div class="brief">' +
    insight("risk", t("Two people at risk in Engineering", "Dua pekerja berisiko dalam Kejuruteraan"), t("Arjun Nair is paid below his level and overdue for promotion. Review before the next cycle closes.", "Gaji Arjun Nair di bawah paras tahapnya dan sudah lama tidak dinaikkan pangkat. Semak sebelum kitaran ini ditutup.")) +
    insight("opportunity", t("Strong Account Executive candidate", "Calon Eksekutif Akaun yang kuat"), t("Aina Sofea scored 9/10 and has an offer out. A quick follow-up could close the hire this week.", "Aina Sofea mendapat skor 9/10 dan tawaran telah dihantar. Susulan segera boleh memuktamadkan pengambilan minggu ini.")) +
    insight("note", t("Three leave requests waiting", "Tiga permohonan cuti menunggu"), t("Approve or decline them before the end of the week so teams can plan cover.", "Luluskan atau tolak sebelum hujung minggu supaya pasukan boleh merancang liputan.")) +
    '</div><p class="reasons" style="margin-top:10px">' + note + "</p>";
  var aiText = {
    "read:e09": t("Arjun is a solid performer who is likely to look elsewhere soon.\n- Bring his salary in line with other L3 engineers (about RM84k).\n- Agree a promotion plan to L4 with clear goals for the next 6 months.\n- Give him ownership of the payroll test automation project.\n\n" + note,
      "Arjun berprestasi baik tetapi berkemungkinan mencari kerja lain tidak lama lagi.\n- Selaraskan gajinya dengan jurutera L3 lain (kira-kira RM84k).\n- Persetujui pelan kenaikan ke L4 dengan matlamat jelas untuk 6 bulan akan datang.\n- Beri dia tanggungjawab projek automasi ujian gaji.\n\n" + note)
  };

  return {
    settings: settings,
    data: { employees: employees, leave: leave, jobs: jobs, candidates: candidates, payroll: [], goals: goals, reviews: reviews,
      claims: claimsBefore, checklists: checklists, policies: policies, attendance: attendance, activity: activity, clock: clock },
    payMonths: payMonths, afterPayroll: claimsAfter, profiles: profiles,
    me: { id: "u02", name: "Nurul Aisyah" },
    chat: chat, briefing: briefing, aiText: aiText
  };
};
