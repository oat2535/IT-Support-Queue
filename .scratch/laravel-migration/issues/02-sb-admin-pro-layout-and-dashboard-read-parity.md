# 02: SB Admin Pro Single-Page Layout, Hostname Resolution, and Dashboard Read Parity

**What to build:** Bring SB Admin Pro static assets from `C:\E-Budget - PHP` plus `TLPHLogo.png` and `moon-zzz.svg` into `public/static/queue_app/`, build the single-page Blade layout (Topbar with Logo, `Get Info` button, Login/Logout button, Offline button, live Thai Buddhist-era clock, Light/Dark/Flat/Auto theme switcher, no Sidebar) and `GET /` dashboard showing KPI counters, Active & Ad-hoc cards, 7-column Queue table (`waiting_rank`, tooltips, badges), multi-word `ILIKE` search, 5-per-page pagination, batched `JobsBms` notes/operator names, 60-second `seamlessReload`, and pure-PHP `HostnameResolver` (`gethostbyaddr` + UDP port 137 NetBIOS fallback, cached 5 minutes).

**Blocked by:** 01

**Status:** resolved

- [x] `GET /` renders SB Admin Pro single-page layout with KPI counters (`waiting`, current-month `done`, `coordinating + waiting_parts`), Active/Ad-hoc cards, and 5-per-page paginated list
- [x] Multi-word search, status tab switching (`waiting`, `done`, `pending`, `active`), global `waiting_rank`, and batched `JobsBms` lookup match Django behavior
- [x] Pure-PHP `HostnameResolver` resolves reverse DNS, falls back to UDP 137 NetBIOS query, falls back to `VPN/Unknown (<ip>)`, and caches for 300s
- [x] CSP `frame-ancestors` allows `imedx-uat.thonglorpet.com:9444` and Feature tests cover dashboard rendering, search, pagination, and `HostnameResolver`
