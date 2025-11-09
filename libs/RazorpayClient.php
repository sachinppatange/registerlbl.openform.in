<?php
/**
 * RazorpayClient - Wrapper around Razorpay PHP SDK
 * 
 * Provides simplified methods for creating orders and verifying payment signatures.
 */

namespace App;

use Razorpay\Api\Api;
use Exception;

class RazorpayClient {
    private $api;
    private $keyId;
    private $keySecret;

    /**
     * Initialize Razorpay client with credentials.
     * 
     * @param string $keyId Razorpay Key ID
     * @param string $keySecret Razorpay Key Secret
     * @throws Exception if credentials are empty
     */
    public function __construct(string $keyId, string $keySecret) {
        if (empty($keyId) || empty($keySecret)) {
            throw new Exception('Razorpay credentials not configured');
        }
        
        $this->keyId = $keyId;
        $this->keySecret = $keySecret;
        $this->api = new Api($keyId, $keySecret);
    }

    /**
     * Create a Razorpay order.
     * 
     * @param int $amount Amount in paise (smallest currency unit)
     * @param string $currency Currency code (e.g., INR)
     * @param string $receipt Receipt/order reference ID
     * @param array $notes Optional notes to attach to the order
     * @return array Order data including order_id
     * @throws Exception on API error
     */
    public function createOrder(int $amount, string $currency, string $receipt, array $notes = []): array {
        try {
            $orderData = [
                'amount' => $amount,
                'currency' => $currency,
                'receipt' => $receipt,
                'notes' => $notes
            ];
            
            $order = $this->api->order->create($orderData);
            
            return [
                'id' => $order->id,
                'entity' => $order->entity,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'receipt' => $order->receipt,
                'status' => $order->status,
                'created_at' => $order->created_at
            ];
        } catch (Exception $e) {
            error_log('Razorpay createOrder error: ' . $e->getMessage());
            throw new Exception('Failed to create order: ' . $e->getMessage());
        }
    }

    /**
     * Verify Razorpay payment signature.
     * 
     * @param array $attributes Array with razorpay_order_id, razorpay_payment_id, razorpay_signature
     * @return bool True if signature is valid, false otherwise
     */
    public function verifySignature(array $attributes): bool {
        try {
            $this->api->utility->verifyPaymentSignature($attributes);
            return true;
        } catch (Exception $e) {
            error_log('Razorpay signature verification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Razorpay Key ID for client-side usage.
     * 
     * @return string Key ID
     */
    public function getKeyId(): string {
        return $this->keyId;
    }
}
