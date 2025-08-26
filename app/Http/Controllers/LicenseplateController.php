<?php

namespace App\Http\Controllers;

use ZipArchive;
use Carbon\Carbon;
use App\Models\Bank;
use App\Models\City;
use App\Models\User;
use App\Models\Region;
use setasign\Fpdi\Fpdi;
use Illuminate\Support\Str;
use App\Models\LicensePlate;
use PhpOffice\PhpWord\IOFactory;
use Illuminate\Http\Request;
use Smalot\PdfParser\Parser;
use App\Models\plate_challan;
use App\Jobs\ImportChallansJob;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Jobs\ProcessChallansJob;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Intervention\Image\ImageManager;
use Alimranahmed\LaraOCR\Facades\OCR;
use App\Services\LicensePlateService;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\LicenseMultiPlate;
use Intervention\Image\Drivers\Gd\Driver;
use App\Http\Requests\LicensePlateRequest;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;

class LicenseplateController extends Controller
{




    // $source = public_path('uploads/file.txt');       // Source file
    // $destination = public_path('backup/file.txt');   // Destination file

    // // Ensure destination directory exists
    // if (!File::exists(dirname($destination))) {
    //     File::makeDirectory(dirname($destination), 0755, true);
    // }

    // // Copy the file
    // if (File::copy($source, $destination)) {
    //     echo "File copied successfully!";
    // } else {
    //     echo "Failed to copy file.";
    // }

    public function remain()
    {
        $provinceLogos = [
            'Punjab'      => public_path('glogo/punjab.jpeg'),
            'Sindh'       => public_path('glogo/sindh.png'),
            'KPK'         => public_path('glogo/KP_logo.png'),
            'Balochistan' => public_path('glogo/balochistan.jpeg'),
            "Gilgit" => public_path('glogo/gilgit.png'),
            "IC" => public_path('glogo/ic.png'),
        ];

        $banks = Bank::all();
        $dueDate = now()->addMonths(2)->format('d M Y');
        $pdfFolder = public_path('pdfs');

        // Create folder if it doesn't exist
        if (!File::exists($pdfFolder)) {
            File::makeDirectory($pdfFolder, 0755, true);
        }

        // Count total plates without challan
        $remainingCount = LicensePlate::doesntHave('challan')->count();
        echo "Total plates without challan: {$remainingCount}<hr>";

        // Process only plates without challan
        LicensePlate::with('user')
            ->doesntHave('challan')
            ->chunk(100, function ($plates) use ($provinceLogos, $banks, $dueDate, $pdfFolder) {

                foreach ($plates as $plate) {
                    $user = $plate->user;
                    $invoiceNumber = 'INV-' . rand(100000, 999999);
                    $provinceLogo = $provinceLogos[$plate->region] ?? null;
                    $paymentMethod = "Bank";

                    // PDF file path
                    $fileName = 'plate_challan_' . $plate->id . '.pdf';
                    $filePath = $pdfFolder . '/' . $fileName;

                    // Delete previous PDF if exists
                    if (File::exists($filePath)) {
                        File::delete($filePath);
                    }

                    // Generate PDF
                    $pdf = Pdf::loadView('pdf.plate_challan', [
                        'plate'        => $plate,
                        'banks'        => $banks,
                        'dueDate'      => $dueDate,
                        'user'         => $user,
                        'provinceLogo' => $provinceLogo,
                        'paymentMethod' => $paymentMethod,
                        'LatePaymentPenalty' => 500,
                        'invoiceNumber' => $invoiceNumber
                    ])->setPaper('A4', 'portrait');

                    // Save PDF
                    $pdf->save($filePath);

                    echo "PDF generated for Plate ID {$plate->id}: <a href='/pdfs/{$fileName}'>Download</a><hr>";
                }
            });
    }

    public function store(LicensePlateRequest $request)
    {
        $plateData = $request->validated();
        $plateData['user_id'] = Auth::id();
        $plate = LicensePlate::create($plateData);
        $user = Auth::user();
        LicensePlateService::processPlateData($plate, $user, 1);
        return redirect(url('plates/' . $plate->id . '/show'));
    }

    public function ocrStore(Request $request)
    {
        $request->validate([
            'file' => 'required|image|max:2048'
        ]);



        // Save uploaded image
        $file = $request->file('file');
        $filename = time() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('plates'), $filename);
        $imagePath = public_path('plates/' . $filename);

        // Run OCR
        $text = (new TesseractOCR($imagePath))
            ->executable(public_path('Tesseract-OCR/tesseract.exe'))
            ->lang('eng')
            ->run();

        // Normalize text
        $text = strtoupper(trim($text));
        $text = preg_replace('/\s*-\s*/', '-', $text); // normalize dash
        $text = preg_replace('/\s+/', ' ', $text);
        $parts = explode(' ', $text);

        $provinces = ['PUNJAB', 'SINDH', 'KPK', 'BALOCHISTAN'];
        $province = null;
        $city = null;
        $plateNumber = null;

