<?php

class KbAmazonDropShipManagerController extends KbAmzAdminController
{
    
    public function __construct()
    {
        add_action('wp_ajax_KbAmazonDropShipManagerrefreshAction', array($this, 'refreshAction'));
        add_action('wp_ajax_KbAmazonDropShipManagerProductDataAction', array($this, 'productDataAction'));
        add_action('wp_ajax_KbAmazonDropShipManagerRemoveAction', array($this, 'removeAction'));
        
        
    }
    
    public function managerAction()
    {
        return new KbView(array());
    }

    public function logsAction()
    {
        return new KbView(array('logs' => getKbAmz()->getOption('DropShipManagerErrors')));
    }

    public function settingsGeneralAction()
    {
        $view = parent::settingsGeneralAction();
        $view->user = wp_get_current_user();
        $view->setTemplate(KbAmazonDropShipManagerPluginPath . '/template/admin/settingsGeneral');
        return $view;
    }

    public function productDataAction()
    {
        $view = new KbView($_POST);
        $view->setTemplate($this->getTemplatePath('productData'));
        echo $view;
        exit;
    }
    
    public function refreshAction()
    {
        try {
            $importer = new KbAmazonImporter;
            $importer->import($_POST['asin']);
            $row = null;
            if (isset($_POST['postId'])) {
                $row = getDropShipManagerTableRow(get_post($_POST['postId']));
            }
            echo json_encode(array('success' => true, 'row' => $row));
        } catch (Exception $e) {
            echo json_encode(array('success' => false));
        }
        exit;
    }

    public function removeAction()
    {
        try {
            getKbAmz()->clearProduct($_POST['post']);
            echo json_encode(array('success' => true));
        } catch (Exception $e) {
            echo json_encode(array('success' => false));
        }
        exit;
    }
    
    public function importByAsinAction()
    {
        add_filter('kbAmzFilterAttributes', array($this, 'addProductMeta'));
        
        $importer = new KbAmazonImporter;
        
        if (isset($_POST['asin']) && !empty($_POST['asin'])) {
            foreach ($_POST['asin'] as $asin) {
                $item = $importer->find($asin);
                $importer->saveProduct($item);
            }
            if (!empty($_POST['asin'])) {
                $this->messages[] = array('Products Imported', 'alert-success');
                $view = new KbView(array());
                return $view;
            }
        }
        
        $data = array();
        if (isset($_GET['asin']) && !empty($_GET['asin'])) {
            if (isset($_GET['post_category']) && !empty($_GET['post_category'])) {
                $importer->setImportCategories($_GET['post_category']);
            }
            $item = $importer->find($_GET['asin']);
            if ($parentAsin = $item->getParentAsin()) {
                $parentItem = $importer->find($parentAsin);
                $variants = $parentItem->getVariantProducts();
                if (!empty($variants)) {
                    $items = array();
                    foreach ($variants['Item'] as $item) {
                        $items[] = new KbAmazonItem(array('Items' => array('Item' => $item)));
                    }
                    $data['items'] = $items;
                    $view = new KbView($data);
                    $view->setTemplate(KbAmazonStorePluginPath . '/template/admin/importItemsWithGallery');
                    return $view;
                }
            }

            $statuses = $importer->import($_GET['asin']);
            $status = $statuses[0];
            if ($status['error']) {
                $this->messages[] = array($status['error'], 'alert-warning');
            } else if ($status['updated']) {
                $this->messages[] = array(
                    sprintf(__('Product with id %s is updated.'), $status['post_id']),
                    'alert-success'
                );
            } else {
                $this->messages[] = array(
                    sprintf(__('Product with id %s is inserted.'), $status['post_id']),
                    'alert-success'
                );
            }
        }

        $view = new KbView($data);
        return $view;
    }
    
    
    public function addProductMeta($meta)
    {
        $meta['KbAmzDropShipManager'] = true;
        return $meta;
    }
    
    public function supportAction()
    {
        $view = parent::supportAction();
        $view->setTemplate(parent::getTemplatePath('support'));
        return $view;
    }
    
    public function downloadImagesAction()
    {
        $view = new KbView(array());
        $view->setTemplate($this->getTemplatePath('downloadImages'));
        return $view;
    }
    
    public function downloadContentAction()
    {
        $data = array();
        if (isset($_POST['asin'])) {
            $importer = new KbAmazonImporter;
            $item = $importer->find($_POST['asin']);
            $data['title'] = $item->getTitle();
            $data['item'] = $item->getItem();
        }
        
        $view = new KbView($data);
        $view->setTemplate($this->getTemplatePath('downloadContent'));
        return $view;
    }
    
    public function reportsAction()
    {
        return new KbView(array());
    }
    
