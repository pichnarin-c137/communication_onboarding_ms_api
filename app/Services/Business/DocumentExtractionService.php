<?php

namespace App\Services\Business;

use App\Exceptions\Business\DocumentExtractionFailedException;
use App\Models\BusinessType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser as PdfParser;
use thiagoalessio\TesseractOCR\TesseractOCR;

class DocumentExtractionService
{
    /**
     * Extract structured fields from an uploaded patent document.
     * Supports text-based PDFs (via pdfparser) and scanned images (via Tesseract OCR).
     */
    public function extract(UploadedFile $file): array
    {
        $mime    = $file->getMimeType();
        $rawText = '';

        if ($mime === 'application/pdf') {
            $rawText = $this->extractFromPdf($file->getRealPath());
        } elseif (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $rawText = $this->extractFromImage($file->getRealPath());
        }

        if (empty(trim($rawText))) {
            throw new DocumentExtractionFailedException(
                'Could not extract text from the uploaded document.',
                context: ['mime' => $mime, 'filename' => $file->getClientOriginalName()]
            );
        }

        $fields = $this->parseFields($rawText);

        return $fields;
    }

    private function extractFromPdf(string $path): string
    {
        try {
            $parser   = new PdfParser();
            $pdf      = $parser->parseFile($path);
            $text     = $pdf->getText();

            return $text ?? '';
        } catch (\Throwable $e) {
            Log::warning('PDF text extraction failed, falling back to OCR.', [
                'error' => $e->getMessage(),
                'path'  => $path,
            ]);

            // Fallback: try Tesseract on PDF as image
            return $this->extractFromImage($path);
        }
    }

    private function extractFromImage(string $path): string
    {
        try {
            $ocr = new TesseractOCR($path);
            $ocr->lang('eng', 'khm');

            return $ocr->run();
        } catch (\Throwable $e) {
            Log::warning('Tesseract OCR failed.', [
                'error' => $e->getMessage(),
                'path'  => $path,
            ]);

            return '';
        }
    }

    private function parseFields(string $text): array
    {
        $extracted = [
            'company_name_en'      => null,
            'company_name_km'      => null,
            'business_type_id'     => null,
            'business_type_name'   => null,
            'owner_name_en'        => null,
            'owner_name_km'        => null,
            'phone'                => null,
            'address_km'           => null,
        ];

        $confidence = [
            'company_name_en'  => false,
            'company_name_km'  => false,
            'business_type'    => false,
            'owner_name_en'    => false,
            'owner_name_km'    => false,
            'phone'            => false,
            'address_km'       => false,
        ];

        //  Phone extraction 
        if (preg_match('/(\+?855|0)[1-9][0-9]{7,8}/', $text, $matches)) {
            $extracted['phone'] = $matches[0];
            $confidence['phone'] = true;
        }

        //  English company name 
        // Covers: "Company Name:", "Business Name:", "Enterprise's name:", "Firm Name:", "Name of enterprise:"
        if (preg_match(
            "/(?:Enterprise(?:'s)?\s+[Nn]ame|Company\s+[Nn]ame|Business\s+[Nn]ame|Firm\s+[Nn]ame|[Nn]ame\s+of\s+[Ee]nterprise)\s*[:\-]\s*([^\n\r]+)/i",
            $text,
            $matches
        )) {
            $extracted['company_name_en'] = trim($matches[1]);
            $confidence['company_name_en'] = true;
        }

        //  English owner/proprietor name 
        // Covers: "Owner:", "Owner's name:", "Owners name:", "Proprietor:", "Director:"
        if (preg_match(
            "/(?:Owner(?:'?s)?\s+[Nn]ame|Owner|Proprietor|Director|Representative)\s*[:\-]\s*([A-Za-z\s\.]+?)(?:\s{2,}|\s+[A-Z][a-z]+\s*:|\n|\r|$)/",
            $text,
            $matches
        )) {
            $extracted['owner_name_en'] = trim($matches[1]);
            $confidence['owner_name_en'] = true;
        }

        //  Khmer text blocks (Unicode range U+1780–U+17FF and U+19E0–U+19FF) 
        $khmerPattern = '/[\x{1780}-\x{17FF}\x{19E0}-\x{19FF}][\x{1780}-\x{17FF}\x{19E0}-\x{19FF}\x{200B}\s]*/u';
        preg_match_all($khmerPattern, $text, $khmerMatches);
        $khmerBlocks = array_map('trim', $khmerMatches[0]);
        $khmerBlocks = array_filter($khmerBlocks, fn ($b) => mb_strlen($b) > 3);
        $khmerBlocks = array_values($khmerBlocks);

        // Assign Khmer blocks heuristically
        if (isset($khmerBlocks[0])) {
            $extracted['company_name_km'] = $khmerBlocks[0];
            $confidence['company_name_km'] = true;
        }

        if (isset($khmerBlocks[1])) {
            $extracted['owner_name_km'] = $khmerBlocks[1];
            $confidence['owner_name_km'] = true;
        }

        // Longer Khmer blocks are likely addresses
        $longKhmer = array_filter($khmerBlocks, fn ($b) => mb_strlen($b) > 20);
        if (! empty($longKhmer)) {
            $extracted['address_km'] = array_values($longKhmer)[0];
            $confidence['address_km'] = true;
        }

        //  Address (English label fallback) 
        if ($extracted['address_km'] === null) {
            if (preg_match('/(?:Address|Location)\s*[:\-]\s*([^\n\r]+)/i', $text, $matches)) {
                $extracted['address_km'] = trim($matches[1]);
                $confidence['address_km'] = true;
            }
        }

        //  Business type matching 
        $businessTypes = BusinessType::all(['id', 'name_en', 'name_km']);
        $bestMatch     = null;
        $bestScore     = 0;

        foreach ($businessTypes as $type) {
            $score = 0;

            if (stripos($text, $type->name_en) !== false) {
                $score += 2;
            }

            if (mb_strpos($text, $type->name_km) !== false) {
                $score += 3; // Khmer match is more precise
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $type;
            }
        }

        if ($bestMatch !== null) {
            $extracted['business_type_id']   = $bestMatch->id;
            $extracted['business_type_name'] = $bestMatch->name_en;
            $confidence['business_type']     = true;
        }

        return [
            'extracted'  => $extracted,
            'confidence' => $confidence,
            'raw_text'   => $text,
        ];
    }
}
