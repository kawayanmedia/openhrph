<?php

namespace App\Sectors;

/**
 * Private-sector policy: SSS-covered, Labor Code rules and 13th month pay.
 *
 * Non-Profit companies follow this policy; the distinction is recorded in the
 * company type, not in a separate policy class. Screens and employee fields
 * come from the private feature pack.
 */
final class PrivateSector extends ConfigurableSector {}
