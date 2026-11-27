@extends('layouts.app')

@section('extra_css')
<style>
    .urgent-icon-btn {
        font-size: 1.45rem;
        cursor: pointer;
        margin-left: 8px;
        vertical-align: middle;
        transition: all 0.25s ease;
    }
    .urgent-inactive {
        color: #ccc;
        filter: grayscale(100%);
        opacity: 0.5;
    }
    .adhoc-inactive {
        color: #ddd;
        filter: grayscale(100%);
        opacity: 0.9;
    }
    .urgent-active {
        opacity: 1;
        animation: emoji-siren-pulse 0.8s infinite alternate;
        filter: drop-shadow(0 0 5px rgba(255, 0, 0, 0.6));
    }
    @keyframes emoji-siren-pulse {
        from { transform: scale(1); filter: brightness(1); }
        to { transform: scale(1.2); filter: brightness(1.3); }
    }
</style>
@endsection

@section('content')
    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-4">
            <a href="?status=waiting" class="text-decoration-none">
                <div class="card card-stat kpi-waiting-bg text-white card-lift shadow h-100 py-2 {{ $active_filter === 'waiting' ? 'card-stat-active' : '' }}">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="small fw-bold text-uppercase mb-1 text-white">รอรับบริการ (Waiting)</div>
                                <div class="h1 mb-0 fw-bold text-white tabular-nums">{{ $waiting_count }}</div>
                            </div>
                            <div>
                                <i class="fas fa-clock fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-xl-4 col-md-4">
            <a href="?status=done" class="text-decoration-none">
                <div class="card card-stat kpi-done-bg text-white card-lift shadow h-100 py-2 {{ $active_filter === 'done' ? 'card-stat-active' : '' }}">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="small fw-bold text-uppercase mb-1 text-white">เสร็จสิ้น (Done เดือนนี้)</div>
                                <div class="h1 mb-0 fw-bold text-white tabular-nums">{{ $done_count }}</div>
                            </div>
                            <div>
                                <i class="fas fa-check-circle fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-xl-4 col-md-4">
            <a href="?status=pending" class="text-decoration-none">
                <div class="card card-stat kpi-pending-bg text-white card-lift shadow h-100 py-2 {{ $active_filter === 'pending' ? 'card-stat-active' : '' }}">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="small fw-bold text-uppercase mb-1 text-white">รอประสานงาน / อะไหล่</div>
                                <div class="h1 mb-0 fw-bold text-white tabular-nums">{{ $coordinating_count + $waiting_parts_count }}</div>
                            </div>
                            <div>
                                <i class="fas fa-exclamation-circle fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Current Queue & Ad-hoc Queue -->
    <div class="row g-3">
        <div class="{{ $current_adhoc ? 'col-lg-6' : 'col-lg-12' }} mb-4">
            @include('queue.partials.active-card', [
                'item' => $current_queue,
                'title' => 'กำลังดำเนินการ',
                'icon' => 'fa-bullhorn',
                'subtitle' => 'หมายเลขคิวปัจจุบัน',
                'borderColor' => 'info',
                'titleColor' => 'primary',
                'numberColor' => 'primary',
                'badgeClass' => 'badge-queue-active',
                'badgeText' => 'กำลังดำเนินการ',
                'isStaffMember' => $is_staff_member,
                'showUrgent' => true,
            ])
        </div>

        @if($current_adhoc)
            <div class="col-lg-6 mb-4">
                @include('queue.partials.active-card', [
                    'item' => $current_adhoc,
                    'title' => 'คิวแทรก (Ad-hoc)',
                    'icon' => 'fa-bolt',
                    'subtitle' => 'หมายเลขคิวแทรก',
                    'borderColor' => 'warning',
                    'titleColor' => 'warning',
                    'numberColor' => 'warning',
                    'badgeClass' => 'badge-queue-adhoc',
                    'badgeText' => 'กำลังดำเนินการ (แทรก)',
                    'extraCardClass' => 'adhoc-queue-card',
                    'isStaffMember' => $is_staff_member,
                    'showUrgent' => false,
                ])
            </div>
        @endif
    </div>

    <!-- Queue List Table -->
    <div class="row" id="queue-list-section">
        <div class="col-lg-12">
            <div class="card card-soft shadow mb-4">
                <div class="card-header py-3 d-flex flex-wrap align-items-center justify-content-between gap-2 bg-white">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="m-0 fw-bold text-dark"><i class="fas fa-list-ol me-2"></i> {{ $list_title }}</h5>
                        <span class="badge bg-light text-muted border fw-normal d-none d-md-inline-block">รีเฟรชอัตโนมัติ 60 วินาที</span>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        @if($is_staff_member)
                            @if($current_adhoc)
                                <button type="button" id="finish-adhoc-btn" class="btn btn-warning text-dark fw-bold shadow-sm d-flex align-items-center">
                                    <i class="fas fa-bolt me-2"></i> จบงานแทรก (Ad-hoc)
                                </button>
                            @endif

                            <button type="button" id="call-next-btn" class="btn btn-primary text-white fw-bold shadow-sm d-flex align-items-center" data-active-count="{{ $active_count }}">
                                <i class="fas fa-bullhorn me-2"></i> เรียกคิวถัดไป
                            </button>
                        @endif

                        <form class="d-flex" method="GET" action="#queue-list-section">
                            @if($active_filter)
                                <input type="hidden" name="status" value="{{ $active_filter }}">
                            @endif
                            <div class="input-group">
                                <input type="text" class="form-control bg-light-subtle-input small" placeholder="ค้นหาชื่อ, สาขา, ปัญหา, เลขคิว..." aria-label="Search" name="q" value="{{ $search_query }}">
                                <button class="btn btn-primary" type="submit" aria-label="ค้นหา">
                                    <i class="fas fa-search fa-sm"></i>
                                </button>
                            </div>
                        </form>

                        <a href="?status={{ $active_filter }}#queue-list-section" class="btn btn-outline-primary">
                            <i class="fas fa-sync-alt me-1"></i> Refresh
                        </a>
                    </div>
                </div>

                <div class="card-body p-0 table-responsive">
                    <table class="table table-striped table-hover queue-table mb-0">
                        <thead class="table-light queue-table-head">
                            <tr>
                                <th class="py-3 ps-4" style="width: 110px;">คิวที่</th>
                                <th class="py-3" style="min-width: 150px;">ผู้แจ้ง</th>
                                <th class="py-3" style="min-width: 120px;">สาขา</th>
                                <th class="py-3">ปัญหา</th>
                                <th class="py-3 text-center" style="width: 90px;">เพิ่มเติม</th>
                                <th class="py-3 text-nowrap" style="width: 115px;">เวลา</th>
                                <th class="py-3 pe-4 text-nowrap" style="width: 150px;">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($queue_list as $item)
                                @php
                                    $statusColor = $item->status->color ?? 'secondary';
                                    $badgeTextClass = in_array($statusColor, ['warning', 'light'], true) ? 'text-dark' : 'text-white';
                                @endphp
                                <tr>
                                    <td class="ps-4 fw-bold text-primary text-nowrap tabular-nums">
                                        @if($active_filter === 'waiting')
                                            <span class="fs-5">{{ $item->waiting_rank }}</span>
                                            <div class="small text-muted fw-normal" style="font-size: 0.75rem;">{{ $item->queue_number }}</div>
                                        @else
                                            {{ $item->queue_number }}
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-bold text-gray-800">{{ $item->user_name }}</div>
                                    </td>
                                    <td>{{ $item->user_department }}</td>
                                    <td data-bs-toggle="tooltip" data-bs-placement="top" data-bs-html="true" title="{!! nl2br(e($item->issue_description)) !!}">
                                        <span>{{ \Illuminate\Support\Str::limit($item->issue_description, 60) }}</span>

                                        @if($is_staff_member && ($item->status->code ?? null) === 'WAITING')
                                            <span
                                                class="urgent-icon-btn {{ $item->is_urgent === 1 ? 'urgent-active' : 'urgent-inactive' }}"
                                                onclick="toggleUrgent({{ $item->id }}, this)"
                                                title="Mark as Urgent"
                                                data-bs-toggle="tooltip">🚨</span>

                                            <span
                                                class="urgent-icon-btn {{ $item->is_adhoc === 1 ? 'text-warning' : 'adhoc-inactive' }} ms-2"
                                                style="font-size: 1.2rem;"
                                                onclick="insertQueue({{ $item->id }})"
                                                title="แทรกคิว (Ad-hoc)"
                                                data-bs-toggle="tooltip">
                                                <i class="fas fa-bolt"></i>
                                            </span>
                                        @else
                                            @if($item->is_urgent === 1)
                                                <span class="urgent-icon-btn urgent-active" style="cursor: default;" title="Urgent">🚨</span>
                                            @endif
                                            @if($item->is_adhoc === 1)
                                                <span class="urgent-icon-btn text-warning ms-2" style="font-size: 1.2rem; cursor: default;" title="รายการแทรกคิว (Ad-hoc)">
                                                    <i class="fas fa-bolt"></i>
                                                </span>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($is_staff_member || $item->comment || $item->bms_note)
                                            <button
                                                type="button"
                                                class="btn btn-sm {{ ($item->comment || $item->bms_note) ? 'btn-info text-white' : 'btn-outline-secondary' }} desc-modal-trigger"
                                                data-id="{{ $item->id }}"
                                                data-comment="{{ $item->comment ?? '' }}"
                                                data-bms-note="{{ $item->bms_note ?? '' }}"
                                                title="บันทึกหมายเหตุ"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top">
                                                <i class="fas {{ ($item->comment || $item->bms_note) ? 'fa-sticky-note' : 'fa-plus' }}"></i>
                                            </button>
                                        @endif
                                    </td>
                                    <td class="text-nowrap tabular-nums">{{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->format('d/m H:i') : '' }}</td>
                                    <td class="pe-4 text-nowrap">
                                        <span class="badge bg-{{ $statusColor }} {{ $badgeTextClass }} px-2 py-1">{{ $item->status->name ?? '' }}</span>
                                        @if($is_staff_member && $active_filter === 'pending')
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-success ms-2 py-0 px-2 rounded-pill"
                                                style="font-size: 0.8rem;"
                                                onclick="closeQueueItem({{ $item->id }})"
                                                title="เสร็จสิ้น"
                                                data-bs-toggle="tooltip">
                                                <i class="fas fa-check"></i> เสร็จสิ้น
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-gray-500">
                                        ไม่พบข้อมูลในรายการนี้
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($queue_list->hasPages())
                    <div class="card-footer bg-white py-3">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center mb-0 tabular-nums">
                                @if($queue_list->onFirstPage())
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#queue-list-section" aria-label="Previous">&laquo;</a>
                                    </li>
                                @else
                                    <li class="page-item">
                                        <a class="page-link" href="?page={{ $queue_list->currentPage() - 1 }}&status={{ $active_filter }}&q={{ urlencode($search_query) }}#queue-list-section" aria-label="Previous">&laquo;</a>
                                    </li>
                                @endif

                                @for($i = 1; $i <= $queue_list->lastPage(); $i++)
                                    @if($queue_list->currentPage() === $i)
                                        <li class="page-item active"><a class="page-link" href="#queue-list-section">{{ $i }}</a></li>
                                    @else
                                        <li class="page-item"><a class="page-link" href="?page={{ $i }}&status={{ $active_filter }}&q={{ urlencode($search_query) }}#queue-list-section">{{ $i }}</a></li>
                                    @endif
                                @endfor

                                @if($queue_list->hasMorePages())
                                    <li class="page-item">
                                        <a class="page-link" href="?page={{ $queue_list->currentPage() + 1 }}&status={{ $active_filter }}&q={{ urlencode($search_query) }}#queue-list-section" aria-label="Next">&raquo;</a>
                                    </li>
                                @else
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#queue-list-section" aria-label="Next">&raquo;</a>
                                    </li>
                                @endif
                            </ul>
                        </nav>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Description Modal -->
    <div class="modal fade" id="descModal" tabindex="-1" aria-labelledby="descModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="descModalLabel">รายละเอียดเพิ่มเติม</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modal-queue-item-id">
                    <div class="mb-3">
                        <label class="form-label" for="modal-bms-note">รายละเอียดแก้ไข / Note(BMS):</label>
                        <textarea class="form-control" id="modal-bms-note" rows="3" readonly></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="modal-desc-text">หมายเหตุ / Comment:</label>
                        <textarea class="form-control" id="modal-desc-text" rows="5"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                    <button type="button" id="modal-save-btn" class="btn btn-primary" onclick="saveDescription()">บันทึก</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('extra_js')
