<style>
/* ══════════════════════════════════════════════
   THEME: MINIMAL  — Ultra-clean, slate accents, whitespace-first
══════════════════════════════════════════════ */
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size:11.5px; color:#374151; }
.page { padding:36px 44px; }

.header { display:flex; justify-content:space-between; align-items:flex-start;
           margin-bottom:28px; }
.company-name { font-size:18px; font-weight:bold; color:#111827; }
.company-info { font-size:10px; color:#9ca3af; margin-top:4px; line-height:1.6; }
.doc-title { font-size:28px; font-weight:bold; color:#111827; text-align:right;
              letter-spacing:-0.5px; }
.doc-meta  { font-size:10px; color:#9ca3af; text-align:right; line-height:1.8; margin-top:4px; }

/* Thin rule replaces the heavy border */
.header-rule { height:1px; background:#e5e7eb; margin-bottom:24px; }

.badge { display:inline-block; padding:2px 8px; border:1px solid currentColor;
          border-radius:2px; font-size:9px; font-weight:bold; letter-spacing:.5px; }
.badge-paid    { color:#059669; border-color:#059669; }
.badge-partial { color:#d97706; border-color:#d97706; }
.badge-unpaid  { color:#dc2626; border-color:#dc2626; }

.parties { display:flex; justify-content:space-between; margin-bottom:24px; gap:20px; }
.party-box { width:48%; }
.party-label { font-size:9px; color:#9ca3af; text-transform:uppercase;
               letter-spacing:.8px; margin-bottom:4px; }
.party-name  { font-size:14px; font-weight:bold; color:#111827; }
.party-detail{ font-size:10px; color:#6b7280; line-height:1.6; margin-top:2px; }

table { width:100%; border-collapse:collapse; margin-bottom:20px; }
th { border-bottom:2px solid #111827; color:#111827; padding:7px 8px;
     text-align:left; font-size:10px; text-transform:uppercase; letter-spacing:.3px;
     font-weight:bold; background:none; }
td { padding:7px 8px; border-bottom:1px solid #f3f4f6; font-size:11px; color:#374151; }
tr:last-child td { border-bottom:none; }

.totals     { float:right; width:230px; }
.totals td  { padding:5px 8px; font-size:11px; border:none; }
.totals .label { color:#9ca3af; }
.totals .value { text-align:right; color:#111827; }
.totals .grand td { border-top:2px solid #111827; padding-top:8px; font-size:13px;
                     font-weight:bold; color:#111827; }

.section-title { font-size:9px; color:#9ca3af; text-transform:uppercase; letter-spacing:.8px;
                 margin:14px 0 6px; }
.notes  { font-size:10px; color:#9ca3af; margin-top:10px; line-height:1.5; }
.footer { position:fixed; bottom:0; left:0; right:0; text-align:center; font-size:9px;
          color:#d1d5db; border-top:1px solid #f3f4f6; padding:5px 0; background:#fff; }
.clearfix::after { content:''; display:table; clear:both; }
.sig-line { margin-top:48px; display:flex; justify-content:space-between; }
.sig-box  { text-align:center; width:40%; border-top:1px solid #d1d5db; padding-top:6px;
            font-size:10px; color:#9ca3af; }
</style>
