<?php
/**
 * RazorpayClient - Wrapper for Razorpay PHP SDK
 * Provides simplified methods for payment operations
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/payment_config.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

class RazorpayClient {
    private $api;
    
    public function __construct() {
        if (!validate_payment_config()) {
            throw new Exception('Razorpay credentials not configured. Please set RZP_KEY_ID and RZP_KEY_SECRET environment variables.');
        }
        $this->api = new Api(RZP_KEY_ID, RZP_KEY_SECRET);
    }
    
    /**
     * Create a Razorpay order
     * 
     * @param int $amountPaise Amount in paise (smallest currency unit)
     * @param string $currency Currency code (default: INR)
     * @param string $receipt Receipt/reference ID
     * @return array Order details including order_id
     * @throws Exception
     */
    public function createOrder(int $amountPaise, string $currency = 'INR', string $receipt = ''): array {
        if ($amountPaise <= 0) {
            throw new Exception('Amount must be greater than zero');
        }
        
        $orderData = [
            'receipt'         => $receipt,
            'amount'          => $amountPaise,
            'currency'        => $currency,
            'payment_capture' => 1 // Auto capture
        ];
        
        try {
            $order = $this->api->order->create($orderData);
            return [
                'id' => $order->id,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'receipt' => $order->receipt,
                'status' => $order->status
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to create Razorpay order: ' . $e->getMessage());
        }
    }
    
    /**
     * Verify payment signature after successful payment
     * 
     * @param array $attributes Array with razorpay_order_id, razorpay_payment_id, razorpay_signature
     * @return bool True if signature is valid
     * @throws SignatureVerificationError
     */
    public function verifySignature(array $attributes): bool {
        try {
            $this->api->utility->verifyPaymentSignature($attributes);
            return true;
        } catch (SignatureVerificationError $e) {
            throw $e;
        }
    }
    
    /**
     * Verify webhook signature
     * 
     * @param string $webhookBody Raw webhook body
     * @param string $webhookSignature Signature from X-Razorpay-Signature header
     * @param string $webhookSecret Webhook secret
     * @return bool True if signature is valid
     */
    public function verifyWebhookSignature(string $webhookBody, string $webhookSignature, string $webhookSecret): bool {
        try {
            $expectedSignature = hash_hmac('sha256', $webhookBody, $webhookSecret);
            return hash_equals($expectedSignature, $webhookSignature);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Fetch payment details by payment ID
     * 
     * @param string $paymentId
     * @return array Payment details
     * @throws Exception
     */
    public function fetchPayment(string $paymentId): array {
        try {
            $payment = $this->api->payment->fetch($paymentId);
            return [
                'id' => $payment->id,
                'order_id' => $payment->order_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'method' => $payment->method ?? null,
                'email' => $payment->email ?? null,
                'contact' => $payment->contact ?? null,
                'created_at' => $payment->created_at
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to fetch payment: ' . $e->getMessage());
        }
    }
}
