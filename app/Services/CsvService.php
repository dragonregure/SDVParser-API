<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CsvService
{
    private $base;
    private $translation;
    public $obsoleteFiles = [];
    public $differentFormatFiles = [];
    public $allFiles = [];
    public $translationFiles = [];
    public $nonTranslationFiles = [];
    public $storage = 'public/cr/';
    public $baseUnfinished;
    public $baseResult;

    public function __construct($base, $translation)
    {
        $this->base = $base;
        $this->translation = $translation;
        $this->baseResult = $this->storage . "Result";
        $this->baseUnfinished = $this->storage . "Unfinished";
    }

    public function extracts(): array
    {
        $result = [];

        $rawPath = $this->storage . $this->base;
        $rawFiles = Storage::allFiles($rawPath);
        foreach ($rawFiles as $rawFilePath) {
            $rawFile = Storage::get($rawFilePath);
            $lines = str_getcsv($rawFile);
            $textResults = [];
            foreach ($lines as $key => $value) {
                if ($value === "xxxx") {
                    $textResults[] = '"' . $lines[$key - 2] . '";"' . $lines[$key - 1] . '"';
                }
            }
            $extractedPath = str_replace($this->storage . $this->base, $this->storage . $this->translation, $rawFilePath);
            $extractedPath = str_replace('.txt', '.csv', $extractedPath);
            $this->writeToDisk(null, $extractedPath, implode("\n", $textResults));
        }
        $result['result'] = 'success';

        return $result;
    }

    private function writeToDisk($basePath = null, $path, $data)
    {
        Log::info("Membuat file csv: " . $basePath . $path);
        return Storage::put(
            $basePath . $path,
            $data
        );
    }
}
