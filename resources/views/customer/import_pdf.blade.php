@extends('layout')

@section('content')
    <div class="container mt-5">
        <h2>Import Plates PDF</h2>
        @error('pdf_file')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
            <a href="{{ url('licennseplate') }}" class="btn btn-outline-secondary">Plates</a>
        @endif
       
        <form action="{{ route('plates.importPDF') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label for="csv_file" class="form-label">Select PDF file</label>

                <input type="file" name="file" id="csv_file" class="form-control">
                <small class="form-text text-muted">Please upload a valid PDF file containing license plates.</small>
            </div>
            <button type="submit" class="btn btn-primary">Import CSV</button>
        </form>
    </div>
@endsection
