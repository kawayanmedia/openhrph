<?php

namespace App\Sectors;

/**
 * Public-sector policy: GSIS-covered, CSC/DBM rules and statutory bonuses.
 *
 * Data differences live in the jurisdiction's Config/sector.php. Screens,
 * commands, reports and employee fields come from the sector feature pack
 * (App\Jurisdictions\Philippines\Government\GovernmentFeaturePack). This class
 * stays a thin typed policy so callers never depend on a concrete module.
 */
final class GovernmentSector extends ConfigurableSector {}
