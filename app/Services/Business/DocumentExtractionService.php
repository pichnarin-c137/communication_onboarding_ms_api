<?php

namespace App\Services\Business;

use App\Exceptions\Business\DocumentExtractionFailedException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Imagick;
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
        $mime = $file->getMimeType();
        $rawText = '';

        if ($mime === 'application/pdf') {
            $rawText = $this->extractFromPdf($file->getRealPath());
        } elseif (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $rawText = $this->extractFromImage($file->getRealPath());
        } else {
            throw new DocumentExtractionFailedException(
                'Unsupported document type.',
                context: ['mime' => $mime, 'filename' => $file->getClientOriginalName()]
            );
        }

        if (empty(trim($rawText))) {
            Log::warning('Document extraction produced empty text.', [
                'mime' => $mime,
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ]);

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
            $parser = new PdfParser;
            $pdf = $parser->parseFile($path);
            $text = $pdf->getText();

            if (! empty(trim((string) $text))) {
                return $text;
            }

            Log::info('PDF text was empty, attempting OCR fallback.', [
                'path' => $path,
            ]);

            return $this->extractFromPdfImages($path);
        } catch (\Throwable $e) {
            Log::warning('PDF text extraction failed, falling back to OCR.', [
                'error' => $e->getMessage(),
                'path' => $path,
            ]);

            return $this->extractFromPdfImages($path);
        }
    }

    private function extractFromImage(string $path): string
    {
        try {
            $ocr = new TesseractOCR($path);
            $ocr->lang('eng');

            return $ocr->run();
        } catch (\Throwable $e) {
            Log::warning('Tesseract OCR failed.', [
                'error' => $e->getMessage(),
                'path' => $path,
            ]);

            return '';
        }
    }

    private function extractFromPdfImages(string $path): string
    {
        if (! class_exists(Imagick::class)) {
            Log::warning('Imagick is not available for PDF OCR fallback.', [
                'path' => $path,
            ]);

            return '';
        }

        try {
            $imagick = new Imagick;
            $imagick->setResolution(200, 200);
            $imagick->readImage($path);

            $textChunks = [];
            $pageLimit = 2;
            $pageIndex = 0;

            foreach ($imagick as $page) {
                if ($pageIndex >= $pageLimit) {
                    break;
                }

                $page->setImageFormat('png');
                $tempPath = tempnam(sys_get_temp_dir(), 'pdf_ocr_').'.png';
                $page->writeImage($tempPath);

                $textChunks[] = $this->extractFromImage($tempPath);

                @unlink($tempPath);
                $pageIndex++;
            }

            $imagick->clear();
            $imagick->destroy();

            return trim(implode("\n", array_filter($textChunks)));
        } catch (\Throwable $e) {
            Log::warning('PDF OCR fallback via Imagick failed.', [
                'error' => $e->getMessage(),
                'path' => $path,
            ]);

            return '';
        }
    }

    private function parseFields(string $text): array
    {
        $extracted = [
            'company_name_en' => null,
            'owner_name_en' => null,
        ];

        $confidence = [
            'company_name_en' => false,
            'owner_name_en' => false,
        ];

        // Company name — "Enterprise's name:", "Company Name:", "Business Name:", etc.
        if (preg_match(
            "/(?:Enterprise(?:'s)?\s+[Nn]ame|Company\s+[Nn]ame|Business\s+[Nn]ame|Firm\s+[Nn]ame|[Nn]ame\s+of\s+[Ee]nterprise)\s*[:\-]\s*([^\n\r]+)/i",
            $text,
            $matches
        )) {
            $extracted['company_name_en'] = trim($matches[1]);
            $confidence['company_name_en'] = true;
        }

        // Owner name — "Owner's name:", "Owner:", "Proprietor:", "Director:", "Representative:"
        if (preg_match(
            "/(?:Owner(?:'?s)?\s+[Nn]ame|Owner|Proprietor|Director|Representative)\s*[:\-]\s*([A-Za-z\s\.]+?)(?:\s{2,}|\s+[A-Z][a-z]+\s*:|\n|\r|$)/",
            $text,
            $matches
        )) {
            $extracted['owner_name_en'] = trim($matches[1]);
            $confidence['owner_name_en'] = true;
        }

        return [
            'extracted' => $extracted,
            'confidence' => $confidence,
        ];
    }
}
