<?php

namespace App\Service;

use App\Entity\Customer;
use App\Entity\Reservation;
use App\Entity\Payment;
use App\Entity\Flower;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

class EmailNotificationService
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private string $fromEmail = 'noreply@floryngarden.com';
    private string $fromName = 'Floryn Garden System';

    public function __construct(MailerInterface $mailer, LoggerInterface $logger)
    {
        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    /**
     * Send reservation confirmation email to customer
     */
    public function sendReservationConfirmation(Reservation $reservation): void
    {
        try {
            $customer = $reservation->getCustomer();
            
            if (!$customer || !$customer->getEmail()) {
                $this->logger->warning('Cannot send reservation confirmation: customer or email is missing', [
                    'reservation_id' => $reservation->getId()
                ]);
                return;
            }
            
            $email = (new Email())
                ->from($this->fromEmail)
                ->to($customer->getEmail())
                ->subject('Reservation Confirmation - Floryn Garden')
                ->html($this->getReservationConfirmationHtml($reservation));

            $this->mailer->send($email);
            $this->logger->info('Reservation confirmation email sent', [
                'reservation_id' => $reservation->getId(),
                'customer_email' => $customer->getEmail()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send reservation confirmation email', [
                'error' => $e->getMessage(),
                'reservation_id' => $reservation->getId()
            ]);
        }
    }

    /**
     * Send payment confirmation email
     */
    public function sendPaymentConfirmation(Payment $payment): void
    {
        try {
            $reservation = $payment->getReservation();
            if (!$reservation) {
                $this->logger->warning('Cannot send payment confirmation: reservation is missing', [
                    'payment_id' => $payment->getId()
                ]);
                return;
            }
            
            $customer = $reservation->getCustomer();
            
            if (!$customer || !$customer->getEmail()) {
                $this->logger->warning('Cannot send payment confirmation: customer or email is missing', [
                    'payment_id' => $payment->getId()
                ]);
                return;
            }
            
            $email = (new Email())
                ->from($this->fromEmail)
                ->to($customer->getEmail())
                ->subject('Payment Confirmation - Floryn Garden')
                ->html($this->getPaymentConfirmationHtml($payment));

            $this->mailer->send($email);
            $this->logger->info('Payment confirmation email sent', [
                'payment_id' => $payment->getId(),
                'customer_email' => $customer->getEmail()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send payment confirmation email', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->getId()
            ]);
        }
    }

    /**
     * Send low stock alert to admin
     */
    public function sendLowStockAlert(Flower $flower, string $adminEmail): void
    {
        try {
            $email = (new Email())
                ->from($this->fromEmail)
                ->to($adminEmail)
                ->subject('⚠️ Low Stock Alert - ' . $flower->getName())
                ->html($this->getLowStockAlertHtml($flower));

            $this->mailer->send($email);
            $this->logger->info('Low stock alert email sent', [
                'flower_id' => $flower->getId(),
                'stock_quantity' => $flower->getStockQuantity()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send low stock alert email', [
                'error' => $e->getMessage(),
                'flower_id' => $flower->getId()
            ]);
        }
    }

    /**
     * Send reservation ready for pickup notification
     */
    public function sendReadyForPickupNotification(Reservation $reservation): void
    {
        try {
            $customer = $reservation->getCustomer();
            
            if (!$customer || !$customer->getEmail()) {
                $this->logger->warning('Cannot send pickup notification: customer or email is missing', [
                    'reservation_id' => $reservation->getId()
                ]);
                return;
            }
            
            $email = (new Email())
                ->from($this->fromEmail)
                ->to($customer->getEmail())
                ->subject('Your Order is Ready for Pickup! - Floryn Garden')
                ->html($this->getReadyForPickupHtml($reservation));

            $this->mailer->send($email);
            $this->logger->info('Ready for pickup notification sent', [
                'reservation_id' => $reservation->getId(),
                'customer_email' => $customer->getEmail()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send ready for pickup notification', [
                'error' => $e->getMessage(),
                'reservation_id' => $reservation->getId()
            ]);
        }
    }

    // HTML Templates

    private function getReservationConfirmationHtml(Reservation $reservation): string
    {
        $customer = $reservation->getCustomer();
        $pickupDate = $reservation->getPickupDate()->format('F d, Y');
        $total = number_format($reservation->getTotalAmount(), 2);

        $itemsHtml = '';
        foreach ($reservation->getReservationDetails() as $detail) {
            $flower = $detail->getFlower();
            $subtotal = number_format($detail->getSubtotal(), 2);
            $itemsHtml .= "
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$flower->getName()}</td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: center;'>{$detail->getQuantity()}</td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>₱{$subtotal}</td>
                </tr>
            ";
        }

        return "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: linear-gradient(135deg, #5E548E 0%, #4C4375 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0;'>
                    <h1 style='margin: 0; font-size: 28px;'>🌸 Reservation Confirmed!</h1>
                </div>
                
                <div style='background: white; padding: 30px; border: 1px solid #ddd; border-top: none;'>
                    <p style='font-size: 16px; color: #333;'>Dear {$customer->getFullName()},</p>
                    <p style='font-size: 14px; color: #666; line-height: 1.6;'>
                        Thank you for choosing Floryn Garden! Your reservation has been confirmed.
                    </p>
                    
                    <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                        <h3 style='margin-top: 0; color: #5E548E;'>Reservation Details</h3>
                        <p style='margin: 5px 0;'><strong>Reservation #:</strong> {$reservation->getId()}</p>
                        <p style='margin: 5px 0;'><strong>Pickup Date:</strong> {$pickupDate}</p>
                        <p style='margin: 5px 0;'><strong>Status:</strong> {$reservation->getReservationStatus()}</p>
                    </div>
                    
                    <h3 style='color: #5E548E;'>Order Summary</h3>
                    <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                        <thead>
                            <tr style='background: #f8f9fa;'>
                                <th style='padding: 10px; text-align: left; border-bottom: 2px solid #5E548E;'>Item</th>
                                <th style='padding: 10px; text-align: center; border-bottom: 2px solid #5E548E;'>Qty</th>
                                <th style='padding: 10px; text-align: right; border-bottom: 2px solid #5E548E;'>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$itemsHtml}
                        </tbody>
                        <tfoot>
                            <tr style='background: #5E548E; color: white; font-weight: bold;'>
                                <td colspan='2' style='padding: 15px; text-align: right;'>Total:</td>
                                <td style='padding: 15px; text-align: right;'>₱{$total}</td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <p style='font-size: 14px; color: #666; margin-top: 30px;'>
                        We'll notify you when your order is ready for pickup. If you have any questions, please don't hesitate to contact us.
                    </p>
                </div>
                
                <div style='background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 10px 10px;'>
                    <p style='margin: 0; font-size: 12px; color: #666;'>© 2025 Floryn Garden System. All rights reserved.</p>
                </div>
            </div>
        ";
    }

    private function getPaymentConfirmationHtml(Payment $payment): string
    {
        $reservation = $payment->getReservation();
        $customer = $reservation->getCustomer();
        $amount = number_format($payment->getAmountPaid(), 2);
        $paymentDate = $payment->getPaymentDate()->format('F d, Y');

        return "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0;'>
                    <h1 style='margin: 0; font-size: 28px;'>✅ Payment Received!</h1>
                </div>
                
                <div style='background: white; padding: 30px; border: 1px solid #ddd; border-top: none;'>
                    <p style='font-size: 16px; color: #333;'>Dear {$customer->getFullName()},</p>
                    <p style='font-size: 14px; color: #666; line-height: 1.6;'>
                        We have successfully received your payment for Reservation #{$reservation->getId()}.
                    </p>
                    
                    <div style='background: #f0fdf4; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #10b981;'>
                        <h3 style='margin-top: 0; color: #059669;'>Payment Details</h3>
                        <p style='margin: 5px 0;'><strong>Amount Paid:</strong> ₱{$amount}</p>
                        <p style='margin: 5px 0;'><strong>Payment Method:</strong> {$payment->getPaymentMethod()}</p>
                        <p style='margin: 5px 0;'><strong>Payment Date:</strong> {$paymentDate}</p>
                        <p style='margin: 5px 0;'><strong>Transaction #:</strong> {$payment->getId()}</p>
                    </div>
                    
                    <p style='font-size: 14px; color: #666; margin-top: 30px;'>
                        Thank you for your payment! Your order will be prepared according to schedule.
                    </p>
                </div>
                
                <div style='background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 10px 10px;'>
                    <p style='margin: 0; font-size: 12px; color: #666;'>© 2025 Floryn Garden System. All rights reserved.</p>
                </div>
            </div>
        ";
    }

    private function getLowStockAlertHtml(Flower $flower): string
    {
        $stock = $flower->getStockQuantity();
        $name = $flower->getName();

        return "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0;'>
                    <h1 style='margin: 0; font-size: 28px;'>⚠️ Low Stock Alert</h1>
                </div>
                
                <div style='background: white; padding: 30px; border: 1px solid #ddd; border-top: none;'>
                    <p style='font-size: 16px; color: #333;'>Admin Alert,</p>
                    <p style='font-size: 14px; color: #666; line-height: 1.6;'>
                        The following flower is running low on stock and may need to be reordered soon.
                    </p>
                    
                    <div style='background: #fef3c7; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b;'>
                        <h3 style='margin-top: 0; color: #d97706;'>Flower Details</h3>
                        <p style='margin: 5px 0;'><strong>Name:</strong> {$name}</p>
                        <p style='margin: 5px 0;'><strong>Current Stock:</strong> <span style='color: #dc2626; font-weight: bold;'>{$stock} units</span></p>
                        <p style='margin: 5px 0;'><strong>Category:</strong> {$flower->getCategory()}</p>
                        <p style='margin: 5px 0;'><strong>Supplier:</strong> {$flower->getSupplier()->getSupplierName()}</p>
                    </div>
                    
                    <p style='font-size: 14px; color: #666; margin-top: 30px;'>
                        Please consider placing an order with the supplier to replenish stock.
                    </p>
                </div>
                
                <div style='background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 10px 10px;'>
                    <p style='margin: 0; font-size: 12px; color: #666;'>© 2025 Floryn Garden System. All rights reserved.</p>
                </div>
            </div>
        ";
    }

    private function getReadyForPickupHtml(Reservation $reservation): string
    {
        $customer = $reservation->getCustomer();
        $pickupDate = $reservation->getPickupDate()->format('F d, Y');

        return "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0;'>
                    <h1 style='margin: 0; font-size: 28px;'>🎉 Ready for Pickup!</h1>
                </div>
                
                <div style='background: white; padding: 30px; border: 1px solid #ddd; border-top: none;'>
                    <p style='font-size: 16px; color: #333;'>Dear {$customer->getFullName()},</p>
                    <p style='font-size: 14px; color: #666; line-height: 1.6;'>
                        Great news! Your flower arrangement is ready for pickup.
                    </p>
                    
                    <div style='background: #dbeafe; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #3b82f6;'>
                        <h3 style='margin-top: 0; color: #2563eb;'>Pickup Information</h3>
                        <p style='margin: 5px 0;'><strong>Reservation #:</strong> {$reservation->getId()}</p>
                        <p style='margin: 5px 0;'><strong>Scheduled Pickup:</strong> {$pickupDate}</p>
                        <p style='margin: 5px 0;'><strong>Total Amount:</strong> ₱" . number_format($reservation->getTotalAmount(), 2) . "</p>
                    </div>
                    
                    <p style='font-size: 14px; color: #666; margin-top: 30px;'>
                        Please bring your reservation confirmation when picking up your order. We look forward to seeing you!
                    </p>
                </div>
                
                <div style='background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 10px 10px;'>
                    <p style='margin: 0; font-size: 12px; color: #666;'>© 2025 Floryn Garden System. All rights reserved.</p>
                </div>
            </div>
        ";
    }
}
