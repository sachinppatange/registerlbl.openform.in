<?php
// Minimal Razorpay wrapper. Requires composer dependency: razorpay/razorpay
// Use: composer require razorpay/razorpay
namespace App\Libs;

require_once __DIR__ . '/../vendor/autoload.php'; // composer autoload

use Razorpay\Api\Api;
use Exception;

class RazorpayClient
{
    private $api;

    public function __construct()
    {
        $key = defined('RZP_KEY_ID') ? RZP_KEY_ID : getenv('RZP_KEY_ID');
        $secret = defined('RZP_KEY_SECRET') ? RZP_KEY_SECRET : getenv('RZP_KEY_SECRET');
        if (empty($key) || empty($secret)) {
            throw new Exception('Razorpay credentials not configured in environment.');
        }
        $this->api = new Api($key, $secret);
    }

    /**
     * Create an order on Razorpay
     * @param int $amountPaise amount in paise (integer)
     * @param string $currency 'INR'
     * @param string $receipt unique receipt id
     * @return array order object as array
     */
    public function createOrder(int $amountPaise, string $currency, string $receipt): array
    {
        $orderData = [
            'amount' => $amountPaise,
            'currency' => $currency,
            'receipt' => $receipt,
            'payment_capture' => 1 // auto-capture
        ];
        $order = $this->api->order->create($orderData);
        return $order->toArray();
    }

    /**
     * Verify payment signature (server-side)
     * @param array $attributes ['razorpay_order_id' => ..., 'razorpay_payment_id' => ..., 'razorpay_signature' => ...]
     * @throws Exception on verification failure
     */
    public function verifySignature(array $attributes): void
    {
        // The SDK exposes utility->verifyPaymentSignature
        $this->api->utility->verifyPaymentSignature($attributes);
    }
}