    public function priceCalculationAction()
    {
        $data = array(
            'ebayFeePercent' => getKbAmz()->getOption('DropShipManagerEbayFeePercent', '0.10'),
            'paypalFeePercent' => getKbAmz()->getOption('DropShipManagerPaypalFeePercent', '0.034'),
            'paypalFeeAmount' => getKbAmz()->getOption('DropShipManagerPaypalFeeAmount', '0.15'),
        );
        $view = new KbView(array('params' => $data));
        $view->setTemplate($this->getTemplatePath('priceCalculation'));
        return $view;
    } 
    

    protected function getActions() {
        return array(
            array(
                'action' => 'home',
                'icon' => 'glyphicon-th',
                'label' => __('Dashboard')
            ),
            array(
                'action' => 'manager',
                'icon' => 'glyphicon-calendar',
                'label' => __('Manager')
            ),
//            array(
//                'action' => 'reports',
//                'icon' => 'glyphicon-folder-open',
//                'label' => __('Reports')
//            ),
            array(
                'action' => 'importByAsin',
                'icon' => 'glyphicon-import',
                'label' => __('Import Item'),
            ),
            array(
                'action' => 'tools',
                'icon' => 'glyphicon-cog',
                'label' => __('Tools'),
                'pages' => array(
                    array('action' => 'downloadImages', 'label' => __('Download Images by ASIN')),
                    array('action' => 'downloadContent', 'label' => __('Download Content ASIN')),
                    array('action' => 'priceCalculation', 'label' => __('Price Calculation')),
                )
            ),
            array(
                'action' => 'settingsGeneral',
                'icon' => 'glyphicon-wrench',
                'label' => __('Settings')
            ),
            array(
                'action' => 'logs',
                'icon' => 'glyphicon-flag',
                'label' => __('Logs')
            ),
            array(
                'action' => 'support',
                'icon' => 'glyphicon-question-sign',
                'label' => __('Feedback')
            ),
        );
    }
    
    protected function getTemplatePath($addup) {
        return KbAmazonDropShipManagerPluginPath . '/template/admin/' . $addup;
    }
}
// do it
KbAmazonDropShipManagerGetContoller();
function KbAmazonDropShipManagerGetContoller()
{
    static $KbAmazonDropShipManagerContoller;
    if (!$KbAmazonDropShipManagerContoller) {
        $KbAmazonDropShipManagerContoller = new KbAmazonDropShipManagerController;
    }
    return $KbAmazonDropShipManagerContoller;
}


if (isset($_POST['2kbDownloadImagesASIN'])) {
    $importer = new KbAmazonImporter;
    $item = $importer->find($_POST['asin']);
    $item = $item->getItem();
    $url = $item['DetailPageURL'];
    $response = wp_remote_get($url);
    $html = $response['body'];

    $lines = explode("\n", $html);

    $images = [];
    foreach ($lines as $line) {
        if (strpos($line, 'data["colorImages"]') !== false) {
            $pair = explode('data["colorImages"] =', $line);
            $json = json_decode(substr(trim($pair[1]), 0, -1), true);
            if (is_array($json)) {
                foreach ($json as $group => $groupImages) {
                    foreach ($groupImages as $pair) {
                        $images[$group][] = !isset($pair['hiRes']) || empty($pair['hiRes']) ? $pair['large'] : $pair['hiRes'];
                    }
                }
                break;
            }
        }
    }

    if (empty($images)) {
        foreach ($lines as $line) {
            if (strpos($line, "'colorImages':") !== false) {
                $pair = explode("'colorImages':", $line);
                $jsonStr = str_replace("'initial'", '"initial"', trim(trim(substr($pair[1], 0, -1))));
                $result = json_decode($jsonStr, true);
                if (is_array($result)) {
                    foreach ($result['initial'] as $data) {
                        $images['large'][] = !isset($data['hiRes']) || empty($data['hiRes']) ? $data['large'] : $data['hiRes'];
                    }
                    break;
                }
            }
        }
    }

    $dir = sys_get_temp_dir();
    $zip = new \ZipArchive;
    $zipFile = $dir . '/' . $_POST['asin'] . '.zip';
    $zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    $filesToRemove = [];
    $filesToRemove[] = $zipFile;

    foreach ($images as $group => $groupImages) {
        foreach ($groupImages as $key => $src) {
            $tmp = $dir . '/' . uniqid($key) . '.jpg';
            file_put_contents($tmp, file_get_contents($src));

            $img = imagecreatefromjpeg($tmp);
            imagejpeg($img, $tmp, 100);

            $zip->addFile($tmp, $group . '/' . $key . '.jpg');
            $filesToRemove[] = $tmp; 
        }
    }
    $zip->close();

    header("Content-Type: application/force-download");
    header("Content-Type: application/octet-stream");
    header("Content-Type: application/download");
    header("Content-Disposition: attachment; filename=\"{$_POST['asin']}.zip\"");
    header("Pragma: public");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header('Content-Length: ' . filesize($zipFile));

    readfile($zipFile);

    foreach ($filesToRemove as $file) {
        @unlink($file);
    }
    exit;  
}