<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessIndicatorImport;

class IndicatorDataValueController extends Controller
{
    public function index()
    {
        return view('admin.indicator_data.index');
    }

    public function import(Request $request)
    {
        if (!$request->hasFile('file')) {
            return redirect()->back()->with('error', 'Please select a valid file before uploading.');
        }

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'csv') {
            try {
                // File ko safe storage me move karein taaki background script access kar sake
                $fileName = 'import_' . time() . '_' . uniqid() . '.csv';
                $storagePath = storage_path('app/chunks');
                
                if (!file_exists($storagePath)) {
                    mkdir($storagePath, 0777, true);
                }
                
                $movedFile = $file->move($storagePath, $fileName);
                $fullPath = $movedFile->getRealPath();

                // Validation aur Header extraction fast processing ke liye
                $handle = fopen($fullPath, 'r');
                $header = fgetcsv($handle, 0, ',');
                fclose($handle);

                if (!$header) {
                    if (file_exists($fullPath)) unlink($fullPath);
                    throw new \Exception("Empty or corrupt CSV layout.");
                }

                $header = array_map(function($h) {
                    return strtolower(trim($h));
                }, $header);

                // 🔥 SUPER POWER: Processing offload to background queue worker
                ProcessIndicatorImport::dispatch($fullPath, $header);

                Log::info("CSV Import pipeline successfully offloaded to Queue Background System.", [
                    'file' => $fileName
                ]);

                return redirect()->back()->with('success', 'CSV import process start .......');

            } catch (\Exception $e) {
                Log::error("Queue Dispatch Error: " . $e->getMessage());
                return redirect()->back()->with('error', 'System issue: ' . $e->getMessage());
            }
        }

        return redirect()->back()->with('error', 'Sirf .csv formats allowed hain is pipeline ke liye.');
    }
}