{{--
    Shared flash/validation message partial.
    Historically, controllers reused Laravel's error bag as a generic
    success-message channel via Redirect::back()->withErrors(['msg' => ...]).
    Real FormRequest validation failures populate the same $errors bag but
    keyed by actual field names. We can tell them apart by checking for the
    'msg' key specifically, without any backend change.
--}}
@if($errors->has('msg'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="ri-checkbox-circle-line me-2"></i>{{ $errors->first('msg') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@elseif($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ri-error-warning-line me-2"></i>{{ $errors->first() }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
