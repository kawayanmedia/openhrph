<?php

namespace App\Sectors;

/**
 * Public-sector policy: GSIS-covered, CSC/DBM rules and statutory bonuses.
 *
 * Behaviour that differs from the private sector lives in config/sectors.php;
 * this class exists so callers can type-hint a distinct policy and so future
 * Government-only rules have an obvious home.
 */
final class GovernmentSector extends ConfigurableSector {}
