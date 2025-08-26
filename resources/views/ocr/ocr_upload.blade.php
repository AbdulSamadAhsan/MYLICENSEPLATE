@extends('layout')

@section('content')
<div class="container mt-5">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0">Upload Plate Image for OCR</h3>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <form action="{{ route('plates.ocr') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="plate_image" class="form-label">Select Plate Image</label>
                    <input type="file" class="form-control @error('file') is-invalid @enderror" 
                           id="plate_image" name="file" accept="image/*" required>
                    @error('plate_image')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="fa fa-upload"></i> Upload & Read Plate
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
