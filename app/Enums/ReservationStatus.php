<?php
namespace App\Enums;
enum ReservationStatus:string { case PENDING='PENDING'; case RESERVED='RESERVED'; case FAILED='FAILED'; case RELEASED='RELEASED'; }
