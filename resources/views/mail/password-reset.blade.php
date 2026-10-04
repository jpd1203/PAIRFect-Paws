<x-mail.layout heading="Password reset requested" action-text="Reset Password" :action-url="$actionUrl" :show-fallback="true">
    <p style="margin:0 0 14px;">We received a request to reset the password for your PAIRfect Paws account.</p>
    <p style="margin:0 0 14px;">This secure link expires in {{ $expiresIn }} minutes and can only be used once.</p>
    <p style="margin:0;">If you did not request this reset, you can ignore this email. Your password will remain unchanged.</p>
</x-mail.layout>
