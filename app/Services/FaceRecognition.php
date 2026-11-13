<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class FaceRecognition
{
    public $client;

    public $collectionId = 'company1';

    public $result;

    public function __construct()
    {
        $this->client = new \Aws\Rekognition\RekognitionClient(config('rekognition'));
    }

    public function setCollectionId($companyId)
    {
        $this->collectionId = $companyId;
    }

    public function createCollection()
    {
        $result = $this->client->createCollection([
            'CollectionId' => $this->collectionId, // REQUIRED
            // 'Tags' => ['<string>', ...],
        ]);

        $this->result = $result;

        return $result;
    }

    public function listCollection()
    {
        $result = $this->client->listCollections([
            'MaxResults' => 100,
            // 'NextToken' => '<string>',
        ]);

        $this->result = $result;

        return $result;
    }

    // Add face to collection
    public function addFace($employeeId, $imageName)
    {
        // Add Image to collection
        $result = $this->client->indexFaces([
            'CollectionId' => $this->collectionId, // REQUIRED
            'ExternalImageId' => (string) $employeeId,
            'Image' => [ // REQUIRED
                'Bytes' => Storage::get($imageName),
            ],
            'MaxFaces' => 1,
            'QualityFilter' => 'MEDIUM',
            // 'QualityFilter' => 'NONE|AUTO|LOW|MEDIUM|HIGH',
        ]);

        $this->result = $result;

        return $result;
    }

    public function listFaces()
    {
        $result = $this->client->listFaces([
            'CollectionId' => $this->collectionId, // REQUIRED
            // 'MaxResults' => <integer>,
            // 'NextToken' => '<string>',
        ]);

        $this->result = $result;

        return $result;
    }

    // Search faces on collection
    public function searchFaces($imageName)
    {
        $result = $this->client->searchFacesByImage([
            'CollectionId' => $this->collectionId, // REQUIRED
            'FaceMatchThreshold' => 80,
            'Image' => [ // REQUIRED
                'Bytes' => Storage::get($imageName),
                // 'S3Object' => [
                //     'Bucket' => '<string>',
                //     'Name' => '<string>',
                //     'Version' => '<string>',
                // ],
            ],
            'MaxFaces' => 3,
            'QualityFilter' => 'MEDIUM',
        ]);

        $this->result = $result;

        return $result;
    }

    public function faceExists()
    {
        if (isset($this->result['FaceMatches'][0]['Face']['FaceId'])) {
            return true;
        }

        return false;
    }

    public function deleteFaces($faceIds)
    {
        $result = $this->client->deleteFaces([
            'CollectionId' => $this->collectionId, // REQUIRED
            'FaceIds' => $faceIds, // REQUIRED
            // 'FaceIds' => ['6c6b3140-c461-408c-ab32-223530491954', 'bf1f9878-729b-4b5c-aef9-55a342266804'], // REQUIRED
        ]);

        return $result;
    }
}
