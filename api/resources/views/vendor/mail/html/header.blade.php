<a href="{{ $url }}" style="display: inline-block;">
    @php
        $logoUrl = config('app.url') . '/images/logo-IDM.png';
    @endphp
    <img src="{{ $logoUrl }}" class="logo" alt="{{ config('app.name') }} Logo" style="display: block; max-width: 200px; width: 100%; height: auto; border: none; padding-top: 1rem; padding-bottom: 1rem;">
</a>