@props([
    'paginator',
    'options' => [50, 100, 250, -1],
])

<div class="tw:p-3 tw:flex tw:items-center tw:justify-between tw:flex-wrap tw:gap-4">
    <div class="tw:flex tw:items-center tw:gap-2 tw:text-sm">
        <label for="per_page" class="tw:whitespace-nowrap">{{ __('Per page') }}</label>
        <select id="per_page" class="form-control input-sm tw:w-auto" onchange="window.location.href = $(this).val()">
            @foreach($options as $option)
                <option value="{{ request()->fullUrlWithQuery(['per_page' => $option, 'page' => 1]) }}" {{ $paginator->perPage() == $option ? ' selected' : '' }}>{{ $option == -1 ? __('All') : $option }}</option>
            @endforeach
        </select>
    </div>
    <div>{{ $paginator->links() }}</div>
</div>
