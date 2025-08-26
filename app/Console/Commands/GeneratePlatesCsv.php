<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Region;
use League\Csv\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

class GeneratePlatesCsv extends Command
{
    protected $signature = 'plates:csv {--count= : Number of plates per province (optional)}';
    protected $description = 'Generate license plates CSV, PDFs, and PNG images per province';

    public function handle()
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 0);

        $provinces = Region::with('cities')->get();
        $letters = range('A', 'T');

        $exportCsvFolder = public_path('exports_csv');
        $exportPdfFolder = public_path('exports_pdf');
        $exportPngFolder = public_path('plates_image');

        foreach ([$exportCsvFolder, $exportPdfFolder, $exportPngFolder] as $folder) {
            if (!file_exists($folder)) mkdir($folder, 0777, true);
        }

        if (file_exists($exportPngFolder)) {
            $filespng = glob($exportPngFolder . '/*.png'); // ✅ use $exportPngFolder
            foreach ($filespng as $filepng) {
                if (is_file($filepng)) {
                    try {
                        @unlink($filepng);
                    } catch (\Exception $e) {
                        $this->error("Could not delete {$filepng}: " . $e->getMessage());
                    }
                }
            }
        } else {
            mkdir($exportPngFolder, 0777, true); // create if missing
        }
        if (file_exists($exportPdfFolder)) {
            $filespdf = glob($exportPdfFolder . '/*.pdf'); // ✅ use $exportPngFolder
            foreach ($filespdf as $filepdf) {
                if (is_file($filepdf)) {
                    try {
                        @unlink($filepdf);
                    } catch (\Exception $e) {
                        $this->error("Could not delete {$filepng}: " . $e->getMessage());
                    }
                }
            }
        } else {
            mkdir($exportPngFolder, 0777, true); // create if missing
        }




        if (file_exists($exportCsvFolder)) {
            $files = glob($exportCsvFolder . '/*.csv'); // only csv files
            foreach ($files as $file) {
                if (is_file($file)) {
                    // Try to unlock & delete
                    try {
                        @fclose(fopen($file, 'r')); // ensure file handle closed
                        @unlink($file);
                    } catch (\Exception $e) {
                        $this->error("Could not delete {$file}: " . $e->getMessage());
                    }
                }
            }
        } else {
            mkdir($exportCsvFolder, 0777, true); // create if missing
        }


            $config = config('licenseplates');

        $provinceLogos = array_map(fn($path) => public_path($path), $config['province_logos']);

        $totalImages = 0;
        $allPlates = []; // For combined CSV
        $count = $this->option('count') ? (int) $this->option('count') : 100;

        foreach ($provinces as $province) {
            $this->info("Generating {$count} plates for {$province->region_name}...");

            $usedPlates = [];
            $cities = $province->cities->pluck('id', 'city_name')->toArray();
            $provincePlates = [];
            $provinceImages = 0;
            // php artisan plates:csv   --count=10
            // --- Generate plates ---
            for ($i = 0; $i <  $count; $i++) {
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
                    'City' => $cityName,
                    'Plate Number' => $plateNumber,
                    'Price' => rand(1000, 4000),
                    'Status' => 'Available'
                ];

                $provincePlates[] = $row;
                $allPlates[] = $row;
            }

            // --- Generate PNG images ---
            $provinceLogo = $provinceLogos[$province->region_name] ?? null;
            foreach ($provincePlates as $row) {
                $html = View::make('plates.plate_templates', [
                    'plate' => $row,
                    'provinceLogo' => $provinceLogo
                ])->render();

                $fileNameImage = $row['Plate Number'] . '_' . date("d-F-Y") . time() . '.png';
                $imagePath = $exportPngFolder . '/' . $fileNameImage;

                Browsershot::html($html)
                    ->windowSize(400, 200)
                    ->timeout(60000)
                    ->save($imagePath);
                $this->info("Image Png Created for {$province->region_name} {$provinceImages} at {$imagePath}");
                $provinceImages++;
                $totalImages++;
            }

            $this->info("PNG images created for {$province->region_name}: {$provinceImages}");

            // --- PDF per province ---
            $pdfPath = $exportPdfFolder . '/' . $province->region_name . '_plates.pdf';
            $pdf = Pdf::loadView('plates.export_pdf', ['plates' => $provincePlates]);
            $pdf->save($pdfPath);
            $this->info("PDF exported for {$province->region_name} at {$pdfPath}");

            // --- CSV per province ---
            $csvPath = $exportCsvFolder . '/' . $province->region_name . rand(11111, 99999) . '_plates.csv';
            $csv = Writer::createFromPath($csvPath, 'w+');
            $csv->insertOne(array_keys($provincePlates[0]));
            foreach ($provincePlates as $row) {
                $csv->insertOne($row);
            }
            $this->info("CSV file created for {$province->region_name} at {$csvPath}");
        }

        // --- Combined CSV for all provinces ---
        if (!empty($allPlates)) {
            $csvPathAll = $exportCsvFolder . '/license_plates_combined' . rand(11111, 99999) . '.csv';
            $csvAll = Writer::createFromPath($csvPathAll, 'w+');
            $csvAll->insertOne(array_keys($allPlates[0]));
            foreach ($allPlates as $row) {
                $csvAll->insertOne($row);
            }
            $this->info("Combined CSV created at {$csvPathAll}");
        }

        $this->info("Total PNG images created: {$totalImages}");
        $this->info("All exports completed!");
    }
}
