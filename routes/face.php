<?php

use App\Models\Employee;
use App\Services\FaceRecognition;
use Aws\Rekognition\RekognitionClient;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// https://docs.aws.amazon.com/rekognition/latest/dg/setting-up.html#setting-up-iam
// https://docs.aws.amazon.com/rekognition/latest/dg/collections.html
// https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-rekognition-2016-06-27.html#searchfacesbyimage
Route::get('/face/recognition', function () {
    abort_unless(auth()->check() || app()->isLocal(), 403);

    $client = new RekognitionClient(config('rekognition'));

    // List faces
    // $result = $client->listFaces([
    //     'CollectionId' => 'company1', // REQUIRED
    // ]);

    // dd($result);
    // Delete faces
    // $result = $client->deleteFaces([
    //     'CollectionId' => 'company1', // REQUIRED
    //     'FaceIds' => ['c501123e-1f77-4976-ac7e-c29fe1514375', 'cacda095-edf9-4223-aaba-5dd2cbf030f0', 'eb9b22c3-a2f5-4c24-ad34-4ae9c3ea9a56']
    // ]);

    // dd($result);

    // Create collection
    // This is where we store our templates or images
    // $result = $client->createCollection([
    //     'CollectionId' => 'company2', // REQUIRED
    //     // 'Tags' => ['<string>', ...],
    // ]);

    // List
    // $result = $client->listCollections([
    //     'MaxResults' => 100,
    //     // 'NextToken' => '<string>',
    // ]);

    // dd(Storage::get('james.jpeg'));

    // Add Image to collection
    // $result = $client->indexFaces([
    //     'CollectionId' => 'company1', // REQUIRED
    //     // 'DetectionAttributes' => ['bon jovi', 'test'],
    //     'ExternalImageId' => '1',
    //     'Image' => [ // REQUIRED
    //         'Bytes' => Storage::get('james.jpeg'),
    //         // 'S3Object' => [
    //         //     'Bucket' => '<string>',
    //         //     'Name' => '<string>',
    //         //     'Version' => '<string>',
    //         // ],
    //     ],
    //     'MaxFaces' => 1,
    //     'QualityFilter' => 'MEDIUM',
    //     // 'QualityFilter' => 'NONE|AUTO|LOW|MEDIUM|HIGH',
    // ]);

    // $employee = Employee::first();
    // $employee->face_image_uuid = $result['FaceRecords'][0]['Face']['FaceId'];
    // $employee->save();

    // dd($result);

    // dd($result['FaceRecords'][0]['Face']['FaceId']);

    // List faces
    // $result = $client->listFaces([
    //     'CollectionId' => 'company1', // REQUIRED
    //     // 'MaxResults' => <integer>,
    //     // 'NextToken' => '<string>',
    // ]);

    // Search
    $result = $client->searchFacesByImage([
        'CollectionId' => 'company1', // REQUIRED
        'FaceMatchThreshold' => 80,
        'Image' => [ // REQUIRED
            'Bytes' => Storage::get('james.jpeg'),
            // 'S3Object' => [
            //     'Bucket' => '<string>',
            //     'Name' => '<string>',
            //     'Version' => '<string>',
            // ],
        ],
        'MaxFaces' => 3,
        'QualityFilter' => 'MEDIUM',
    ]);

    $employee = Employee::where('face_image_uuid', $result['FaceMatches'][0]['Face']['FaceId'])->first();

    dd($employee->id);
    // $employee->face_image_uuid =

    // Delete faces
    // $result = $client->deleteFaces([
    //     'CollectionId' => 'company1', // REQUIRED
    //     'FaceIds' => ['6c6b3140-c461-408c-ab32-223530491954', 'bf1f9878-729b-4b5c-aef9-55a342266804'], // REQUIRED
    // ]);

    // dd($result);
    dd($result['FaceMatches'][0]['Face']);
    dd($result['FaceMatches'][0]['Face']['ExternalImageId']);

    // face id: c3d86435-551e-4f3c-a7ed-d1c1672c9f9b
    // image id: fd254d6f-13f5-3767-b125-f1700d970f5d

});

Route::get('/face/attendance', function () {
    abort_unless(auth()->check() || app()->isLocal(), 403);

    return view('face');
});

Route::post('/face/register', function () {
    abort_unless(auth()->check() || app()->isLocal(), 403);

    $employee = Employee::find(request('employeeId'));

    if ($employee == null) {
        return [];
    }

    $face = new FaceRecognition;

    // https://usefulangle.com/post/352/javascript-capture-image-from-camera
    // https://laracasts.com/discuss/channels/laravel/create-image-from-base64-string-laravel
    $imageName = Str::random(36).'.'.'jpg';

    Storage::put($imageName, base64_decode(str_replace(' ', '+', str_replace('data:image/jpeg;base64,', '', request('image')))));

    // Check first of the face is already enrolled
    $face->searchFaces($imageName);

    if (isset($face->result['FaceMatches'][0]['Face']['FaceId'])) {
        // Delete after check
        Storage::delete($imageName);

        return ['already enrolled'];
    }

    $face->addFace($employee->id, $imageName);

    $employee->face_image_uuid = $face->result['FaceRecords'][0]['Face']['FaceId'];
    $employee->save();

    // Delete after check
    Storage::delete($imageName);

    return ['okay'];

})->name('face_register');

Route::post('/face/check', function () {
    abort_unless(auth()->check() || app()->isLocal(), 403);

    // https://usefulangle.com/post/352/javascript-capture-image-from-camera
    // https://laracasts.com/discuss/channels/laravel/create-image-from-base64-string-laravel
    $imageName = Str::random(36).'.'.'jpg';

    Storage::put($imageName, base64_decode(str_replace(' ', '+', str_replace('data:image/jpeg;base64,', '', request('image')))));

    $face = new FaceRecognition;

    // Check first of the face is already enrolled
    $face->searchFaces($imageName);

    if (isset($face->result['FaceMatches'][0]['Face']['FaceId'])) {
        $employee = Employee::where('face_image_uuid', $face->result['FaceMatches'][0]['Face']['FaceId'])->first();

        // Delete after check
        Storage::delete($imageName);

        // TODO login using creds
        // employee_id
        // time
        // am_login or pm_login /etc
        return [
            'data' => [
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'face_image_uuid' => $employee->face_image_uuid,
                'aws_image_id' => $face->result['FaceMatches'][0]['Face']['ImageId'],
                'image' => $employee->image,
            ],
        ];
    }

})->name('face_check');
