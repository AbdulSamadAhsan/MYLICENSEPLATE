<?php

namespace App\Services;

use Illuminate\Support\Str;
use App\Models\LicensePlate;
use Barryvdh\DomPDF\Facade\Pdf;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Mail;
use App\Jobs\MailJob;
use App\Models\plate_challan;
use App\Jobs\SendMultiplePlates;
use App\Models\User;
use App\Models\Bank;

class LicensePlateService
{
    public function __construct() { }

    public static function generateChallan(LicensePlate $plate, $user)
    {
        $config = config('licenseplates');
        $provinceLogos = array_map(fn($path) => public_path($path), $config['province_logos']);
        $provinceLogo = $provinceLogos[$plate->region] ?? null;

        $dueDate = now()->addMonths(2)->format('d M Y');
        $banks = Bank::get()->toArray();
        $invoiceNumber = 'INV-' . mt_rand(1000000000, 9999999999);
        $paymentMethod = config("licenseplates.method");
        $penalty = config("licenseplates.penalty");

        $pdf = Pdf::loadView('pdf.plate_challan', [
            'plate' => $plate,
            'banks' => $banks,
            'dueDate' => $dueDate,
            'user' => $user,
            'provinceLogo' => $provinceLogo,
            'paymentMethod' => $paymentMethod,
            'LatePaymentPenalty' => $penalty,
            'invoiceNumber' => $invoiceNumber
        ])->setPaper('A4', 'portrait');

        $challanDir = public_path('challans');
        if (!file_exists($challanDir)) mkdir($challanDir, 0777, true);

        $fileName = 'challan_' . $plate->id . '.pdf';
        $filePath = $challanDir . '/' . $fileName;
        $pdf->save($filePath);

        return [
            'pdf_file_name' => $fileName,
            'invoice_number' => $invoiceNumber,
            'pdf_file_path' => $filePath,
            'dueDate' => $dueDate,
            'provinceLogo' => $provinceLogo
        ];
    }

    public static function generateImage(LicensePlate $plate)
    {
        $config = config('licenseplates');
        $provinceLogos = array_map(fn($path) => public_path($path), $config['province_logos']);
        $provinceLogo = $provinceLogos[$plate->region] ?? null;

        $html = View::make('plates.plate_template', [
            'plate' => $plate,
            'provinceLogo' => $provinceLogo
        ])->render();

        $fileName = $plate->plate_number . date("d-F-Y") . time() . '.png';
        $imgDir = public_path('plates');
        if (!file_exists($imgDir)) mkdir($imgDir, 0777, true);

        $imagePath = $imgDir . '/' . $fileName;
        Browsershot::html($html)
            ->windowSize(400, 200)
            ->timeout(60000)
            ->save($imagePath);

        return [
            'image_file_name' => $fileName,
            'image_file_path' => $imagePath,
            'provinceLogo' => $provinceLogo
        ];
    }

    public static function sendMail($plate, $dueDate, $user, $provinceLogo, $pdfFile)
    {
        if ($pdfFile && file_exists($pdfFile)) {
            MailJob::dispatch($plate, $dueDate, $user, $provinceLogo, $pdfFile)->delay(now()->addMinutes(5));
        }
    }

    public static function processPlates(array $validated, $user)
    {
        $platesData = [];
        $attachments = [];
        $insertedIds = [];
        $image_attachment = [];

        $config = config('licenseplates');
        $provinceLogos = array_map(fn($path) => public_path($path), $config['province_logos']);

        foreach ($validated['plate_number'] as $index => $plateNumber) {
            $plate = LicensePlate::updateOrCreate(
                ['plate_number' => $plateNumber],
                [
                    'region' => $validated['province'][$index],
                    'city' => $validated['city'][$index],
                    'price' => $validated['price'][$index],
                    'status' => $validated['status'][$index] ?? null,
                    'user_id' => $user->id,
                ]
            );

            $insertedIds[] = $plate->id;
            $provinceLogo = $provinceLogos[$plate->region] ?? null;

            $challan = self::generateChallan($plate, $user);
            $imagePNG = self::generateImage($plate);
            $data = array_merge($challan, $imagePNG);

            plate_challan::updateOrCreate(
                ['licenseplate_id' => $plate->id],
                [
                    'pdf_path' => $data['pdf_file_name'],
                    'image_path' => $data['image_file_name'],
                    'invoice_number' => $data['invoice_number'],
                ]
            );

            $platesData[] = ['plate' => $plate, 'provinceLogo' => $provinceLogo];
            $attachments[] = $data['pdf_file_path'];
            $image_attachment[] = $data['image_file_path'];
        }

        $attach_docs = array_merge($attachments, $image_attachment);
        return [$platesData, $attach_docs, $insertedIds];
    }

    public static function sendMultipleMail($platesData, $dueDate, $user, $attachments)
    {
        Mail::send('emails.plate_challan_multiple', [
            'platesData' => $platesData,
            'dueDate' => $dueDate,
            'user' => $user
        ], function ($message) use ($user, $attachments) {
            $message->to($user->email)->subject('Your License Plate Challans');
            foreach ($attachments as $filePath) {
                if ($filePath && file_exists($filePath)) {
                    $message->attach($filePath, [
                        'as' => basename($filePath),
                        'mime' => mime_content_type($filePath),
                    ]);
                }
            }
        });

        SendMultiplePlates::dispatch($platesData, $dueDate, $user, $attachments);
    }

    public static function deleteOldPlateImage(LicensePlate $plate): void
    {
        $challan = plate_challan::where("licenseplate_id", $plate->id)->first();
        if ($challan && $challan->image_path) {
            $oldFile = public_path("plates/" . $challan->image_path);
            if (file_exists($oldFile)) unlink($oldFile);
        }
    }

    public static function processPlateData(LicensePlate $plate, User $user, $sendMail = 1)
    {
        $challan = self::generateChallan($plate, $user);
        $imagePNG = self::generateImage($plate);
        $data = array_merge($challan, $imagePNG);

        plate_challan::updateOrCreate(
            ['licenseplate_id' => $plate->id],
            [
                'pdf_path' => $data['pdf_file_name'],
                'image_path' => $data['image_file_name'],
                'invoice_number' => $data['invoice_number'],
            ]
        );

        if ($sendMail == 1) {
            self::sendMail(
                $plate,
                $data['dueDate'],
                $user,
                $data['provinceLogo'],
                $data['pdf_file_path']
            );
        }

        return $plate;
    }
}
