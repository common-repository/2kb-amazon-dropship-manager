<?php
function getDropShipManagerTableRow($post, $opts = array())
{
    ob_start();
    
    /*
    ["KbAmzOfferSummary.TotalNew"]=>
    string(1) "5"
    ["KbAmzPriceAmountFormatted"]=>
    string(6) "£6.90"
    ["KbAmzPriceAmount"]=>
    string(3) "6.9"
     */
    $storage       = get_post_meta($post->ID, '_KbAmazonDropShipManagerStorageHistory', true);
    $importStorage = get_post_meta($post->ID, '_KbAmazonDropShipManagerImportStorage', true);
    
    $importPrice = '';
    if (isset($importStorage['KbAmzPriceAmountFormatted'])) {
        $importPrice = sprintf(
            '<small title="Import Price"><em>%s</em></small>&nbsp;',
            $importStorage['KbAmzPriceAmountFormatted']
        );
    }

    echo '<td>';
        echo $opts['index'];
    echo '</td>';
    
    echo '<td>';
    edit_post_link($post->ID, '', '', $post->ID);
    echo '</td>';
    
    echo '<td>';
        echo '<div style="height:25px; overflow:hidden;">';
        echo getKbAmz()->getProductImages($post->ID)->getFirst(null, true, array('style' => 'max-height:25px;width:auto;'));
        echo '</div>';
    echo '</td>';
    
    $asin = get_post_meta($post->ID, 'KbAmzASIN', true);
    echo sprintf(
        '<td><a href="#" data-post="%s" class="product-modal"><span aria-hidden="true" class="glyphicon glyphicon-info-sign"></span></a> %s <a href="%s" target="_blank"><span aria-hidden="true" class="glyphicon glyphicon-share"></span></a></td>',
        $post->ID,
        $asin,
        get_post_meta($post->ID, 'KbAmzDetailPageURL', true)
    );
    
    echo sprintf(
        '<td>%s</td>',
        $post->post_title
    );
   
   
    $formattedPrice = get_post_meta($post->ID, 'KbAmzPriceAmountFormatted', true);
    $quantity       = get_post_meta($post->ID, 'KbAmzOfferSummary.TotalNew', true);
    
    $oldPrice       = '';
    $oldQuantity    = '';
    if ($storage && $storage['KbAmzPriceAmountFormatted'] != $formattedPrice) {
        $oldPrice = sprintf(
            '<small class="text-danger" title="Previous Price"><em>%s</em></small>&nbsp;',
            $storage['KbAmzPriceAmountFormatted']
        );
    }
    if ($storage && $storage['KbAmzOfferSummary.TotalNew'] != $quantity) {
        $oldQuantity = sprintf(
            '<small class="text-danger" title="Previous Quantity"><em>%s</em></small>&nbsp;',
            $storage['KbAmzOfferSummary.TotalNew']
        );
    }
    
    echo sprintf(
        '<td style="text-align:right;">%s</td>',
        $importPrice
    );
    
    echo sprintf(
        '<td style="text-align:right;">%s<b>%s</b></td>',
        $oldPrice,
        $formattedPrice
    );
    
    echo sprintf(
        '<td style="text-align:right;">%s<b>%s</b></td>',
        $oldQuantity,
        $quantity
    );
    
    $time = get_post_meta($post->ID, 'KbAmzLastUpdateTime', true);
    
    echo sprintf(
        '<td style="text-align:right;">%s</td>',
        human_time_diff(strtotime($post->post_modified))
    );
    
    if (!isset($opts['refresh']) || $opts['refresh']) {
        echo sprintf(
            '<td><a class="product-refresh" href="#" data-asin="%s" data-post="%s"><span title="refresh" aria-hidden="true" class="glyphicon glyphicon-refresh"></span></a></td>',
            $asin,
            $post->ID
        );
    }
    
    if (!isset($opts['remove']) || $opts['remove']) {
        echo sprintf(
            '<td><a class="product-remove" href="#" data-post="%s"><span title="refresh" aria-hidden="true" class="glyphicon glyphicon-remove"></span></a></td>',
            $post->ID
        );
    }
    
    return ob_get_clean();
}

function getKbDropShipManagerPosts()
{
    $args = array(
        'posts_per_page'   => -1,
        'offset'           => 0,
        'orderby'          => 'post_modified',
        'order'            => 'DESC',
       // 'meta_key'         => 'KbAmzLastUpdateTime',
        'post_type'        => 'post',
        'post_status'      => 'any',
        'meta_query' => array(
            array(
                'key'     => 'KbAmzDropShipManager',
                'value'   => 1,
            ),
        ),
    );

    return get_posts($args);
}

function getKbAmazonDropShipManagerPluginUrl($append = null)
{
    return plugins_url($append, __FILE__ );
}