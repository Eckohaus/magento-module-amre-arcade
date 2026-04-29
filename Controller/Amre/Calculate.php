<?php
namespace Eckohaus\AmreArcade\Controller\Amre;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Eckohaus\AmreArcade\Model\WalletFactory;
use Eckohaus\AmreArcade\Model\ResourceModel\Wallet as WalletResource;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class Calculate extends Action implements CsrfAwareActionInterface
{
    protected $resultJsonFactory;
    protected $customerSession;
    protected $walletFactory;
    protected $walletResource;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CustomerSession $customerSession,
        WalletFactory $walletFactory,
        WalletResource $walletResource
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->customerSession = $customerSession;
        $this->walletFactory = $walletFactory;
        $this->walletResource = $walletResource;
        parent::__construct($context);
    }

    // Bypass Magento's strict CSRF check for our custom HTML arcade frontend
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException { return null; }
    public function validateForCsrf(RequestInterface $request): ?bool { return true; }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        // 1. Verify User is Logged In
        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData(['status' => 'error', 'message' => 'ACCESS DENIED: Please log in to the terminal.']);
        }

        $customerId = $this->customerSession->getCustomerId();
        $wallet = $this->walletFactory->create();
        $this->walletResource->load($wallet, $customerId, 'customer_id');

        // 2. Verify Token Balance
        $balance = (int)$wallet->getTokenBalance();
        if ($balance <= 0) {
            return $result->setData(['status' => 'error', 'message' => 'INSUFFICIENT FUNDS: Please insert coin (Purchase AMRE Tokens).']);
        }

        // 3. Take Payment (Deduct 1 Token)
        $wallet->setTokenBalance($balance - 1);
        $this->walletResource->save($wallet);

        // 4. Extract User Input & Translate to GET Parameters
        $payload = $this->getRequest()->getPostValue('fortran_data', '');
        
        // Decode the JSON string (e.g. {"high":"1.0536", "low":"1.0247"...}) back into a PHP array
        $matrixData = json_decode($payload, true) ?: [];

        // Convert array to Fortran's expected URL format: high=x&low=y&lambda=z&depth=w
        $queryString = http_build_query($matrixData);

        // 5. Contact the Fortran Engine (Internal Server Request via GET)
        // Target the absolute binary path directly and append the query string
        $fortranEndpoint = 'http://127.0.0.1:8080/api/base_equation.bin?' . $queryString; 

        $ch = curl_init($fortranEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Removed POST configurations. cURL now defaults to a standard GET request.
        
        // Execute the calculation
        $fortranResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 6. Deliver the Goods
        if ($httpCode === 200 && $fortranResponse) {
            return $result->setData([
                'status' => 'success', 
                'message' => 'CALCULATION COMPLETE', 
                'remaining_tokens' => ($balance - 1),
                'output' => json_decode($fortranResponse, true)
            ]);
        } else {
            // If the Fortran server fails, refund the token
            $wallet->setTokenBalance($balance);
            $this->walletResource->save($wallet);
            return $result->setData(['status' => 'error', 'message' => 'SYSTEM FAILURE: Fortran engine offline. Token refunded.']);
        }
    }
}