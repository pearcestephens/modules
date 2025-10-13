<?php
declare(strict_types=1);

namespace Modules\Consignments\controllers;

final class HomeController extends BaseTransferController
{
    public function index(): string
    {
        // Get standard transfer data for home page stats
        $standardData = $this->getStandardTransferData(0);
        
        $homeData = [
            'page_title' => 'Transfers Home',
            'page_blurb' => 'Central hub for all transfer operations | Total transfers: ' . $standardData['transferCount'],
            'page_id' => 'consignments_home',
            'bodyClass' => $this->getTransferBodyClass('home'),
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => 'Transfers', 'active' => true],
            ],
        ];
        
        return $this->view(dirname(__DIR__) . '/views/home/index.php', 
            array_merge($standardData, $homeData)
        );
    }
}
