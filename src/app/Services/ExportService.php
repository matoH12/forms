<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /**
     * Export submissions to CSV
     */
    public function exportToCsv(Collection $submissions, Form $form): StreamedResponse
    {
        $filename = $this->generateFilename($form, 'csv');
        $headers = $this->getHeaders($form);

        $response = new StreamedResponse(function () use ($submissions, $form, $headers) {
            // Clean any previous output
            if (ob_get_level()) {
                ob_end_clean();
            }

            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Write headers
            fputcsv($handle, $headers, ';');

            // Write data rows
            foreach ($submissions as $submission) {
                $row = $this->formatSubmissionRow($submission, $form);
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
            exit; // Prevent any further output
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);

        return $response;
    }

    /**
     * Export submissions to Excel (XLSX) using simple XML format
     */
    public function exportToExcel(Collection $submissions, Form $form): StreamedResponse
    {
        $filename = $this->generateFilename($form, 'xlsx');
        $headers = $this->getHeaders($form);

        return new StreamedResponse(function () use ($submissions, $form, $headers) {
            $this->generateXlsx($submissions, $form, $headers);
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }

    /**
     * Get column headers for export
     */
    private function getHeaders(Form $form): array
    {
        $headers = [
            'ID',
            'Dátum odoslania',
            'Stav',
            'Používateľ',
            'Email',
        ];

        // Add form field headers
        $fields = $form->schema['fields'] ?? [];
        foreach ($fields as $field) {
            // Handle multilingual labels
            $label = $field['label'] ?? $field['name'];
            if (is_array($label)) {
                $label = $label['sk'] ?? $label['en'] ?? $field['name'];
            }
            $headers[] = $label;
        }

        $headers[] = 'Odpoveď admina';
        $headers[] = 'Schválil';
        $headers[] = 'Dátum schválenia';
        $headers[] = 'IP adresa';

        return $headers;
    }

    /**
     * Format a single submission row
     */
    private function formatSubmissionRow(FormSubmission $submission, Form $form): array
    {
        $row = [
            $submission->id,
            $submission->created_at->format('d.m.Y H:i'),
            $this->translateStatus($submission->status),
            $submission->user?->name ?? 'Anonymný',
            $submission->user?->email ?? '-',
        ];

        // Add form field values
        $fields = $form->schema['fields'] ?? [];
        $data = $submission->data ?? [];

        foreach ($fields as $field) {
            $fieldName = $field['name'];
            $value = $data[$fieldName] ?? '';

            // Format value based on field type
            if (is_array($value)) {
                $value = implode(', ', $value);
            } elseif (is_bool($value)) {
                $value = $value ? 'Áno' : 'Nie';
            }

            $row[] = $value;
        }

        $row[] = $submission->admin_response ?? '';
        $row[] = $submission->reviewer?->name ?? '';
        $row[] = $submission->reviewed_at ? $submission->reviewed_at->format('d.m.Y H:i') : '';
        $row[] = $submission->ip_address ?? '';

        return $row;
    }

    /**
     * Translate status to Slovak
     */
    private function translateStatus(string $status): string
    {
        return match ($status) {
            'submitted' => 'Odoslaný',
            'approved' => 'Schválený',
            'rejected' => 'Zamietnutý',
            'pending' => 'Čakajúci',
            default => $status,
        };
    }

    /**
     * Generate filename for export
     */
    private function generateFilename(Form $form, string $extension): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($form->localized_name));
        $date = now()->format('Y-m-d');
        return "odpovede-{$slug}-{$date}.{$extension}";
    }

    /**
     * Generate XLSX file using PHP ZipArchive and XML
     */
    private function generateXlsx(Collection $submissions, Form $form, array $headers): void
    {
        // Use Laravel's storage path with cryptographically secure random name
        // and restrictive permissions (0700 = owner only)
        $randomSuffix = bin2hex(random_bytes(16));
        $tempDir = storage_path('app/temp/xlsx_' . $randomSuffix);

        // Create directories with restrictive permissions
        mkdir($tempDir, 0700, true);
        mkdir($tempDir . '/_rels', 0700);
        mkdir($tempDir . '/xl', 0700);
        mkdir($tempDir . '/xl/_rels', 0700);
        mkdir($tempDir . '/xl/worksheets', 0700);

        // [Content_Types].xml
        file_put_contents($tempDir . '/[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>');

        // _rels/.rels
        file_put_contents($tempDir . '/_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

        // xl/_rels/workbook.xml.rels
        file_put_contents($tempDir . '/xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>');

        // xl/workbook.xml
        file_put_contents($tempDir . '/xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Odpovede" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>');

        // Collect all strings and build shared strings
        $sharedStrings = [];
        $stringIndex = [];

        $allRows = [$headers];
        foreach ($submissions as $submission) {
            $allRows[] = $this->formatSubmissionRow($submission, $form);
        }

        foreach ($allRows as $row) {
            foreach ($row as $cell) {
                $cellStr = (string) $cell;
                if (!isset($stringIndex[$cellStr])) {
                    $stringIndex[$cellStr] = count($sharedStrings);
                    $sharedStrings[] = $cellStr;
                }
            }
        }

        // xl/sharedStrings.xml
        $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $ssXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($sharedStrings) . '" uniqueCount="' . count($sharedStrings) . '">';
        foreach ($sharedStrings as $str) {
            $ssXml .= '<si><t>' . htmlspecialchars($str, ENT_XML1, 'UTF-8') . '</t></si>';
        }
        $ssXml .= '</sst>';
        file_put_contents($tempDir . '/xl/sharedStrings.xml', $ssXml);

        // xl/worksheets/sheet1.xml
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $sheetXml .= '<sheetData>';

        $rowNum = 1;
        foreach ($allRows as $row) {
            $sheetXml .= '<row r="' . $rowNum . '">';
            $colNum = 0;
            foreach ($row as $cell) {
                $colLetter = $this->getColumnLetter($colNum);
                $cellRef = $colLetter . $rowNum;
                $cellStr = (string) $cell;
                $idx = $stringIndex[$cellStr];
                $sheetXml .= '<c r="' . $cellRef . '" t="s"><v>' . $idx . '</v></c>';
                $colNum++;
            }
            $sheetXml .= '</row>';
            $rowNum++;
        }

        $sheetXml .= '</sheetData>';
        $sheetXml .= '</worksheet>';
        file_put_contents($tempDir . '/xl/worksheets/sheet1.xml', $sheetXml);

        // Create ZIP file
        $zipFile = $tempDir . '.xlsx';
        $zip = new \ZipArchive();
        $zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $this->addFolderToZip($zip, $tempDir, '');

        $zip->close();

        // Output the file
        readfile($zipFile);

        // Cleanup
        $this->deleteDirectory($tempDir);
        unlink($zipFile);
    }

    /**
     * Get Excel column letter from index
     */
    private function getColumnLetter(int $index): string
    {
        $letter = '';
        while ($index >= 0) {
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intval($index / 26) - 1;
        }
        return $letter;
    }

    /**
     * Add folder contents to ZIP
     */
    private function addFolderToZip(\ZipArchive $zip, string $folder, string $zipPath): void
    {
        $files = scandir($folder);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $filePath = $folder . '/' . $file;
            $zipFilePath = $zipPath ? $zipPath . '/' . $file : $file;

            if (is_dir($filePath)) {
                $zip->addEmptyDir($zipFilePath);
                $this->addFolderToZip($zip, $filePath, $zipFilePath);
            } else {
                $zip->addFile($filePath, $zipFilePath);
            }
        }
    }

    /**
     * Delete directory recursively
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * Export all submissions (for admin submissions page)
     */
    public function exportAllToCsv(Collection $submissions): StreamedResponse
    {
        $filename = 'vsetky-odpovede-' . now()->format('Y-m-d') . '.csv';

        $response = new StreamedResponse(function () use ($submissions) {
            // Clean any previous output
            if (ob_get_level()) {
                ob_end_clean();
            }

            $handle = fopen('php://output', 'w');

            // UTF-8 BOM
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Headers
            fputcsv($handle, [
                'ID',
                'Formulár',
                'Dátum odoslania',
                'Stav',
                'Používateľ',
                'Email',
                'Dáta (JSON)',
                'Odpoveď admina',
                'IP adresa',
            ], ';');

            foreach ($submissions as $submission) {
                // Get localized form name
                $formName = $submission->form?->localized_name ?? $submission->form?->slug ?? 'N/A';

                fputcsv($handle, [
                    $submission->id,
                    $formName,
                    $submission->created_at->format('d.m.Y H:i'),
                    $this->translateStatus($submission->status),
                    $submission->user?->name ?? 'Anonymný',
                    $submission->user?->email ?? '-',
                    json_encode($submission->data, JSON_UNESCAPED_UNICODE),
                    $submission->admin_response ?? '',
                    $submission->ip_address ?? '',
                ], ';');
            }

            fclose($handle);
            exit; // Prevent any further output
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);

        return $response;
    }
}
