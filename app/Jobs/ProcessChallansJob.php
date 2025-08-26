<?php

namespace App\Jobs;

use App\Models\LicensePlate;
use App\Models\Bank;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use ZipArchive;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\View;

class ProcessChallansJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $filePath;
    protected int $userId;

    public $timeout = 0; // unlimited execution time
    public $tries = 3;   // retry if it fails

    public function __construct(string $filePath, int $userId)
    {
        $this->filePath = $filePath;
        $this->userId   = $userId;
    }

    public function handle(): void
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '-1');

        $user = User::find($this->userId);

        if (!$user) {
            Log::error("ProcessChallansJob: User not found for ID {$this->userId}");
            return;
        }

        $insertedIds = [];
        $zipPaths = [];

        $batch = 1;
        $batchSize = 5000;
        $rows = [];



        //       $html = View::make('plates.plate_template', [
        //             'plate' => $plate,
        //             'provinceLogo' => $provinceLogo
        //         ])->render();


        //  $fileNameImage =  $plate->plate_number . date("d-F-Y") . time() . '.png';
        //         $imagePath = public_path('plates/' .  $fileNameImage);
        //         Browsershot::html($html)
        //             ->windowSize(400, 200) // adjust size
        //             ->timeout(60000)
        //             ->save($imagePath);


        if (!file_exists(public_path('challans_mail'))) {
            mkdir(public_path('challans_mail'), 0777, true);
        }

        if (!file_exists(public_path('challans_image_mail'))) {
            mkdir(public_path('challans_image_mail'), 0777, true);
        }

        if (!file_exists(public_path('mail_zip'))) {
            mkdir(public_path('mail_zip'), 0777, true);
        }


        if (($handle = fopen($this->filePath, 'r')) !== false) {
            $rowIndex = 0;

            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                $rowIndex++;
                if ($rowIndex === 1) continue; // skip header

                $rows[] = $row;

                if (count($rows) >= $batchSize) {
                    $ids = $this->processBatch($rows, $batch, $zipPaths, $user);
                    $insertedIds = array_merge($insertedIds, $ids);
                    $rows = [];
                    $batch++;
                }
            }

            if (!empty($rows)) {
                $ids = $this->processBatch($rows, $batch, $zipPaths, $user);
                $insertedIds = array_merge($insertedIds, $ids);
            }

            fclose($handle);
        }

        // ✅ Send email
        try {
            Mail::send('emails.challan_ready', [
                'user'        => $user,
                'links'       => collect($zipPaths)->map(fn($p) => url($p)),
                'insertedIds' => $insertedIds,
                'total'       => count($insertedIds),
            ], function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Your License Plate Challans Are Ready');
            });


            Log::info("ProcessChallansJob: Email sent to {$user->email}");
        } catch (\Exception $e) {
            Log::error("ProcessChallansJob: Failed to send email - " . $e->getMessage());
        }
    }

    private function processBatch(array $rows, int $batch, array &$zipPaths, User $user): array
    {
        $insertedIds = [];
        $pdfPaths = [];
        $pngImagePaths = [];


        foreach ($rows as $row) {
            if (count($row) < 4) continue;

            $plate = LicensePlate::updateOrCreate(
                [
                    'plate_number' => trim($row[2])
                ],
                [
                    'region'    => trim($row[0]),
                    'city'      => trim($row[1]),
                    'price'     => trim($row[3]),
                    'status'    => $row[4] ?? 'Available',
                    'user_id'   => $user->id,
                    'featured'  => rand(0, 1) // randomly 0 or 1
                ]
            );

            $banks = Bank::all();
            $provinceLogos = [
                'Punjab'      => public_path('glogo/punjab.jpeg'),
                'Sindh'       => public_path('glogo/sindh.png'),
                'KPK'         => public_path('glogo/KP_logo.png'),
                'Balochistan' => public_path('glogo/balochistan.jpeg'),
                "Gilgit" => public_path('glogo/gilgit.png'),
            ];
            $provinceLogo = $provinceLogos[$plate->region] ?? null;

            $insertedIds[] = $plate->id;

            $dueDate = $plate->created_at->copy()->addMonths(2)->format('d M Y');
            $invoiceNumber = 'INV-' . rand(100000, 999999);

            $pdf = Pdf::loadView('pdf.plate_challan', [
                'plate'              => $plate,
                'banks'              => $banks,
                'dueDate'            => $dueDate,
                'user'               => $user,
                'provinceLogo'       => $provinceLogo,
                'paymentMethod'      => "Bank",
                'LatePaymentPenalty' => 500,
                'invoiceNumber'      => $invoiceNumber,
            ])->setPaper('A4', 'portrait');


            $html = View::make('plates.plate_template', [
                'plate' => $plate,
                'provinceLogo' => $provinceLogo
            ])->render();

            //   if (file_exists($exportPdfFolder)) {
            //             $filespdf = glob($exportPdfFolder . '/*.pdf'); // ✅ use $exportPngFolder
            //             foreach ($filespdf as $filepdf) {
            //                 if (is_file($filepdf)) {
            //                     try {
            //                         @unlink($filepdf);
            //                     } catch (\Exception $e) {
            //                         $this->error("Could not delete {$filepng}: " . $e->getMessage());
            //                     }
            //                 }
            //             }



            $fileNameImage =  $plate->plate_number . date("d-F-Y") . time() . '.png';
            $imagePath = public_path('challans_image_mail/' .  $fileNameImage);
            Browsershot::html($html)
                ->windowSize(400, 200)
                // ensure final image is cropped exactly
                ->save($imagePath);
            $pngImagePaths[] = $imagePath;
            $pdfPath = public_path("challans_mail/{$plate->id}_" . date('d-F-Y') . ".pdf");
            $pdf->save($pdfPath);
            $pdfPaths[] = $pdfPath;
        }

        // ✅ Zip batch
        $zipPathFull = public_path("mail_zip/batch_{$batch}.zip");
        $zip = new ZipArchive();
        if ($zip->open($zipPathFull, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach ($pdfPaths as $pdf) {
                $zip->addFile($pdf, basename($pdf));
            }
            foreach ($pngImagePaths as  $pngImagePath) {
                $zip->addFile($pngImagePath, basename($pngImagePath));
            }
            $zip->close();
        }

        $zipPaths[] = "mail_zip/batch_{$batch}.zip";
        foreach ($pdfPaths as $pdfFile) {
            if (file_exists($pdfFile)) {
                unlink($pdfFile);
            }
        }
        foreach ($pngImagePaths as $singleImagepath) {
            if (file_exists($singleImagepath)) {
                unlink($singleImagepath);
            }
        }

        return $insertedIds;
    }
}