<script>
    function openDescModal(id, comment, bmsNote) {
        hideAllTooltips();
        document.getElementById('modal-queue-item-id').value = id;
        document.getElementById('modal-desc-text').value = comment || '';
        document.getElementById('modal-bms-note').value = bmsNote || '';

        const descField = document.getElementById('modal-desc-text');
        const saveBtn = document.getElementById('modal-save-btn');
        const titleEl = document.getElementById('descModalLabel');

        if (!window.IS_STAFF) {
            descField.disabled = true;
            saveBtn.style.display = 'none';
            titleEl.textContent = 'รายละเอียดเพิ่มเติม (Read Only)';
        } else {
            descField.disabled = false;
            saveBtn.style.display = '';
            titleEl.textContent = 'รายละเอียดเพิ่มเติม';
        }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('descModal')).show();
    }

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('.desc-modal-trigger');
        if (!trigger) return;
        openDescModal(
            trigger.getAttribute('data-id'),
            trigger.getAttribute('data-comment') || '',
            trigger.getAttribute('data-bms-note') || ''
        );
    });

    function confirmPostAction({ title, text, icon, confirmColor, cancelColor, confirmText, url, payload, failTitle }) {
        hideAllTooltips();
        Swal.fire({
            title,
            text,
            icon,
            showCancelButton: true,
            confirmButtonColor: confirmColor,
            cancelButtonColor: cancelColor,
            confirmButtonText: confirmText,
            cancelButtonText: 'ยกเลิก',
        }).then((result) => {
            if (!result.isConfirmed) return;
            apiFetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: payload ? JSON.stringify(payload) : null,
            })
                .then((res) => res.json())
                .then((response) => {
                    if (response.success) {
                        seamlessReload();
                    } else {
                        Swal.fire({
                            icon: 'warning',
                            title: failTitle,
                            text: response.error || 'Error',
                            confirmButtonText: 'ตกลง',
                        });
                    }
                })
                .catch(() => Swal.fire('Connection Error', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error'));
        });
    }

    function saveDescription() {
        const id = document.getElementById('modal-queue-item-id').value;
        const comment = document.getElementById('modal-desc-text').value;

        apiFetch(window.QUEUE_ROUTES.updateJobDesc, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, comment }),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.success) {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('descModal')).hide();
                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกข้อมูลเรียบร้อย',
                        showConfirmButton: false,
                        timer: 1500,
                    }).then(() => seamlessReload());
                } else {
                    Swal.fire('Error', response.error || 'Operation failed', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Connection Error', 'error'));
    }

    function toggleUrgent(id, el) {
        const isCurrentlyUrgent = el.classList.contains('urgent-active');
        const newState = isCurrentlyUrgent ? 0 : 1;

        apiFetch(window.QUEUE_ROUTES.toggleUrgent, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, is_urgent: newState }),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.success) {
                    el.classList.toggle('urgent-active', newState === 1);
                    el.classList.toggle('urgent-inactive', newState !== 1);
                } else {
                    Swal.fire('Error', response.error || 'Operation failed', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Connection Error', 'error'));
    }

    function insertQueue(id) {
        confirmPostAction({
            title: 'ต้องการแทรกคิวงานนี้ใช่หรือไม่?',
            text: 'งานนี้จะถูกเปลี่ยนสถานะเป็นกำลังดำเนินการทันที',
            icon: 'warning',
            confirmColor: '#f4a100',
            cancelColor: '#e81500',
            confirmText: 'ใช่, แทรกคิวเลย!',
            url: window.QUEUE_ROUTES.insertQueue,
            payload: { id },
            failTitle: 'ไม่สามารถแทรกคิวได้',
        });
    }

    function closeQueueItem(id) {
        confirmPostAction({
            title: 'ยืนยันปิดงาน?',
            text: 'คุณต้องการปิดงานคิวนี้ใช่หรือไม่',
            icon: 'question',
            confirmColor: '#00ac69',
            cancelColor: '#e81500',
            confirmText: 'ใช่, ปิดงานเลย!',
            url: window.QUEUE_ROUTES.closeQueue,
            payload: { id },
            failTitle: 'ไม่สามารถปิดงานได้',
        });
    }

    document.addEventListener('click', (e) => {
        if (e.target.closest('#call-next-btn')) {
            e.preventDefault();
            confirmPostAction({
                title: 'ยืนยันเรียกคิวถัดไป?',
                text: 'คุณต้องการปิดงานปัจจุบันและเรียกคิวถัดไปใช่หรือไม่',
                icon: 'question',
                confirmColor: '#0061f2',
                cancelColor: '#69707a',
                confirmText: 'ใช่, เรียกเลย',
                url: window.QUEUE_ROUTES.callNext,
                payload: null,
                failTitle: 'ไม่สามารถเรียกคิวถัดไปได้',
            });
            return;
        }

        if (e.target.closest('#finish-adhoc-btn')) {
            e.preventDefault();
            confirmPostAction({
                title: 'ยืนยันจบงานคิวแทรก?',
                text: 'คุณต้องการจบงานคิวแทรก (Ad-hoc) นี้ใช่หรือไม่',
                icon: 'question',
                confirmColor: '#f4a100',
                cancelColor: '#e81500',
                confirmText: 'ใช่, จบงานเลย',
                url: window.QUEUE_ROUTES.finishAdhoc,
                payload: null,
                failTitle: 'ไม่สามารถปิดงานได้',
            });
        }
    });

    if (!window.dashboardRefreshInterval) {
        window.dashboardRefreshInterval = setInterval(() => {
            if (!document.querySelector('.modal.show')) {
                seamlessReload();
            }
        }, 60000);
    }
</script>
@endsection
