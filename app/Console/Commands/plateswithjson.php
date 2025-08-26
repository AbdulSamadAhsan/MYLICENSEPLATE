<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Region;
use League\Csv\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\File;
use ZipArchive;
use Illuminate\Support\Facades\Mail;
use App\Models\User;

class PlatesWithJson extends Command
{
    protected $signature = 'plates:json {--count= : Number of plates per province (optional)}';
    protected $description = 'Generate license plates CSV, PDFs, PNG images, JSON, and Word per province';

    public function handle()
    {
        $startTime = microtime(true);

        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', 0);

        $provinces = Region::with('cities')->get();
        $letters = range('A', 'T');

        $exportBaseFolder = public_path('exports_new');
        $exportFolders = [
            'csv'  => $exportBaseFolder . '/csv',
            'pdf'  => $exportBaseFolder . '/pdf',
            'png'  => $exportBaseFolder . '/png',
            'json' => $exportBaseFolder . '/json',
            'word' => $exportBaseFolder . '/word',
        ];

        foreach ($exportFolders as $folder) {
            if (File::exists($folder)) {
                File::deleteDirectory($folder); // Delete folder with all contents
            }
            File::makeDirectory($folder, 0777, true); // Recreate empty folder
        }
        $config = config('licenseplates');
        $provinceLogos = array_map(fn($path) => public_path($path), $config['province_logos'] ?? []);

        $totalImages = 0;
        $allPlates = [];
        $count = $this->option('count') ? (int) $this->option('count') : 100;

        foreach ($provinces as $province) {
            $this->info("Generating {$count} plates for {$province->region_name}...");

            $usedPlates = [];
            $cities = $province->cities->pluck('id', 'city_name')->toArray();
            $provincePlates = [];
            $provinceImages = 0;

            // --- Generate Plates ---
            for ($i = 0; $i < $count; $i++) {
                $cityName = array_rand($cities);

                do {
                    $plateNumber = $letters[array_rand($letters)]
                        . $letters[array_rand($letters)]
                        . $letters[array_rand($letters)]
                        . '-'
                        . rand(100, 999);
                } while (in_array($plateNumber, $usedPlates));

                $usedPlates[] = $plateNumber;

                $row = [
                    'Province' => $province->region_name,
                    'City' => str_replace(' ', '', $cityName),
                    'Plate Number' => $plateNumber,
                    'Price' => rand(1000, 4000),
                    'Status' => 'Available'
                ];

                $provincePlates[] = $row;
                $allPlates[] = $row;
            }

            $provinceLogo = $provinceLogos[$province->region_name] ?? null;

            // --- Create province subfolders ---
            $provinceFolders = [];
            foreach ($exportFolders as $key => $folder) {
                $provinceFolder = $folder . '/' . $province->region_name;
                if (!file_exists($provinceFolder)) mkdir($provinceFolder, 0777, true);
                $provinceFolders[$key] = $provinceFolder;
            }

            // --- PNG images ---
            foreach ($provincePlates as $index => $row) {
                $html = View::make('plates.plate_templates', [
                    'plate' => $row,
                    'provinceLogo' => $provinceLogo
                ])->render();

                $fileNameImage = $row['Plate Number'] . '_' . date("d-F-Y") . time() . '.png';
                $imagePath = $provinceFolders['png'] . '/' . $fileNameImage;

                Browsershot::html($html)
                    ->windowSize(400, 200)
                    ->timeout(60000)
                    ->save($imagePath);

                $provinceImages++;
                $totalImages++;
                $completePercentage = round(($provinceImages / $count) * 100);

                $this->info("Created image {$provinceImages}/{$count} for {$province->region_name}: {$fileNameImage}");
                $this->info("{$completePercentage} % Image Generation For {$province->region_name} Completed");
            }
            $this->info("PNG images created for {$province->region_name}: {$provinceImages}");

            // --- PDF per province (chunk if >1500) ---
            $chunks = array_chunk($provincePlates, 50);
            if (count($provincePlates) > 100) {
                foreach ($chunks as $index => $chunk) {
                    $pdfPath = $provinceFolders['pdf'] . '/' . $province->region_name . '_plates_part' . ($index + 1) . '.pdf';
                    $pdf = Pdf::loadView('plates.export_pdf', ['plates' => $chunk]);
                    $pdf->save($pdfPath);
                    $this->info("Chunked PDF exported for {$province->region_name} at {$pdfPath}");
                }
            } else {
                $pdfPath = $provinceFolders['pdf'] . '/' . $province->region_name . '_plates.pdf';
                $pdf = Pdf::loadView('plates.export_pdf', ['plates' => $provincePlates]);
                $pdf->save($pdfPath);
                $this->info("PDF exported for {$province->region_name} at {$pdfPath}");
            }

            // --- CSV per province ---
            $csvPath = $provinceFolders['csv'] . '/' . $province->region_name . '_plates.csv';
            $csv = Writer::createFromPath($csvPath, 'w+');
            $csv->insertOne(array_keys($provincePlates[0]));
            foreach ($provincePlates as $row) $csv->insertOne($row);
            $this->info("CSV file created for {$province->region_name} at {$csvPath}");

            // --- JSON per province ---
            $jsonPath = $provinceFolders['json'] . '/' . $province->region_name . '_plates.json';
            file_put_contents($jsonPath, json_encode($provincePlates, JSON_PRETTY_PRINT));
            $this->info("JSON file created for {$province->region_name} at {$jsonPath}");

            // --- Word per province ---
            $wordPath = $provinceFolders['word'] . '/' . $province->region_name . '_plates.doc';
            $htmlContent = "<h2>{$province->region_name} License Plates</h2>";
            $htmlContent .= "<table border='1' cellpadding='5' cellspacing='0'><tr>";
            foreach (array_keys($provincePlates[0]) as $heading) $htmlContent .= "<th>{$heading}</th>";
            $htmlContent .= "</tr>";
            foreach ($provincePlates as $plate) {
                $htmlContent .= "<tr>";
                foreach ($plate as $cell) $htmlContent .= "<td>{$cell}</td>";
                $htmlContent .= "</tr>";
            }
            $htmlContent .= "</table>";
            file_put_contents($wordPath, $htmlContent);
            $this->info("Word file created for {$province->region_name} at {$wordPath}");
        }

        // --- Combined Files ---
        if (!empty($allPlates)) {
            $csvPathAll = $exportFolders['csv'] . '/license_plates_combined.csv';
            $csvAll = Writer::createFromPath($csvPathAll, 'w+');
            $csvAll->insertOne(array_keys($allPlates[0]));
            foreach ($allPlates as $row) $csvAll->insertOne($row);
            $this->info("Combined CSV created at {$csvPathAll}");

            $jsonPathAll = $exportFolders['json'] . '/license_plates_combined.json';
            file_put_contents($jsonPathAll, json_encode($allPlates, JSON_PRETTY_PRINT));
            $this->info("Combined JSON created at {$jsonPathAll}");

            $wordPathAll = $exportFolders['word'] . '/license_plates_combined.doc';
            $htmlContent = "<h2>All Provinces License Plates</h2>";
            $htmlContent .= "<table border='1' cellpadding='5' cellspacing='0'><tr>";
            foreach (array_keys($allPlates[0]) as $heading) $htmlContent .= "<th>{$heading}</th>";
            $htmlContent .= "</tr>";
            foreach ($allPlates as $plate) {
                $htmlContent .= "<tr>";
                foreach ($plate as $cell) $htmlContent .= "<td>{$cell}</td>";
                $htmlContent .= "</tr>";
            }
            $htmlContent .= "</table>";
            file_put_contents($wordPathAll, $htmlContent);
            $this->info("Combined Word file created at {$wordPathAll}");
            $zipPathAll = $exportBaseFolder . '/license_plates_combined.zip';
            $zip = new ZipArchive();
            if ($zip->open($zipPathAll, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                foreach ($exportFolders as $folder) {
                    $files = File::allFiles($folder);
                    foreach ($files as $file) {
                        $relativeName = basename($folder) . '/' . $file->getFilename();
                        $zip->addFile($file->getRealPath(), $relativeName);
                    }
                }
                $zip->close();
                $this->info("Combined ZIP created at {$zipPathAll}");


                
            }
        }
        
                   







             $users = User::role(['user', 'User']) // only users with these roles
            ->whereHas('licensePlates')               // must have created plates
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'admin');    // exclude admins
            })
            ->get();
            
            foreach($users as $user){
           $email=$user->email;
            $body = "Dear {$user->name},\n\nPlease find attached the latest plates export.\n\nThanks,\n" . config('app.name');
            Mail::raw( $body, function ($message) use ($email, $zipPathAll) {
                $message->to($email)
                    ->subject('Latest Plates Export')
                    ->attach($zipPathAll, [
                        'as' => 'plates_export.zip',
                        'mime' => 'application/zip',
                    ]);
            });

            $this->info("Mail Send to {$user->name} having mail : {$email}");
        }
        




        $this->info("Total PNG images created: {$totalImages}");

        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        $this->info("All exports completed in {$executionTime} seconds!");
    }
}
