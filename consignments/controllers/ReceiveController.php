<?php
declare(strict_types=1);

namespace Modules\Consignments\controllers;

use Modules\Base\Helpers;

final class ReceiveController extends BaseTransferController
{
    public function index(): string
    {
        $transferId = $this->getTransferIdFromRequest();
        
        // Get standard transfer data from base controller
        $standardData = $this->getStandardTransferData($transferId);
        
        // Add receive-specific data
        $receiveData = [
            'page_title' => $this->getTransferPageTitle('Receive', $transferId),
            'page_blurb' => 'Receive and verify items for transfer | Total transfers: ' . $standardData['transferCount'],
            'page_id' => 'consignments_receive',
            'bodyClass' => $this->getTransferBodyClass('receive'),
            'breadcrumbs' => $this->getTransferBreadcrumbs('Receive', $transferId),
            'moduleCSS' => ['/modules/consignments/assets/css/receive.css'],
            'moduleJS' => ['/modules/consignments/assets/js/receive.bundle.js'],
        ];
        
        // Merge user preferences
        $preferences = $this->getUserTransferPreferences();
        
        return $this->view(dirname(__DIR__) . '/views/receive/simple.php', 
            array_merge($standardData, $receiveData, $preferences)
        );
    }
}
