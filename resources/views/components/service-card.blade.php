@props(['image', 'title'])

<div class="group overflow-hidden rounded-xl border-b-4 border-maroon-500 bg-white shadow-card transition hover:-translate-y-1">
    <div class="aspect-[4/3] w-full overflow-hidden">
        <img src="{{ asset($image) }}" alt="{{ $title }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
    </div>
    <div class="p-5">
        <h3 class="text-lg font-black text-gray-900">{{ $title }}</h3>
        <p class="mt-2 text-sm leading-relaxed font-semibold text-gray-800">{{ $slot }}</p>
    </div>
</div>
