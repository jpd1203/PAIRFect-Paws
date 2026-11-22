<div class="checkbox-group">
    <input type="checkbox" id="document_consent_{{ $consentId }}" name="document_consent" value="1" required>
    <label for="document_consent_{{ $consentId }}">
        I agree to the document processing terms, including sending this file to Google Cloud Vision for text extraction when enabled.
        <a class="underline" href="{{ route('study.intro') }}">How documents are handled</a>
    </label>
</div>
