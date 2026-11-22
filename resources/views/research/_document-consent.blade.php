<div class="checkbox-group">
    <input type="checkbox" id="document_consent_{{ $consentId }}" name="document_consent" value="1">
    <label for="document_consent_{{ $consentId }}">
        I agree to the optional document check and to sending this file to Google Cloud Vision for text extraction when available.
        <a class="underline" href="{{ route('study.intro') }}">How documents are handled</a>
    </label>
</div>
