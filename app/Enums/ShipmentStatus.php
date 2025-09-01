<?php
namespace App\Enums;
enum ShipmentStatus:string
{
    case PENDING='PENDING';
    case SCHEDULED='SCHEDULED';
    case SHIPPED='SHIPPED';
    case DELIVERED='DELIVERED';
    case CANCELLED='CANCELLED';
}
