<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class JsonService
{
    private $base;
    private $translation;
    public $obsoleteFiles = [];
    public $differentFormatFiles = [];
    public $allFiles = [];
    public $translationFiles = [];
    public $nonTranslationFiles = [];
    public $storage = 'public/sdv/';
    public $baseUnfinished;
    public $baseResult;

    public function __construct($base, $translation)
    {
        $this->base = $base;
        $this->translation = $translation;
        $this->baseResult = $this->storage . "Result";
        $this->baseUnfinished = $this->storage . "Unfinished";
    }

    public function convertObjects() {
        $arrayOfData = json_decode($this->translation, true);
        $result = [];
        $blacklist = [];
        foreach ($arrayOfData as $key => $value) {
            $explodedData = explode('/', $value);

            if (
                !is_string($explodedData[0])
                || !is_string($explodedData[4])
                || !is_string($explodedData[5])
                || $explodedData[0] == "???"
                || $explodedData[4] == "???"
                || $explodedData[5] == "???"
            ) {
                continue;
            }

            $filteredString = str_replace("'", "", $explodedData[0]);
            $filteredString = str_replace(":", "", $filteredString);
            $filteredString = str_replace(".", "", $filteredString);
            $filteredString = str_replace("(", "", $filteredString);
            $filteredString = str_replace(")", "", $filteredString);


            $title = Str::ucfirst(Str::camel($filteredString));
            $title = str_replace("Of", "of", $title);


            if (in_array($title, $blacklist)) {
                continue;
            }

            if (!empty($result[$title . "_Name"])) {
                unset($result[$title . "_Name"]);
                unset($result[$title . "_Description"]);
                $blacklist[] = $title;
                continue;
            }

            $name = $explodedData[4];
            $description = $explodedData[5];

            $result[$title . "_Name"] = $name;
            $result[$title . "_Description"] = $description;
        }

        return $result;
    }

    public function convertWeapons() {
        $arrayOfData = json_decode($this->translation, true);
        $result = [];
        $blacklist = [];
        foreach ($arrayOfData as $key => $value) {
            $explodedData = explode('/', $value);

            if (
                !is_string($explodedData[0])
                || !is_string($explodedData[14])
                || !is_string($explodedData[1])
                || $explodedData[0] == "???"
                || $explodedData[14] == "???"
                || $explodedData[1] == "???"
            ) {
                continue;
            }

            $filteredString = str_replace("'", "", $explodedData[0]);
            $filteredString = str_replace(":", "", $filteredString);
            $filteredString = str_replace(".", "", $filteredString);
            $filteredString = str_replace("(", "", $filteredString);
            $filteredString = str_replace(")", "", $filteredString);

            $title = Str::ucfirst(Str::camel($filteredString));
            $title = str_replace("Of", "of", $title);


            if (in_array($title, $blacklist)) {
                continue;
            }

            if (!empty($result[$title . "_Name"])) {
                unset($result[$title . "_Name"]);
                unset($result[$title . "_Description"]);
                $blacklist[] = $title;
                continue;
            }

            $name = $explodedData[14];
            $description = $explodedData[1];

            $result[$title . "_Name"] = $name;
            $result[$title . "_Description"] = $description;
        }

        return $result;
    }

    public function map($base = null, $translation = null) {
        $base = json_decode($base ?? $this->base, true);
        $translation = json_decode($translation ?? $this->translation, true);
        $result = $base;
        $unMapped = $base;
        $leftOver = $translation;

        foreach ($base as $key => $value) {
            if (isset($translation[$key])) {
                $result[$key] = $translation[$key];
                unset($unMapped[$key]);
                unset($leftOver[$key]);
            }
        }

        return [
            "result" => $result,
            "unmapped" => $unMapped,
            "leftover" => $leftOver
        ];
    }

    public function mergeFinished()
    {
        Log::info("Proses merging finished folder dimulai");

        Log::info("Menghapus folder FinishedResult");
        Storage::deleteDirectory($this->storage . $this->translation . $this->base);

        $baseStorage = $this->storage . $this->base;
        $baseTranslation = $this->storage . $this->translation;

        Log::info("Mengcopy file base dari Content yang sudah di update tapi belum di translate");
        $allFiles = Storage::allFiles($baseStorage);
        //Copy base file to final folder
        foreach ($allFiles as $file) {
            $finishedPath = str_replace($this->base, $this->translation . $this->base, $file);
            Storage::copy($file, $finishedPath);
        }

        Log::info("Mulai mapping data");
        $allTranslatedFiles = Storage::allFiles($baseTranslation);
        foreach ($allTranslatedFiles as $translatedFilePath) {
            $finishedPath = str_replace($this->translation, $this->translation . $this->base, $translatedFilePath);
            $translatedFile = Storage::get($translatedFilePath);

            $targetFilePath = str_replace($this->translation, $this->base, $translatedFilePath);

            if (!Storage::exists($targetFilePath)) {
                continue;
            }

            $targetFile = Storage::get($targetFilePath);

            Log::info("Mapping file $translatedFilePath");
            $result = $this->map($targetFile, $translatedFile);
            $this->writeToDisk(null, $finishedPath, $result['result']);
        }
        Log::info("Mapping sukses");
        Log::info("Proses merging finished folder selesai");

        return ['result' => "success"];
    }

    public function merge()
    {
        Log::info("Proses merging dimulai");
        $baseStorage = $this->storage . $this->base;
        $baseTranslation = $this->storage . $this->translation;

        $this->allFiles = Storage::allFiles($baseTranslation);
        $this->translationFiles = $this->filterNonJsonFiles();
        $this->nonTranslationFiles = $this->filterJsonFiles();

        $this->filterObsoleteFiles()
            ->filterDifferentFormatFiles()
            ->mapTranslationFilesToBase()
            ->copyNonJsonFiles();

        Log::info("Proses merging dimulai");

        return ['result' => "success"];
    }

    private function copyNonJsonFiles()
    {
        Log::info("Menyalin kembali file non json");
        foreach ($this->nonTranslationFiles as $nonTranslationFile) {
            $resultPath = str_replace($this->storage . $this->translation, $this->baseResult, $nonTranslationFile);
            Storage::copy($nonTranslationFile, $resultPath);
        }
    }

    private function mapTranslationFilesToBase()
    {
        Log::info("Mulai mapping data");
        foreach ($this->translationFiles as $translationFile) {
            Log::info("Mapping file $translationFile");
            $baseFile = str_replace($this->translation, $this->base, $translationFile);
            $resultPath = str_replace($this->storage . $this->translation, $this->baseResult, $translationFile);
            $unfinishedPath = str_replace($this->storage . $this->translation, $this->baseUnfinished, $translationFile);
            $unfinishedPathLeftover = str_replace(".json", "_leftover.json", $unfinishedPath);

            $baseFile = Storage::get($baseFile);
            $translationFile = Storage::get($translationFile);
            $mapResult = $this->map($baseFile, $translationFile);

            $this->writeToDisk(null, $resultPath, $mapResult["result"]);
            if (!empty($mapResult["unmapped"])) {
                $this->writeToDisk(null, $unfinishedPath, $mapResult["unmapped"]);
                Log::info("Terdapat data yang belum di translasi");
            }
            if (!empty($mapResult["leftover"])) {
                $this->writeToDisk(null, $unfinishedPathLeftover, $mapResult["leftover"]);
                Log::info("Terdapat data translasi yang tidak ter map");
            }
        }
        Log::info("Proses mapping selesai");

        return $this;
    }

    private function filterNonJsonFiles()
    {
        Log::info("Membuang file non json");
        return array_filter($this->allFiles, function ($file) {
            return Str::endsWith($file, '.json');
        });
    }

    private function filterJsonFiles()
    {
        return array_filter($this->allFiles, function ($file) {
            return !Str::endsWith($file, '.json');
        });
    }

    private function filterObsoleteFiles()
    {
        Log::info("Pengecekan file yang tidak tersedia di base");
        foreach ($this->translationFiles as $key => $translationFile) {
            Log::info("Cek file $translationFile");
            $updatedFile = str_replace($this->translation, $this->base, $translationFile);
            if (!Storage::exists($updatedFile)) {
                $this->obsoleteFiles[] = $translationFile;
                unset($this->translationFiles[$key]);
                Log::info("File tidak tersedia di base");
            }
        }
        Log::info("Pengecekan file yang tidak tersedia di base sukses");

        $this->writeToDisk($this->baseUnfinished, "/obsolete_files.json", $this->obsoleteFiles);

        return $this;
    }

    private function filterDifferentFormatFiles()
    {
        Log::info("Mulai cek format file");
        foreach ($this->translationFiles as $key => $translationFile) {
            Log::info("Cek kesamaan format file $translationFile");
            $updatedFile = str_replace($this->translation, $this->base, $translationFile);
            $translationFileContent = json_decode(Storage::get($translationFile), true);
            $updatedFileContent = json_decode(Storage::get($updatedFile), true);
            $isSimilar = $this->checkSimilarity($translationFileContent, $updatedFileContent);
            if (!$isSimilar) {
                $this->differentFormatFiles[] = $translationFile;
                unset($this->translationFiles[$key]);
                Log::info("Format tidak sama");
            }
        }
        Log::info("Pengecekan format sukses");

        $this->writeToDisk($this->baseUnfinished, "/different_format_files.json", $this->differentFormatFiles);

        return $this;
    }

    private function checkSimilarity(array $translationFileContent, array $updatedFileContent): bool
    {
        $result = false;
        $totalLines = count($translationFileContent);
        $comparator = floor($totalLines / 2);
        $similarIndex = 0;
        $similarValue = 0;

        foreach ($translationFileContent as $key => $value) {
            if (!isset($updatedFileContent[$key])) {
                continue;
            }

            $updatedValue = $updatedFileContent[$key];
            if (isset($updatedValue)) {
                $similarIndex++;
            }

            if (!is_string($value) || !is_string($updatedValue)) {
                continue;
            }

            $isValueContainSlash = Str::contains($value, '/');
            $isUpdatedValueContainSlash = Str::contains($updatedValue, '/');
            $similarSlashValue = ($isUpdatedValueContainSlash && $isValueContainSlash)
                || (!$isUpdatedValueContainSlash && !$isValueContainSlash);
            if (!$similarSlashValue) {
                continue;
            }

            if(
                str_contains($updatedValue, '[')
                && str_contains($updatedValue, ']')
                && str_contains($updatedValue, 'Local')
            ) {
                continue;
            }

            if ($isUpdatedValueContainSlash && $isValueContainSlash) {
                $arrayUpdatedValue = explode('/', $updatedValue);
                $arrayValue = explode('/', $value);

                if (count($arrayUpdatedValue) !== count($arrayValue)) {
                    continue;
                }

                foreach ($arrayUpdatedValue as $key => $updatedValue) {
                    if (
                        (empty($updatedValue) && !empty($arrayValue[$key]))
                        || (!empty($updatedValue) && empty($arrayValue[$key]))
                    ) {
                        continue 2;
                    }
                }
            }

            $similarValue++;
        }

        if ($similarIndex >= $comparator && $similarValue >= $comparator) {
            $result = true;
        }

        return $result;
    }

    private function writeToDisk($basePath = null, $path, $data)
    {
        Log::info("Membuat file json: " . $basePath . $path);
        return Storage::put(
            $basePath . $path,
            json_encode(
                $data,
                JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_FORCE_OBJECT
            )
        );
    }
}
