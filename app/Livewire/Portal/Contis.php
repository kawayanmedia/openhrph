<?php

namespace App\Livewire\Portal;

use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

class Contis extends Component
{
    public $title = 'Dashboard';

    public $filename;

    public function render()
    {
        $today = now()->format('Ymd');

        $yesterday = now()->yesterday()->format('Ymd');

        // Check if folder exists
        if (is_dir($today)) {
            $this->comment('today Folder exist');

            // Get the unit number
            $unitNumber = 125;

            $filename = $today.'-'.$unitNumber.'.zip'; // Name of our archive to download

            // $recordExist = $this->recordExists($today, $unitNumber);

            // dd($this->dowloaded($today, $unitNumber));

            dd($this->addRecord($today, $unitNumber, $filename));

            $zipSuccess = false;
            // If not exists in server
            if (! $recordExist) {
                // $zipSuccess = $this->zipFolder($today, $unitNumber, $filename);

                if ($zipSuccess) {

                }

                // zip and upload the file

                $this->addRecord($today, $unitNumber, $filename);
                // Check if exists in the API
            }

            // $extractZip = $this->extractZip($filename, $unitNumber);

        }

        if (is_dir($yesterday)) {
            $this->comment('yesterday Folder exist');

            // Zip the folder

            // Check if exists in the API
        }

        $this->comment('Payroll Frequency111: ');

        return view('livewire.portal.dashboard')
            ->title($this->title)->layout('layouts.portal');
    }

    public function comment($comment)
    {
        Debugbar::info($comment);
    }

    public function recordExists($date, $unitNumber)
    {
        $http = Http::get('https://app.hris.test/contis/api/exists?date='.$date.'&unit_number='.$unitNumber);

        return $http->json()['exists'];
    }

    public function addRecord($date, $unitNumber, $filename)
    {
        $http = Http::attach('zip_file', file_get_contents($filename), $filename)
            ->post('https://app.hris.test/contis/api/store?date='.$date.'&unit_number='.$unitNumber.'&filename='.$filename);

        return $http->json()['saved'];
    }

    public function dowloaded($date, $unitNumber)
    {
        $http = Http::get('https://app.hris.test/contis/api/downloaded?date='.$date.'&unit_number='.$unitNumber);

        return $http->json()['downloaded'];
    }

    public function notDowloaded($date, $unitNumber)
    {
        $http = Http::get('https://app.hris.test/contis/api/not-downloaded');

        return $http->json();
    }

    public function zipFolder($date, $unitNumber, $filename)
    {
        // https://laraveldaily.com/post/how-to-create-zip-archive-with-files-and-download-it-in-laravel
        // Get the unit number
        // $unitNumber = 125;

        // Zip the folder
        $zip_file = $date.'-'.$unitNumber.'.zip'; // Name of our archive to download

        $this->filename = $zip_file;

        // Initializing PHP class
        $zip = new \ZipArchive();
        $zip->open($zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $path = $date;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        foreach ($files as $name => $file) {
            // We're skipping all subfolders
            if (! $file->isDir()) {
                // $filePath = $file->getRealPath();

                // Use this!!!!
                $zip->addFile($file);
            }
        }
        $zip->close();

        return true;
    }

    public function extractZip($filename, $directory)
    {
        $zip = new \ZipArchive;
        if ($zip->open($filename) === true) {
            // $zip->extractTo('/my/destination/dir/');
            $zip->extractTo($directory);
            $zip->close();
            echo 'ok';
        } else {
            echo 'failed';
        }

        return true;
    }
}

// create a poll that check current date and date yesterday
// check if folder exists example 20230825 20230825
// check on line if already uploaded the branch id and date
// upload if not (zip file)

// create entry in API server

// date, branch id, is_downloaded=false

// server side

// get all entries that not yet is_downloaded=false
// download zip files and extract to corresponding folder based
// on branch id

// update the is_downloaded=true
