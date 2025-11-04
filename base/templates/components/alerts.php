@if(!empty($_SESSION['flash_messages']))
    @foreach($_SESSION['flash_messages'] as $type => $messages)
        @foreach($messages as $message)
            <div class="alert alert-{{ $type }} alert-dismissible fade show" role="alert">
                @if($type === 'success')
                    <i class="fas fa-check-circle"></i>
                @elseif($type === 'danger')
                    <i class="fas fa-exclamation-circle"></i>
                @elseif($type === 'warning')
                    <i class="fas fa-exclamation-triangle"></i>
                @elseif($type === 'info')
                    <i class="fas fa-info-circle"></i>
                @endif
                {{ $message }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endforeach
    @endforeach
    @php
        unset($_SESSION['flash_messages']);
    @endphp
@endif
