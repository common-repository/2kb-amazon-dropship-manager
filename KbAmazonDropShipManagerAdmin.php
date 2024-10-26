<?php
/**
 * ADMIN PART
 */
add_action( 'admin_menu', 'kbAmazonDropShipManagerMenu', 101);
function kbAmazonDropShipManagerMenu()
{
    /**
     *  $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function = ''
     */
    add_submenu_page(
        'kbAmz',
        __('DropShip Manager'),
        __('DropShip Manager'),
        'manage_options',
        'kbAmzDropShipManager',
        array(KbAmazonDropShipManagerGetContoller(), 'indexAction')
    ); 
}

function getDropShipManagerReminderEmail()
{
    $user = wp_get_current_user();
    $default = null;
    if ($user && is_object($user) && isset($user->user_email)) {
        $default = $user->user_email;
    }     
    return getKbAmz()->getOption('DropShipManagerReminderEmail', $default);
}