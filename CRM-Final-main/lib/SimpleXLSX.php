<?php
/**
 * Lightweight XLSX reader for this project.
 * Provides a subset of the SimpleXLSX API used by the import flow.
 */

if (!function_exists('simplexml_load_string')) {
    trigger_error('SimpleXLSX: PHP extension "ext_simplexml" is required', E_USER_ERROR);
}
if (!class_exists('ZipArchive')) {
    trigger_error('SimpleXLSX: PHP extension "ext_zip" is required', E_USER_ERROR);
}

class SimpleXLSX
{
    public static $revision = 'local-1.0.0';
    public static $code = 0;
    public static $message = false;

    protected $rows = [];
    protected $sheet_names = [];

    public function __construct($filename, $is_data = false)
    {
        $this->parseInternal($filename, $is_data);
    }

    public static function parse($filename, $is_data = false)
    {
        self::resetError();
        $instance = new static($filename, $is_data);
        return $instance->success() ? $instance : false;
    }

    public static function errorMessage()
    {
        return (string) self::$message;
    }

    public function success()
    {
        return self::$code === 0;
    }

    public function rows($sheet_id = 0)
    {
        return $this->rows[$sheet_id] ?? [];
    }

    public function getSheetNames()
    {
        return $this->sheet_names;
    }

    protected static function resetError()
    {
        self::$code = 0;
        self::$message = false;
    }

    protected static function setError($code, $message)
    {
        self::$code = (int) $code;
        self::$message = (string) $message;
    }

    protected function parseInternal($filename, $is_data)
    {
        if ($is_data) {
            self::setError(6, 'Parsing raw XLSX data is not supported in this build');
            return;
        }

        $file = @realpath($filename);
        if (!is_string($file) || !is_file($file)) {
            self::setError(1, 'file not found ' . (string) $filename);
            return;
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($file);
        if ($openResult !== true) {
            self::setError(2, 'cannot open zip ' . $file);
            return;
        }

        $rootRelsXml = $this->loadXmlFromZip($zip, '_rels/.rels');
        if ($rootRelsXml === false) {
            $zip->close();
            return;
        }

        $workbookPath = $this->findRelationshipTargetByType($rootRelsXml, 'officeDocument');
        if (!$workbookPath) {
            self::setError(3, 'cannot resolve workbook path');
            $zip->close();
            return;
        }

        $workbookPath = $this->normalizeZipPath('', $workbookPath);
        $workbookXml = $this->loadXmlFromZip($zip, $workbookPath);
        if ($workbookXml === false) {
            $zip->close();
            return;
        }

        $workbookBase = $this->dirnameZip($workbookPath);
        $workbookRelsPath = $this->normalizeZipPath($workbookBase, '_rels/' . basename($workbookPath) . '.rels');
        $workbookRelsXml = $this->loadXmlFromZip($zip, $workbookRelsPath, true);

        $sharedStrings = [];
        if ($workbookRelsXml !== false) {
            $sharedTarget = $this->findRelationshipTargetByType($workbookRelsXml, 'sharedStrings');
            if ($sharedTarget) {
                $sharedPath = $this->normalizeZipPath($workbookBase, $sharedTarget);
                $sharedXml = $this->loadXmlFromZip($zip, $sharedPath, true);
                if ($sharedXml !== false) {
                    $sharedStrings = $this->parseSharedStrings($sharedXml);
                }
            }
        }

        $sheetNodes = $workbookXml->xpath('//*[local-name()="sheets"]/*[local-name()="sheet"]');
        if (!is_array($sheetNodes) || empty($sheetNodes)) {
            self::setError(3, 'no sheets found in workbook');
            $zip->close();
            return;
        }

        $sheetIndex = 0;
        foreach ($sheetNodes as $sheetNode) {
            $sheetName = (string) $sheetNode['name'];
            $rid = (string) $sheetNode->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];

            $sheetTarget = false;
            if ($workbookRelsXml !== false && $rid !== '') {
                $sheetTarget = $this->findRelationshipTargetByRid($workbookRelsXml, $rid);
            }

            if (!$sheetTarget) {
                continue;
            }

            $sheetPath = $this->normalizeZipPath($workbookBase, $sheetTarget);
            $sheetXml = $this->loadXmlFromZip($zip, $sheetPath, true);
            if ($sheetXml === false) {
                continue;
            }

            $this->sheet_names[$sheetIndex] = $sheetName;
            $this->rows[$sheetIndex] = $this->parseSheetRows($sheetXml, $sharedStrings);
            $sheetIndex++;
        }

        if (empty($this->rows)) {
            self::setError(3, 'could not parse worksheet data');
        }

        $zip->close();
    }

    protected function loadXmlFromZip(ZipArchive $zip, $path, $silent = false)
    {
        $normalized = $this->normalizeZipPath('', $path);
        $raw = $zip->getFromName($normalized);
        if ($raw === false) {
            $raw = $zip->getFromName(ltrim($normalized, '/'));
        }
        if ($raw === false) {
            if (!$silent) {
                self::setError(3, 'cannot read XML data: ' . $normalized);
            }
            return false;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw);
        if ($xml === false) {
            if (!$silent) {
                self::setError(4, 'cannot parse XML data: ' . $normalized);
            }
            return false;
        }

        return $xml;
    }

