@props([
    'item',
    'title',
    'icon',
    'subtitle',
    'borderColor' => 'info',
    'titleColor' => 'primary',
    'numberColor' => 'primary',
    'badgeClass' => 'badge-queue-active',
    'badgeText' => 'กำลังดำเนินการ',
    'emptyTitle' => 'ว่าง',
    'emptySubtitle' => 'รอเรียกคิวถัดไป',
    'extraCardClass' => '',
    'isStaffMember' => false,
    'showUrgent' => false,
])

<div class="card current-queue-card {{ $extraCardClass }} card-soft shadow border-start border-4 border-{{ $borderColor }} h-100">
    <div class="card-header py-3 bg-white border-0">
        <h5 class="m-0 fw-bold text-{{ $titleColor }}"><i class="fas {{ $icon }} me-2"></i> {{ $title }}</h5>
    </div>
    <div class="card-body text-center py-4 px-4 position-relative">
        <p class="mb-2 queue-card-subtitle">{{ $subtitle }}</p>

        @if($item)
            @if($item->operator_name)
                <div class="position-absolute" style="top: 15px; right: 20px;">
                    <span class="badge bg-light text-{{ $titleColor }} shadow-sm border px-3 py-2" style="font-size: 0.88rem; font-weight: 600;">
                        <i class="fas fa-user-circle me-1"></i> {{ $item->operator_name }}
                    </span>
                </div>
            @endif

            <div class="queue-number-large text-{{ $numberColor }}">{{ $item->queue_number }}</div>
            <h2 class="queue-requester-name">{{ $item->user_name }}</h2>
            <p class="queue-issue-summary mb-3">
                <span class="fw-semibold">{{ $item->user_department }}</span> -
                <span data-bs-toggle="tooltip" data-bs-placement="top" data-bs-html="true" title="{!! nl2br(e($item->issue_description)) !!}">
                    {{ \Illuminate\Support\Str::limit($item->issue_description, 75) }}
                </span>
            </p>

            <div class="mb-2">
                <span class="badge {{ $badgeClass }} rounded-pill px-4 py-2" style="font-size: 1.15rem;">{{ $badgeText }}</span>

                @if($isStaffMember || $item->comment || $item->bms_note)
                    <button
                        type="button"
                        class="btn btn-sm {{ ($item->comment || $item->bms_note) ? 'btn-info text-white' : 'btn-outline-secondary' }} rounded-circle ms-2 desc-modal-trigger"
                        data-id="{{ $item->id }}"
                        data-comment="{{ $item->comment ?? '' }}"
                        data-bms-note="{{ $item->bms_note ?? '' }}"
                        title="บันทึกหมายเหตุ"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        style="width: 32px; height: 32px;">
                        <i class="fas {{ ($item->comment || $item->bms_note) ? 'fa-sticky-note' : 'fa-plus' }}"></i>
                    </button>
                @endif

                @if($showUrgent)
                    @if($isStaffMember)
                        <span
                            class="urgent-icon-btn {{ $item->is_urgent === 1 ? 'urgent-active' : 'urgent-inactive' }}"
                            onclick="toggleUrgent({{ $item->id }}, this)"
                            title="Mark as Urgent"
                            data-bs-toggle="tooltip">🚨</span>
                    @elseif($item->is_urgent === 1)
                        <span class="urgent-icon-btn urgent-active" style="cursor: default;" title="Urgent">🚨</span>
                    @endif
                @endif
            </div>
        @else
            <div class="queue-number-large text-gray-300">-</div>
            <h2 class="fw-bold text-gray-500">{{ $emptyTitle }}</h2>
            <p class="text-muted">{{ $emptySubtitle }}</p>
        @endif
    </div>
</div>