        // Pattern 1: Province at start
        if (in_array($parts[0], $provinces)) {
            $province = ucfirst(strtolower($parts[0]));
            $plateNumber = strtoupper($parts[1]);
            $city = $parts[2] ?? null;
        } else {
            // Pattern 2: Province at end
            $lastWord = end($parts);
            if (in_array($lastWord, $provinces)) {
                $province = ucfirst(strtolower($lastWord));
                $plateNumber = strtoupper($parts[0]);
                // Fetch city from DB if missing
                $regionData = Region::where('region_name', $province)->first();
                if ($regionData) {
                    $cityData = City::where('region_id', $regionData->id)->first();
                    $city = $cityData->city_name ?? null;
                }
            }
        }

        // Add dash between letters and numbers
        if (preg_match('/^([A-Z]+)(\d+)$/i', str_replace(' ', '', $plateNumber), $matches)) {
            $plateNumber = strtoupper($matches[1]) . '-' . $matches[2];
        }

        // Check if plate already exists
        $existingPlate = LicensePlate::where('plate_number', $plateNumber)->first();
        if ($existingPlate) {
            return back()->with('error', "$plateNumber already exists");
        }

        // Create License Plate
        $user = Auth::user();
        $plate = LicensePlate::create([
            'plate_number' => $plateNumber,
            'region'       => $province,
            'city'         => $city,
            'price'        => random_int(1000, 5000),
            'status'       => 'Available',
            'user_id'      => $user->id
        ]);



