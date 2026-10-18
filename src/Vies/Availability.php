<?php

declare(strict_types=1);

namespace GoSuccess\VatId\Vies;

/**
 * Whether VIES can currently answer for a member state.
 */
enum Availability: string
{
    case Available = 'Available';
    case Unavailable = 'Unavailable';
    case MonitoringDisabled = 'Monitoring Disabled';
}
