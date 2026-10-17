@props(['title' => null])
<section {{ $attributes->class(['panel']) }}>@if($title)<div class="panel-heading"><h2 class="panel-title">{{ $title }}</h2></div>@endif<div class="panel-body">{{ $slot }}</div></section>
