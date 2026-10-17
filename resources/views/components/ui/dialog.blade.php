@props(['id', 'title'])
<dialog id="{{ $id }}" {{ $attributes->class(['dialog']) }} aria-labelledby="{{ $id }}-title"><div class="dialog-header"><h2 id="{{ $id }}-title" class="text-lg font-semibold">{{ $title }}</h2><x-ui.button variant="ghost" data-close-dialog :aria-label="'Tutup '.$title"><x-icon name="close" /></x-ui.button></div>{{ $slot }}</dialog>
