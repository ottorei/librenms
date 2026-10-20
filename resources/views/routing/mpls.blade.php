@extends('layouts.librenmsv1')

@section('title', __('MPLS'))

@section('content')
    <div class="container-fluid">
        <x-routing-tabs tab="mpls" />

        <x-option-bar name="{{ __('MPLS') }}" :options="$mpls_options" :selected="$view" />

        <x-panel>
            <x-routing.mpls-table :view="$view" :items="$items" show-device />

            <x-slot:footer>
                <x-pagination-footer :paginator="$paginator" />
            </x-slot:footer>
        </x-panel>
    </div>
@endsection
