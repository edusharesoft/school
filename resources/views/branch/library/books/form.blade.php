@extends('layouts.branch')
@section('title', $book->exists ? __('Edit book') : __('Add book'))

@section('content')
<x-page-header :title="$book->exists ? __('Edit book') : __('Add book')" :breadcrumbs="[__('Books') => route('branch.library.books.index'), ($book->exists ? __('Edit') : __('New')) => null]" />

<form method="POST" class="card" style="max-width: 960px" action="{{ $book->exists ? route('branch.library.books.update', $book) : route('branch.library.books.store') }}">
    @csrf @if($book->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.input name="title" :label="__('Title')" :value="$book->title" required maxlength="191" />
        <x-form.input name="title_bn" :label="__('Title (Bangla)')" :value="$book->title_bn" maxlength="191" />
        <x-form.input name="authors" :label="__('Authors')" :value="$book->authors" maxlength="255" />
        <x-form.input name="publisher" :label="__('Publisher')" :value="$book->publisher" maxlength="150" />
        <x-form.input name="isbn" :label="__('ISBN')" :value="$book->isbn" maxlength="20" col="col-md-4" :help="__('ISBN-10 or ISBN-13, checked for validity')" />
        <x-form.input name="edition" :label="__('Edition')" :value="$book->edition" maxlength="50" col="col-md-4" />
        <x-form.input name="published_year" type="number" :label="__('Year')" :value="$book->published_year" col="col-md-2" min="1000" />
        <x-form.input name="pages" type="number" :label="__('Pages')" :value="$book->pages" col="col-md-2" min="1" />
        <x-form.select name="library_category_id" :label="__('Category')" :options="$categories" :value="$book->library_category_id" :placeholder="__('— None —')" col="col-md-4" />
        <x-form.select name="language" :label="__('Language')" :options="config('library.languages')" :value="$book->language" required col="col-md-4" />
        <x-form.input name="price" type="number" step="0.01" min="0" :label="__('Price (৳)')" :value="$book->price" col="col-md-2" />
        <x-form.input name="shelf" :label="__('Shelf / rack')" :value="$book->shelf" maxlength="50" col="col-md-2" />
        <div class="col-12"><label class="form-label" for="description">{{ __('Description') }}</label><textarea id="description" name="description" rows="3" maxlength="3000" class="form-control">{{ old('description', $book->description) }}</textarea></div>
        @unless($book->exists)
            <x-form.input name="copies" type="number" min="0" max="200" :label="__('Copies to add now')" value="1" col="col-md-4" :help="__('Each copy gets its own accession number and barcode.')" />
            <x-form.input name="copy_price" type="number" step="0.01" min="0" :label="__('Price per copy (if different)')" col="col-md-4" />
        @endunless
        <x-form.switch name="is_active" :label="__('Available for lending')" :checked="$book->exists ? $book->is_active : true" />
    </div>
    <x-form.actions :cancel="route('branch.library.books.index')" />
</form>
@endsection
