@extends('layouts.librenmsv1')

@section('title', __('CEF'))

@section('content')
    <div class="container-fluid">
        <x-routing-tabs tab="cef" />

        <x-option-bar name="CEF" :options="$cef_options" :selected="$view" />

        <x-panel>
            <x-routing.cef-table :cefs="$cefs" :graphs="$view === 'graphs'" show-device />

            <x-slot:footer>
                <x-pagination-footer :paginator="$paginator" />
            </x-slot:footer>
        </x-panel>
    </div>
@endsection
