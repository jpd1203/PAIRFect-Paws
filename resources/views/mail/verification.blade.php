<x-mail.layout heading="Welcome to PAIRfect Paws!" action-text="Verify Email Address" :action-url="$actionUrl" :show-fallback="true">
    <p style="margin:0 0 14px;">Please verify your email address to submit adoption applications and use protected account features.</p>
    <p style="margin:0 0 14px;">This verification link expires in {{ $expiresIn }} minutes.</p>
    <p style="margin:0;">If you did not create this account, you can ignore this email.</p>
</x-mail.layout>
