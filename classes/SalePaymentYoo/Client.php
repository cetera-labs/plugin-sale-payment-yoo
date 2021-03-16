<?php
namespace SalePaymentYoo;

use YooCheckout\Common\Exceptions\ApiException;
use YooCheckout\Common\Exceptions\AuthorizeException;
use YooCheckout\Common\Exceptions\BadApiRequestException;
use YooCheckout\Common\Exceptions\ExtensionNotFoundException;
use YooCheckout\Common\Exceptions\ForbiddenException;
use YooCheckout\Common\Exceptions\InternalServerError;
use YooCheckout\Common\Exceptions\NotFoundException;
use YooCheckout\Common\Exceptions\ResponseProcessingException;
use YooCheckout\Common\Exceptions\TooManyRequestsException;
use YooCheckout\Common\Exceptions\UnauthorizedException;
use YooCheckout\Common\HttpVerb;
use YooCheckout\Helpers\TypeCast;
use YooCheckout\Helpers\UUID;
use YooCheckout\Model\PaymentInterface;
use YooCheckout\Model\RefundInterface;
use YooCheckout\Model\Webhook\Webhook;
use YooCheckout\Request\PaymentOptionsRequest;
use YooCheckout\Request\PaymentOptionsRequestInterface;
use YooCheckout\Request\PaymentOptionsRequestSerializer;
use YooCheckout\Request\PaymentOptionsResponse;
use YooCheckout\Request\Payments\CreatePaymentRequest;
use YooCheckout\Request\Payments\CreatePaymentRequestInterface;
use YooCheckout\Request\Payments\CreatePaymentResponse;
use YooCheckout\Request\Payments\CreatePaymentRequestSerializer;
use YooCheckout\Request\Payments\Payment\CancelResponse;
use YooCheckout\Request\Payments\Payment\CreateCaptureRequest;
use YooCheckout\Request\Payments\Payment\CreateCaptureRequestInterface;
use YooCheckout\Request\Payments\Payment\CreateCaptureRequestSerializer;
use YooCheckout\Request\Payments\Payment\CreateCaptureResponse;
use YooCheckout\Request\Payments\PaymentResponse;
use YooCheckout\Request\Payments\PaymentsRequest;
use YooCheckout\Request\Payments\PaymentsRequestInterface;
use YooCheckout\Request\Payments\PaymentsRequestSerializer;
use YooCheckout\Request\Payments\PaymentsResponse;
use YooCheckout\Request\Receipts\AbstractReceiptResponse;
use YooCheckout\Request\Receipts\CreatePostReceiptRequest;
use YooCheckout\Request\Receipts\CreatePostReceiptRequestInterface;
use YooCheckout\Request\Receipts\CreatePostReceiptRequestSerializer;
use YooCheckout\Request\Receipts\ReceiptResponseFactory;
use YooCheckout\Request\Receipts\ReceiptsResponse;
use YooCheckout\Request\Refunds\CreateRefundRequest;
use YooCheckout\Request\Refunds\CreateRefundRequestInterface;
use YooCheckout\Request\Refunds\CreateRefundRequestSerializer;
use YooCheckout\Request\Refunds\CreateRefundResponse;
use YooCheckout\Request\Refunds\RefundResponse;
use YooCheckout\Request\Refunds\RefundsRequest;
use YooCheckout\Request\Refunds\RefundsRequestInterface;
use YooCheckout\Request\Refunds\RefundsRequestSerializer;
use YooCheckout\Request\Refunds\RefundsResponse;
use YooCheckout\Request\Webhook\WebhookListResponse;

class Client extends  \YooCheckout\Client {

    public function createReceiptNew($receipt, $idempotenceKey = null)
    {
        $path = self::RECEIPTS_PATH;

        $headers = array();

        if ($idempotenceKey) {
            $headers[self::IDEMPOTENCY_KEY_HEADER] = $idempotenceKey;
        } else {
            $headers[self::IDEMPOTENCY_KEY_HEADER] = UUID::v4();
        }

        $httpBody = json_encode($receipt);

        $response = $this->execute($path, HttpVerb::POST, null, $httpBody, $headers);

        $receiptResponse = null;
        if ($response->getCode() == 200) {
            $resultArray = $this->decodeData($response);
            $factory = new ReceiptResponseFactory();
            $receiptResponse = $factory->factory($resultArray);
        } else {
            $this->handleError($response);
        }

        return $receiptResponse;
    }

}