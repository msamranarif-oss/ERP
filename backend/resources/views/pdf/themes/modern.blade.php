<style>
/* ══════════════════════════════════════════════
   THEME: MODERN  — Dark charcoal header, high contrast, bold
══════════════════════════════════════════════ */
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size:12px; color:#111827; background:#fff; }
.page { padding:0; }

/* Dark hero header */
.header { background:#111827; color:#fff; padding:24px 36px; margin-bottom:0;
           display:flex; justify-content:space-between; align-items:center; }
.company-name { font-size:20px; font-weight:bold; color:#f9fafb; }
.company-info { font-size:10px; color:#9ca3af; margin-top:4px; line-height:1.6; }
.doc-title { font-size:26px; font-weight:bold; color:#f59e0b; text-align:right; letter-spacing:2px; }
.doc-meta  { font-size:11px; color:#d1d5db; text-align:right; line-height:1.7; margin-top:4px; }

/* Accent bar */
.accent-bar { height:4px; background:linear-gradient(to right,#f59e0b,#ef4444); margin-bottom:24px; }

.badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:10px; font-weight:bold; }
.badge-paid    { background:#059669; color:#fff; }
.badge-partial { background:#d97706; color:#fff; }
.badge-unpaid  { background:#dc2626; color:#fff; }

.parties { display:flex; justify-content:space-between; padding:0 36px; margin-bottom:20px; gap:16px; }
.party-box { width:48%; background:#f9fafb; border-left:4px solid #f59e0b;
              padding:12px 14px; border-radius:0 4px 4px 0; }
.party-label { font-size:9px; color:#6b7280; font-weight:bold; text-transform:uppercase;
               letter-spacing:.5px; margin-bottom:3px; }
.party-name  { font-size:13px; font-weight:bold; color:#111827; }
.party-detail{ font-size:10px; color:#6b7280; line-height:1.5; }

table { width:100%; border-collapse:collapse; margin-bottom:16px; }
th { background:#1f2937; color:#f9fafb; padding:9px 10px; text-align:left; font-size:10px;
     text-transform:uppercase; letter-spacing:.4px; }
td { padding:8px 10px; border-bottom:1px solid #f3f4f6; font-size:11px; }
tr:hover td { background:#fffbeb; }

.totals     { float:right; width:250px; }
.totals td  { padding:6px 10px; font-size:11px; }
.totals .label { color:#6b7280; }
.totals .value { text-align:right; font-weight:bold; }
.totals .grand td { background:#f59e0b; color:#111827; font-size:13px; font-weight:bold; }

.section-title { font-size:10px; font-weight:bold; color:#f59e0b; text-transform:uppercase;
                 letter-spacing:.5px; margin:14px 0 6px; }
.notes  { font-size:10px; color:#6b7280; border-top:1px solid #f3f4f6; padding-top:8px; margin-top:8px; }
.footer { position:fixed; bottom:0; left:0; right:0; text-align:center; font-size:9px;
          color:#9ca3af; padding:5px 0; background:#111827; color:#6b7280; }
.clearfix::after { content:''; display:table; clear:both; }
.sig-line { margin-top:44px; display:flex; justify-content:space-between; padding:0 36px; }
.sig-box  { text-align:center; width:42%; border-top:2px solid #f59e0b; padding-top:5px;
            font-size:10px; color:#6b7280; }
.inner-pad { padding:0 36px; }
</style>
