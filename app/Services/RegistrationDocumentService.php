<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class RegistrationDocumentService
{
    /** @var array<string, string> */
    public const MERCHANT_FILE_MAP = [
        'selfie'             => 'selfie_path',
        'valid_id'           => 'valid_id_path',
        'digital_signature'  => 'digital_signature_path',
        'barangay_permit'    => 'barangay_permit_path',
        'mayors_permit'      => 'mayors_permit_path',
        'dti_permit'         => 'dti_permit_path',
    ];

    /** @var array<string, string> */
    public const DRIVER_FILE_MAP = [
        'selfie'                  => 'selfie_path',
        'selfie_with_motorcycle'  => 'selfie_with_motorcycle_path',
        'resume'                  => 'resume_path',
        'barangay_clearance'      => 'barangay_clearance_path',
        'drivers_license'         => 'drivers_license_path',
        'motorcycle_orcr'         => 'motorcycle_orcr_path',
        'scanned_orcr'            => 'scanned_orcr_path',
        'digital_signature'       => 'digital_signature_path',
    ];

    /**
     * @return array<string, string>
     */
    public static function storeMerchantDocuments(Request $request): array
    {
        return self::storeDocumentSet(
            $request,
            self::MERCHANT_FILE_MAP,
            'registrations/merchants'
        );
    }

    /**
     * @return array<string, string>
     */
    public static function storeDriverDocuments(Request $request): array
    {
        return self::storeDocumentSet(
            $request,
            self::DRIVER_FILE_MAP,
            'registrations/drivers'
        );
    }

    /**
     * @param  array<string, string>  $fileToPathKey
     * @return array<string, string>
     */
    private static function storeDocumentSet(
        Request $request,
        array $fileToPathKey,
        string $directoryPrefix
    ): array {
        MediaStorageService::assertCloudDiskReady();

        $uploadFolder = $directoryPrefix . '/' . Str::uuid();
        $paths = [];

        foreach ($fileToPathKey as $fileField => $pathKey) {
            if (!$request->hasFile($fileField)) {
                throw new RuntimeException(
                    'Missing required document upload: ' . str_replace('_', ' ', $fileField)
                );
            }

            $file = $request->file($fileField);
            if (!$file || !$file->isValid()) {
                throw new RuntimeException(
                    'Invalid document upload: ' . str_replace('_', ' ', $fileField)
                );
            }

            $extension = $file->getClientOriginalExtension() ?: 'bin';
            $storedName = $fileField . '.' . strtolower($extension);

            $paths[$pathKey] = MediaStorageService::storeUploadedFile(
                $file,
                $uploadFolder,
                $storedName
            );
        }

        return $paths;
    }

    public static function isPhoneVerified(string $phone): bool
    {
        return Cache::has(self::phoneVerificationCacheKey($phone));
    }

    public static function phoneVerificationCacheKey(string $phone): string
    {
        return 'phone_verified:' . preg_replace('/[^0-9]/', '', $phone);
    }
}
