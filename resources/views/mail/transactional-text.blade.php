PAIRfect Paws

{{ $heading }}

@foreach($lines as $line)
{{ $line }}

@endforeach
@if($actionText && $actionUrl)
{{ $actionText }}:
@php echo $actionUrl; @endphp

@endif
PAIRfect Paws - This is an automated message.
