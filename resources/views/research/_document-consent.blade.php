<p class="text-sm">Use only the example ID provided to you. Do not upload a real ID. The file is deleted after checking. PAIRfect Paws keeps the result, not the extracted text.</p>
<div class="checkbox-group">
    <input type="checkbox" id="document_consent_{{ $consentId }}" name="document_consent" value="1" required>
    <label for="document_consent_{{ $consentId }}">
        I agree to the document processing terms, including sending this file to Google Cloud Vision for text extraction when enabled.
        <a class="underline" href="{{ route('legal.privacy') }}">Privacy Notice</a>
    </label>
</div>
