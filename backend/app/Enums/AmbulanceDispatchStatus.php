<?php

namespace App\Enums;

enum AmbulanceDispatchStatus: string
{
    case Dispatched = 'dispatched';
    case EnRoute = 'en_route';
    case OnScene = 'on_scene';
    case Transporting = 'transporting';
    case Delivered = 'delivered';
}
