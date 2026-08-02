{{-- Bridges Laravel session flash messages to the JS toast system.
     Usage from controllers: redirect()->with('success', 'Mensaje')
     Supported keys: success, error, warning, info, status. --}}
@php
    $flashMap = ['success' => 'success', 'error' => 'error', 'warning' => 'warning', 'info' => 'info', 'status' => 'info'];
    $flashes = [];
    foreach ($flashMap as $key => $type) {
        if (session()->has($key)) {
            $flashes[] = ['type' => $type, 'message' => session($key)];
        }
    }
@endphp
@if (!empty($flashes))
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const flashes = @json($flashes);
            flashes.forEach(f => window.DM?.toast?.[f.type]?.(f.message));
        });
    </script>
    @endpush
@endif