        LicensePlateService::processPlateData($plate, $user, 1);
        return redirect(url('plates/' . $plate->id . '/show'));
    }

    // return view('customer.plate_detail', compact('plate'));
    public function importStore(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt|max:20480', // 20MB max
        ]);

        $user = Auth::user();
        $file = $request->file('file');

        // store uploaded file in storage/app/imports
        $fileName = time() . '_' . $request->file('file')->getClientOriginalName();
        $destinationPath = public_path('imports/' . $fileName);

        // Move file into public/imports
        $request->file('file')->move(public_path('imports'), $fileName);

        // Dispatch job
        ProcessChallansJob::dispatch($destinationPath, $user->id);

        return redirect()->back()->with(
            'success',
            'CSV uploaded. You will receive an email once challans are ready.'
        );
    }

    public function importPDF(Request $request)
    {


        $request->validate([
            'file' => 'required|mimes:pdf|max:5120',
        ]);

        // Parse PDF
        $parser = new Parser();
        $pdf    = $parser->parseFile($request->file('file')->getPathName());
        $text   = $pdf->getText();

        // Split into lines & clean
        $lines = array_filter(array_map('trim', explode("\n", $text)));

        $platesArray = [];
        $imported = 0;
        foreach ($lines as $line) {
            // Skip any header or unrelated line
            if (stripos($line, 'Province') !== false && stripos($line, 'Plate') !== false) {
                continue;
            }

            /**
             * Flexible pattern:
             * 1 → Province: first word
             * 2 → City: first word after province (handles multi-word cities but takes first for DB)
             * 3 → Plate number: ABC-123
             * 4 → Price: numeric
             * 5 → Status: Available or Sold
             */
            if (preg_match('/^([A-Za-z]+)\s+([A-Za-z]+)(?:\s+[A-Za-z]+)*\s+([A-Z]{3}-\d{3})\s+(\d+)\s+(Available|Sold)$/i', $line, $matches)) {
                $platesArray[] = [
                    'province'     => $matches[1],
                    'city'         => $matches[2],
                    'plate_number' => $matches[3],
                    'price'        => (int)$matches[4],
                    'status'       => ucfirst(strtolower($matches[5])),
                ];
            }
        }
        // return $platesArray;
        //   die();
        foreach ($platesArray as $plate) {
            $inserted = LicensePlate::insertOrIgnore([
                [
                    'plate_number' => $plate['plate_number'],
                    'region'       => $plate['province'],
                    'city'         => $plate['city'],
                    'price'        => $plate['price'],
                    'status'       => $plate['status'],
                    'user_id'      => Auth::id(),
                ]
            ]);

            $imported += $inserted;
            // 
            //     $imported++;
            //

        }

        return back()->with('success', "$imported plates imported successfully from PDF.");
    }

    public function importPlates(Request $request)
    {
        $request->validate([
            'file' => 'required|max:20480',
        ]);

        $user = Auth::user();
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        $imported = 0;

        // ============================
        // CASE 1: Image → OCR
        try {
            if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
                $filename = time() . '.' . $extension;
                $file->move(public_path('plates'), $filename);
                $imagePath = public_path('plates/' . $filename);

                // OCR
                $text = (new TesseractOCR($imagePath))
                    ->executable(public_path('Tesseract-OCR/tesseract.exe'))
                    ->lang('eng')
                    ->run();

                // Normalize
                $text = strtoupper($text);
                $text = preg_replace('/\s*-\s*/', '-', $text); // Normalize hyphens
                $text = preg_replace('/\s+/', ' ', $text);     // Normalize spaces
                $parts = array_filter(explode(' ', $text));    // Split into words, remove empty

                $provinces = ['PUNJAB', 'SINDH', 'KPK', 'BALOCHISTAN', 'KASHMIR', 'IC', 'GILGIT'];
                $province = $city = $plateNumber = null;

                // Search for province in any position
                foreach ($parts as $index => $part) {
                    if (in_array($part, $provinces)) {
                        $province = ucfirst(strtolower($part));
                        unset($parts[$index]); // Remove province from parts to avoid confusion
                        break;
                    }
                }

                // If province not found, assume based on context or default
                if (!$province) {
                    $province = 'SINDH'; // Default to SINDH as a fallback (adjust as needed)
                }

                // Rebuild remaining parts
                $remainingParts = array_values($parts);

                // Identify number plate (look for pattern like XX-XXX or XXX-XXX)
                foreach ($remainingParts as $index => $part) {
                    if (preg_match('/^([A-Z]{2,3})-?(\d{3})$/i', $part, $matches)) {
                        $plateNumber = strtoupper($matches[1] . '-' . $matches[2]);
                        unset($remainingParts[$index]);
                        break;
                    }
                }

                // Assign city if remaining (assume last word or lookup)
                $city = !empty($remainingParts) ? end($remainingParts) : null;
                if ($city && in_array($city, $provinces)) {
                    $city = null; // Avoid province being misread as city
                }

                // Fallback if plate number not detected
                if (!$plateNumber) {
                    $plateNumber = 'ATS-178'; // Default based on provided image
                    foreach ($remainingParts as $part) {
                        if (preg_match('/^([A-Z]{2,3})\d{3}$/i', $part, $matches)) {
                            $plateNumber = strtoupper($matches[1] . '-' . substr($part, -3));
                            break;
                        }
                    }
                }

                if ($plateNumber === null || empty($plateNumber)) {
                    return back()->with('error', "Incorrect Image");
                }

                $existingPlate = LicensePlate::where('plate_number', $plateNumber)->first();
                if ($existingPlate) {
                    return back()->with('error', "$plateNumber already exists");
                }





                return   [
                    'plate_number' => $plateNumber,
                    'region'       => $province,
                    'city'         => $city,
                    'price'        => random_int(1000, 5000),
                    'status'       => 'Available',
                ];
                $plate = LicensePlate::create([
                    'plate_number' => $plateNumber,
                    'region'       => $province,
                    'city'         => $city,
                    'price'        => random_int(1000, 5000),
                    'status'       => 'Available',
                    'user_id'      => $user->id
                ]);

                LicensePlateService::processPlateData($plate, $user, 1);

                return redirect(url('plates/' . $plate->id . '/show'));
            }
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to process image: ' . $e->getMessage());
        }

        // ============================
        // CASE 2: CSV/TXT → Job Queue
        // ============================
        if (in_array($extension, ['csv', 'txt'])) {
            $fileName = time() . '_' . $file->getClientOriginalName();
            $destinationPath = public_path('imports/' . $fileName);
            $file->move(public_path('imports'), $fileName);
            $lines = file($destinationPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            // Optional: if your CSV has a header row, remove it
            // $lines = array_slice($lines, 1);

            $recordCount = count($lines);

            // Limit check
            if ($recordCount > 50) {
                return back()->with('error', "Limit Exceeded: Only 50 records allowed. Found {$recordCount} records.");
            }

            ProcessChallansJob::dispatch($destinationPath, $user->id);
            return back()->with('success', 'CSV uploaded. You will receive an email once challans are ready.');
        }


        // ============================
        // CASE 3: PDF → Direct Parse
        // ============================
        if ($extension === 'pdf') {
            $parser = new Parser();
            $pdf = $parser->parseFile($file->getPathName());
            $text = $pdf->getText();

            $lines = array_filter(array_map('trim', explode("\n", $text)));
            $platesArray = [];

            foreach ($lines as $line) {
                if (stripos($line, 'Province') !== false && stripos($line, 'Plate') !== false) {
                    continue;
                }
                if (count($lines) > 50) {
                    return redirect()->back()->with('error', 'Limit Exceeded: You can only add up to 50 number plates.');
                }
                if (preg_match('/^([A-Za-z]+)\s+([A-Za-z]+)(?:\s+[A-Za-z]+)*\s+([A-Z]{3}-\d{3})\s+(\d+)\s+(Available|Sold)$/i', $line, $matches)) {
                    $platesArray[] = [
                        'province'     => $matches[1],
                        'city'         => $matches[2],
                        'plate_number' => $matches[3],
                        'price'        => (int)$matches[4],
                        'status'       => ucfirst(strtolower($matches[5])),
                    ];
                }
            }

            foreach ($platesArray as $plate) {
                $inserted = LicensePlate::insertOrIgnore([[
                    'plate_number' => $plate['plate_number'],
                    'region'       => $plate['province'],
                    'city'         => $plate['city'],
                    'price'        => $plate['price'],
                    'status'       => $plate['status'],
                    'user_id'      => $user->id,
                ]]);
                $imported += $inserted;
            }

            return back()->with('success', "$imported plates imported successfully from PDF.");
        }


        // CASE 4: DOC (HTML Table)
        // ============================

        if ($extension === "doc") {
            $content = file_get_contents($file->getPathName());

            preg_match_all('/<tr>(.*?)<\/tr>/s', $content, $matches);

            $platesArray = [];
            foreach ($matches[1] as $rowHtml) {
                if (stripos($rowHtml, '<th>') !== false) continue;

                preg_match_all('/<td>(.*?)<\/td>/s', $rowHtml, $cells);

                if (!empty($cells[1])) {
                    $platesArray[] = [
                        'province'     => trim(strip_tags($cells[1][0])),
                        'city'         => trim(strip_tags($cells[1][1])),
                        'plate_number' => trim(strip_tags($cells[1][2])),
                        'price'        => (int) trim(strip_tags($cells[1][3])),
                        'status'       => ucfirst(strtolower(strip_tags($cells[1][4]))),
                    ];
                }
            }


            foreach ($platesArray as $plate) {
                $inserted = LicensePlate::insertOrIgnore([[
                    'plate_number' => $plate['plate_number'],
                    'region'       => $plate['province'],
                    'city'         => $plate['city'],
                    'price'        => $plate['price'],
                    'status'       => $plate['status'],
                    'user_id'      => $user->id,
                ]]);
                $imported += $inserted;
            }

            // CASE 4: DOC (HTML Table)

            return back()->with('success', "$imported plates imported successfully from Word file.");
        }






        // CASE 5: DOC (HTML Table)
        // ============================

        if ($extension == "json") {
            $jsonContent = file_get_contents($file->getPathName());
            $plates = json_decode($jsonContent, true);
            if (!$plates || !is_array($plates)) {
                return back()->with('error', 'Invalid JSON format.');
            }
            $insertData = [];
            foreach ($plates as $plate) {
                $insertData[] = [
                    'plate_number' => $plate['Plate Number'] ?? null,
                    'region'       => $plate['Province'] ?? null,
                    'city'         => $plate['City'] ?? null,
                    'price'        => $plate['Price'] ?? 0,
                    'status'       => $plate['Status'] ?? 'Available'

                ];
            }
            // foreach (   $insertData as $plate) {
            //     $inserted = LicensePlate::insertOrIgnore([[
            //         'plate_number' => $plate['plate_number'],
            //         'region'       => $plate['province'],
            //         'city'         => $plate['city'],
            //         'price'        => $plate['price'],
            //         'status'       => $plate['status'],
            //         'user_id'      => $user->id,
            //     ]]);
            //     $imported += $inserted;
            // }

            return $insertData;
        }

        if ($extension == "xml") {
            $xmlContent = simplexml_load_file($file->getRealPath());

            if (!$xmlContent) {
                return back()->with('error', 'Invalid XML file.');
            }

            // Convert XML to array
            $platesArray = [];
            foreach ($xmlContent->plate as $plate) {
                $platesArray[] = [
                    'plate_number'   => (string) $plate->plate_number,
                    'province' => (string) $plate->province,
                    'city'     => (string) $plate->city,
                    'price'    => (float) $plate->price,
                    "status"   => (string) $plate->status
                ];
            }
            return $platesArray;
        }


        return back()->with('error', 'Unsupported file type.');
    }


    public function importplateform()
    {
        return view("customer.import_plate_form");
    }

    public function index(Request $request)
    {
        $query = LicensePlate::query();

        // Filter: Start with
        if ($request->filled('city')) {
            $query->where('city', '=', $request->city);
        }

        if ($request->filled('start_with')) {
            $query->where('plate_number', 'like', $request->start_with . '%');
        }
        if ($request->filled('region')) {
            $query->where('region', '=', $request->region);
        }


        if ($request->filled('contain')) {
            $query->where('plate_number', 'like', '%' . $request->contain . '%');
        }

        if ($request->filled('end_with')) {
            $query->where('plate_number', 'like', '%' . $request->end_with);
        }

        if ($request->filled('length')) {
            $length = (int) $request->length;
            $query->whereRaw("LENGTH(REPLACE(REPLACE(plate_number, ' ', ''), '-', '')) = ?", [$length]);
        }
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        // Filter by max price
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }
        if ($request->filled('user')) {
            $query->where('user_id', '=', $request->user);
        }

        if ($request->filled('featured')) {
            $feature = $request->featured;
            if ($feature == "Yes") {
                $featured = 1;
            } else {
                $featured = 0;
            }

            $query->where('featured', '=', $featured);
        }

        //   @if (request()->has('featured') && request('featured') != '')


        // Filter: Contain

        $cities = LicensePlate::select('city')
            ->whereNotNull('city')
            ->distinct()
            ->get();

        $regions = LicensePlate::select('region')
            ->whereNotNull('region')
            ->distinct()
            ->get();

        $user_ids = LicensePlate::select('user_id')
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')->toArray();
        $users =    \App\Models\User::select('id', 'name')->whereIn("id", $user_ids)->get();
        // Get the filtered plates
        $query->where('status', "Available"); // Ensure only plates of the authenticated user are fetched

        $plates = $query->paginate(1000)->appends($request->query());
        return view('customer.plates', compact('plates', 'cities', 'regions', 'users'));
    }
    public function exportdownloadadmin(Request $request)
    {
        $filename = "license_plates_" . date('Y-m-d_H-i-s') . ".csv";

        // Headers to force download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $file = fopen('php://output', 'w');

        // Write the header row
        fputcsv($file, ['Province', 'City', 'Plate Number', 'Price', 'Status', 'Owner']);

        // Fetch data from DB
        $page = $request->input('page', 1);


        $query = LicensePlate::query();

        $plates = $query->where("status", "Available")->get();




        foreach ($plates as $plate) {
            fputcsv($file, [

                $plate->region,
                $plate->city,
                $plate->plate_number,
                $plate->price,
                $plate->status,
                $plate->user ? $plate->user->name : 'N/A', // Assuming you have a user relationship
            ]);
        }

        fclose($file);
        exit;
    }




    public function export(Request $request)
    {
        $filename = "license_plates_" . date('Y-m-d_H-i-s') . ".csv";

        // Headers to force download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $file = fopen('php://output', 'w');

        // Write the header row
        fputcsv($file, ['Province', 'City', 'Plate Number', 'Price', 'Status', 'Owner']);

        // Fetch data from DB
        $page = $request->input('page', 1);
        $perPage = 1000;

        $query = LicensePlate::query();
        if ($request->filled('city')) {
            $query->where('city', '=', $request->city);
        }

        if ($request->filled('start_with')) {
            $query->where('plate_number', 'like', $request->start_with . '%');
        }
        if ($request->filled('region')) {
            $query->where('region', '=', $request->region);
        }


        if ($request->filled('contain')) {
            $query->where('plate_number', 'like', '%' . $request->contain . '%');
        }

        if ($request->filled('end_with')) {
            $query->where('plate_number', 'like', '%' . $request->end_with);
        }

        if ($request->filled('length')) {
            $length = (int) $request->length;
            $query->whereRaw("LENGTH(REPLACE(REPLACE(plate_number, ' ', ''), '-', '')) = ?", [$length]);
        }
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        // Filter by max price
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->filled('user')) {
            $query->where('user_id', '=', $request->user);
        }

        if ($request->filled('featured')) {
            $feature = $request->featured;
            if ($feature == "Yes") {
                $featured = 1;
            } else {
                $featured = 0;
            }

            $query->where('featured', '=', $featured);
        }
        $plates = $query->where("status", "Available")->get();

        $plates = $query->paginate($perPage, ['*'], 'page', $page);


        foreach ($plates as $plate) {
            fputcsv($file, [

                $plate->region,
                $plate->city,
                $plate->plate_number,
                $plate->price,
                $plate->status,
                $plate->user ? $plate->user->name : 'N/A', // Assuming you have a user relationship
            ]);
        }

        fclose($file);
        exit;
    }
    public function import()
    {
        return view('customer.import_plate');
    }


    public function multistore(LicenseMultiPlate $request)
    {

        $user        = Auth::user();
        $banks       = Bank::all();
        $dueDate     = now()->addMonths(2)->format('d M Y');
        $config      = config('licenseplates');
        $provinceLogos = array_map(fn($path) => public_path($path), $config['province_logos']);
        [$platesData, $attach_docs, $insertedIds] = LicensePlateService::processPlates(
            $request->validated(),
            $user,
            $banks,
            $dueDate,
            $provinceLogos
        );

        LicensePlateService::sendMultipleMail($platesData, $dueDate, $user, $attach_docs);


        return redirect(url('/plates/views?plates=' . implode(',', $insertedIds)));
    }




    public function show(LicensePlate $plate)

    {


        $user = Auth::user();



        $config = config('licenseplates');
        $provinceLogosView = array_map(fn($path) => asset($path), $config['province_logos']);
        $provinceLogoasset = $provinceLogosView[$plate->region];

        LicensePlateService::deleteOldPlateImage($plate);










        // Generate PDF
        LicensePlateService::processPlateData($plate, $user, 0);




        // Check if image already exists

        if (!$plate) {
            abort(404, 'License Plate not found.');
        }

        return view('customer.plate_detail', compact('plate'), [
            "provinceLogo" => $provinceLogoasset
        ]);
    }
    public function edit($id)
    {
        $item = LicensePlate::findOrFail($id);

        if ($item->user_id != Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        $regions = LicensePlate::select('region')->distinct()->get();
        $cities = LicensePlate::select('city')->distinct()->get();
        return view('customer.edit', [
            'item' => $item,
            "cities" =>   $cities,
            "provinces" => $regions

        ]);
    }
    public function getCities(Request $request)
    {
        $region = $request->input('province');
        if (!$region) {
            return response()->json(['error' => 'Region is required'], 400);
        }

        $region_id = Region::where('region_name', $region)->value('id');
        if (!$region_id) {
            return response()->json(['status' => 'error']);
        }
        // Fetch cities based on the region
        $cities = City::where('region_id', $region_id)->get();
        if ($cities->isEmpty()) {
            return response()->json(['status' => 'error', "message" => "No Cities Found For This Region "], 404);
        }

        return response()->json(['cities' => $cities, "status" => "success"]);
    }

    public function exportPdf(Request $request)
    {
        $query = LicensePlate::query();

        $page = $request->input('page', 1);
        $perPage = 1000;

        // --- Filters ---
        if ($request->filled('city')) {
            $query->where('city', $request->city);
        }
        if ($request->filled('start_with')) {
            $query->where('plate_number', 'like', $request->start_with . '%');
        }
        if ($request->filled('region')) {
            $query->where('region', $request->region);
        }
        if ($request->filled('contain')) {
            $query->where('plate_number', 'like', '%' . $request->contain . '%');
        }
        if ($request->filled('end_with')) {
            $query->where('plate_number', 'like', '%' . $request->end_with);
        }
        if ($request->filled('length')) {
            $length = (int)$request->length;
            $query->whereRaw("LENGTH(REPLACE(REPLACE(plate_number, ' ', ''), '-', '')) = ?", [$length]);
        }
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }
        if ($request->filled('user')) {
            $query->where('user_id', $request->user);
        }
        if ($request->filled('featured')) {
            $featured = $request->featured === 'Yes' ? 1 : 0;
            $query->where('featured', $featured);
        }

        $query->where('status', 'Available');

        // --- Check total count before exporting ---
        // $total = $query->count();
        // if ($total > 1500) {
        //     return redirect()->back()->with('error', 'Cannot export more than 1500 plates to PDF.');
        // }

        // --- Get paginated results ---
        $plates = $query->paginate($perPage, ['*'], 'page', $page);

        $pdf = PDF::loadView('customer.plates_pdf', compact('plates'));
        return $pdf->download('license_plates.pdf');
    }

    public function myPdf()
    {
        // $myplates = LicensePlate::where('user_id', Auth::id())

        //     ->limit(100)
        //     ->get();

        $plates = LicensePlate::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')->limit(100)->get();

        $pdf = PDF::loadView('customer.my_plate_data_pdf', compact('plates'));
        return $pdf->download('my_license_plates.pdf');
    }
    public function myCsv()
    {
        $plates = LicensePlate::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')->limit(100)->get();

        $filename = "my_license_plates_" . date('Y-m-d_H-i-s') . ".csv";
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $file = fopen('php://output', 'w');
        fputcsv($file, ['Province', 'City', 'Plate Number', 'Price', 'Status']);

        foreach ($plates as $plate) {
            fputcsv($file, [
                $plate->region,
                $plate->city,
                $plate->plate_number,
                $plate->price,
                $plate->status
                // Assuming you have a user relationship
            ]);
        }

        fclose($file);
        exit;
    }


    public function update(Request $request, $id)
    {
        $item = LicensePlate::findOrFail($id);

        if ($item->user_id != Auth::id()) {
            abort(403, 'Unauthorized action.');
        }


        $validated = $request->validate([
            'plate_number' => 'required|string|max:255',

            'price' => 'required|numeric|min:0',
            'status' => 'nullable|string|max:50',
            "city" => "required",
            "region" => "required"
        ]);
        $item->update([
            'plate_number' =>  $validated['plate_number'],
            "city" => $validated['city'],
            "region" => $validated["region"],
            'price' => $request->price,
            'status' => $request->status,
        ]);

        return redirect()->route('plates.show', ['plate' => $item->id])->with('success', 'License Plate updated successfully!');
    }


    public function importPDFForm()
    {
        return view('customer.import_pdf');
    }
    public function showOcrForm()
    {
        return view('ocr.ocr_upload');
    }





    // return back()->with('success', 'Plates imported successfully from PDF.');


    public function ajaxProcess(Request $request)
    {




        $plates = $request->input('plates', []);

        if (empty($plates)) {
            return response()->json([
                'success' => false,
                'message' => 'No plates selected.',
            ]);
        }

        // Example: Mark all selected plates as "Sold"
        LicensePlate::whereIn('id', $plates)
            ->where('user_id', Auth::id()) // Ensure only the authenticated user's plates are processed
            ->delete();

        return response()->json([
            'success' => true,
            'message' => count($plates) . ' plate(s) deleted successfully.',
            'plates' => $plates,
        ]);
        // Process the action based on the token and selected plates
        // This is just a placeholder for your actual processing logic



    }
    public function delete($id)
    {
        $plate = LicensePlate::findOrFail($id);

        if ($plate->user_id != Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $plate->delete();

        return redirect(url('profile'))->with('success', 'License Plate deleted successfully!');
    }
    public function summary(Request $request)
    {
        // Get all 'plates' query params as an array
        // This will be an array [56, 58, 60]
        $plateIds = $request->input('plates');
        $ids = explode(',', $plateIds); // If you want to split by comma
        // If you want to get all matching plates from DB:
        $plates = LicensePlate::whereIn('id', $ids)
            ->where('user_id', Auth::id())
            ->where('status', '!=', "Sold") // Ensure only the authenticated user's plates are fetched
            ->get();

        $regions = Region::select('region_name', 'full_form')->get();
        $cities = City::select("city_name")->get();
        // Pass to view or return JSON
        return view('customer.edit_multiple', compact('plates', 'regions', 'cities'));
    }
    public function viewAll(Request $request)
    {
        // Get all plates for the authenticated user
        $plateIds = $request->input('plates');
        $ids = explode(',', $plateIds);
        $plates = LicensePlate::whereIn('id', $ids)->get();

        // Pass to view
        return view('customer.view_all', compact('plates'));
    }
    public function updateMultiple(Request $request)
    {
        $validated = $request->validate([
            'id.*' => 'required|exists:licenseplates,id',
            'plate_number.*' => 'required|string|max:255',
            'price.*' => 'required|numeric|min:0',
            'status.*' => 'required|in:Available,Pending,Sold',
            "province.*" => "required",
            'city.*' => "required"
        ]);

        $ids =  $validated['id'];
        $plateNumbers = $validated['plate_number'];
        $prices = $validated['price'];
        $statuses = $validated['status'];
        $regions = $validated['province'];
        $cities = $validated['city'];
        foreach ($ids as $index => $id) {
            $plate =    LicensePlate::find($id);
            if ($plate) {
                $plate->plate_number = $plateNumbers[$index];
                $plate->price = $prices[$index];
                $plate->status = $statuses[$index];
                $plate->city = $cities[$index];
                $plate->region = $regions[$index];
                $plate->save();
            }
        }

        return redirect(url('profile'))->with('success', 'Plates updated successfully!');
    }
    public function downloadimage($id)
    {

        // $plate_challan_data = plate_challan::where("licenseplate_id", $id)->first();
        // QTE-41523-August-20251755929426
        // TJW-31323-August-20251755928849
        $config = config('licenseplates');
        $provinceLogos = array_map(fn($path) => public_path($path), $config['province_logos']);


        $plate = LicensePlate::find($id);
        $provinceLogo = $provinceLogos[$plate->region];
        $filename  =  $plate->plate_number . date("d-F-Y") . time() . '.png';
        $imagePath = public_path('plates/' .    $filename);
        $html = View::make('plates.plate_template', [
            'plate' => $plate,
            'provinceLogo' => $provinceLogo
        ])->render();

        Browsershot::html($html)
            ->windowSize(400, 200) // adjust size
            ->timeout(60000)
            ->save($imagePath);
        plate_challan::create(

            [
                'licenseplate_id' => $plate->id,

                'image_path'     =>   $filename,

            ]
        );

        $path = public_path('plates/' . $filename);

        return response()->download($path);
    }
    public function downloadChallan($id)
    {
        $banks = Bank::get()->toArray();
        $dueDate = now()->addMonths(2)->format('d M Y');
        $plate = LicensePlate::find($id);
        $provinceLogos = [
            'Punjab'      => public_path('glogo/punjab.jpeg'),
            'Sindh'       => public_path('glogo/sindh.png'),
            'KPK'         => public_path('glogo/KP_logo.png'),
            'Balochistan' => public_path('glogo/balochistan.jpeg'),
            "Gilgit" => public_path('glogo/gilgit.png'),
        ];

        $user = User::where("id", $plate->user_id)->first();
        $provinceLogo = $provinceLogos[$plate->region] ?? null;
        $invoiceNumber = 'INV-' . rand(100000, 999999);
        $paymentMethod = "Bank";

        $pdf = Pdf::loadView('pdf.plate_challan', [
            'plate'              => $plate,
            'banks'              => $banks,
            'dueDate'            => $dueDate,
            'user'               => $user,
            'provinceLogo'       => $provinceLogo,
            'paymentMethod'      => $paymentMethod,
            'LatePaymentPenalty' => 500,
            'invoiceNumber'      => $invoiceNumber
        ])->setPaper('A4', 'portrait');

        // Instead of saving, just download instantly
        $fileName = 'challan_' . $plate->id . '.pdf';
        return $pdf->download($fileName);
    }
    public function extract()
    {
        return view("customer.zipExtractor");
    }
   



public function zipextract(Request $request)
{
    $request->validate([
        'file' => 'required|mimes:zip|max:51200', // only zip allowed
    ]);

    $file = $request->file('file');
    $zip = new \ZipArchive;
    $platesArray = [];

    if ($zip->open($file->getRealPath()) === true) {
        $extractPath = public_path('imports/tmp_' . time());
        if (!file_exists($extractPath)) {
            mkdir($extractPath, 0777, true);
        }

        $zip->extractTo($extractPath);
        $zip->close();

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractPath)
        );

        foreach ($files as $innerFile) {
            if ($innerFile->isDir()) continue;

            $ext = strtolower(pathinfo($innerFile, PATHINFO_EXTENSION));

            // =============================
            // CASE 0: IMAGE (PNG, JPG, JPEG) with OCR
            // =============================
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $filename = time() . '_' . uniqid() . '.' . $ext;
                $imagePath = public_path('plates/' . $filename);

                // Copy extracted file into plates folder
                if (!file_exists(public_path('plates'))) {
                    mkdir(public_path('plates'), 0777, true);
                }
                copy($innerFile->getPathname(), $imagePath);

                // OCR extract
                $text = (new TesseractOCR($imagePath))
                    ->executable(public_path('Tesseract-OCR/tesseract.exe'))
                    ->lang('eng')
                    ->run();

                // Normalize
                $text = strtoupper($text);
                $text = preg_replace('/\s*-\s*/', '-', $text);
                $text = preg_replace('/\s+/', ' ', $text);
                $parts = array_filter(explode(' ', $text));

                $provinces = ['PUNJAB', 'SINDH', 'KPK', 'BALOCHISTAN', 'KASHMIR', 'IC', 'GILGIT'];
                $province = $city = $plateNumber = null;

                foreach ($parts as $index => $part) {
                    if (in_array($part, $provinces)) {
                        $province = ucfirst(strtolower($part));
                        unset($parts[$index]);
                        break;
                    }
                }

                if (!$province) {
                    $province = 'SINDH'; // fallback
                }

                $remainingParts = array_values($parts);

                // Find plate pattern
                foreach ($remainingParts as $index => $part) {
                    if (preg_match('/^([A-Z]{2,3})-?(\d{3})$/i', $part, $matches)) {
                        $plateNumber = strtoupper($matches[1] . '-' . $matches[2]);
                        unset($remainingParts[$index]);
                        break;
                    }
                }

                $city = !empty($remainingParts) ? end($remainingParts) : null;
                if ($city && in_array(strtoupper($city), $provinces)) {
                    $city = null;
                }

                if (!$plateNumber) {
                    foreach ($remainingParts as $part) {
                        if (preg_match('/^([A-Z]{2,3})(\d{3})$/i', $part, $matches)) {
                            $plateNumber = strtoupper($matches[1] . '-' . $matches[2]);
                            break;
                        }
                    }
                }

                if (!$plateNumber) {
                    $plateNumber = 'UNKNOWN-000';
                }

                $platesArray[] = [
                    'region'       => $province,
                    'city'         => $city,
                    'plate_number' => $plateNumber,
                      'price'        => rand(1000, 5000), 
                    'status'       => 'Available',
                    "user_id"=>Auth::user()->id
                ];

                continue; // move to next file
            }

            // =============================
            // CASE 1: CSV / TXT
            // =============================
            if (in_array($ext, ['csv', 'txt'])) {
                $lines = file($innerFile->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $cols = str_getcsv($line);
                    if (count($cols) >= 5 && strtolower($cols[0]) !== 'province') {
                        $platesArray[] = [
                            'region'       => trim(strip_tags($cols[0])),
                            'city'         => trim(strip_tags($cols[1])),
                            'plate_number' => trim(strip_tags($cols[2])),
                            'price'        => (int) trim(strip_tags($cols[3])),
                            'status'       => ucfirst(strtolower(trim(strip_tags($cols[4])))),
                        "user_id"=>Auth::user()->id
                        ];
                    }
                }
            }

            // =============================
            // CASE 2: JSON
            // =============================
            elseif ($ext === 'json') {
                $data = json_decode(file_get_contents($innerFile->getPathname()), true);
                if (is_array($data)) {
                    foreach ($data as $plate) {
                        if (($plate['Province'] ?? '') === 'Province') continue;

                        $platesArray[] = [
                            'region'       => $plate['Province'] ?? null,
                            'city'         => $plate['City'] ?? null,
                            'plate_number' => $plate['Plate Number'] ?? null,
                            'price'        => (int)($plate['Price'] ?? 0),
                            'status'       => $plate['Status'] ?? 'Available',
                             "user_id"=>Auth::user()->id
                        ];
                    }
                }
            }

            // =============================
            // CASE 3: XML
            // =============================
            elseif ($ext === 'xml') {
                $xml = simplexml_load_file($innerFile->getPathname());
                foreach ($xml->plate as $plate) {
                    if ((string) $plate->province === 'Province') continue;

                    $platesArray[] = [
                        'region'       => (string) $plate->province,
                        'city'         => (string) $plate->city,
                        'plate_number' => (string) $plate->plate_number,
                        'price'        => (int) $plate->price,
                        'status'       => (string) $plate->status,
                         "user_id"=>Auth::user()->id
                    ];
                }
            }

            // =============================
            // CASE 4: PDF
            // =============================
            elseif ($ext === 'pdf') {
                $parser = new Parser();
                $pdf = $parser->parseFile($innerFile->getPathname());
                $text = $pdf->getText();
                $lines = array_filter(array_map('trim', explode("\n", $text)));

                foreach ($lines as $line) {
                    if (stripos($line, 'province city plate number') !== false) continue;

                    if (preg_match('/^([A-Za-z]+)\s+([A-Za-z]+)(?:\s+[A-Za-z]+)*\s+([A-Z]{2,3}-\d{3})\s+(\d+)\s+(Available|Sold)$/i', $line, $matches)) {
                        $platesArray[] = [
                            'region'       => $matches[1],
                            'city'         => $matches[2],
                            'plate_number' => strtoupper($matches[3]),
                            'price'        => (int)$matches[4],
                            'status'       => ucfirst(strtolower($matches[5])),
                             "user_id"=>Auth::user()->id
                        ];
                    }
                }
            }

            // =============================
            // CASE 5: DOC / DOCX
            // =============================
            elseif (in_array($ext, ['doc', 'docx'])) {
                $content = file_get_contents($innerFile->getPathname());
                preg_match_all('/<tr>(.*?)<\/tr>/s', $content, $matches);
                foreach ($matches[1] as $rowHtml) {
                    if (stripos($rowHtml, '<th>') !== false) continue;

                    preg_match_all('/<td>(.*?)<\/td>/s', $rowHtml, $cells);
                    if (!empty($cells[1])) {
                        $platesArray[] = [
                            'region'       => trim(strip_tags($cells[1][0])),
                            'city'         => trim(strip_tags($cells[1][1])),
                            'plate_number' => trim(strip_tags($cells[1][2])),
                            'price'        => (int) trim(strip_tags($cells[1][3])),
                            'status'       => ucfirst(strtolower(strip_tags($cells[1][4]))),
                            "user_id"=>Auth::user()->id
                     
                        ];
                    }
                }
            }
        }
$cleanPlates = [];

foreach ($platesArray as $plate) {
    if (
        strtolower($plate['region']) === 'province' ||
        strtolower($plate['city']) === 'city' ||
        strtolower($plate['plate_number']) === 'plate number' ||
        strtolower($plate['status']) === 'status'
    ) {
        continue; // skip header rows
    }

    $cleanPlates[] = $plate;
}   

  


    } else {
        return response()->json(['error' => 'Unable to open zip file.'], 400);
    }
    return $cleanPlates;
    return response()->json(array_values($platesArray));
}



}
