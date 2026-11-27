<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title', 'IT Smart Queue')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap" rel="stylesheet" />

    <link href="{{ asset('static/queue_app/css/styles.css') }}?v=20261127_03" rel="stylesheet" />
    <link href="{{ asset('static/queue_app/css/theme-override.css') }}?v=20261127_03" rel="stylesheet" />
    <link href="{{ asset('static/queue_app/css/polish.css') }}?v=20261127_03" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css" rel="stylesheet" crossorigin="anonymous" />
    <link rel="icon" type="image/png" href="{{ asset('static/queue_app/assets/img/TLPHLogo.png') }}" />

    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.0/feather.min.js" crossorigin="anonymous"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --queue-font-sans: "Plus Jakarta Sans", "Noto Sans Thai", -apple-system, BlinkMacSystemFont, "Segoe UI", Tahoma, sans-serif;
            --bs-font-sans-serif: var(--queue-font-sans);
            --bs-body-font-family: var(--queue-font-sans);
        }
        body {
            font-family: var(--queue-font-sans) !important;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }
        .tabular-nums {
            font-variant-numeric: tabular-nums;
        }
        .bg-header {
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
            color: #ffffff;
        }
        body.theme-flat .bg-header {
            background: linear-gradient(135deg, #008b99 0%, #5800e8 100%);
        }
        body.theme-dark .bg-header {
            background: linear-gradient(135deg, #141a29 0%, #1e293b 100%) !important;
            border-bottom: 1px solid #334155;
        }
        .card-stat {
            border: 2px solid transparent;
            border-radius: 0.75rem;
        }
        .card-stat-active {
            border-color: rgba(255, 255, 255, 0.85) !important;
            box-shadow: 0 0.5rem 1.25rem rgba(15, 23, 42, 0.25) !important;
        }
        .kpi-waiting-bg {
            background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%) !important;
        }
        .kpi-done-bg {
            background: linear-gradient(135deg, #047857 0%, #059669 100%) !important;
        }
        .kpi-pending-bg {
            background: linear-gradient(135deg, #0052cc 0%, #0061f2 100%) !important;
        }
        .badge-queue-active {
            background-color: #0284c7 !important;
            color: #ffffff !important;
        }
        .badge-queue-adhoc {
            background-color: #d97706 !important;
            color: #ffffff !important;
        }
        .current-queue-card {
            border: none;
            border-radius: 0.85rem;
            overflow: hidden;
        }
        .queue-card-subtitle {
            font-size: 0.9rem;
            font-weight: 600;
            color: #64748b;
            letter-spacing: 0.01em;
        }
        .queue-number-large {
            font-family: "Plus Jakarta Sans", "Noto Sans Thai", sans-serif;
            font-size: clamp(3.25rem, 5.2vw, 4.75rem);
            font-weight: 700;
            line-height: 1.08;
            margin: 0.5rem 0 0.75rem;
            letter-spacing: 0.01em;
            font-variant-numeric: tabular-nums;
        }
        .queue-requester-name {
            font-size: 1.85rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.3;
            margin-bottom: 0.4rem;
        }
        .queue-issue-summary {
            font-size: 1.02rem;
            font-weight: 500;
            color: #475569;
            line-height: 1.6;
        }
        .adhoc-queue-card {
            background-color: #fffbeb;
        }
        body.theme-dark .adhoc-queue-card {
            background-color: #262115 !important;
        }
        body.theme-dark .queue-card-subtitle {
            color: #94a3b8 !important;
        }
        body.theme-dark .queue-requester-name {
            color: #f8fafc !important;
        }
        body.theme-dark .queue-issue-summary {
            color: #cbd5e1 !important;
        }
        .queue-table th {
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }
        .queue-table td {
            vertical-align: middle;
            font-size: 0.94rem;
            padding-top: 0.85rem;
            padding-bottom: 0.85rem;
        }
        body.theme-dark .queue-table-head th {
            background-color: #262b36 !important;
            color: #f1f5f9 !important;
            border-color: #374151 !important;
        }
        body.theme-dark .table-striped > tbody > tr:nth-of-type(odd) > * {
            --bs-table-accent-bg: rgba(255, 255, 255, 0.03);
            color: #e2e8f0;
        }
        body.theme-dark .table-hover > tbody > tr:hover > * {
            --bs-table-accent-bg: rgba(255, 255, 255, 0.06);
            color: #ffffff;
        }
        body.theme-dark .card-footer.bg-white,
        body.theme-dark .card-header.bg-white {
            background-color: #1e1e1e !important;
            border-color: #333 !important;
        }
        body.theme-dark .pagination .page-link {
            background-color: #262b36;
            border-color: #374151;
            color: #e2e8f0;
        }
        body.theme-dark .pagination .page-item.active .page-link {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
            color: #ffffff;
        }
        body.theme-dark .pagination .page-item.disabled .page-link {
            background-color: #1a1e26;
            color: #64748b;
        }
        body.theme-dark .text-gray-800,
        body.theme-dark .text-gray-500,
        body.theme-dark .text-dark {
            color: #e2e8f0 !important;
        }
        body.theme-dark .text-gray-300 {
            color: #6b7280 !important;
        }
        body.theme-dark .bg-light-subtle-input {
            background-color: #2c2c2c !important;
            color: #e0e0e0 !important;
            border-color: #444 !important;
        }
        body.theme-dark .btn-outline-primary {
            color: #93c5fd;
            border-color: #3b82f6;
        }
    </style>
    @yield('extra_css')
</head>
<body id="page-top">
    <script>
        (function () {
            const savedTheme = localStorage.getItem('app-theme') || 'light';
            let activeTheme = savedTheme;
            if (savedTheme === 'auto') {
                activeTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            if (activeTheme === 'dark') {
                document.body.classList.add('theme-dark');
            } else if (savedTheme === 'flat') {
                document.body.classList.add('theme-flat');
            }
        })();
    </script>

    <!-- Single-Page Header (SB Admin Pro styled, no sidebar) -->
    <header class="bg-header shadow-sm mb-4">
        <div class="container-fluid px-4 py-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-white text-decoration-none me-2">
                    <img src="{{ asset('static/queue_app/assets/img/TLPHLogo.png') }}" alt="TLPH Logo" style="height: 46px;" class="me-3">
                    <span class="h3 mb-0 fw-bold text-white" style="letter-spacing: 0.3px;">IT Smart Queue</span>
                </a>

                <button id="get-info-btn" type="button" class="btn btn-light btn-sm rounded-pill px-3 fw-semibold shadow-sm" style="color: #1a237e;">
                    <i class="fas fa-info-circle me-1"></i> Get Info
                </button>

                @if($logged_in_member)
                    <button id="logout-btn" type="button" class="btn btn-danger btn-sm rounded-pill px-3 fw-semibold shadow-sm">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout ({{ $logged_in_member }})
                    </button>
                @else
                    <button id="login-btn" type="button" class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold shadow-sm">
                        <i class="fas fa-sign-in-alt me-1"></i> Login
                    </button>
                @endif
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-light rounded-pill px-3 dropdown-toggle" id="navbarDropdownTheme" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Toggle Theme">
                        <i data-feather="sun" id="themeToggleIcon" class="me-1" style="width: 14px; height: 14px;"></i> Theme
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow animated--fade-in-up" aria-labelledby="navbarDropdownTheme">
                        <li><a class="dropdown-item" href="javascript:void(0);" onclick="setTheme('light')"><i data-feather="sun" class="me-2"></i> Light Mode</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0);" onclick="setTheme('dark')"><i data-feather="moon" class="me-2"></i> Dark Mode</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0);" onclick="setTheme('flat')"><i data-feather="droplet" class="me-2"></i> Flat Mode</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0);" onclick="setTheme('auto')"><i data-feather="monitor" class="me-2"></i> Auto (OS Default)</a></li>
                    </ul>
                </div>

                @if($is_staff_member)
                    <button id="close-shift-btn" type="button" class="btn btn-danger btn-sm rounded-pill px-3 fw-semibold shadow-sm">
                        <i class="fas fa-store-slash me-1"></i> Offline
                    </button>
                @endif

                <div class="h6 mb-0 fw-bold text-white border-start border-light border-opacity-25 ps-3 ms-1 tabular-nums" id="clock">00:00:00</div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main id="main-content" class="container-fluid px-4 pb-4" data-shift-closed="{{ $is_shift_closed ? 'true' : 'false' }}">
        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="{{ asset('static/queue_app/js/scripts.js') }}?v=20261127_02"></script>
    <script>
        window.IS_STAFF = @json($is_staff_member);
        window.IS_SHIFT_CLOSED = @json($is_shift_closed);
        window.CLIENT_IP = @json($client_ip);
        window.CLIENT_HOSTNAME = @json($client_hostname);
        window.QUEUE_ROUTES = {
            dashboard: @json(route('dashboard')),
            loginMember: @json(route('login_member')),
            logoutMember: @json(route('logout_member')),
            toggleShiftStatus: @json(route('toggle_shift_status')),
            callNext: @json(route('call_next')),
            finishAdhoc: @json(route('finish_adhoc')),
            updateJobDesc: @json(route('update_job_desc')),
            toggleUrgent: @json(route('toggle_urgent')),
            insertQueue: @json(route('insert_queue')),
            closeQueue: @json(route('close_queue')),
        };

        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = String(value ?? '');
            return div.innerHTML;
        }

        function getCsrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        }

        function apiFetch(url, options = {}) {
            const headers = Object.assign({
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            }, options.headers || {});

            return fetch(url, Object.assign({}, options, { headers }));
        }

        function updateClock() {
            const now = new Date();
            const days = ['วันอาทิตย์ที่', 'วันจันทร์ที่', 'วันอังคารที่', 'วันพุธที่', 'วันพฤหัสบดีที่', 'วันศุกร์ที่', 'วันเสาร์ที่'];
            const months = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
            const dateString = `${days[now.getDay()]} ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear() + 543}`;
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const clockEl = document.getElementById('clock');
            if (clockEl) {
                clockEl.textContent = `${dateString}  เวลา : ${hours}:${minutes}:${seconds}`;
            }
        }
        setInterval(updateClock, 1000);
        updateClock();

        function initQueueTooltips() {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
                const existing = bootstrap.Tooltip.getInstance(el);
                if (existing) {
                    existing.dispose();
                }
                new bootstrap.Tooltip(el, { html: true });
            });
        }

        function hideAllTooltips() {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
                const instance = bootstrap.Tooltip.getInstance(el);
                if (instance) {
                    instance.hide();
                }
            });
            document.querySelectorAll('.tooltip').forEach((el) => el.remove());
        }

        function toggleShift(shouldClose) {
            apiFetch(window.QUEUE_ROUTES.toggleShiftStatus, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ closed: shouldClose }),
            })
                .then((res) => res.json())
                .then((response) => {
                    if (response.success) {
                        if (!shouldClose) {
                            window.location.href = `${window.QUEUE_ROUTES.dashboard}?status=waiting`;
                        } else {
                            window.location.reload();
                        }
                    } else {
                        Swal.fire('Error', response.error || 'Operation failed', 'error');
                    }
                })
                .catch(() => Swal.fire('Error', 'Connection Error', 'error'));
        }

        function showClosedPopup() {
            const contentHtml = 'เวลาให้บริการ 08.00 น. - 21.00 น.<br>หากมีเคสเร่งด่วนรบกวนติดต่อที่เบอร์ 55999';
            const footerContent = window.IS_STAFF
                ? '<button class="btn btn-success fw-bold py-2 px-4 shadow" onclick="openShift()"><i class="fas fa-store me-2"></i>Online (Open Shift)</button>'
                : '';

            Swal.fire({
                title: 'ตอนนี้อยู่นอกเวลาการให้บริการของแผนก IT',
                html: contentHtml,
                imageUrl: '{{ asset('static/queue_app/assets/img/moon-zzz.svg') }}',
                imageWidth: 120,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                backdrop: 'rgba(0, 0, 0, 0.98)',
                footer: footerContent,
                customClass: {
                    footer: 'border-0 pt-0 pb-4',
                    image: 'border-0 mb-4 mt-2',
                },
            });
        }

        function openShift() {
            Swal.fire({
                title: 'ยืนยัน Online ?',
                text: 'ต้องการเริ่มให้บริการตามปกติใช่หรือไม่',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#00ac69',
                cancelButtonColor: '#e81500',
                confirmButtonText: 'ใช่, Online',
                cancelButtonText: 'ยกเลิก',
            }).then((result) => {
                if (result.isConfirmed) {
                    toggleShift(false);
                } else {
                    showClosedPopup();
                }
            });
        }

        function seamlessReload(url, pushState) {
            if (!url) url = window.location.href;

            fetch(url, {
                cache: 'no-store',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then((response) => {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.text();
                })
                .then((html) => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const incoming = doc.getElementById('main-content');
                    const liveContainer = document.getElementById('main-content');

                    if (!incoming || !liveContainer) {
                        window.location.href = url;
                        return;
                    }

                    const shiftAttr = incoming.getAttribute('data-shift-closed');
                    hideAllTooltips();
                    liveContainer.innerHTML = incoming.innerHTML;
                    liveContainer.setAttribute('data-shift-closed', shiftAttr || 'false');
                    initQueueTooltips();

                    if (typeof feather !== 'undefined') {
                        feather.replace();
                    }

                    if (pushState && url !== window.location.href) {
                        window.history.pushState({ path: url }, '', url);
                    }

                    if (shiftAttr !== null) {
                        const newShiftClosed = shiftAttr === 'true';
                        if (newShiftClosed && !window.IS_SHIFT_CLOSED) {
                            window.IS_SHIFT_CLOSED = true;
                            showClosedPopup();
                        } else if (!newShiftClosed && window.IS_SHIFT_CLOSED) {
                            window.IS_SHIFT_CLOSED = false;
                            Swal.close();
                        }
                    }
                })
                .catch(() => {
                    window.location.href = url;
                });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const themeIcon = document.getElementById('themeToggleIcon');
            const updateTheme = (theme) => {
                let activeTheme = theme;
                if (theme === 'auto') {
                    activeTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }

                if (activeTheme === 'dark') {
                    document.body.classList.add('theme-dark');
                    document.body.classList.remove('theme-flat');
                    if (themeIcon) themeIcon.setAttribute('data-feather', 'moon');
                } else if (theme === 'flat') {
                    document.body.classList.remove('theme-dark');
                    document.body.classList.add('theme-flat');
                    if (themeIcon) themeIcon.setAttribute('data-feather', 'droplet');
                } else {
                    document.body.classList.remove('theme-dark');
                    document.body.classList.remove('theme-flat');
                    if (themeIcon) themeIcon.setAttribute('data-feather', 'sun');
                }

                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
            };

            window.setTheme = (theme) => {
                localStorage.setItem('app-theme', theme);
                updateTheme(theme);
            };

            updateTheme(localStorage.getItem('app-theme') || 'light');
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                if ((localStorage.getItem('app-theme') || 'light') === 'auto') {
                    updateTheme('auto');
                }
            });

            initQueueTooltips();

            if (window.IS_SHIFT_CLOSED) {
                showClosedPopup();
            }

            const closeShiftBtn = document.getElementById('close-shift-btn');
            if (closeShiftBtn) {
                closeShiftBtn.addEventListener('click', () => {
                    Swal.fire({
                        title: 'ยืนยัน Offline ?',
                        text: 'คุณต้องการปิดระบบให้บริการใช่หรือไม่ หน้าจอจะถูก Freeze ทันที',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#e81500',
                        cancelButtonColor: '#69707a',
                        confirmButtonText: 'ใช่, Offline เลย',
                        cancelButtonText: 'ยกเลิก',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            toggleShift(true);
                        }
                    });
                });
            }

            const getInfoBtn = document.getElementById('get-info-btn');
            if (getInfoBtn) {
                getInfoBtn.addEventListener('click', () => {
                    const safeIp = escapeHtml(window.CLIENT_IP);
                    const safeHost = escapeHtml(window.CLIENT_HOSTNAME);
                    Swal.fire({
                        title: 'รายละเอียดเครื่อง',
                        html: `
                            <div class="text-start mt-3 p-3 rounded border bg-light text-dark" style="font-size: 1.05rem;">
                                <p class="mb-2"><i class="fas fa-network-wired me-2 text-primary"></i><strong>IP Address:</strong> <span class="ms-2">${safeIp}</span></p>
                                <p class="mb-0"><i class="fas fa-desktop me-2 text-primary"></i><strong>ชื่อเครื่อง:</strong> <span class="ms-2">${safeHost}</span></p>
                            </div>
                        `,
                        icon: 'info',
                        confirmButtonColor: '#0061f2',
                        confirmButtonText: 'ปิด',
                    });
                });
            }

            const loginBtn = document.getElementById('login-btn');
            if (loginBtn) {
                loginBtn.addEventListener('click', () => {
                    Swal.fire({
                        title: 'Login',
                        html:
                            '<input id="swal-input-username" class="swal2-input" placeholder="Username">' +
                            '<input id="swal-input-password" class="swal2-input" placeholder="Password" type="password">',
                        focusConfirm: false,
                        showCancelButton: true,
                        confirmButtonText: 'Login',
                        preConfirm: () => {
                            const username = document.getElementById('swal-input-username').value;
                            const password = document.getElementById('swal-input-password').value;
                            if (!username || !password) {
                                Swal.showValidationMessage('กรุณากรอก Username และ Password');
                            }
                            return { username, password };
                        },
                    }).then((result) => {
                        if (result.isConfirmed) {
                            apiFetch(window.QUEUE_ROUTES.loginMember, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(result.value),
                            })
                                .then((res) => res.json())
                                .then((response) => {
                                    if (response.success) {
                                        window.location.reload();
                                    } else {
                                        Swal.fire('Error', response.error || 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', 'error');
                                    }
                                })
                                .catch(() => Swal.fire('Error', 'Connection Error', 'error'));
                        }
                    });
                });
            }

            const logoutBtn = document.getElementById('logout-btn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', () => {
                    Swal.fire({
                        title: 'ยืนยัน Logout',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#e81500',
                        confirmButtonText: 'Logout',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            apiFetch(window.QUEUE_ROUTES.logoutMember, { method: 'POST' })
                                .then((res) => res.json())
                                .then((response) => {
                                    if (response.success) {
                                        window.location.reload();
                                    }
                                })
                                .catch(() => Swal.fire('Error', 'Connection Error', 'error'));
                        }
                    });
                });
            }

            document.addEventListener('click', (e) => {
                const anchor = e.target.closest('a');
                if (!anchor) return;
                const href = anchor.getAttribute('href');
                if (!href) return;
                if (href.startsWith('?') || href.startsWith(window.location.pathname)) {
                    if (anchor.getAttribute('target') === '_blank' || anchor.classList.contains('no-seamless')) {
                        return;
                    }
                    e.preventDefault();
                    seamlessReload(href, true);
                }
            });

            document.addEventListener('submit', (e) => {
                const form = e.target;
                if (!form || (form.getAttribute('method') || '').toUpperCase() !== 'GET') return;
                e.preventDefault();

                let action = form.getAttribute('action') || window.location.pathname;
                let hash = '';
                const hashIndex = action.indexOf('#');
                if (hashIndex !== -1) {
                    hash = action.substring(hashIndex);
                    action = action.substring(0, hashIndex);
                }
                const params = new URLSearchParams(new FormData(form)).toString();
                const separator = action.includes('?') ? '&' : '?';
                const fullUrl = `${action}${separator}${params}${hash}`.replace('?&', '?');
                seamlessReload(fullUrl, true);
            });

            window.addEventListener('popstate', () => {
                seamlessReload(window.location.href, false);
            });
        });
    </script>
    @yield('extra_js')
</body>
</html>
