<?php

use App\Http\Controllers\AuthController;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Models\LicensePlate;
use App\Models\City;
use App\Models\Region;

Route::redirect('/', 'licenseplate');


Route::get("register",[AuthController::class,"index"]);
Route::post("register",[AuthController::class,"store"])->name("register");
Route::get("login", function () {
    return view('login');
});
Route::get("profile", function () {
    Auth::check() ? Auth::user() : abort(403, 'Unauthorized action.');
   $query = LicensePlate::query();

        // Filter: Start with
        if (request()->filled('city')) {
            $query->where('city', '=', request()->city);
        }
        if (request()->filled('status')) {
            $query->where('status', '=', request()->status);
        }

        if (request()->filled('start_with')) {
            $query->where('plate_number', 'like', request()->start_with . '%');
        }
        if (request()->filled('region')) {
            $query->where('region', '=', request()->region);
        }


        if (request()->filled('contain')) {
            $query->where('plate_number', 'like', '%' . request()->contain . '%');
        }

        if (request()->filled('end_with')) {
            $query->where('plate_number', 'like', '%' . request()->end_with);
        }

        if (request()->filled('length')) {
            $length = (int) request()->length;
            $query->whereRaw("LENGTH(REPLACE(REPLACE(plate_number, ' ', ''), '-', '')) = ?", [$length]);
        }
        if (request()->filled('min_price')) {
            $query->where('price', '>=', request()->min_price);
        }

        // Filter by max price
        if (request()->filled('max_price')) {
            $query->where('price', '<=', request()->max_price);
        }


        // Filter: Contain

        $cities = LicensePlate::select('city')
            ->whereNotNull('city')
            ->distinct()
            ->get();

        $regions = LicensePlate::select('region')
            ->whereNotNull('region')
            ->distinct()
            ->get();

        // Get the filtered plates
       // Ensure only plates of the authenticated user are fetched

      $myplates = $query->
        where('user_id', Auth::id())->
        paginate(1000);







    return view('customer.profile', compact('myplates','cities', 'regions'));
})->name("profile");
Route::get("platesold", function () {
    Auth::check() ? Auth::user() : abort(403, 'Unauthorized action.');


$myplates = LicensePlate::where('user_id', Auth::id())
->where('status', 'Sold')
->get();





    return view('customer.mysold', compact('myplates'));
});
Route::post("login",[AuthController::class,'login'])->name("login");
Route::get('/plates/add', function () {
$regions = Region::get();
    Auth::check() ? Auth::user() : abort(403, 'Unauthorized action.');
    return view('customer.add_plate', compact('regions'));
})->name('plates.create');
Route::get('getCities',[App\Http\Controllers\LicenseplateController::class, 'getCities'])->name('plates.getCities') ;
Route::get('/profile/edit', function () {
    return view('customer.edit_profile');
})->name('profile.edit');
Route::put('/profile/update', [AuthController::class, 'update'])->name('profile.update');
Route::post('plates_add', [App\Http\Controllers\LicenseplateController::class, 'store'])->name('plates.store');
Route::post('plates/add/multiple', [App\Http\Controllers\LicenseplateController::class, 'multistore'])->name('multiplates.store');
Route::post('/plates/ajaxProcess', [App\Http\Controllers\LicenseplateController::class, 'ajaxProcess'])->name('plates.ajaxProcess');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('licenseplate', [App\Http\Controllers\LicenseplateController::class, 'index']);
Route::get('plates/export', [App\Http\Controllers\LicenseplateController::class, 'export'])->name('plates.export');
Route::get('plates/import', [App\Http\Controllers\LicenseplateController::class, 'import'])->name('plates.import');
Route::post('plates/import', [App\Http\Controllers\LicenseplateController::class, 'importStore'])->name('plates.import.store');
Route::get('/plates/export_pdf', [App\Http\Controllers\LicenseplateController::class, 'exportPdf'])->name('plates.export.pdf');
Route::get('plates/add/multiple', function () {
    return view('customer.add_multiple_plate');
})->name('plates.add.multiple');
Route::get('/plates/mypdf', [App\Http\Controllers\LicenseplateController::class, 'myPdf'])->name('plates.my.pdf');
Route::get('/plates/mycsv', [App\Http\Controllers\LicenseplateController::class, 'myCsv'])->name('plates.my.csv');
Route::get('/plates/export/pdf', [App\Http\Controllers\LicenseplateController::class, 'exportPdf']);
Route::get('/plates/{plate}/download-image', [App\Http\Controllers\LicensePlateController::class, 'downloadImage'])
     ->name('plates.download-image');

Route::get('platesupload', [App\Http\Controllers\LicensePlateController::class, 'showOcrForm'])->name('plates.upload');
Route::post('platesocr', [App\Http\Controllers\LicensePlateController::class, 'ocrStore'])->name('plates.ocr');

Route::get('/plates/import/form', [App\Http\Controllers\LicenseplateController::class, 'importPDFForm'])->name('plates.import.form');
Route::post('/plates/import-pdf', [App\Http\Controllers\LicenseplateController::class, 'importPDF'])->name('plates.importPDF');
Route::post('plates/import', [App\Http\Controllers\LicenseplateController::class, 'importStore'])->name('plates.import.store');
Route::get('plates/{plate}/show', [App\Http\Controllers\LicenseplateController::class, 'show'])->name('plates.show');
Route::get('plates/{id}/edit', [App\Http\Controllers\LicenseplateController::class, 'edit'])->name('items.edit');
Route::put('/items/{id}', [App\Http\Controllers\LicenseplateController::class, 'update'])->name('items.update');
Route::get('/plates/summary', [App\Http\Controllers\LicenseplateController::class, 'summary'])->name('plates.summary');
Route::post('/updateMultiple', [App\Http\Controllers\LicenseplateController::class, 'updateMultiple']);
Route::get('plates/{id}/delete', [App\Http\Controllers\LicenseplateController::class, 'delete']);
Route::get('/plates/views', [App\Http\Controllers\LicenseplateController::class, 'viewAll']);

Route::get('licenseplatedownload/{id}', [App\Http\Controllers\LicenseplateController::class, 'downloadimage']);
Route::get("challandownload/{id}",[App\Http\Controllers\LicenseplateController::class, 'downloadChallan']);
Route::view('forgotpassword', 'forget')->name('forgotpassword');
Route::post('forget', [App\Http\Controllers\ForgetPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('password/reset/{token}', [App\Http\Controllers\ForgetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('reset', [App\Http\Controllers\ForgetPasswordController::class, 'reset'])->name('password.update');
Route::post("admin/login",[AuthController::class,"adminLogin"])->name("admin.login.post");
Route::get("admin/login",[AuthController::class,'loginform']);
Route::get("/failedJob",function(){
return \App\Models\FailedJob::select('exception','failed_at')->get();
});
Route::get("createRemain",[App\Http\Controllers\LicenseplateController::class,"remain"]);
Route::get("admin/exportdownload",[App\Http\Controllers\LicenseplateController::class,"exportdownloadadmin"]);

Route::get("importPlates",[App\Http\Controllers\LicenseplateController::class,"importplateform"]);
Route::post("importPlates",[App\Http\Controllers\LicenseplateController::class,"importPlates"]);
Route::get("zipExtract",[App\Http\Controllers\LicenseplateController::class,"extract"]);
Route::post("zipextract",[App\Http\Controllers\LicenseplateController::class,"zipextract"]);


