@if($errors->any())
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="ri-file-info-line me-2"></i>
        {{ $errors->first() }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif