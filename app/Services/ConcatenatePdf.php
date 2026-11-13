<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;

class ConcatenatePdf extends FPDI
{
    // ------------------------------------------------------------------------

    public $files;

    public $orientation = [];

    public $default_orientation = 'P';

    // ------------------------------------------------------------------------

    public function setFiles($files)
    {
        $this->files = $files;
    }

    // ------------------------------------------------------------------------

    public function concat()
    {
        foreach ($this->files as $key => $file) {
            $pagecount = $this->setSourceFile($file);

            for ($i = 1; $i <= $pagecount; $i++) {
                $tplidx = $this->ImportPage($i);
                $s = $this->getTemplatesize($tplidx);

                $orientation = 'P';

                // If the array key is not numeric
                // might be 'P' or 'L' portrait or
                // landscape.
                if (! is_numeric($key)) {
                    $orientation = $key;
                }

                if ($this->default_orientation == 'L') {
                    $orientation = 'L';
                }

                //$this->AddPage('P', array($s['w'], $s['h'])); // orig
                // $this->AddPage($orientation, [$s['w'], $s['h']]);
                $this->AddPage($orientation, [$s['width'], $s['height']]);
                $this->useTemplate($tplidx);
            }
        }
    }
}
