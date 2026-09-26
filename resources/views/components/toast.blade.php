@php
    $toast = session('toast');
    if ($toast) {
        session()->forget('toast');
    }

    $type = $toast['type'] ?? null;
    $message = $toast['message'] ?? null;
    $position = $toast['position'] ?? 'tr';
    $timeout = (int) ($toast['timeout'] ?? 3800);
    $size = $toast['size'] ?? 'lg';

    $firstError = isset($errors) && method_exists($errors, 'first') && $errors->any() ? $errors->first() : null;
    if (!$message && $firstError) {
        $message = $firstError;
        $type = $type ?: 'warning';
    }
    if (!$message && session('error')) {
        $message = session('error');
        $type = $type ?: 'error';
    }
    if (!$message && session('status')) {
        $message = session('status');
        $type = $type ?: 'success';
    }
@endphp

<div class="toast-overlay" aria-live="polite" aria-atomic="true"></div>
