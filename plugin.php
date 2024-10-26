<?php
/**
 * Plugin Name: 2kb Amazon DropShip Manager
 * Plugin URI: http://www.2kblater.com/
 * Description: Amazon DropShip Manager helps drop ship sellers to manage their products more easily. This plugin will help you keep track of quantity and price. It will send you reports for product changes by email.
 * Version: 1.1.2
 * Author: 2kblater.com
 * Author URI: http://www.2kblater.com
 * License: GPL2
 */

!defined('ABSPATH') and exit;

define('KbAmazonDropShipManagerVersion', '1.1.2');
define('KbAmazonDropShipManagerNumber', 112);
define('KbAmazonDropShipManagerFolderName',  pathinfo(dirname(__FILE__), PATHINFO_FILENAME));
define('KbAmazonDropShipManagerPluginPath',  dirname(__FILE__) . '/');

if (!defined('KbAmazonVersionNumber') || KbAmazonVersionNumber < 200) {
    add_action( 'admin_notices', 'kbAmazonDropShipManagerPluginRequired' );
    function kbAmazonDropShipManagerPluginRequired()
    {
        echo sprintf(
            '<div class="error"><p>2kb Amazon DropShip Manager requires 2kb Amazon Affiliate Store Plugin v2.0.0 or bigger. <b><a href="%s">Install it</a></b> or <b><a href="%s">open in wordpress.org</a></b></p></div>',
            admin_url() . 'plugin-install.php?tab=search&s=2kb+amazon+affiliate+store',
            'https://wordpress.org/plugins/2kb-amazon-affiliates-store/' 
        );
    }
    return;
}

