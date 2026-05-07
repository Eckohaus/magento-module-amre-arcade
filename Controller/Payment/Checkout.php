<?php
namespace Eckohaus\AmreArcade\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class Checkout extends Action
{
    protected $resultRedirectFactory;
    protected $scopeConfig;
    protected $customerSession;
    protected $storeManager;
    protected $encryptor;

    public function __construct(
        Context $context,
        RedirectFactory $resultRedirectFactory,
        ScopeConfigInterface $scopeConfig,
        CustomerSession $customerSession,
        StoreManagerInterface $storeManager,
        EncryptorInterface $encryptor
    ) {
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->scopeConfig = $scopeConfig;
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManager;
        $this->encryptor = $encryptor;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        if (!$this->customerSession->isLoggedIn()) {
            return $resultRedirect->setPath('downloadable/customer/products/');
        }

        // Retrieve AND Decrypt the API key
        $encryptedSecret = $this->scopeConfig->getValue('amre_arcade/stripe/secret_key');
        $stripeSecret = $this->encryptor->decrypt($encryptedSecret);
        
        if (empty($stripeSecret)) {
             return $resultRedirect->setPath('downloadable/customer/products/');
        }

        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        $customerId = $this->customerSession->getCustomerId();

        $stripePayload = http_build_query([
            'success_url' => $baseUrl . 'downloadable/customer/products/',
            'cancel_url' => $baseUrl . 'downloadable/customer/products/',
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'gbp',
                        'product_data' => ['name' => 'AMRE Calculation Token // JUPITER-IV'],
                        'unit_amount' => 100, // £1.00
                    ],
                    'quantity' => 1,
                ],
            ],
            'mode' => 'payment',
            'client_reference_id' => $customerId
        ]);

        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $stripePayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $stripeSecret,
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $stripeData = json_decode($response, true);

        if ($httpCode === 200 && isset($stripeData['url'])) {
            // INSTANT TELEPORT: Redirect straight to the Stripe URL
            return $resultRedirect->setUrl($stripeData['url']);
        } else {
            // If it fails, silently return them to the terminal
            return $resultRedirect->setPath('downloadable/customer/products/');
        }
    }
}