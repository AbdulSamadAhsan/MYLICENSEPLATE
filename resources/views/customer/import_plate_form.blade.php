@extends('layout')

@section('content')
<div class="container mt-5">
  <h2>Import Plates CSV</h2>
    @if(session('success'))
         <div class="alert alert-success">{{ session('success') }}</div>
     @elseif(session('error'))
   <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
     @error('file')
    
    <div class="alert alert-danger">{{ $message }}</div>
     @enderror
  <form action="{{ url('importPlates') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <div class="mb-3">
      <label for="csv_file" class="form-label">PNG,PDF,CSV,JSON,XML,WORD File</label>
      <input type="file" name="file" id="csv_file" class="form-control">
    </div>
    <button type="submit" class="btn btn-primary">Import CSV</button>
  </form>
</div>
@endsection