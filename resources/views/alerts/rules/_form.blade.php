@php
    $selectedChannels = old('channels', $rule['channels'] ?? []);
    $selectedChannels = is_array($selectedChannels) ? $selectedChannels : [];
    $enabled = old('enabled', ($rule['enabled'] ?? true) ? '1' : '0');
@endphp

@if(isset($errors) && $errors->any())
    <div class="feedback err"><span>{{ $errors->first() }}</span></div>
@endif

<div class="panel form-panel">
    <div class="panel-b">
        <div class="form-grid">
            <label class="form-field span-2">
                <span>Name</span>
                <input name="name" value="{{ old('name', $rule['name'] ?? '') }}" maxlength="100" required>
            </label>

            <label class="form-field">
                <span>Metric</span>
                <select name="metric" required>
                    @foreach($metrics as $value => $label)
                        <option value="{{ $value }}" @selected(old('metric', $rule['metric'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="form-field">
                <span>Condition</span>
                <select name="condition" required>
                    @foreach($conditions as $value => $label)
                        <option value="{{ $value }}" @selected(old('condition', $rule['condition'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="form-field">
                <span>Value</span>
                <input name="value" type="number" step="0.01" value="{{ old('value', $rule['value'] ?? '') }}" required>
            </label>

            <label class="form-field">
                <span>Window minutes</span>
                <input name="window_minutes" type="number" min="1" max="1440" value="{{ old('window_minutes', $rule['window_minutes'] ?? 15) }}" required>
            </label>

            <label class="form-field">
                <span>Cooldown minutes</span>
                <input name="cooldown_minutes" type="number" min="1" max="10080" value="{{ old('cooldown_minutes', $rule['cooldown_minutes'] ?? 15) }}" required>
            </label>
        </div>

        <div class="form-section">
            <div class="form-label">Channels</div>
            <div class="choice-row">
                @foreach($channels as $name => $channel)
                    <label class="choice {{ $channel['configured'] ? '' : 'muted' }}">
                        <input type="checkbox" name="channels[]" value="{{ $name }}" @checked(in_array($name, $selectedChannels, true)) @disabled(! $channel['configured'])>
                        <span>{{ $channel['label'] }}</span>
                        <em>{{ $channel['configured'] ? 'Configured' : 'Not configured' }}</em>
                    </label>
                @endforeach
            </div>
        </div>

        <label class="choice single">
            <input type="checkbox" name="enabled" value="1" @checked((string) $enabled === '1')>
            <span>Enabled</span>
        </label>
    </div>
</div>

<div class="form-actions">
    <button class="btn primary" type="submit">{{ $submitLabel }}</button>
    <a class="btn" href="{{ route('lookout.alerts') }}">Cancel</a>
</div>