    protected function parseSharedStrings(SimpleXMLElement $xml)
    {
        $out = [];
        $siNodes = $xml->xpath('//*[local-name()="si"]');
        if (!is_array($siNodes)) {
            return $out;
        }

        foreach ($siNodes as $si) {
            $texts = $si->xpath('.//*[local-name()="t"]');
            if (is_array($texts) && !empty($texts)) {
                $buffer = '';
                foreach ($texts as $t) {
                    $buffer .= (string) $t;
                }
                $out[] = $buffer;
            } else {
                $out[] = '';
            }
        }

        return $out;
    }

    protected function parseSheetRows(SimpleXMLElement $sheetXml, array $sharedStrings)
    {
        $rowsByNumber = [];
        $rowNodes = $sheetXml->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]');
        if (!is_array($rowNodes)) {
            return [];
        }

        $fallbackRowNum = 1;
        foreach ($rowNodes as $rowNode) {
            $rowNumber = (int) $rowNode['r'];
            if ($rowNumber <= 0) {
                $rowNumber = $fallbackRowNum;
            }
            $fallbackRowNum = $rowNumber + 1;

            $cellMap = [];
            $maxCol = -1;
            $nextCol = 0;

            $cellNodes = $rowNode->xpath('./*[local-name()="c"]');
            if (!is_array($cellNodes)) {
                continue;
            }

            foreach ($cellNodes as $cellNode) {
                $ref = (string) $cellNode['r'];
                $colIndex = ($ref !== '') ? $this->columnIndexFromCellRef($ref) : $nextCol;
                if ($colIndex < 0) {
                    $colIndex = $nextCol;
                }

                $type = (string) $cellNode['t'];
                $value = '';

                if ($type === 's') {
                    $vNodes = $cellNode->xpath('./*[local-name()="v"]');
                    $idx = (is_array($vNodes) && isset($vNodes[0])) ? (int) ((string) $vNodes[0]) : -1;
                    $value = ($idx >= 0 && array_key_exists($idx, $sharedStrings)) ? (string) $sharedStrings[$idx] : '';
                } elseif ($type === 'inlineStr') {
                    $tNodes = $cellNode->xpath('./*[local-name()="is"]//*[local-name()="t"]');
                    if (is_array($tNodes) && !empty($tNodes)) {
                        $buf = '';
                        foreach ($tNodes as $tNode) {
                            $buf .= (string) $tNode;
                        }
                        $value = $buf;
                    }
                } else {
                    $vNodes = $cellNode->xpath('./*[local-name()="v"]');
                    $value = (is_array($vNodes) && isset($vNodes[0])) ? (string) $vNodes[0] : '';
                }

                $cellMap[$colIndex] = $value;
                if ($colIndex > $maxCol) {
                    $maxCol = $colIndex;
                }
                $nextCol = $colIndex + 1;
            }

            if ($maxCol < 0) {
                $rowsByNumber[$rowNumber] = [];
                continue;
            }

            $rowValues = array_fill(0, $maxCol + 1, '');
            foreach ($cellMap as $idx => $cellValue) {
                $rowValues[$idx] = $cellValue;
            }
            $rowsByNumber[$rowNumber] = $rowValues;
        }

        if (empty($rowsByNumber)) {
            return [];
        }

        ksort($rowsByNumber, SORT_NUMERIC);
        return array_values($rowsByNumber);
    }

    protected function findRelationshipTargetByType(SimpleXMLElement $relsXml, $typeSuffix)
    {
        $relNodes = $relsXml->xpath('//*[local-name()="Relationship"]');
        if (!is_array($relNodes)) {
            return false;
        }

        $needle = '/relationships/' . ltrim((string) $typeSuffix, '/');
        foreach ($relNodes as $rel) {
            $type = (string) $rel['Type'];
            if ($type !== '' && substr($type, -strlen($needle)) === $needle) {
                return (string) $rel['Target'];
            }
        }

        return false;
    }

    protected function findRelationshipTargetByRid(SimpleXMLElement $relsXml, $rid)
    {
        $relNodes = $relsXml->xpath('//*[local-name()="Relationship"]');
        if (!is_array($relNodes)) {
            return false;
        }

        foreach ($relNodes as $rel) {
            if ((string) $rel['Id'] === (string) $rid) {
                return (string) $rel['Target'];
            }
        }

        return false;
    }

    protected function columnIndexFromCellRef($cellRef)
    {
        if (!preg_match('/^([A-Z]+)\d+$/i', (string) $cellRef, $m)) {
            return -1;
        }

        $letters = strtoupper($m[1]);
        $index = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    protected function dirnameZip($path)
    {
        $path = $this->normalizeZipPath('', $path);
        $pos = strrpos($path, '/');
        if ($pos === false) {
            return '';
        }
        return substr($path, 0, $pos);
    }

    protected function normalizeZipPath($baseDir, $target)
    {
        $target = str_replace('\\', '/', (string) $target);
        $baseDir = str_replace('\\', '/', (string) $baseDir);

        if ($target === '') {
            return '';
        }

        if (substr($target, 0, 1) === '/') {
            $full = ltrim($target, '/');
        } else {
            $prefix = trim($baseDir, '/');
            $full = $prefix === '' ? $target : ($prefix . '/' . $target);
        }

        $parts = explode('/', $full);
        $normalized = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if (!empty($normalized)) {
                    array_pop($normalized);
                }
                continue;
            }
            $normalized[] = $part;
        }

        return implode('/', $normalized);
    }
}
?>
