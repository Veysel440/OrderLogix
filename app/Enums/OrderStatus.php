<?php

namespace App\Enums;
enum OrderStatus:string { case PENDING='PENDING'; case CONFIRMED='CONFIRMED'; case CANCELLED='CANCELLED'; case FULFILLED='FULFILLED'; }
