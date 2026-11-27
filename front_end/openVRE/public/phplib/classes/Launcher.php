<?php

namespace OpenVRE;


enum Launcher: string
{
    case SGE = "SGE";
    case docker_SGE = "docker_SGE";
    case docker_SGE_EGA = "docker_SGE_EGA";
    case slurm = "Slurm_Singularity";
    case kubernetes_native = "kubernetes_native";
}
