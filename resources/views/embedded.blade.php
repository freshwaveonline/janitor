{{--
    Renders the error card inside your own layout instead of as a standalone
    document. Point `error-pages.views.layout` at a layout that yields a
    `content` section and this view is used automatically.
--}}
@extends(config('error-pages.views.layout'))

@section('content')
    @include('error-pages::partials.styles')

    <div class="ep-shell">
        @include('error-pages::partials.card')
    </div>

    @include('error-pages::partials.scripts')
@endsection
