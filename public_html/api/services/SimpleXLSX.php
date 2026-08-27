<?php
declare(strict_types=1);

/**
 * Lightweight Standalone XLSX Parser for PHP 8.x
 * Zero external dependencies (uses built-in ZipArchive and SimpleXML)
 * Fully compatible with Shared Hosting (cPanel / DirectAdmin)
 */
class SimpleXLSX {
    private string $filename;
    private array $sharedStrings = [];
    private array $sheetNames = [];
    private array $sheetFiles = [];

    public function __construct(string $filename) {
        $this->filename = $filename;
    }

    public static function parse(string $filename): ?self {
        $xlsx = new self($filename);
        if ($xlsx->load()) {
            return $xlsx;
        }
        return null;
    }

    public function getSheetNames(): array {
        return $this->sheetNames;
    }

    private function load(): bool {
        if (!file_exists($this->filename)) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($this->filename) !== true) {
            return false;
        }

        // 1. Read Shared Strings
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml !== false) {
            $xml = simplexml_load_string($sharedStringsXml);
            if ($xml !== false) {
                foreach ($xml->si as $val) {
                    if (isset($val->t)) {
                        $this->sharedStrings[] = (string)$val->t;
                    } elseif (isset($val->r)) {
                        $text = '';
                        foreach ($val->r as $r) {
                            $text .= (string)$r->t;
                        }
                        $this->sharedStrings[] = $text;
                    } else {
                        $this->sharedStrings[] = '';
                    }
                }
            }
        }

        // 2. Read Workbook Structure & Sheet names
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $workbookRelsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        
        $rels = [];
        if ($workbookRelsXml !== false) {
            $xmlRels = simplexml_load_string($workbookRelsXml);
            if ($xmlRels !== false) {
                foreach ($xmlRels->Relationship as $rel) {
                    $rels[(string)$rel['Id']] = (string)$rel['Target'];
                }
            }
        }

        if ($workbookXml !== false) {
            $xmlWb = simplexml_load_string($workbookXml);
            if ($xmlWb !== false && isset($xmlWb->sheets->sheet)) {
                foreach ($xmlWb->sheets->sheet as $s) {
                    $name = (string)$s['name'];
                    $rId = (string)$s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                    $this->sheetNames[] = $name;
                    $target = $rels[$rId] ?? ('worksheets/sheet' . count($this->sheetNames) . '.xml');
                    if (!str_starts_with($target, 'xl/')) {
                        $target = 'xl/' . ltrim($target, '/');
                    }
                    $this->sheetFiles[$name] = $target;
                }
            }
        }

        $zip->close();
        return true;
    }

    public function rows(int|string $sheetIndexOrName = 0): array {
        $zip = new ZipArchive();
        if ($zip->open($this->filename) !== true) {
            return [];
        }

        $sheetFile = null;
        if (is_int($sheetIndexOrName)) {
            $sheetName = $this->sheetNames[$sheetIndexOrName] ?? null;
            if ($sheetName && isset($this->sheetFiles[$sheetName])) {
                $sheetFile = $this->sheetFiles[$sheetName];
            } else {
                $sheetFile = 'xl/worksheets/sheet' . ($sheetIndexOrName + 1) . '.xml';
            }
        } else {
            $sheetFile = $this->sheetFiles[$sheetIndexOrName] ?? null;
        }

        if (!$sheetFile || ($sheetXml = $zip->getFromName($sheetFile)) === false) {
            $zip->close();
            return [];
        }

        $xml = simplexml_load_string($sheetXml);
        $zip->close();

        if ($xml === false || !isset($xml->sheetData->row)) {
            return [];
        }

        $rows = [];
        foreach ($xml->sheetData->row as $r) {
            $rowNum = (int)$r['r'];
            $rowData = [];
            $currentCol = 1;

            foreach ($r->c as $c) {
                $cellRef = (string)$c['r'];
                $colLetters = preg_replace('/[0-9]/', '', $cellRef);
                $colIndex = $this->columnLetterToIndex($colLetters);

                // Fill blanks between columns
                while ($currentCol < $colIndex) {
                    $rowData[] = null;
                    $currentCol++;
                }

                $cellType = (string)$c['t'];
                $val = isset($c->v) ? (string)$c->v : null;

                if ($cellType === 's' && $val !== null) {
                    $sIndex = (int)$val;
                    $val = $this->sharedStrings[$sIndex] ?? '';
                } elseif ($cellType === 'inlineStr' && isset($c->is->t)) {
                    $val = (string)$c->is->t;
                }

                $rowData[] = ($val !== null && trim($val) !== '') ? trim($val) : null;
                $currentCol++;
            }

            $rows[$rowNum] = $rowData;
        }

        // Normalize indices to 1-indexed contiguous array
        $maxRow = empty($rows) ? 0 : max(array_keys($rows));
        $normalizedRows = [];
        for ($i = 1; $i <= $maxRow; $i++) {
            $normalizedRows[$i] = $rows[$i] ?? [];
        }

        return $normalizedRows;
    }

    private function columnLetterToIndex(string $col): int {
        $index = 0;
        $len = strlen($col);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord(strtoupper($col[$i])) - ord('A') + 1);
        }
        return $index;
    }
}
