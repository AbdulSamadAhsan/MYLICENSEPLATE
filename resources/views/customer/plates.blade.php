@extends('layout')

@section('content')
    <div class="container py-4">
        <h2 class="text-center mb-4">Available Plates</h2>

        <marquee behavior="" direction="">If Record Is Greater Than 1500 No PDF is Available To Export Data Use Csv
            Instead</marquee>

        <h2 class="text-center mb-4"> {{ $plates->count() }} Plates Found</h2>
        <div class="row mb-4">
            <div class="col-md-12">
                <form action="{{ url('licenseplate') }}" method="GET" class="d-flex justify-content-end">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="">Start With</label>
                            <input type="text" name="start_with" class="form-control" placeholder="Start with"
                                value="{{ request('start_with') }}">
                        </div>
                        <div class="col-md-6">
                            <label for="">Contain</label>
                            <input type="text" name="contain" class="form-control" placeholder="Contain"
                                value="{{ request('contain') }}">
                        </div>
                        <div class="col-md-6">
                            <label for="">End With</label>
                            <input type="text" name="end_with" class="form-control" placeholder="End with"
                                value="{{ request('end_with') }}">
                        </div>
                        <div class="col-md-6">
                            <label for="">Character Length</label>
                            <input type="number" name="length" class="form-control" placeholder="Length"
                                value="{{ request('length') }}">
                        </div>
                        <div class="col-md-6">
                            <label for="">Max Price</label>
                            <input type="number" name="max_price" class="form-control" placeholder="Max Price"
                                value="{{ request('max_price') }}">
                        </div>
                        <div class="col-md-6">

                            <label for="">Min Price</label>
                            <input type="number" name="min_price" class="form-control" placeholder="Min Price"
                                value="{{ request('min_price') }}">
                        </div>

                        <div class="col-md-6">
                            <label for="province" class="form-label">Province</label>
                            <select name="region" id="province" class="form-select mt-1">

                                <option value="">Select Province</option>
                                @foreach ($regions as $region)
                                    <option value="{{ $region->region }}"
                                        {{ request('region') == $region->region ? 'selected' : '' }}>
                                        @if ($region->region == 'KPK')
                                            Khyber Pakhtunkhwa
                                        @else
                                            {{ $region->region }}
                                        @endif

                                    </option>
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
                            <label for="province" class="form-label">User</label>
                            <select name="user" class="form-select mt-1">
                                <option value="">Select User</option>
                                @foreach ($users as $user)
                                    @if ($user->name != '')
                                        <option value="{{ $user->id }}"
                                            {{ request('user') == $user->id ? 'selected' : '' }}>
                                            @if (auth()->check() && auth()->id() == $user->id)
                                                Me
                                            @else
                                                {{ $user->name }}
                                            @endif
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="province" class="form-label">Featured</label>
                            <select name="featured" class="form-select mt-1">
                                <option value="">Select Feature</option>
                                @foreach (['Yes', 'No'] as $status)
                                    <option value="{{ $status }}"
                                        {{ request('featured') == $status ? 'selected' : '' }}>
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
                        request()->has('min_price') ||
                        request()->has('user') ||
                        request()->has('featured'))
                    <p class="mb-0">
                        @if (request('city') != '' ||
                                request('region') != '' ||
                                request('min_price') != '' ||
                                request('max_price') != '' ||
                                request('length') != '' ||
                                request('end_with') != '' ||
                                request('contain') != '' ||
                                request('start_with') != '' ||
                                request('user') != '' ||
                                request('featured') != '')
                            Current search:
                        @endif

                        @if (request()->has('start_with') && request('start_with') != '')
                            <span class="btn-simple btn py-0 px-2">#start_with : {{ request('start_with') }}</span>
                        @endif
                        @if (request()->has('contain') && request('contain') != '')
                            <span class="btn-simple btn py-0 px-2">#contain : {{ request('contain') }}</span>
                        @endif
                        @if (request()->has('end_with') && request('end_with') != '')
                            <span class="btn-simple btn py-0 px-2">#end_with : {{ request('end_with') }}</span>
                        @endif
                        @if (request()->has('length') && request('length') != '')
                            Current search:
                            <span class="btn-simple btn py-0 px-2">#length : {{ request('length') }}</span>
                        @endif
                        @if (request()->has('max_price') && request('max_price') != '')
                            <span class="btn-simple btn py-0 px-2">#max_price : {{ request('max_price') }}</span>
                        @endif
                        @if (request()->has('min_price') && request('min_price') != '')
                            <span class="btn-simple btn py-0 px-2">#min_price : {{ request('min_price') }}</span>
                        @endif
                        @if (request()->has('region') && request('region') != '')
                            @if (request('region') == 'KPK')
                                <span class="btn-simple btn py-0 px-2">#province : Khyber Pakhtunkhwa</span>
                            @else
                                <span class="btn-simple btn py-0 px-2">#province : {{ request('region') }}</span>
                            @endif
                        @endif

                        @if (request()->has('city') && request('city') != '')
                            <span class="btn-simple btn py-0 px-2">#city : {{ request('city') }}</span>
                        @endif


                        @if (request()->has('featured') && request('featured') != '')
                            <span class="btn-simple btn py-0 px-2">#featured : {{ request('featured') }}</span>
                        @endif

                        @if (request()->has('user') && request('user') != '')
                            @php
                                $user = \App\Models\User::select('id', 'name')->where('id', request('user'))->first();

                            @endphp

                            @if (auth()->check() && auth()->id() == $user->id)
                                <span class="btn-simple btn py-0 px-2">#user: Me</span>
                            @else
                                <span class="btn-simple btn py-0 px-2">#user: {{ $user->name }}
                                </span>
                            @endif
                        @endif
                        @if (request('city') != '' ||
                                request('region') != '' ||
                                request('min_price') != '' ||
                                request('max_price') != '' ||
                                request('length') != '' ||
                                request('end_with') != '' ||
                                request('contain') != '' ||
                                request('end_with') != '' ||
                                request('start_with') != '' ||
                                request('user') != '' ||
                                request('featured') != '')
                            <a class="btn-danger btn py-0 px-2" href="{{ url('licenseplate') }}"><i class="fa fa-trash"
                                    aria-hidden="true"></i>Cancel</a>
                        @endif
                    </p>
                @endif


            </div>

            <div class="row g-3">
                <div class="mb-3">
                    @php
                        $nonEmptyQuery = collect(request()->query())
                            ->filter(fn($value) => trim($value) !== '')
                            ->toArray();
                    @endphp

                    <a href="{{ url('plates/export?' . http_build_query($nonEmptyQuery)) }}"
                        class="btn btn-outline-secondary">Export Plates CSV</a>


                    {{-- Show export button with filtered query parameters --}}
                    <a href="{{ url('plates/export/pdf?' . http_build_query($nonEmptyQuery)) }}"
                        class="btn btn-outline-secondary">
                        Export Plates PDF
                    </a>


                </div>
                {{-- @php
                    $regions = [
                        'Punjab' => ['bg' => 'primary', 'logo' => 'punjab.jpeg'],
                        'Sindh' => ['bg' => 'warning', 'logo' => 'sindh.png'],
                        'Balochistan' => ['bg' => 'danger', 'logo' => 'balochistan.jpeg'],
                        'KPK' => ['bg' => 'info', 'logo' => 'KP_logo.png'],
                    ];
                @endphp

                @foreach ($plates as $plate)
                    @php
                       
                        $regionData = $regions[$plate->region] ?? null;

                    @endphp

                    @if ($regionData)
                        <div class="col-md-3">
                            <div
                                class="d-flex flex-column justify-content-between border border-primary bg-{{ $regionData['bg'] }} rounded p-3 shadow">
                                <img src="{{ asset('glogo/' . $regionData['logo']) }}" width="40" height="40">

                                <div class="text-center">
                                    <div class="fw-bold" style="font-size: 1rem;">
                                        @if ($plate->featured == 1)
                                            {{ $plate->regionRelation->urdu_name }}
                                        @else
                                            {{ strtoupper($plate->regionRelation->full_form) }}
                                        @endif
                                    </div>
                                    <div style="font-size: 2rem; letter-spacing: 5px; color: white;">
                                        {{ $plate->plate_number ?? 'ABC 000' }}
                                    </div>
                                </div>

                                <div class="d-flex justify-content-center mt-2">
                                    <div class="text-muted">
                                        @if ($plate->featured == 1)
                                          {{$plate->cityRelation->name_ur}}
                                        @else
                                            {{ strtoupper($plate->city) }}
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="fw-bold text-center">Owner: {{ $plate->user->name }}</div>
                            <div class="fw-bold text-center">Number: {{ $plate->user->mobile }}</div>
                            <div class="fw-bold text-center">PKR {{ $plate->price }}</div>

                            <a href="{{ url('plates/' . $plate->id . '/show') }}" class="btn btn-primary">View Detail</a>

                            @auth
                                @if ($plate->user_id != Auth::user()->id)
                                    <a href="" class="btn btn-danger">Order</a>
                                @endif
                            @endauth
                        </div>
                    @endif
                @endforeach --}}
                @foreach ($plates as $plate)
                    @switch($plate->region)
                        @case('Punjab')
                            <div class="col-md-3">
                                <x-punjab-plate :plate="$plate" />
                            </div>
                        @break

                        @case('Sindh')
                            <div class="col-md-3">
                                <x-sindh-plate :plate="$plate" />
                            </div>
                        @break

                        @case('Balochistan')
                            <div class="col-md-3">
                                <x-balochistan-plate :plate="$plate" />
                            </div>
                        @break

                        @case('KPK')
                            <div class="col-md-3">
                                <x-kpk-plate :plate="$plate" />
                            </div>
                        @break

                        @case('Gilgit')
                            <div class="col-md-3">
                                <x-gilgit-plate :plate="$plate" />
                            </div>
                        @break

                        @case('Kashmir')
                            <div class="col-md-3">
                                <x-azad-plate :plate="$plate" />
                            </div>
                        @break
                         @case('IC')
                            <div class="col-md-3">
                                <x-islamabad-plate :plate="$plate" />
                            </div>
                        @break
                    @endswitch
                @endforeach

                {{ $plates->links() }}



            </div>
        </div>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

                $.ajax({
                    url: "{{ url('getCities') }}",
                    data: {
                        province: province
                    },
                    type: 'GET',
                    success: function(cities) {
                        console.log(cities);

                        cities.cities.forEach(function(city) {
                            const option = document.createElement('option');
                            option.value = city.city_name;
                            option.text = city.city_name;
                            if (city.city_name === selectedCity) {
                                option.selected = true;
                            }
                            citySelect.appendChild(option);
                        });

                    }
                });

                console.log(province);
                citySelect.innerHTML = '<option value="">Select City</option>';

                // cities.forEach(function(city) {
                //     const option = document.createElement('option');
                //     option.value = city;
                //     option.text = city;
                //     if (city === selectedCity) {
                //         option.selected = true;
                //     }
                //     citySelect.appendChild(option);
                // });
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
