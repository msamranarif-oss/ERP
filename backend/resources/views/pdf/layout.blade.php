<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    {{-- Inject the requested theme CSS (defaults to classic) --}}
    @includeFirst([
        'pdf.themes.' . ($theme ?? 'classic'),
        'pdf.themes.classic'
    ])
</head>
<body>
<div class="{{ $theme === 'modern' ? '' : 'page' }}">
    @yield('content')
</div>
<div class="footer">
    {{ $company['name'] ?? 'ERP System' }} &mdash; {{ now()->format('d M Y H:i') }}
</div>
</body>
</html>
