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

class UniquePlatesData extends Command
{
    protected $signature = 'plates:unique {--count= : Number of plates per province (optional)}';
    protected $description = 'Generate license plates CSV, PDFs, PNG images, JSON, and Word per province with unique data';

    public function handle()
    {
        $startTime = microtime(true);

        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', 0);

        $provinces = Region::with('cities')->get();
        $letters = range('A', 'Z');

        $exportBaseFolder = public_path('exports_new_unique');
        $exportFolders = [
            'csv'  => $exportBaseFolder . '/csv',
            'pdf'  => $exportBaseFolder . '/pdf',
            'png'  => $exportBaseFolder . '/png',
            'json' => $exportBaseFolder . '/json',
            'word' => $exportBaseFolder . '/word',
        ];

        // --- Clear old folders ---
        foreach ($exportFolders as $folder) {
            if (File::exists($folder)) {
                File::deleteDirectory($folder);
            }
            File::makeDirectory($folder, 0777, true);
        }

        $config = config('licenseplates');
        $provinceLogos = array_map(fn($path) => public_path($path), $config['province_logos'] ?? []);

        $totalImages = 0;
        $count = $this->option('count') ? (int) $this->option('count') : 100;

        foreach ($provinces as $province) {
            $this->info("Generating {$count} plates for {$province->region_name}...");

            // --- Create province subfolders ---
            $provinceFolders = [];
            foreach ($exportFolders as $key => $folder) {
                $provinceFolder = $folder . '/' . $province->region_name;
                if (!file_exists($provinceFolder)) mkdir($provinceFolder, 0777, true);
                $provinceFolders[$key] = $provinceFolder;
            }

            $provinceLogo = $provinceLogos[$province->region_name] ?? null;

            /**
             * PNG EXPORT
             */
            $platesPng = $this->generatePlates($province, $letters, $count);
            $provinceImages = 0;
            foreach ($platesPng as $row) {
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
                $this->info("{$completePercentage}% Image Generation For {$province->region_name} Completed");
            }

            /**
             * PDF EXPORT with chunking > 500
             */
            $platesPdf = $this->generatePlates($province, $letters, $count);
            if (count($platesPdf) > 500) {
                $chunks = array_chunk($platesPdf, 500);
                foreach ($chunks as $index => $chunk) {
                    $pdfPath = $provinceFolders['pdf'] . '/' . $province->region_name . '_plates_part' . ($index + 1) . '.pdf';
                    $pdf = Pdf::loadView('plates.export_pdf', ['plates' => $chunk]);
                    $pdf->save($pdfPath);
                    $this->info("Chunked PDF exported for {$province->region_name} at {$pdfPath}");
                }
            } else {
                $pdfPath = $provinceFolders['pdf'] . '/' . $province->region_name . '_plates.pdf';
                $pdf = Pdf::loadView('plates.export_pdf', ['plates' => $platesPdf]);
                $pdf->save($pdfPath);
                $this->info("PDF exported for {$province->region_name} at {$pdfPath}");
            }

            /**
             * CSV EXPORT
             */
            $platesCsv = $this->generatePlates($province, $letters, $count);
            $csvPath = $provinceFolders['csv'] . '/' . $province->region_name . '_plates.csv';
            $csv = Writer::createFromPath($csvPath, 'w+');
            $csv->insertOne(array_keys($platesCsv[0]));
            foreach ($platesCsv as $row) $csv->insertOne($row);
            $this->info("CSV file created for {$province->region_name} at {$csvPath}");

            /**
             * JSON EXPORT
             */
            $platesJson = $this->generatePlates($province, $letters, $count);
            $jsonPath = $provinceFolders['json'] . '/' . $province->region_name . '_plates.json';
            file_put_contents($jsonPath, json_encode($platesJson, JSON_PRETTY_PRINT));
            $this->info("JSON file created for {$province->region_name} at {$jsonPath}");

            /**
             * WORD EXPORT
             */
            $platesWord = $this->generatePlates($province, $letters, $count);
            $wordPath = $provinceFolders['word'] . '/' . $province->region_name . '_plates.doc';
            $htmlContent = "<h2>{$province->region_name} License Plates</h2>";
            $htmlContent .= "<table border='1' cellpadding='5' cellspacing='0'><tr>";
            foreach (array_keys($platesWord[0]) as $heading) $htmlContent .= "<th>{$heading}</th>";
            $htmlContent .= "</tr>";
            foreach ($platesWord as $plate) {
                $htmlContent .= "<tr>";
                foreach ($plate as $cell) $htmlContent .= "<td>{$cell}</td>";
                $htmlContent .= "</tr>";
            }
            $htmlContent .= "</table>";
            file_put_contents($wordPath, $htmlContent);
            $this->info("Word file created for {$province->region_name} at {$wordPath}");
        }

        /**
         * ZIP ALL EXPORTS
         */
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

        /**
         * SEND EMAIL
         */
        $users = User::role(['user', 'User'])
            ->whereHas('licensePlates')
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'admin');
            })
            ->get();

        foreach ($users as $user) {
            $email = $user->email;
            $body = "Dear {$user->name},\n\nPlease find attached the latest plates export.\n\nThanks,\n" . config('app.name');
            Mail::raw($body, function ($message) use ($email, $zipPathAll) {
                $message->to($email)
                    ->subject('Latest Plates Export')
                    ->attach($zipPathAll, [
                        'as' => 'plates_export.zip',
                        'mime' => 'application/zip',
                    ]);
            });

            $this->info("Mail sent to {$user->name} ({$email})");
        }

        $this->info("Total PNG images created: {$totalImages}");
        $executionTime = round(microtime(true) - $startTime, 2);
        $this->info("All exports completed in {$executionTime} seconds!");
    }

    /**
     * Generate unique plates for a province
     */
    private function generatePlates($province, $letters, $count)
    {
        $usedPlates = [];
        $cities = $province->cities->pluck('id', 'city_name')->toArray();
        $plates = [];

        for ($i = 0; $i < $count; $i++) {
            $cityName = array_rand($cities);

            do {
                $plateNumber = $letters[array_rand($letters)]
                    . $letters[array_rand($letters)]
                    . $letters[array_rand($letters)]
                    . '-' . rand(100, 999);
            } while (in_array($plateNumber, $usedPlates));

            $usedPlates[] = $plateNumber;

            $plates[] = [
                'Province' => $province->region_name,
                'City' => str_replace(' ', '', $cityName),
                'Plate Number' => $plateNumber,
                'Price' => rand(1000, 4000),
                'Status' => 'Available'
            ];
        }

        return $plates;
    }
}
