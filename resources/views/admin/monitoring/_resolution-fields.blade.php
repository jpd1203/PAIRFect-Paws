@php($fieldPrefix = $fieldPrefix ?? 'resolution')
<div class="grid gap-3">
    <div>
        <label class="form-label" for="{{ $fieldPrefix }}-outcome">Resolution Outcome *</label>
        <select id="{{ $fieldPrefix }}-outcome" name="resolution_outcome" class="form-control" required data-resolution-outcome>
            <option value="">Select an outcome</option>
            @foreach (\App\Enums\ResolutionOutcome::cases() as $outcome)
                <option value="{{ $outcome->value }}" @selected(old('resolution_outcome') === $outcome->value)>{{ $outcome->label() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label" for="{{ $fieldPrefix }}-note">Resolution Note *</label>
        <textarea id="{{ $fieldPrefix }}-note" name="resolution_note" class="form-control remarks-textarea" rows="3" required maxlength="2000" placeholder="Record the action or recommendation taken...">{{ old('resolution_note') }}</textarea>
    </div>
    <fieldset data-return-fields class="hidden gap-3 rounded-lg border border-amber-300 bg-amber-50 p-3">
        <legend class="font-semibold text-amber-900">Physical return details</legend>
        <div><label class="form-label" for="{{ $fieldPrefix }}-date">Return Date *</label><input id="{{ $fieldPrefix }}-date" name="return_date" type="date" max="{{ today('Asia/Manila')->toDateString() }}" value="{{ old('return_date') }}" class="form-control" data-return-required disabled></div>
        <div><label class="form-label" for="{{ $fieldPrefix }}-reason">Return Reason *</label><textarea id="{{ $fieldPrefix }}-reason" name="return_reason" rows="2" maxlength="2000" class="form-control" data-return-required disabled>{{ old('return_reason') }}</textarea></div>
        <div><label class="form-label" for="{{ $fieldPrefix }}-condition">Condition Upon Return *</label><textarea id="{{ $fieldPrefix }}-condition" name="return_condition" rows="2" maxlength="2000" class="form-control" data-return-required disabled>{{ old('return_condition') }}</textarea></div>
        <div><label class="form-label" for="{{ $fieldPrefix }}-handler">Handled By Staff *</label><select id="{{ $fieldPrefix }}-handler" name="return_handled_by_user_id" class="form-control" data-return-required disabled><option value="">Select staff member</option>@foreach ($staffHandlers as $staffHandler)<option value="{{ $staffHandler->id }}" @selected((string) old('return_handled_by_user_id') === (string) $staffHandler->id)>{{ $staffHandler->full_name }}</option>@endforeach</select></div>
    </fieldset>
    <input type="hidden" name="confirm_return" value="" data-return-confirmation>
</div>
