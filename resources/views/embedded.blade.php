{{--
    Renders the error card inside your own layout instead of as a standalone
    document. Point `janitor.views.layout` at a layout that yields a
    `content` section and this view is used automatically.
--}}
@extends(config('janitor.views.layout'))

@section('content')
    @include('janitor::partials.styles')

    <div class="jn-shell">
        @include('janitor::partials.card')
    </div>

    @include('janitor::partials.scripts')
@endsection
