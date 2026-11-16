<?php

namespace OpenVRE;


/**
 * State of an Airlock landing artifact set (LandingArtifactSetState in the Airlock API).
 */
enum LandingArtifactSetState: string
{
    case Verifying = "VERIFYING";
    case ReadyForSubmission = "READY_FOR_SUBMISSION";
    case Expired = "EXPIRED";
    case Quarantined = "QUARANTINED";
    case Failed = "FAILED";
}
