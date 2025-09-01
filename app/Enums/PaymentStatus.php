<?php

namespace App\Enums;
enum PaymentStatus:string {
    case PENDING='PENDING';
    case AUTHORIZED='AUTHORIZED';
    case CAPTURED='CAPTURED';
    case FAILED='FAILED';
    case REFUNDED='REFUNDED';
}
