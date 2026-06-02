@php
    $markers = collect($deployMarkers ?? []);
@endphp

<div class="deploy-strip">
    <span class="deploy-strip-title">Deploy markers</span>
    @forelse($markers->take(8) as $marker)
        @php
            $commit = $marker['commit'] ?? null;
            $shortCommit = is_string($commit) && $commit !== '' ? substr($commit, 0, 7) : null;
        @endphp
        <span class="deploy-chip">
            <span class="deploy-version">{{ $marker['version'] ?? 'deploy' }}</span>
            <span class="deploy-env">{{ $marker['environment'] ?? 'env' }}</span>
            @if($shortCommit)
                <span class="deploy-commit">{{ $shortCommit }}</span>
            @endif
            <span class="deploy-time">{{ $marker['deployed_at'] ?? '' }}</span>
        </span>
    @empty
        <span class="deploy-empty">No deploys in this window</span>
    @endforelse
</div>
