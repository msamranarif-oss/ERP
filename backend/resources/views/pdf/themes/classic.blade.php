<style>
/* ══════════════════════════════════════════════
   THEME: CLASSIC  — Blue accent, traditional
══════════════════════════════════════════════ */
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size:12px; color:#1a1a2e; }
.page { padding:32px 38px; }

.header { display:flex; justify-content:space-between; align-items:flex-start;
          border-bottom:3px solid #1e3a8a; padding-bottom:16px; margin-bottom:20px; }
.company-name { font-size:22px; font-weight:bold; color:#1e3a8a; }
.company-info { font-size:10px; color:#555; margin-top:4px; line-height:1.6; }
.doc-title { font-size:24px; font-weight:bold; color:#1e3a8a; text-align:right; letter-spacing:1px; }
.doc-meta  { font-size:11px; color:#444; text-align:right; line-height:1.7; margin-top:4px; }

.badge { display:inline-block; padding:2px 10px; border-radius:3px; font-size:10px; font-weight:bold; }
.badge-paid    { background:#d1fae5; color:#065f46; }
.badge-partial { background:#fef3c7; color:#92400e; }
.badge-unpaid  { background:#fee2e2; color:#991b1b; }

.parties { display:flex; justify-content:space-between; margin-bottom:18px; gap:16px; }
.party-box { width:48%; }
.party-label { font-size:9px; color:#6b7280; font-weight:bold; text-transform:uppercase;
               letter-spacing:.5px; margin-bottom:3px; }
.party-name  { font-size:13px; font-weight:bold; color:#111; }
.party-detail{ font-size:10px; color:#555; line-height:1.5; }

table { width:100%; border-collapse:collapse; margin-bottom:16px; }
th { background:#1e3a8a; color:#fff; padding:8px 9px; text-align:left; font-size:10px; letter-spacing:.3px; }
td { padding:7px 9px; border-bottom:1px solid #e5e7eb; font-size:11px; }
tr:nth-child(even) td { background:#f0f4ff; }

.totals     { float:right; width:240px; }
.totals td  { padding:5px 9px; font-size:11px; border-bottom:1px solid #e5e7eb; }
.totals .label { color:#555; }
.totals .value { text-align:right; font-weight:bold; }
.totals .grand td { background:#1e3a8a; color:#fff; font-size:13px; font-weight:bold; border:none; }
.totals .grand .value { color:#fff; }

.section-title { font-size:10px; font-weight:bold; color:#1e3a8a; text-transform:uppercase;
                 letter-spacing:.5px; margin:14px 0 6px; border-left:3px solid #1e3a8a; padding-left:7px; }
.notes  { font-size:10px; color:#666; border-top:1px dashed #d1d5db; padding-top:8px; margin-top:10px; }
.footer { position:fixed; bottom:0; left:0; right:0; text-align:center; font-size:9px;
          color:#9ca3af; border-top:1px solid #e5e7eb; padding:5px 0; background:#fff; }
.clearfix::after { content:''; display:table; clear:both; }
.sig-line { margin-top:44px; display:flex; justify-content:space-between; }
.sig-box  { text-align:center; width:40%; border-top:1px solid #9ca3af; padding-top:4px;
            font-size:10px; color:#888; }
</style>
