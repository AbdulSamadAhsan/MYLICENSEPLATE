<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Region;
use App\Models\User;
use League\Csv\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\File;
use ZipArchive;
use App\Notifications\PlatesExportNotification;

class OptimizePlatesGeneration extends Command
{
    protected $signature = 'plates:optimized {--count= : Number of plates per province (optional)}';
    protected $description = 'Generate license plates CSV, PDFs, PNG images, JSON, Word and XML per province with unique data and notify users';

    public function handle()
    {
        $startTime = microtime(true);

        ini_set('memory_limit', '4096M');
        ini_set('max_execution_time', 0);

        $provinces = Region::with('cities')->get();
        $letters = range('A', 'Z');

        $exportBaseFolder = public_path('exports_new_xml_notify');
        $exportFolders = [
            'csv'  => $exportBaseFolder . '/csv',
            'pdf'  => $exportBaseFolder . '/pdf',
            'png'  => $exportBaseFolder . '/png',
            'json' => $exportBaseFolder . '/json',
            'word' => $exportBaseFolder . '/word',
            'xml'  => $exportBaseFolder . '/xml',
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
        $allPlates = [];
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

            // --- Generate plates ONCE per province ---
            $plates = $this->generatePlates($province, $letters, $count);

            // --- PNG EXPORT (can be queued) ---
            foreach ($plates as $row) {
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

                $totalImages++;
            }

            // --- PDF EXPORT ---
            $chunks = array_chunk($plates, 50);
            foreach ($chunks as $index => $chunk) {
                $pdfPath = $provinceFolders['pdf'] . '/' . $province->region_name . '_plates_part' . ($index + 1) . '.pdf';
                $pdf = Pdf::loadView('plates.export_pdf', ['plates' => $chunk]);
                $pdf->save($pdfPath);
            }

            // --- CSV EXPORT ---
            $csvPath = $provinceFolders['csv'] . '/' . $province->region_name . '_plates.csv';
            $csv = Writer::createFromPath($csvPath, 'w+');
            $csv->insertOne(array_keys($plates[0]));
            foreach ($plates as $row) $csv->insertOne($row);

            // --- JSON EXPORT ---
            $jsonPath = $provinceFolders['json'] . '/' . $province->region_name . '_plates.json';
            file_put_contents($jsonPath, json_encode($plates, JSON_PRETTY_PRINT));

            // --- WORD EXPORT ---
            $wordPath = $provinceFolders['word'] . '/' . $province->region_name . '_plates.doc';
            $htmlContent = "<h2>{$province->region_name} License Plates</h2>";
            $htmlContent .= "<table border='1' cellpadding='5' cellspacing='0'><tr>";
            foreach (array_keys($plates[0]) as $heading) $htmlContent .= "<th>{$heading}</th>";
            $htmlContent .= "</tr>";
            foreach ($plates as $plate) {
                $htmlContent .= "<tr>";
                foreach ($plate as $cell) $htmlContent .= "<td>{$cell}</td>";
                $htmlContent .= "</tr>";
            }
            $htmlContent .= "</table>";
            file_put_contents($wordPath, $htmlContent);

            // --- XML EXPORT ---
            $xml = new \SimpleXMLElement('<plates/>');
            foreach ($plates as $plate) {
                $plateNode = $xml->addChild('plate');
                $plateNode->addChild('province', $plate['Province']);
                $plateNode->addChild('city', $plate['City']);
                $plateNode->addChild('plate_number', $plate['Plate Number']);
                $plateNode->addChild('price', $plate['Price']);
                $plateNode->addChild('status', $plate['Status']);
            }
            $xmlPath = $provinceFolders['xml'] . '/' . $province->region_name . '_plates.xml';
            $xml->asXML($xmlPath);

            $allPlates = array_merge($allPlates, $plates);
        }

        // --- COMBINED EXPORTS + ZIP ---
        if (!empty($allPlates)) {
            $zipPathAll = $this->createCombinedExports($exportFolders, $allPlates);
        }

        // --- Send Notifications to Users ---
        $users = User::role(['user', 'User'])->whereHas('licensePlates')->get();
        foreach ($users as $user) {
            $user->notify(new PlatesExportNotification($zipPathAll));
        }

        $executionTime = round(microtime(true) - $startTime, 2);
        $this->info("All exports completed in {$executionTime} seconds!");
        $this->info("Total PNG images created: {$totalImages}");
    }

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

    private function createCombinedExports($exportFolders, $allPlates)
    {
        // --- Combined CSV ---
        $csvPathAll = $exportFolders['csv'] . '/license_plates_combined.csv';
        $csvAll = Writer::createFromPath($csvPathAll, 'w+');
        $csvAll->insertOne(array_keys($allPlates[0]));
        foreach ($allPlates as $row) $csvAll->insertOne($row);

        // --- Combined JSON ---
        $jsonPathAll = $exportFolders['json'] . '/license_plates_combined.json';
        file_put_contents($jsonPathAll, json_encode($allPlates, JSON_PRETTY_PRINT));

        // --- Combined WORD ---
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

        // --- Combined XML ---
        $xmlAll = new \SimpleXMLElement('<plates/>');
        foreach ($allPlates as $plate) {
            $plateNode = $xmlAll->addChild('plate');
            $plateNode->addChild('province', $plate['Province']);
            $plateNode->addChild('city', $plate['City']);
            $plateNode->addChild('plate_number', $plate['Plate Number']);
            $plateNode->addChild('price', $plate['Price']);
            $plateNode->addChild('status', $plate['Status']);
        }
        $xmlPathAll = $exportFolders['xml'] . '/license_plates_combined.xml';
        $xmlAll->asXML($xmlPathAll);

        // --- ZIP ALL FILES ---
        $zipPathAll = dirname($exportFolders['csv']) . '/license_plates_combined.zip';
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
        }

        return $zipPathAll;
    }
}
