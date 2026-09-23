{{-- Shared print footer. Fixed position so it repeats on every printed page in Chrome. --}}
@props(['note' => null])
<div class="ey-print-footer">
    <span>Eyana &copy; {{ now()->format('Y') }}{{ $note ? ' — '.$note : '' }}</span>
    <span>{{ now()->format('Y-m-d H:i') }}</span>
</div>
