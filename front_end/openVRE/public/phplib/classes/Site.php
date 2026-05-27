<?php

namespace OpenVRE;


enum Site: string
{
    case Local = "local";
    case SSH = "SSH";
    case Swift = "Swift";
    case EGA = "EGA";
    case MareNostrum = "MareNostrum";
}
