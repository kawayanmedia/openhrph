<?php

namespace App\Support\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;
use Throwable;

class TenantPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media).'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media).'/responsive-images/';
    }

    protected function getBasePath(Media $media): string
    {
        $prefix = config('media-library.prefix', '');

        $companyId = $media->custom_properties['company_id'] ?? $this->companyId($media);

        if ($companyId === null) {
            // Legacy media that predates tenancy keeps its original path.
            return $prefix !== '' ? $prefix.'/'.$media->getKey() : (string) $media->getKey();
        }

        $path = 'companies/'.$companyId.'/'.$media->getKey();

        return $prefix !== '' ? $prefix.'/'.$path : $path;
    }

    private function companyId(Media $media): ?int
    {
        try {
            $companyId = $media->model?->company_id;
        } catch (Throwable) {
            $companyId = null;
        }

        return $companyId !== null ? (int) $companyId : null;
    }
}
