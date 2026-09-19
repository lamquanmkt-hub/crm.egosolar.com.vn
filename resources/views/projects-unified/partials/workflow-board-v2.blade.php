@php
    $workflowDefinitions = $workflow['definitions'] ?? [];
    $workflowSteps = $workflow['steps'] ?? [];
@endphp

<section class="wf2-board" aria-label="Quy trình quản lý công trình 7 bước">
    @foreach($workflowDefinitions as $workflowCode => $workflowDefinition)
        @php
            $workflowStep = $workflowSteps[$workflowCode] ?? null;
            $workflowStatus = (string) ($workflowStep['status'] ?? 'not_assigned');
            $workflowStateClass = match ($workflowStatus) {
                'approved' => 'is-approved',
                'submitted' => 'is-submitted',
                'revision' => 'is-revision',
                'in_progress' => 'is-progress',
                'assigned' => 'is-assigned',
                default => 'is-pending',
            };
            $workflowStateClass .= !empty($workflowStep['is_selected']) ? ' is-selected' : '';
            $workflowStateClass .= empty($workflowStep['is_unlocked']) ? ' is-locked' : '';
            $workflowHref = route('projects-unified.show', [
                'site' => $site->id,
                'step' => $workflowCode,
            ]).'#workflow';
        @endphp

        <a class="wf2-board-step {{ $workflowStateClass }}"
           href="{{ $workflowHref }}"
           style="--wf2-tone: var(--wf2-{{ $workflowDefinition['tone'] ?? 'blue' }});">
            <span class="wf2-board-number">
                @if(empty($workflowStep['is_unlocked']))
                    <i class="bi bi-lock-fill"></i>
                @elseif($workflowStatus === 'approved')
                    <i class="bi bi-check-lg"></i>
                @else
                    {{ $workflowDefinition['sequence'] ?? $loop->iteration }}
                @endif
            </span>

            <span class="wf2-board-copy">
                <strong>{{ $workflowDefinition['short'] ?? $workflowDefinition['label'] ?? $workflowCode }}</strong>
                <small>{{ $workflowStep['status_label'] ?? 'Chưa giao việc' }}</small>
                @if(!empty($workflowStep['due_at']))
                    <em class="{{ !empty($workflowStep['overdue_days']) ? 'is-overdue' : '' }}">
                        Hạn {{ $workflowStep['due_at']->format('d/m/Y H:i') }}
                    </em>
                @else
                    <em>Chưa đặt hạn</em>
                @endif
            </span>
        </a>
    @endforeach
</section>
