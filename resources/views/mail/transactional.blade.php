<x-mail.layout :heading="$heading" :action-text="$actionText" :action-url="$actionUrl">
    @foreach($lines as $line)
        <p style="margin:0 0 14px;">{{ $line }}</p>
    @endforeach
</x-mail.layout>
