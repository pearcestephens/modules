<?php
declare(strict_types=1);

namespace Modules\Consignments\controllers;

use Modules\Base\Helpers;

final class PackController extends BaseTransferController
{
    public function index(): string
    {
        $transferId = $this->getTransferIdFromRequest();
        
        // Get standard transfer data from base controller
        $standardData = $this->getStandardTransferData($transferId);
        
        // Add pack-specific data
        $packData = [
            'page_title' => $this->getTransferPageTitle('Pack', $transferId),
            'page_blurb' => 'Pack and ship items for transfer | Total transfers: ' . $standardData['transferCount'],
            'page_id' => 'consignments_pack',
            'bodyClass' => $this->getTransferBodyClass('pack'),
            'breadcrumbs' => $this->getTransferBreadcrumbs('Pack', $transferId),
            'moduleCSS' => ['/modules/consignments/assets/css/pack.css'],
            'moduleJS' => ['/modules/consignments/assets/js/pack.bundle.js'],
        ];
        
        // Merge user preferences
        $preferences = $this->getUserTransferPreferences();
        
        return $this->view(dirname(__DIR__) . '/views/pack/simple.php', 
            array_merge($standardData, $packData, $preferences)
        );
    }
}
