@extends('layouts.librenmsv1')

@section('title', __('ISIS'))

@section('content')
    <div class="container-fluid">
        <x-routing-tabs tab="isis" />

        <x-option-bar name="{{ __('Adjacencies') }}" :options="$state_options" :selected="$state" />

        <x-panel>
            <x-routing.isis-table :adjacencies="$adjacencies" show-device />

            <x-slot:footer>
                <x-pagination-footer :paginator="$paginator" />
            </x-slot:footer>
        </x-panel>
    </div>
@endsection
