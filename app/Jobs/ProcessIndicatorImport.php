<?php

namespace App\Jobs;

use App\Imports\IndicatorDataValuesImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIndicatorImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 Ghanta safe window background execution ke liye
    protected string $filePath;
    protected array $header;

    public function __construct(string $filePath, array $header)
    {
        $this->filePath = $filePath;
        $this->header = $header;
    }

    public function handle()
    {
        $startTime = microtime(true);
        Log::info("=== BACKGROUND QUEUE JOB STARTED ===");

        if (!file_exists($this->filePath)) {
            Log::error("Target CSV file not found at: " . $this->filePath);
            return;
        }

        $handle = fopen($this->filePath, 'r');
        // Main header row skip karein kyunki hum controller se parse karke bhej rahe hain
        fgetcsv($handle, 0, ','); 

        $headerCount = count($this->header);
        $importProcessor = new IndicatorDataValuesImport();
        $rowCount = 0;

        while (($rawRow = fgetcsv($handle, 0, ',')) !== false) {
            if (empty($rawRow) || (count($rawRow) === 1 && $rawRow[0] === null)) {
                continue;
            }

            $rowCount++;
            $rawRowCount = count($rawRow);

            // Mismatch alignment check
            if ($rawRowCount < $headerCount) {
                $rawRow = array_pad($rawRow, $headerCount, null);
            } elseif ($rawRowCount > $headerCount) {
                $rawRow = array_slice($rawRow, 0, $headerCount);
            }

            $row = array_combine($this->header, $rawRow);
            $importProcessor->model($row);

            // 5,000 records par BigQuery push
            if ($rowCount % 5000 === 0) {
                $importProcessor->flushToBigQuery();
            }
        }

        fclose($handle);
        $importProcessor->flushToBigQuery(); // Remaining clear

        // Kaam khatam hone ke baad temporary uploaded file delete karein cost bachane ke liye
        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }

        $executionTime = round(microtime(true) - $startTime, 2);
        Log::info("=== BACKGROUND QUEUE JOB COMPLETED ===", [
            'total_processed' => $rowCount,
            'time_taken' => $executionTime . 's'
        ]);
    }
}