<?php

namespace OpenVRE;


/**
 * State of an openVRE export request through the Airlock.
 */
enum AirlockExportState: string
{
    case MoveRequested = "MOVE_REQUESTED";
    case Ready = "READY";
    case Submitted = "SUBMITTED";
    case Failed = "FAILED";
}
