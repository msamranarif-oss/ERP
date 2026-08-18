{{-- Shared logo partial: renders company logo if available, else company initials --}}
@if(!empty($company['logo_base64']))
    <img src="{{ $company['logo_base64'] }}"
         style="max-height:64px; max-width:180px; object-fit:contain; display:block;"
         alt="{{ $company['name'] ?? 'Logo' }}">
@else
    {{-- Fallback: text initials badge --}}
    <div style="width:56px; height:56px; border-radius:6px; background:#1e3a8a;
                display:flex; align-items:center; justify-content:center;
                font-size:18px; font-weight:bold; color:#fff; line-height:56px; text-align:center;">
        {{ strtoupper(substr($company['name'] ?? 'E', 0, 1)) }}
    </div>
@endif
