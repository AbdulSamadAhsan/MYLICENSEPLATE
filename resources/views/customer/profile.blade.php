@extends('layout')
@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <img src="https://i.pravatar.cc/100" class="rounded-circle me-3" width="80" height="80"
                                alt="Profile">
                            <div>
                                <h4 class="mb-0">{{ Auth::user()->name }}</h4>
                                <small class="text-muted">{{ Auth::user()->email }}</small>

                                <p> <small class="text-muted">{{ Auth::user()->mobile }}</small>

                            </div>
                        </div>
                        <hr>
                        <h5>My Plates</h5>
                        <h6>My Total Plates Searched: {{ $myplates->count() }}</h6>
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <form action="{{ url('profile') }}" method="GET" class="d-flex justify-content-end">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="">Start With</label>
                                            <input type="text" name="start_with" class="form-control"
                                                placeholder="Start with" value="{{ request('start_with') }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="">Contain</label>
                                            <input type="text" name="contain" class="form-control" placeholder="Contain"
                                                value="{{ request('contain') }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="">End With</label>
                                            <input type="text" name="end_with" class="form-control"
                                                placeholder="End with" value="{{ request('end_with') }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="">Character Length</label>
                                            <input type="number" name="length" class="form-control" placeholder="Length"
                                                value="{{ request('length') }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="">Max Price</label>
                                            <input type="number" name="max_price" class="form-control"
                                                placeholder="Max Price" value="{{ request('max_price') }}">
                                        </div>
                                        <div class="col-md-6">

                                            <label for="">Min Price</label>
                                            <input type="number" name="min_price" class="form-control"
                                                placeholder="Min Price" value="{{ request('min_price') }}">
                                        </div>

                                        <div class="col-md-6">
                                            <label for="province" class="form-label">Province</label>
                                            <select name="region" id="province" class="form-select mt-1">

                                                <option value="">Select Province</option>
                                                @foreach ($regions as $region)
                                                    <option value="{{ $region->region }}"
                                                        {{ request('region') == $region->region ? 'selected' : '' }}>
                                                        {{ $region->region }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="province" class="form-label">City</label>
                                            <select name="city" id="city" class="form-select mt-1">
                                                   <option value="">Select City</option>
                                                @foreach ($cities as $city)
                                                    @if ($city->city != '')
                                                        <option value="{{ $city->city }}"
                                                            {{ request('city') == $city->city ? 'selected' : '' }}>
                                                            {{ $city->city }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="province" class="form-label">Status</label>
                                            <select name="status" class="form-select mt-1">
                                                <option value="">Select Status</option>
                                                @foreach (['Available', 'Pending', 'Sold'] as $status)
                                                    <option value="{{ $status }}"
                                                        {{ request('status') == $status ? 'selected' : '' }}>
                                                        {{ $status }}</option>
                                                @endforeach


                                            </select>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label"></label>
                                            <button type="submit" class="btn btn-primary w-100 mt-1">Search</button>

                                        </div>

                                    </div>



                                </form>
                                @if (request()->has('start_with') ||
                                        request()->has('contain') ||
                                        request()->has('end_with') ||
                                        request()->has('length') ||
                                        request()->has('max_price') ||
                                        request()->has('region') ||
                                        request()->has('city') ||
                                        request()->has('status') ||
                                        request()->has('min_price'))
                                    <p class="mb-0">
                                        @if (request('city') != '' ||
                                                request('region') != '' ||
                                                request('min_price') != '' ||
                                                request('max_price') != '' ||
                                                request('length') != '' ||
                                                request('end_with') != '' ||
                                                request('contain') != '' ||
                                                request('end_with') || 
                                                   request('status')!=""
                                                )
                                            Current search:
                                        @endif
                                        @if (request()->has('start_with') && request('start_with') != '')
                                            <span class="btn-simple btn py-0 px-2">#start_with :
                                                {{ request('start_with') }}</span>
                                        @endif
                                        @if (request()->has('contain') && request('contain') != '')
                                            <span class="btn-simple btn py-0 px-2">#contain :
                                                {{ request('contain') }}</span>
                                        @endif
                                        @if (request()->has('end_with') && request('end_with') != '')
                                            <span class="btn-simple btn py-0 px-2">#end_with :
                                                {{ request('end_with') }}</span>
                                        @endif
                                        @if (request()->has('length') && request('length') != '')
                                            <span class="btn-simple btn py-0 px-2">#length : {{ request('length') }}</span>
                                        @endif
                                        @if (request()->has('max_price') && request('max_price') != '')
                                            <span class="btn-simple btn py-0 px-2">#max_price :
                                                {{ request('max_price') }}</span>
                                        @endif
                                        @if (request()->has('min_price') && request('min_price') != '')
                                            <span class="btn-simple btn py-0 px-2">#min_price :
                                                {{ request('min_price') }}</span>
                                        @endif
                                        @if (request()->has('region') && request('region') != '')
                                            <span class="btn-simple btn py-0 px-2">#province :
                                                {{ request('region') }}</span>
                                        @endif

                                        @if (request()->has('city') && request('city') != '')
                                            <span class="btn-simple btn py-0 px-2">#city : {{ request('city') }}</span>
                                        @endif
                                        @if (request()->has('status') && request('status') != '')
                                            <span class="btn-simple btn py-0 px-2">#status : {{ request('status') }}</span>
                                        @endif
                                        @if (request('city') != '' ||
                                                request('region') != '' ||
                                                request('min_price') != '' ||
                                                request('max_price') != '' ||
                                                request('length') != '' ||
                                                request('end_with') != '' ||
                                                request('contain') != '' ||
                                                request('end_with') != '' ||
                                                request('status')!=""
                                                
                                                )
                                            <a class="btn-danger btn py-0 px-2" href="{{ url('profile') }}"><i
                                                    class="fa fa-trash" aria-hidden="true"></i>Cancel</a>
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>

                        <table class="table table-striped table-bordered">
                            <button class="m-1 btn btn-outline-danger" id="showSelected">Delete All</button>
                            <button class="m-1 btn btn-outline-primary" id="editAll">Edit All</button>
                            <button class="m-1 btn btn-outline-primary" id="viewAll">View All</button>
                            <a href="{{ route('plates.import.form') }}" class="m-1 btn btn-outline-primary">Import Plates
                                PDF</a>
                            <a href="{{ route('profile.edit') }}" class="m-1 btn btn-outline-primary">Edit Profile</a>
                            <a href="{{ route('plates.import') }}" class="m-1 btn btn-outline-primary">Import Plates
                                CSV</a>
                            <a href="{{ url('/plates/add/multiple') }}" class="m-1 btn btn-success">Add Multiple
                                Plates</a>
                            <a href="{{ url('/plates/add') }}" class="btn btn-success m-1">Add New Plate</a>

                            <a href="{{ url('/plates/mypdf') }}" class="btn btn-success m-1">Export Latest 100 Plate
                                PDF</a>
                            <a href="{{ url('/plates/mycsv') }}" class="btn btn-success m-1">Export Latest 100 Plate
                                CSV</a>

                            <a href="{{route('plates.upload')}}" class="btn btn-success m-1">Create From Image</a>

 <a href="{{ url('/importPlates') }}" class="btn btn-success m-1"> Import Plate</a>

                            <thead>
                                <tr>
                                    <th><input type="checkbox" class="form-check-input" id="selectAllPlates"></th>
                                    <th>Plate Number</th>
                                    <th>City</th>
                                    <th>Region</th>
                                    <th>Status</th>
                                    <th>Price</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($myplates as $plate)
                                    <tr>
                                        <td><input type="checkbox" class="form-check-input" name="plates[]"
                                                value="{{ $plate->id }}"></td>
                                        <td>{{ $plate->plate_number }}</td>
                                        <td>{{ $plate->city }}</td>
                                        <td>{{ $plate->region }}</td>
                                        <td>
                                            @if ($plate->status == 'Sold')
                                                <span class="badge bg-danger rounded-pill">Sold</span>
                                            @elseif($plate->status == 'Pending')
                                                <span class="badge bg-warning text-dark rounded-pill">Pending</span>
                                            @else
                                                <span class="badge bg-success rounded-pill">Available</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary rounded-pill">{{ $plate->price }} PKR</span>
                                        </td>
                                        <td>
                                            <a href="{{ url('plates/' . $plate->id . '/show') }}"
                                                class="btn btn-sm btn-outline-primary">View</a>
                                            <a href="{{ url('plates/' . $plate->id . '/edit') }}"
                                                class="btn btn-sm btn-outline-primary">Edit</a>
                                            <a href="{{ url('plates/' . $plate->id . '/delete') }}"
                                                class="btn btn-sm btn-outline-danger">Delete</a>
                                        </td>
                                    </tr>
                                @endforeach

                            </tbody>
                        </table>

                        <div class="mt-4 text-end">
                            {{ $myplates->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAllPlates');
            const plateCheckboxes = document.querySelectorAll('input[name="plates[]"]');
            const showSelectedBtn = document.getElementById('showSelected');
            const editAllBtn = document.getElementById('editAll');
            const viewAllBtn = document.getElementById('viewAll');
            // Select All toggle
            selectAllCheckbox.addEventListener('change', function() {
                plateCheckboxes.forEach(checkbox => {
                    checkbox.checked = selectAllCheckbox.checked;
                });
            });

            // Sync "Select All" when individual checkboxes change
            plateCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (!this.checked) {
                        selectAllCheckbox.checked = false;
                    } else {
                        const allChecked = Array.from(plateCheckboxes).every(cb => cb.checked);
                        selectAllCheckbox.checked = allChecked;
                    }
                });
            });
            showSelectedBtn.addEventListener('click', () => {
                const checkedCheckboxes = document.querySelectorAll('input[name="plates[]"]:checked');
                const checkedValues = Array.from(checkedCheckboxes).map(cb => cb.value);

                if (checkedValues.length === 0) {
                    alert('No plates selected.');
                    return;
                }

                // Send AJAX request with selected plates
                const token = document.querySelector('input[name="_token"]').value;
                checkedCheckboxes.forEach(checkbox => {
                    const row = checkbox.closest('tr'); // Adjust if not using <tr>
                    if (row) row.remove();
                });

                // Optionally, update the "Select All" checkbox state
                const remainingCheckboxes = document.querySelectorAll(
                    'input[name="plates[]"]');
                if (remainingCheckboxes.length === 0) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.disabled = true;
                }
                alert(token);
                fetch('{{ url('plates/ajaxProcess') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,

                        },
                        body: JSON.stringify({
                            plates: checkedValues
                        }),
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log(data);
                        window.location.reload();
                    })
                    .catch(error => {
                        responseDiv.innerHTML = `<p style="color:red;">Request failed. Try again.</p>`;
                        console.error('Error:', error);
                    });

                alert('Selected plates: ' + checkedValues.join(', '));
            });

            editAllBtn.addEventListener('click', () => {
                const checkedValues = Array.from(document.querySelectorAll(
                        'input[name="plates[]"]:checked'))
                    .map(cb => cb.value);

                if (checkedValues.length === 0) {
                    alert('No plates selected.');
                    return;
                }

                // Build query param: plates[]=id1&plates[]=id2 ...
                const query = `plates=${checkedValues.map(id => encodeURIComponent(id)).join(',')}`;

                // Redirect to new route with query params
                window.location.href = `/plates/summary?${query}`;
            }); // Show values of checked plates on button click
            viewAllBtn.addEventListener('click', () => {
                const checkedValues = Array.from(document.querySelectorAll(
                        'input[name="plates[]"]:checked'))
                    .map(cb => cb.value);

                if (checkedValues.length === 0) {
                    alert('No plates selected.');
                    return;
                }

                // Build query param: plates[]=id1&plates[]=id2 ...
                const query = `plates=${checkedValues.map(id => encodeURIComponent(id)).join(',')}`;

                // Redirect to new route with query params
                window.location.href = `/plates/views?${query}`;
            });
        });
    </script>
    <script>
        const cityOptions = {
            'Punjab': ['Lahore', 'Faisalabad', 'Multan', 'Rawalpindi', 'Gujranwala', 'Sialkot', 'Bahawalpur',
                'Sargodha'
            ],
            'Balochistan': ['Quetta', 'Khuzdar', 'Turbat', 'Zhob', 'Gwadar', 'Loralai', 'Sibi'],
            'Sindh': ['Karachi', 'Hyderabad', 'Sukkur', 'Mirpurkhas', 'Larkana', 'Nawabshah', 'Thatta'],
            'KPK': ['Peshawar', 'Abbottabad', 'Mardan', 'Swat', 'Bannu', 'Dera Ismail Khan', 'Charsadda'],
            'Islamabad': ['Islamabad']
        };

        const provinceSelect = document.getElementById('province');
        const citySelect = document.getElementById('city');

        const oldProvince = "{{ old('region') }}";
        const oldCity = "{{ old('city') }}";

        function populateCities(province, selectedCity = null) {
            const cities = cityOptions[province] || [];
            citySelect.innerHTML = '<option value="">Select City</option>';
            cities.forEach(function(city) {
                const option = document.createElement('option');
                option.value = city;
                option.text = city;
                if (city === selectedCity) {
                    option.selected = true;
                }
                citySelect.appendChild(option);
            });
        }

        // Auto-select old values on load
        if (oldProvince) {
            populateCities(oldProvince, oldCity);
        }

        // Change handler
        provinceSelect.addEventListener('change', function() {
            populateCities(this.value);
        });
    </script>
@endsection