if(!class_exists('WP_List_Table')){
   require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

require_once KbAmazonDropShipManagerPluginPath . 'KbAmazonDropShipManagerController.php';
require_once KbAmazonDropShipManagerPluginPath . 'KbAmazonDropShipManagerAdmin.php';
require_once KbAmazonDropShipManagerPluginPath . 'functions.php';

add_filter('getKbAmzDefaultOptions', 'KbAmazonDropShipManagerDefaultOptions');
function KbAmazonDropShipManagerDefaultOptions($options)
{
    $options['DropShipManagerLowQuantity'] = 5;
    $options['DropShipManagerMediumQuantity'] = 20;
    $options['DropShipManagerLargeQuantity'] = 50;
    $options['DropShipManagerReminderEmail'] = null;
    $options['DropShipManagerReminderLow'] = true;
    $options['DropShipManagerReminderMedium'] = false;
    $options['DropShipManagerReminderLarge'] = false;
    $options['DropShipManagerReminderTryScrap'] = true;
    $options['DropShipManagerDontShowDeleteOnNoQuantity'] = false;
    $options['DropShipManagerErrors'] = array();
    
    return $options;
}

add_action('KbAmazonImporter::saveProduct', 'KbAmazonDropShipManagerProductSave');
function KbAmazonDropShipManagerProductSave($std)
{
    if (!getKbAmz()->isCronRunning()) {
        return;
    }
    
    $postId = $std->postId;
    
    $isDropShipProduct = get_post_meta($postId, 'KbAmzDropShipManager', true);
    if (!$isDropShipProduct) {
        return;
    }
    
    $quantity = get_post_meta($postId, 'KbAmzOfferSummary.TotalNew', true);
    $low = getKbAmz()->getOption('DropShipManagerReminderLow');
    
    if ($low && $quantity <= getKbAmz()->getOption('DropShipManagerLowQuantity')) {
        KbAmazonDropShipManagerNotifyForQuantityChange($postId, $quantity, 'Low');
        return;
    }
    
    $medium = getKbAmz()->getOption('DropShipManagerReminderMedium');
    if ($medium && $quantity <= getKbAmz()->getOption('DropShipManagerMediumQuantity')) {
        KbAmazonDropShipManagerNotifyForQuantityChange($postId, $quantity, 'Medium');
        return;
    }
    
    $large = getKbAmz()->getOption('DropShipManagerReminderLarge');
    if ($large && $quantity <= getKbAmz()->getOption('DropShipManagerLargeQuantity')) {
        KbAmazonDropShipManagerNotifyForQuantityChange($postId, $quantity, 'Large');
        return;
    }
}


function KbAmazonDropShipManagerNotifyForQuantityChange($postId, $quantity, $type)
{
    $lastEmailSentTime = getKbAmz()->getOption('DropShipManagerLastEmailSentTime');
    if ($lastEmailSentTime && $lastEmailSentTime + 1800 > time()) {
        return;
    }
    getKbAmz()->setOption('DropShipManagerLastEmailSentTime', time());
            
    $post = get_post($postId);
    $asin = get_post_meta($post->ID, 'KbAmzASIN', true);
    $postTitle = $post->post_title;
    
    $emails = explode(',', getKbAmz()->getOption('DropShipManagerReminderEmail'));
    foreach ($emails as $email) {
        $email = trim($email);

        $title = '2kb Amazon DropShip Manager Reminder';
        $headers = array();
        $headers[] = 'From: 2kb Amazon DropShip Manager <'.$email.'>';
        $message = "Product with ASIN $asin ($postTitle) has reached quantity of $quantity.";
        $message .= "\n";
        $message .= get_admin_url() . 'admin.php?page=kbAmzDropShipManager&kbAction=manager';
        try {
            $isSent = wp_mail(
                $email,
                $title,
                $message,
                $headers
            );

            if (!$isSent) {
                $errors = getKbAmz()->getOption('DropShipManagerErrors');
                $errors[] = array(
                    'date' => date('Y-m-d H:i:s'),
                    'msg' => 'Mail Send Error: Unknown'
                );
                getKbAmz()->setOption('DropShipManagerErrors', $errors);
            }
        } catch (Exception $e) {
            $errors = getKbAmz()->getOption('DropShipManagerErrors');
            $errors[] = array(
                'date' => date('Y-m-d H:i:s'),
                'msg' => 'Mail Send Error: ' . $e->getMessage()
            );
            getKbAmz()->setOption('DropShipManagerErrors', $errors);
        }
    }
    
}


add_action('KbAmazonImporter::saveProduct', 'KbAmazonDropShipManagerProductSaveForceScrap', 1);
function KbAmazonDropShipManagerProductSaveForceScrap($std)
{
    if (!getKbAmz()->getOption('DropShipManagerReminderTryScrap')) {
        return;
    }
    
    $postId = $std->postId;
    $isDropShipProduct = get_post_meta($postId, 'KbAmzDropShipManager', true);
    if (!$isDropShipProduct) {
        return;
    }
    
    $postId = $std->postId;
    
    $post = get_post($postId);
    $meta = getKbAmz()->getProductMeta($post->ID, true);
    if (!isset($meta['KbAmzDetailPageURL'])) {
        return;
    }

    // B00K6DVA8C
    $urlParts = parse_url($meta['KbAmzDetailPageURL']);
    $url = sprintf(
        '%s://%s/dp/%s',
        $urlParts['scheme'],
        $urlParts['host'],
        $meta['KbAmzASIN']
    );

    $args = array(
        'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_4) AppleWebKit/536.30.1 (KHTML, like Gecko) Version/6.0.5 Safari/536.30.1'
    );
    
    $content = wp_remote_get($url, $args);
    if (!is_array($content)) {
        $content = wp_remote_get($meta['KbAmzDetailPageURL'], $args);
    }
    if (!isset($content['body'])) {
        return;
    }
    $content = $content['body'];
    
    $meta = array();
    $quantityParts = explode('<select name="quantity"', $content);
    if (isset($quantityParts[1])) {
        $quantityOptionsParts = explode('</select>', $quantityParts[1]);
        if (isset($quantityOptionsParts[0])) {
            $options = $quantityOptionsParts[0];
            $quantity = substr_count($options, '</option>');
            $meta['KbAmzOfferSummary.TotalNew'] = $quantity;
        }
    }
    
    if (!isset($meta['KbAmzOfferSummary.TotalNew'])) {
        $quantityParts = explode('id="availability"', $content);
        if (isset($quantityParts[1])) {
            $quantityOptionsParts = explode('</div>', $quantityParts[1]);
            if (isset($quantityOptionsParts[0])) {
                $quantity = preg_replace("/[^0-9]/", "", $quantityOptionsParts[0]);
                $meta['KbAmzOfferSummary.TotalNew'] = $quantity;
            }
        }
    }
    
    if (isset($meta['KbAmzOfferSummary.TotalNew'])
    && empty($meta['KbAmzOfferSummary.TotalNew'])) {
        unset($meta['KbAmzOfferSummary.TotalNew']);
    }
    
    $priceParts = explode('id="priceblock_ourprice"', $content);
    
    if (isset($priceParts[1])) {
        $priceParts = explode('</span>', $priceParts[1]);
        if (isset($priceParts[0])) {
            $priceParts = explode('>', $priceParts[0]);
            if (isset($priceParts[1]) && !empty($priceParts[1])) {
                $meta['KbAmzPriceAmountFormatted'] = $priceParts[1];
                $meta['KbAmzPriceAmount']          = KbAmazonImporter::paddedNumberToDecial(
                    KbAmazonImporter::formattedNumberToDecial($priceParts[1]) . '00'
                );
            }
        }
    }

    foreach ($meta as $name => $val) {
        update_post_meta($post->ID, $name, $val);
    }
}


/**
 * Save Price Differences
 */
add_action('KbAmazonImporter::preSaveProduct', 'KbAmazonDropShipManagerProductPreSaveQuantityManager');
function KbAmazonDropShipManagerProductPreSaveQuantityManager($std)
{
    $postId = $std->postId;
    if ($postId) {
        $isDropShipProduct = get_post_meta($postId, 'KbAmzDropShipManager', true);
        if (!$isDropShipProduct) {
            return;
        }
        
        $meta = getKbAmz()->getProductMeta($postId, true);
        
        $currentStorage =
        get_post_meta(
            $postId,
            '_KbAmazonDropShipManagerStorageHistory',
            true
        );

        if (!isset($currentStorage['history'])) {
            $currentStorage['history'] = array();
        }

        $storageRow = array(
            'KbAmzOfferSummary.TotalNew'     => $meta['KbAmzOfferSummary.TotalNew'],
            'KbAmzPriceAmountFormatted'      => $meta['KbAmzPriceAmountFormatted'],
            'KbAmzPriceAmount'               => $meta['KbAmzPriceAmount'],
            'time'                           => time()
        );

        $currentStorage['history'][] = $storageRow;
        $currentStorage['history'] = array_slice($currentStorage['history'], -100);

        $storageRow['history'] = $currentStorage['history'];

        update_post_meta(
            $postId,
            '_KbAmazonDropShipManagerStorageHistory',
            $storageRow
        );
    }
}

/**
 * Save original price
 */
add_action('KbAmazonImporter::saveProduct', 'KbAmazonDropShipManagerProductSaveOriginalPrice', 10);
function KbAmazonDropShipManagerProductSaveOriginalPrice($std)
{
    $postId = $std->postId;
    if ($postId) {
        $isDropShipProduct = get_post_meta($postId, 'KbAmzDropShipManager', true);
        if (!$isDropShipProduct) {
            return;
        }
        
        $meta = getKbAmz()->getProductMeta($postId, true);
        if (isset($meta['KbAmzOfferSummary.TotalNew'])
        && isset($meta['KbAmzPriceAmountFormatted'])
        && isset($meta['KbAmzPriceAmount'])) {
            
            $currentStorage =
            get_post_meta(
                $postId,
                '_KbAmazonDropShipManagerImportStorage',
                true
            );
            
            if (empty($currentStorage)
            || (empty($currentStorage['KbAmzOfferSummary.TotalNew']) 
            || empty($currentStorage['KbAmzPriceAmountFormatted']) 
            || empty($currentStorage['KbAmzPriceAmount']))) {
                $storageRow = array(
                    'KbAmzOfferSummary.TotalNew'     => $meta['KbAmzOfferSummary.TotalNew'],
                    'KbAmzPriceAmountFormatted'      => $meta['KbAmzPriceAmountFormatted'],
                    'KbAmzPriceAmount'               => $meta['KbAmzPriceAmount'],
                    'time'                           => time()
                );
                update_post_meta(
                    $postId,
                    '_KbAmazonDropShipManagerImportStorage',
                    $storageRow
                );
            }
        }
    }
}
