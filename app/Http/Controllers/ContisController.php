<?php

namespace App\Http\Controllers;

use App\Models\Contis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContisController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Get the uploaded file
        if ($request->hasFile('zip_file')) {
            $request->zip_file->storeAs('zip_files', $request->filename);
        }
        
        $record = Contis::firstOrNew(['date' => $request->date, 'unit_number' => $request->unit_number]);
        $record->file_name = $request->filename;
        $record->save();

        return ['saved' => true];
    }

    public function download(Request $request)
    {
        $record = Contis::find($request->id);

        // dd(Storage::exists('zip_files/' . $record->file_name));

        if ($record) {
            return response()->download(storage_path('app/zip_files/' . $record->file_name));
        }
        return ['saved' => true];
    }

    public function exists(Request $request)
    {
        $exist = $this->recordExists($request->date, $request->unit_number);

        return ['exists' => $exist];
    }

    public function recordExists($date, $unitNumber)
    {
        $exists = Contis::where('date', $date)->where('unit_number', $unitNumber)->first();

        return (bool) $exists;
    }

    public function downloaded(Request $request)
    {
        $exists = Contis::where('date', $request->date)->where('unit_number', $request->unit_number)->update(['is_downloaded' => true]);

        return ['downloaded' => $exists];
    }

    public function notDownloaded(Request $request)
    {
        $record = Contis::where('is_downloaded', false)->first();

        if ($record) {
            return [
                'id' => $record->id,
                'unit_number' => $record->unit_number,
                'date' => str_replace('-', '', $record->date),
                'real_date' => $record->date,
                'file_name' => $record->file_name,
            ];
        }

        return [
            'id' => 0,
            'unit_number' => 0,
            "file_name" => 0,
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Contis $contis)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Contis $contis)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contis $contis)
    {
        //
    }
}
