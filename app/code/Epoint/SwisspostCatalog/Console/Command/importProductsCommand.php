<?php

namespace Epoint\SwisspostCatalog\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Magento\Framework\App\State as AppState;
use Epoint\SwisspostApi\Model\Api\Product as ApiProductModel;
use Epoint\SwisspostCatalog\Service\Product as ProductService;
use Epoint\SwisspostApi\Model\Api\Lists\Product as ApiProductList;
use Epoint\SwisspostCatalog\Helper\Product as ProductHelper;

class importProductsCommand extends Command
{
    /**
     * Product argument
     *
     * @const PRODUCT_ARGUMENT
     */
    const PRODUCT_ARGUMENT = 'sku';

    /**
     * @var \Magento\Framework\App\ObjectManager $objectManager
     */
    private $objectManager;

    /**
     * @var AppState
     */
    protected $appState;

    /**
     * @var ApiProductModel
     */
    protected $apiProductModel;

    /**
     * @var ProductService
     */
    protected $productService;

    /**
     * @var ApiProductList
     */
    protected $apiProductList;

    /**
     * @var ProductHelper
     */
    protected $productHelper;

    /**
     * Implement configure method.
     */
    protected function configure()
    {
        $this->setName('epoint-swisspostapi:importProducts')
            ->setDescription(__('Run importProducts'))
            ->setDefinition([
                    new InputArgument(
                        self::PRODUCT_ARGUMENT,
                        InputArgument::OPTIONAL,
                        'Products'
                    )
                ]
            );
        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->appState = $this->objectManager->get(\Magento\Framework\App\State::class);
        $this->apiProductModel = $this->objectManager->get(\Epoint\SwisspostApi\Model\Api\Product::class);
        $this->productService = $this->objectManager->get(\Epoint\SwisspostCatalog\Service\Product::class);
        $this->productHelper = $this->objectManager->get(\Epoint\SwisspostCatalog\Helper\Product::class);
        $this->apiProductList = $this->productService->listFactory();
    }

    /**
     * Execute command method.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Set area code.
        $this->appState->setAreaCode('adminhtml');
        // Getting the product to be imported sku
        $productSku = $input->getArgument(self::PRODUCT_ARGUMENT);
        $products = [];
        if ($productSku) {
            $apiProduct = $this->apiProductModel->load($productSku);
            if ($apiProduct) {
                $products[] = $apiProduct;
            }
        } else {
            // Before we start trigger the import action,
            // we must check if an import limit value has been setup
            $limitImport = $this->productHelper->getProductImportLimit();
            $filter = [];
            // If the limiter has any other value beside the default one (0)
            // we add it to the filter
            if ($limitImport > 0){
                $filter['limit'] = (int)$limitImport;
            }
            // Trigger the action
            $products = $this->apiProductList->search($filter);
        }

        if ($products) {
            $processed = $this->productService->run($products);
            foreach ($processed as $product) {
                if ($product) {
                    $output->writeln(sprintf(__('Successful imported product: %s'),
                        $product->getSKU()));
                }
            }
        } else {
            $output->writeln(sprintf(__('Swisspost API load products result no values')));
        }
    }
}
