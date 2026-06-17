<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $coupon_id
 * @property int $user_id
 * @property int $order_id
 */
final class CouponRedemption extends Model
{
    protected $fillable = ['coupon_id', 'user_id', 'order_id'];
}
